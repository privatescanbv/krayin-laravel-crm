<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\Department as DepartmentModel;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Admin\DataGrids\Settings\UserDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\UserResource;
use Webkul\Admin\Notifications\User\Create as UserCreatedNotification;
use Webkul\Lead\Models\Channel;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Type as LeadType;
use Webkul\User\Repositories\GroupRepository;
use Webkul\User\Repositories\RoleRepository;
use Webkul\User\Repositories\UserRepository;
use Webkul\User\Models\UserDefaultValue;

class UserController extends Controller
{
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
        $roles  = $this->roleRepository->all();
        $groups = $this->groupRepository->all();

        return view('admin::settings.users.create', compact('roles', 'groups'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(): View|JsonResponse | RedirectResponse
    {
        try {
             $this->validate(request(), $this->getRequestValidationRules());
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
        // if (isset($data['password']) && $data['password']) {
        //     $data['password'] = bcrypt($data['password']);
        // }

        $data['status'] = array_key_exists('status', $data) ? $data['status'] : 0;

        Event::dispatch('settings.user.create.before');

        $admin = $this->userRepository->create($data);

        $admin->view_permission = $data['view_permission'];

        $admin->save();

        // Normalize groups from payload (supports array of ids or array of objects with id)
        $groupInput = request()->input('groups', []);
        $groupIds = collect(is_array($groupInput) ? $groupInput : [])
            ->map(function ($g) { return is_array($g) ? ($g['id'] ?? null) : $g; })
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

        $roles  = $this->roleRepository->all();
        $groups = $this->groupRepository->all();

        return view('admin::settings.users.edit', [
            'user'        => $admin,
            'roles'       => $roles,
            'groups'      => $groups,
            'settingsMap' => $settingsMap,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(int $id): View|JsonResponse|RedirectResponse
    {
        try {
            $this->validate(request(), $this->getRequestValidationRules($id));
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
        if (!array_key_exists('password', $data)) {
            unset($data['password'], $data['confirm_password']);
        }
        // else {
        //     $data['password'] = bcrypt($data['password']);
        // }

        if (auth()->guard('user')->user()->id != $id) {
            $data['status'] = $data['status'] ? 1 : 0;
        }

        Event::dispatch('settings.user.update.before', $id);

        $admin = $this->userRepository->update($data, $id);

        $admin->view_permission = $data['view_permission'];

        $admin->save();

        // Normalize groups from payload (supports array of ids or array of objects with id)
        $groupInput = request()->input('groups', []);
        $groupIds = collect(is_array($groupInput) ? $groupInput : [])
            ->map(function ($g) { return is_array($g) ? ($g['id'] ?? null) : $g; })
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

        foreach ($users as $users) {
            if (auth()->guard('user')->user()->id == $users->id) {
                continue;
            }

            Event::dispatch('settings.user.update.before', $users->id);

            $this->userRepository->update([
                'status' => $massDestroyRequest->input('value'),
            ], $users->id);

            Event::dispatch('settings.user.update.after', $users->id);

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

    private function getRequestValidationRules(?int $userId = null): array {

        return [
            'email'            => 'required|email|unique:users,email,'.$userId ?? '',
            'first_name'       => 'required',
            'last_name'        => 'required',
            'password'         => 'nullable',
            'confirm_password' => 'nullable|required_with:password|same:password',
            'role_id'          => 'required',
            'signature'        => 'nullable|string|max:50000',
            'groups'           => 'nullable|array',
            'groups.*'         => 'nullable|integer|exists:groups,id',
            'groups.*.id'      => 'nullable|integer|exists:groups,id',
            // Support both nested and dotted key forms for department default
            'user_default_values.lead\\.department_id' => [
                'nullable',
                'integer',
                Rule::in([DepartmentModel::findHerniaId(), DepartmentModel::findPrivateScanId()]),
            ],
            'user_default_values.lead\\.type_id' =>  [
                'nullable',
                'integer',
                Rule::exists(LeadType::class, 'id'),
            ],
            'user_default_values.lead\\.lead_channel_id' =>  [
                'nullable',
                'integer',
                Rule::exists(Channel::class, 'id'),
            ],
            'user_default_values.lead\\.lead_source_id' =>  [
                'nullable',
                'integer',
                Rule::exists(Source::class, 'id'),
            ],
        ];
    }
}
