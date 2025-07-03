<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use App\Enums\LeadAttributeKeys;
use App\Enums\PersonAttributeKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Admin\DataGrids\Contact\PersonDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\AttributeForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Resources\PersonResource;
use Webkul\Attribute\Models\AttributeValue;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
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
        Event::dispatch('contacts.person.create.before');

        $person = $this->personRepository->create($request->all());

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
        $person = $this->personRepository->findOrFail($id);

        return view('admin::contacts.persons.edit', compact('person'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AttributeForm $request, int $id): RedirectResponse|JsonResponse
    {
        Event::dispatch('contacts.person.update.before', $id);

        $person = $this->personRepository->update($request->all(), $id);

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
        $attributeId = $this->attributeRepository->getAttributeByCode(LeadAttributeKeys::FIRSTNAME->value)->id;
        $leadFirstName = AttributeValue::where('entity_type', 'leads')
            ->select('text_value')
            ->where('entity_id', $lead->id)
            ->where('attribute_id', $attributeId)
            ->first()?->text_value;
        $attributeId = $this->attributeRepository->getAttributeByCode(LeadAttributeKeys::LASTNAME->value)->id;
        $leadLastName = AttributeValue::where('entity_type', 'leads')
            ->select('text_value')
            ->where('entity_id', $lead->id)
            ->where('attribute_id', $attributeId)
            ->first()?->text_value;

        // Query persons met een join op attribute_values
        $query = Person::query()
            ->select('persons.*')
            ->join('attribute_values as av_first', function ($join) {
                $join->on('persons.id', '=', 'av_first.entity_id')
                    ->where('av_first.entity_type', 'persons')
                    ->where('av_first.attribute_id', function ($query) {
                        $query->select('id')
                            ->from('attributes')
                            ->where('code', PersonAttributeKeys::FIRST_NAME->value)
                            ->where('entity_type', 'persons')
                            ->limit(1);
                    });
            })
            ->join('attribute_values as av_last', function ($join) {
                $join->on('persons.id', '=', 'av_last.entity_id')
                    ->where('av_last.entity_type', 'persons')
                    ->where('av_last.attribute_id', function ($query) {
                        $query->select('id')
                            ->from('attributes')
                            ->where('code', PersonAttributeKeys::LAST_NAME->value)
                            ->where('entity_type', 'persons')
                            ->limit(1);
                    });
            });
// Filter op de lead-waarden (exact match, je kunt ook LIKE gebruiken voor fuzzy)
        if (!empty($leadFirstName)) {
            $query->where('av_first.text_value', $leadFirstName);
        }
        if (!empty($leadLastName)) {
            $query->where('av_last.text_value', $leadLastName);
        }
        $sql = $query->toRawSql();

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
        $persons = $this->personRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

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
