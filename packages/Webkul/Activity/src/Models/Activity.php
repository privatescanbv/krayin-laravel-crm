<?php

namespace Webkul\Activity\Models;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\AppointmentTimeFilter;
use App\Enums\EntityType;
use App\Enums\PatientMessageSenderType;
use App\Models\CallStatus;
use App\Models\Clinic;
use App\Models\Order;
use App\Models\PatientMessage;
use App\Models\SalesLead;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Webkul\Activity\Contracts\Activity as ActivityContract;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\EmailProxy;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Lead\Models\LeadProxy;
use Webkul\User\Models\GroupProxy;
use Webkul\User\Models\UserProxy;

class Activity extends Model implements ActivityContract
{
    /**
     * Define table name of property
     *
     * @var string
     */
    protected $table = 'activities';

    /**
     * Define relationships that should be touched on save
     *
     * @var array
     */
    protected $with = ['user'];

    /**
     * Cast attributes to date time
     *
     * @var array
     */
    protected $casts = [
        'schedule_from' => 'datetime',
        'schedule_to'   => 'datetime',
        'assigned_at'   => 'datetime',
        'additional'    => 'array',
        'is_done'              => 'boolean',
        'publish_to_portal'  => 'boolean',
        'type'         => ActivityType::class,
        'status'       => ActivityStatus::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'type',
        'location',
        'comment',
        'additional',
        'schedule_from',
        'schedule_to',
        'is_done',
        'publish_to_portal',
        'status',
        'user_id',
        'assigned_at',
        'group_id',
        'lead_id',
        'sales_lead_id',
        'order_id',
        'clinic_id',
        'person_id',
        'external_id',
    ];

    /**
     * Entity foreign key columns (derived from EntityType enum).
     *
     * @return string[]
     */
    public static function entityForeignKeys(): array
    {
        return array_map(
            fn (EntityType $e) => $e->getForeignKey(),
            EntityType::cases(),
        );
    }

    /**
     * All foreign key columns on the activities table (entity + assignment).
     *
     * @return string[]
     */
    public static function foreignKeyFields(): array
    {
        return array_merge(
            self::entityForeignKeys(),
            ['user_id', 'group_id'],
        );
    }

    /**
     * Normalize FK values in a data array:
     * - empty strings / null -> null
     * - numeric strings -> int
     * - zero -> null for entity FKs
     */
    public static function normalizeForeignKeys(array &$data): void
    {
        $entityFks = self::entityForeignKeys();

        foreach (self::foreignKeyFields() as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($data[$field] === '' || $data[$field] === null) {
                $data[$field] = null;

                continue;
            }

            if (is_numeric($data[$field])) {
                $data[$field] = (int) $data[$field];
            }

            if (in_array($field, $entityFks, true) && $data[$field] === 0) {
                $data[$field] = null;
            }
        }
    }

    /**
     * Get the user that owns the activity.
     */
    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }


    /**
     * Get the file associated with the activity.
     */
    public function files()
    {
        return $this->hasMany(FileProxy::modelClass(), 'activity_id');
    }

    /**
     * Get the lead that owns the activity.
     */
    public function lead()
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    /**
     * Get the workflow lead (sales lead) that owns the activity.
     */
    /**
     * Get the sales lead that owns the activity.
     */
    public function salesLead()
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    /**
     * Get the clinic that owns the activity.
     */
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Get the order that owns the activity.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the patient messages associated with the activity.
     */
    public function patientMessages()
    {
        return $this->hasMany(PatientMessage::class, 'activity_id');
    }

    /**
     * Get the primary person that owns the activity (direct FK).
     */
    public function person()
    {
        return $this->belongsTo(PersonProxy::modelClass(), 'person_id');
    }

    /**
     * Call statuses linked to this activity.
     */
    public function callStatuses()
    {
        return $this->hasMany(CallStatus::class, 'activity_id');
    }

    public function emails()
    {
        return $this->hasMany(EmailProxy::modelClass(), 'activity_id');
    }

    /**
     * Get the group that is assigned to this activity.
     */
    public function group()
    {
        return $this->belongsTo(GroupProxy::modelClass(), 'group_id');
    }

    public function getSugarLinkAttribute() :?string
    {
        // no support
//        if ($this->external_id) {
//            $baseUrl = config('services.sugarcrm.base_url');
//            $record = $this->external_id;
//            $activityType = $this->mapSugarActivityType();
//            return "{$baseUrl}action=DetailView&module={$activityType}&record={$record}";
//            //&offset=3&stamp=1758266889055967400{$record}
//        }
        return null;
    }

    private function mapSugarActivityType(): ?string
    {
        return match ($this->type) {
            ActivityType::CALL => 'Calls',
            ActivityType::TASK => 'Tasks',
            default => null,
        };
    }


    public function getPatientFromActivity(): ?Person
    {
        return ($this->person_id ? $this->person : null)
            ?? $this->lead?->persons->first()
            ?? $this->salesLead?->persons->first();
    }

    /**
     * Resolve all persons associated with this activity via any known relation:
     * direct person_id FK, lead, sales lead, or order → sales lead.
     *
     * @return Collection<int, Person>
     */
    public function getPatientsFromActivity(): Collection
    {
        $persons = collect();

        if ($this->person_id) {
            $persons = $persons->merge($this->person ? [$this->person] : []);
        }

        if ($this->lead_id) {
            $persons = $persons->merge($this->lead?->persons ?? collect());
        }

        if ($this->sales_lead_id) {
            $persons = $persons->merge($this->salesLead?->persons ?? collect());
        }

        if ($this->order_id) {
            $persons = $persons->merge($this->order?->salesLead?->persons ?? collect());
        }

        return $persons->unique('id')->values();
    }

    public function getIsReadAttribute(): bool
    {
        if ($this->type === ActivityType::PATIENT_MESSAGE) {
            // Check if there are any unread messages from PATIENT for this activity
            // If any message is NOT read, the whole activity is unread.
            // Assuming messages from staff are always read or don't count.
            return ! $this->patientMessages()
                ->where('is_read', false)
                ->where('sender_type', PatientMessageSenderType::PATIENT)
                ->exists();
        }

        return true;
    }

    /**
     * Scope activities that are published to the patient portal.
     */
    public function scopePublishedToPortal(Builder $query): Builder
    {
        return $query->where('publish_to_portal', true);
    }

    /**
     * Scope activities by type.
     */
    public function scopeOfType(Builder $query, ActivityType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /**
     * Scope activities related to a person via any of the known relations:
     * direct person_id FK, lead, sales lead, or order.
     */
    public function scopeForPerson(Builder $query, Person $person): Builder
    {
        return $query->where(function (Builder $q) use ($person) {
            $q->where('person_id', $person->id)
                ->orWhereHas('lead.persons', fn (Builder $sub) => $sub->whereKey($person->id))
                ->orWhereHas('salesLead.persons', fn (Builder $sub) => $sub->whereKey($person->id))
                ->orWhereHas('order.salesLead.persons', fn (Builder $sub) => $sub->whereKey($person->id));
        });
    }

    /**
     * Scope activity time filtering based on schedule_from.
     */
    public function scopeScheduleTimeFilter(Builder $query, ?AppointmentTimeFilter $filter, Carbon $now): Builder
    {
        return $query
            ->when($filter === AppointmentTimeFilter::FUTURE, fn (Builder $q) => $q->where('schedule_from', '>=', $now))
            ->when($filter === AppointmentTimeFilter::PAST,   fn (Builder $q) => $q->where('schedule_from', '<', $now));
    }

    /**
     * Reopen activity, schedule for next 7 days.
     */
    public function reOpen():Activity {
        $this->is_done = false;
        $this->status = ActivityStatus::ACTIVE;
        $this->schedule_from = Carbon::today();
        $this->schedule_to = Carbon::today()->addWeek();
        return $this;
    }
}
