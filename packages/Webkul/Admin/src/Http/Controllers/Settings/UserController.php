<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use App\Enums\ActivityType;
use App\Enums\RoleCode;
use App\Models\Clinic;
use App\Models\Department as DepartmentModel;
use App\Models\Resource;
use App\Support\UserSignature;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Activity\Models\Activity;
use Webkul\Admin\DataGrids\Settings\UserDataGrid;
use Webkul\Admin\Http\Controllers\Concerns\ConcatsEmailActivities;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\UserResource;
use Webkul\Admin\Notifications\User\Create as UserCreatedNotification;
use Webkul\Lead\Models\Channel;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Type as LeadType;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;
use Webkul\User\Models\UserDefaultValue;
use Webkul\User\Repositories\GroupRepository;
use Webkul\User\Repositories\RoleRepository;
use Webkul\User\Repositories\UserRepository;

class UserController extends Controller
{
    use ConcatsEmailActivities;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected UserRepository $userRepository,
        protected GroupRepository $groupRepository,
        protected RoleRepository $roleRepository
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(UserDataGrid::class)->process();
        }

        return view('admin::settings.users.index');
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): View
    {
        $roles = $this->roleRepository->all();
        $groups = $this->groupRepository->all();

        return view('admin::settings.users.create', array_merge(
            compact('roles', 'groups'),
            $this->getDefaultValueOptions(),
            $this->getPortalRoleOptions()
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(): View|JsonResponse|RedirectResponse
    {
        $this->nullifyEmptyUserDefaultValues();

        try {
            $this->validate(request(), $this->getRequestValidationRules(), [], $this->getRequestValidationAttributes());
        } catch (ValidationException $e) {
            // For web requests, redirect back with errors so tests can assert 302 + session errors
            if (! request()->expectsJson() && ! request()->ajax()) {
                return redirect()->back()->withErrors($e->validator)->withInput();
            }
            throw $e;
        }

        $data = request()->all();
        // Normalize composite name field expected by backend
        $data['name'] = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

        $data['status'] = array_key_exists('status', $data) ? $data['status'] : 0;

        $data['resource_id'] = $this->roleHasCode($data['role_id'], RoleCode::ARTS) ? ($data['resource_id'] ?? null) : null;
        $data['clinic_id'] = $this->roleHasCode($data['role_id'], RoleCode::KLINIEK_MEDEWERKER) ? ($data['clinic_id'] ?? null) : null;

        // Always auto-generate the signature for new users
        $data['signature'] = UserSignature::generate(
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $data['email'] ?? '',
        );

        Event::dispatch('settings.user.create.before');

        $admin = $this->userRepository->create($data);

        $admin->view_permission = $data['view_permission'];

        $admin->save();

        // Normalize groups from payload (supports array of ids or array of objects with id)
        $groupInput = request()->input('groups', []);
        $groupIds = collect(is_array($groupInput) ? $groupInput : [])
            ->map(function ($g) {
                return is_array($g) ? ($g['id'] ?? null) : $g;
            })
            ->filter()
            ->values()
            ->all();
        $admin->groups()->sync($groupIds);

        // Save user default values if provided
        $settings = request('user_default_values', request('user_settings', []));
        if (is_array($settings)) {
            foreach ($settings as $key => $value) {
                if ($key === '' || $key === null) {
                    continue;
                }
                UserDefaultValue::updateOrCreate(
                    [
                        'user_id' => $admin->id,
                        'key'     => (string) $key,
                    ],
                    [
                        'value' => isset($value) ? (string) $value : null,
                    ]
                );
            }
        }

        try {
            Mail::queue(new UserCreatedNotification($admin));
        } catch (Exception $e) {
            report($e);
        }

        Event::dispatch('settings.user.create.after', $admin);

        $message = trans('admin::app.settings.users.index.create-success');

        if (request()->ajax() || request()->wantsJson()) {
            return new JsonResponse([
                'data'    => $admin,
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('admin.settings.users.index')
            ->with('success', $message);
    }

    /**
     * Read-only overview of a user.
     */
    public function view(int $id): View
    {
        $user = $this->userRepository->with(['role', 'groups', 'resource.clinicDepartment', 'clinic'])->findOrFail($id);

        return view('adminc::users.view', [
            'user'            => $user,
            'activitiesCount' => $this->auditLog($user)->count(),
        ]);
    }

    /**
     * Impersonation log of a clinic user.
     */
    public function activities(int $id)
    {
        abort_if(! bouncer()->hasPermission('settings.user.users.view'), 403);

        return $this->paginateSystemActivities($this->auditLog($this->userRepository->findOrFail($id)));
    }

    /**
     * Full history of this user: impersonations (written on the clinic of that moment by
     * ImpersonationService::logUserActivity, matched on the impersonated user id only so the log stays
     * complete after a clinic change) and field changes (LogUserChangesAction, subject_user.id).
     */
    private function auditLog(User $user)
    {
        return Activity::where('type', ActivityType::SYSTEM)->where(function ($q) use ($user) {
            $q->where('additional->clinic_user->id', $user->id)
                ->orWhere('additional->subject_user->id', $user->id);
        });
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View|JsonResponse
    {
        $admin = $this->userRepository->with(['role', 'groups', 'defaultValues'])->findOrFail($id);

        // Transform default values to a key=>value map for simpler frontend binding
        $settingsMap = [];
        foreach ($admin->defaultValues as $setting) {
            $settingsMap[$setting->key] = $setting->value;
        }

        if (request()->ajax() || request()->wantsJson()) {
            $data = $admin->toArray();
            $data['user_default_values'] = $settingsMap;

            return new JsonResponse([
                'data' => $data,
            ]);
        }

        $roles = $this->roleRepository->all();
        $groups = $this->groupRepository->all();

        return view('admin::settings.users.edit', array_merge(
            [
                'user'        => $admin,
                'roles'       => $roles,
                'groups'      => $groups,
                'settingsMap' => $settingsMap,
            ],
            $this->getDefaultValueOptions(),
            $this->getPortalRoleOptions()
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(int $id): View|JsonResponse|RedirectResponse
    {
        $this->nullifyEmptyUserDefaultValues();

        try {
            $this->validate(request(), $this->getRequestValidationRules($id), [], $this->getRequestValidationAttributes());
        } catch (ValidationException $e) {
            // For web requests, redirect back with errors so tests can assert 302 + session errors
            if (! request()->expectsJson() && ! request()->ajax()) {
                return redirect()->back()->withErrors($e->validator)->withInput();
            }
            throw $e;
        }

        $data = request()->all();
        // Normalize composite name field expected by backend
        $data['name'] = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

        // Don't hash password here - let the User model's mutator handle it
        // This allows UserObserver to capture the plaintext password
        // The edit form always submits a password field, so check for an
        // empty value (not just field absence) to avoid wiping the password.
        if (empty($data['password'])) {
            unset($data['password'], $data['confirm_password']);
        }

        if (auth()->guard('user')->user()->id != $id) {
            $data['status'] = $data['status'] ? 1 : 0;
        }

        $data['resource_id'] = $this->roleHasCode($data['role_id'], RoleCode::ARTS) ? ($data['resource_id'] ?? null) : null;
        $data['clinic_id'] = $this->roleHasCode($data['role_id'], RoleCode::KLINIEK_MEDEWERKER) ? ($data['clinic_id'] ?? null) : null;

        Event::dispatch('settings.user.update.before', $id);

        $admin = $this->userRepository->update($data, $id);

        $admin->view_permission = $data['view_permission'];

        $admin->save();

        // Normalize groups from payload (supports array of ids or array of objects with id)
        $groupInput = request()->input('groups', []);
        $groupIds = collect(is_array($groupInput) ? $groupInput : [])
            ->map(function ($g) {
                return is_array($g) ? ($g['id'] ?? null) : $g;
            })
            ->filter()
            ->values()
            ->all();
        $admin->groups()->sync($groupIds);

        // Save user default values if provided
        $settings = request('user_default_values', request('user_settings', []));
        if (is_array($settings)) {
            foreach ($settings as $key => $value) {
                if ($key === '' || $key === null) {
                    continue;
                }
                UserDefaultValue::updateOrCreate(
                    [
                        'user_id' => $admin->id,
                        'key'     => (string) $key,
                    ],
                    [
                        'value' => isset($value) ? (string) $value : null,
                    ]
                );
            }
        }

        Event::dispatch('settings.user.update.after', $admin);

        $message = trans('admin::app.settings.users.index.update-success');

        if (request()->ajax() || request()->wantsJson()) {
            return new JsonResponse([
                'data'    => $admin,
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('admin.settings.users.index')
            ->with('success', $message);
    }

    /**
     * Search user results.
     */
    public function search(): JsonResource
    {
        $users = $this->userRepository
            ->pushCriteria(app(RequestCriteria::class))
            ->scopeQuery(fn ($query) => $this->userRepository->excludingClinicRoles($query))
            ->all();

        return UserResource::collection($users);
    }

    /**
     * Destroy specified user.
     */
    public function destroy(int $id): JsonResponse
    {
        if ($this->userRepository->count() == 1) {
            return new JsonResponse([
                'message' => trans('admin::app.settings.users.index.last-delete-error'),
            ], 400);
        }

        try {
            Event::dispatch('user.admin.delete.before', $id);

            $this->userRepository->delete($id);

            Event::dispatch('user.admin.delete.after', $id);

            return new JsonResponse([
                'message' => trans('admin::app.settings.users.index.delete-success'),
            ], 200);
        } catch (Exception $e) {
        }

        return new JsonResponse([
            'message' => trans('admin::app.settings.users.index.delete-failed'),
        ], 500);
    }

    /**
     * Mass Update the specified resources.
     */
    public function massUpdate(MassUpdateRequest $massDestroyRequest): JsonResponse
    {
        $count = 0;

        $users = $this->userRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

        foreach ($users as $user) {
            if (auth()->guard('user')->user()->id == $user->id) {
                continue;
            }

            Event::dispatch('settings.user.update.before', $user->id);

            $this->userRepository->update([
                'status' => $massDestroyRequest->input('value'),
            ], $user->id);

            Event::dispatch('settings.user.update.after', $user->id);

            $count++;
        }

        if (! $count) {
            return response()->json([
                'message' => trans('admin::app.settings.users.index.mass-update-failed'),
            ], 400);
        }

        return response()->json([
            'message' => trans('admin::app.settings.users.index.mass-update-success'),
        ]);
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $count = 0;

        $users = $this->userRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

        foreach ($users as $user) {
            if (auth()->guard('user')->user()->id == $user->id) {
                continue;
            }

            Event::dispatch('settings.user.delete.before', $user->id);

            $this->userRepository->delete($user->id);

            Event::dispatch('settings.user.delete.after', $user->id);

            $count++;
        }

        if (! $count) {
            return response()->json([
                'message' => trans('admin::app.settings.users.index.mass-delete-failed'),
            ], 400);
        }

        return response()->json([
            'message' => trans('admin::app.settings.users.index.mass-delete-success'),
        ]);
    }

    private function getDefaultValueOptions(): array
    {
        return [
            'departments' => DepartmentModel::orderBy('name')->pluck('name', 'id'),
            'leadTypes'   => LeadType::orderBy('name')->pluck('name', 'id'),
            'channels'    => Channel::orderBy('name')->pluck('name', 'id'),
            'sources'     => Source::orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function nullifyEmptyUserDefaultValues(): void
    {
        $values = request('user_default_values', []);

        if (is_array($values)) {
            $sanitized = array_map(fn ($v) => ($v === '' ? null : $v), $values);
            request()->merge(['user_default_values' => $sanitized]);
        }
    }

    private function getRequestValidationAttributes(): array
    {
        return [
            'first_name'                                   => 'voornaam',
            'last_name'                                    => 'achternaam',
            'email'                                        => 'e-mailadres',
            'password'                                     => 'wachtwoord',
            'password_confirmation'                        => 'wachtwoordbevestiging',
            'role_id'                                      => 'rol',
            'resource_id'                                  => 'arts-resource',
            'clinic_id'                                    => 'kliniek',
            'user_default_values.lead.department_id'       => 'standaard afdeling',
            'user_default_values.lead.type_id'             => 'standaard type',
            'user_default_values.lead.lead_channel_id'     => 'standaard kanaal',
            'user_default_values.lead.lead_source_id'      => 'standaard bron',
        ];
    }

    private function roleHasCode(mixed $roleId, RoleCode $code): bool
    {
        return $roleId && Role::find((int) $roleId)?->code === $code;
    }

    private function getPortalRoleOptions(): array
    {
        return [
            'artsResources' => Resource::artsen()->activeWithActiveClinic()->with('clinicDepartment.clinic')->orderBy('name')->get(),
            'clinics'       => Clinic::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function getRequestValidationRules(?int $userId = null): array
    {

        return [
            'email'            => 'required|email|unique:users,email,'.$userId ?? '',
            'first_name'       => 'required',
            'last_name'        => 'required',
            'password'         => ['nullable', Password::defaults()],
            'confirm_password' => 'nullable|required_with:password|same:password',
            'role_id'          => 'required',
            'resource_id'      => [
                Rule::requiredIf(fn () => $this->roleHasCode(request('role_id'), RoleCode::ARTS)),
                'nullable',
                'integer',
                Rule::in(Resource::artsen()->activeWithActiveClinic()->pluck('id')->all()),
                Rule::unique('users', 'resource_id')->ignore($userId),
            ],
            'clinic_id'        => [
                Rule::requiredIf(fn () => $this->roleHasCode(request('role_id'), RoleCode::KLINIEK_MEDEWERKER)),
                'nullable',
                'integer',
                Rule::exists('clinics', 'id')->where('is_active', true),
            ],
            'signature'                                => 'nullable|string|max:50000',
            'groups'                                   => 'nullable|array',
            'groups.*'                                 => 'nullable|integer|exists:groups,id',
            'groups.*.id'                              => 'nullable|integer|exists:groups,id',
            'user_default_values.lead\\.department_id' => [
                'nullable',
                'integer',
                Rule::in([DepartmentModel::findHerniaId(), DepartmentModel::findPrivateScanId()]),
            ],
            'user_default_values.lead\\.type_id' => [
                'nullable',
                'integer',
                Rule::exists(LeadType::class, 'id'),
            ],
            'user_default_values.lead\\.lead_channel_id' => [
                'nullable',
                'integer',
                Rule::exists(Channel::class, 'id'),
            ],
            'user_default_values.lead\\.lead_source_id' => [
                'nullable',
                'integer',
                Rule::exists(Source::class, 'id'),
            ],
        ];
    }
}
