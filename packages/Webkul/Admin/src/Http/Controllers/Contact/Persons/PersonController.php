<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Admin\DataGrids\Contact\PersonDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\AttributeForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Resources\PersonResource;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Core\Contracts\Validations\EmailValidator;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

class PersonController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct(
        protected PersonRepository  $personRepository,
        private LeadRepository      $leadRepository,
        private AttributeRepository $attributeRepository)
    {
        request()->request->add(['entity_type' => 'persons']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(PersonDataGrid::class)->process();
        }

        return view('admin::contacts.persons.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin::contacts.persons.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AttributeForm $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'emails' => ['nullable', 'array'],
            'emails.*.value' => ['nullable', new EmailValidator()],
            'emails.*.label' => ['nullable', 'string'],
        ]);
        Event::dispatch('contacts.person.create.before');

        $data = $request->all();
        $data['entity_type'] = 'persons';

        // Filter out entity field if present
        if (isset($data['entity'])) {
            unset($data['entity']);
        }

        // Handle empty date fields
        if (isset($data['date_of_birth']) && empty($data['date_of_birth'])) {
            $data['date_of_birth'] = null;
        }

        // Handle empty unique_id field - convert empty string to null to avoid duplicate key errors
        if (isset($data['unique_id']) && empty($data['unique_id'])) {
            $data['unique_id'] = null;
        }

        // Filter out empty phone numbers
        if (isset($data['phones']) && is_array($data['phones'])) {
            $data['phones'] = array_filter($data['phones'], function($phone) {
                return isset($phone['value']) && !empty(trim($phone['value']));
            });

            // If no valid phones remain, set to empty array
            if (empty($data['phones'])) {
                $data['phones'] = [];
            }
        }

        // Filter out empty email addresses
        if (isset($data['emails']) && is_array($data['emails'])) {
            $data['emails'] = array_filter($data['emails'], function($email) {
                return isset($email['value']) && !empty(trim($email['value']));
            });

            // If no valid emails remain, set to empty array
            if (empty($data['emails'])) {
                $data['emails'] = [];
            }
        }

        // Normaliseer is_default naar boolean voor phones
        if (isset($data['phones']) && is_array($data['phones'])) {
            $data['phones'] = array_map(function($phone) {
                if (isset($phone['is_default'])) {
                    $phone['is_default'] = $phone['is_default'] === true || $phone['is_default'] === 'on' || $phone['is_default'] === '1';
                }
                return $phone;
            }, $data['phones']);
        }
        // Normaliseer is_default naar boolean voor emails
        if (isset($data['emails']) && is_array($data['emails'])) {
            $data['emails'] = array_map(function($email) {
                if (isset($email['is_default'])) {
                    $email['is_default'] = $email['is_default'] === true || $email['is_default'] === 'on' || $email['is_default'] === '1';
                }
                return $email;
            }, $data['emails']);
        }

        // Debug logging
        Log::info('Person create request data:', $data);

        $person = $this->personRepository->create($data);

        Event::dispatch('contacts.person.create.after', $person);

        if (request()->ajax()) {
            return response()->json([
                'data' => $person,
                'message' => trans('admin::app.contacts.persons.index.create-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.contacts.persons.index.create-success'));

        return redirect()->route('admin.contacts.persons.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): View
    {
        $person = $this->personRepository->findOrFail($id);

        return view('admin::contacts.persons.view', compact('person'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $person = $this->personRepository->with('address')->findOrFail($id);

        return view('admin::contacts.persons.edit', compact('person'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AttributeForm $request, int $id): RedirectResponse|JsonResponse
    {
        $request->validate([
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'emails' => ['nullable', 'array'],
            'emails.*.value' => ['nullable', new EmailValidator()],
            'emails.*.label' => ['nullable', 'string'],
        ]);
        Event::dispatch('contacts.person.update.before', $id);

        $data = $request->all();
        $data['entity_type'] = 'persons';

        // Filter out entity field if present
        if (isset($data['entity'])) {
            unset($data['entity']);
        }

        // Debug: Log the incoming data
        Log::info('Person update request data:', $data);

        // Handle empty date fields
        if (isset($data['date_of_birth']) && empty($data['date_of_birth'])) {
            $data['date_of_birth'] = null;
        }

        // Handle empty unique_id field - convert empty string to null to avoid duplicate key errors
        if (isset($data['unique_id']) && empty($data['unique_id'])) {
            $data['unique_id'] = null;
        }

        // Filter out empty phone numbers
        if (isset($data['phones']) && is_array($data['phones'])) {
            $data['phones'] = array_filter($data['phones'], function($phone) {
                return isset($phone['value']) && !empty(trim($phone['value']));
            });

            // If no valid phones remain, set to empty array
            if (empty($data['phones'])) {
                $data['phones'] = [];
            }
        }

        // Filter out empty email addresses
        if (isset($data['emails']) && is_array($data['emails'])) {
            $data['emails'] = array_filter($data['emails'], function($email) {
                return isset($email['value']) && !empty(trim($email['value']));
            });

            // If no valid emails remain, set to empty array
            if (empty($data['emails'])) {
                $data['emails'] = [];
            }
        }

        // Normaliseer is_default naar boolean voor phones
        if (isset($data['phones']) && is_array($data['phones'])) {
            $data['phones'] = array_map(function($phone) {
                if (isset($phone['is_default'])) {
                    $phone['is_default'] = $phone['is_default'] === true || $phone['is_default'] === 'on' || $phone['is_default'] === '1';
                }
                return $phone;
            }, $data['phones']);
        }
        // Normaliseer is_default naar boolean voor emails
        if (isset($data['emails']) && is_array($data['emails'])) {
            $data['emails'] = array_map(function($email) {
                if (isset($email['is_default'])) {
                    $email['is_default'] = $email['is_default'] === true || $email['is_default'] === 'on' || $email['is_default'] === '1';
                }
                return $email;
            }, $data['emails']);
        }

        // Debug: Log the processed data
        Log::info('Person update processed data:', $data);

        $person = $this->personRepository->update($data, $id);

        Event::dispatch('contacts.person.update.after', $person);

        if (request()->ajax()) {
            return response()->json([
                'data' => $person,
                'message' => trans('admin::app.contacts.persons.index.update-success'),
            ], 200);
        }

        session()->flash('success', trans('admin::app.contacts.persons.index.update-success'));

        return redirect()->route('admin.contacts.persons.index');
    }

    /**
     * Search person results.
     */
    public function search(): JsonResource
    {
        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $persons = $this->personRepository
                ->pushCriteria(app(RequestCriteria::class))
                ->findWhereIn('user_id', $userIds);
        } else {
            $persons = $this->personRepository
                ->pushCriteria(app(RequestCriteria::class))
                ->all();
        }

        return PersonResource::collection($persons);
    }

    /**
     * Search person results based on lead data.
     */
    public function searchByLead(Lead $lead): JsonResource
    {
        // Query persons met een join op attribute_values
        $query = Person::query()
            ->select('persons.*');
// Filter op de lead-waarden (exact match, je kunt ook LIKE gebruiken voor fuzzy)
        if (!empty($lead->first_name)) {
            $query->where('first_name', $lead->first_name);
        }
        if (!empty($lead->last_name)) {
            $query->where('last_name', $lead->last_name);
        }
        Log::info($query->toRawSql());

        // Uniek maken
        $persons = $query->distinct()->get();

        return PersonResource::collection($persons);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $person = $this->personRepository->findOrFail($id);

        try {
            Event::dispatch('contacts.person.delete.before', $id);

            $person->delete($id);

            Event::dispatch('contacts.person.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.contacts.persons.index.delete-success'),
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.contacts.persons.index.delete-failed'),
            ], 400);
        }
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $persons = $this->personRepository->findWhereIn('id', $massDestroyRequest->get('indices'));

        foreach ($persons as $person) {
            Event::dispatch('contact.person.delete.before', $person);

            $this->personRepository->delete($person->id);

            Event::dispatch('contact.person.delete.after', $person);
        }

        return response()->json([
            'message' => trans('admin::app.contacts.persons.index.delete-success'),
        ]);
    }
}
