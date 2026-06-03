<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use App\Enums\ActivityType;
use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Contact\Models\Person;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Admin\Http\Controllers\Concerns\ConcatsEmailActivities;

class ActivityController extends Controller
{
    use ConcatsEmailActivities;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected ActivityRepository $activityRepository,
        protected EmailRepository $emailRepository,
        protected AttachmentRepository $attachmentRepository
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index($id)
    {
        $isDoneFilter = request()->has('is_done') ? (int) request('is_done') : null;

        // 1. Eigen person-activiteiten — geen label
        $personQuery = Activity::where('person_id', $id);
        if (! is_null($isDoneFilter)) {
            $personQuery->where('is_done', $isDoneFilter);
        }
        $personActivities = $personQuery->get();

        // 2. Lead-activiteiten — label "Lead: naam"
        $leadIds = DB::table('lead_persons')->where('person_id', $id)->pluck('lead_id');
        $leadActivities = collect();
        if ($leadIds->isNotEmpty()) {
            $leadNames = Lead::whereIn('id', $leadIds)->get(['id', 'first_name', 'last_name', 'lastname_prefix'])->pluck('name', 'id');
            $leadQuery = Activity::whereIn('lead_id', $leadIds);
            if (! is_null($isDoneFilter)) {
                $leadQuery->where('is_done', $isDoneFilter);
            }
            $leadActivities = $leadQuery->get();
            $leadActivities->each(fn ($a) => $a->entity_source = [
                'type'  => 'lead',
                'label' => 'Lead: '.($leadNames[$a->lead_id] ?? $a->lead_id),
                'url'   => route('admin.leads.view', $a->lead_id),
            ]);
        }

        // 3. SalesLead-activiteiten — label "Sales: naam"
        $salesLeadIds = DB::table('saleslead_persons')->where('person_id', $id)->pluck('saleslead_id');
        $salesActivities = collect();
        if ($salesLeadIds->isNotEmpty()) {
            $salesNames = SalesLead::whereIn('id', $salesLeadIds)->pluck('name', 'id');
            $salesQuery = Activity::whereIn('sales_lead_id', $salesLeadIds);
            if (! is_null($isDoneFilter)) {
                $salesQuery->where('is_done', $isDoneFilter);
            }
            $salesActivities = $salesQuery->get();
            $salesActivities->each(fn ($a) => $a->entity_source = [
                'type'  => 'sales',
                'label' => 'Sales: '.($salesNames[$a->sales_lead_id] ?? $a->sales_lead_id),
                'url'   => route('admin.sales-leads.view', $a->sales_lead_id),
            ]);
        }

        // 4. Order-activiteiten — label "Order: titel"
        $orderActivities = collect();
        if ($salesLeadIds->isNotEmpty()) {
            $orderIds = Order::whereIn('sales_lead_id', $salesLeadIds)->pluck('id');
            if ($orderIds->isNotEmpty()) {
                $orderTitles = Order::whereIn('id', $orderIds)->pluck('title', 'id');
                $orderQuery = Activity::whereIn('order_id', $orderIds);
                if (! is_null($isDoneFilter)) {
                    $orderQuery->where('is_done', $isDoneFilter);
                }
                $orderActivities = $orderQuery->get();
                $orderActivities->each(fn ($a) => $a->entity_source = [
                    'type'  => 'order',
                    'label' => 'Order: '.($orderTitles[$a->order_id] ?? $a->order_id),
                    'url'   => route('admin.orders.view', $a->order_id),
                ]);
            }
        }

        $all = $personActivities
            ->merge($leadActivities)
            ->merge($salesActivities)
            ->merge($orderActivities)
            ->unique('id')
            ->values();

        return ActivityResource::collection(
            $this->concatEmailActivitiesFor('person', (int) $id, $all, $this->attachmentRepository)
        );
    }

    /**
     * Start a patient message chat for a person.
     */
    public function store(int $id): RedirectResponse
    {
        if (! bouncer()->hasPermission('activities.create')) {
            abort(403);
        }

        $person = Person::query()->findOrFail($id);
        $existingActivity = $person->primaryActivities()
            ->where('type', ActivityType::PATIENT_MESSAGE->value)
            ->orderByDesc('updated_at')
            ->first();

        if ($existingActivity) {
            return redirect()->to(route('admin.contacts.persons.view', $person->id) . '#patient-berichten');
        }

        $activity = $this->activityRepository->create([
            'type'          => ActivityType::PATIENT_MESSAGE->value,
            'title'         => 'Berichten patiënt portaal',
            'comment'       => null,
            'user_id'       => auth()->guard('user')->id(),
            'schedule_from' => now(),
            'schedule_to'   => now(),
            'is_done'       => 0,
            'person_id'     => $person->id,
            'additional'    => [
                'skip_patient_message_creation' => true,
            ],
        ]);

        return redirect()->to(route('admin.contacts.persons.view', $person->id) . '#patient-berichten');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function concatEmailAsActivities($personId, $activities)
    {
        return $this->concatEmailActivitiesFor('person', (int) $personId, $activities, $this->attachmentRepository);
    }
}
