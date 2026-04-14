<?php

namespace App\Enums;

enum EntityType: string
{
    case LEAD = 'lead';
    case SALES = 'sales';
    case ORDER = 'order';
    case CLINIC = 'clinic';
    case PERSON = 'person';

    public static function haveActivities(): array
    {
        return [
            self::LEAD,
            self::SALES,
            self::ORDER,
            self::CLINIC,
        ];
    }

    public static function resolveFromActivity(object $activity): ?self
    {
        foreach (self::cases() as $type) {
            if (! empty($activity->{$type->getForeignKey()})) {
                return $type;
            }
        }

        return null;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::LEAD   => 'Lead',
            self::SALES  => 'Sales',
            self::ORDER  => 'Order',
            self::CLINIC => 'kliniek',
            self::PERSON => 'Persoon',
        };
    }

    public function getRoute(): string
    {
        return match ($this) {
            self::LEAD   => 'admin.leads.view',
            self::SALES  => 'admin.sales-leads.view',
            self::ORDER  => 'admin.orders.view',
            self::CLINIC => 'admin.clinics.view',
            self::PERSON => 'admin.contacts.persons.view',
        };
    }

    /**
     * Note: requirement is that you name the relation key always as this method is returning. For now used in relation for Email
     *
     * @return string database column name
     */
    public function getForeignKey(): string
    {
        return match ($this) {
            self::LEAD   => 'lead_id',
            self::SALES  => 'sales_lead_id',
            self::ORDER  => 'order_id',
            self::CLINIC => 'clinic_id',
            self::PERSON => 'person_id',
        };
    }

    public function getRelation(): string
    {
        return match ($this) {
            self::LEAD   => 'lead',
            self::SALES  => 'salesLead',
            self::ORDER  => 'order',
            self::CLINIC => 'clinic',
            self::PERSON => 'person',
        };
    }

    public function getModel(): string
    {
        return match ($this) {
            self::LEAD   => \Webkul\Lead\Models\Lead::class,
            self::SALES  => \App\Models\SalesLead::class,
            self::ORDER  => \App\Models\Order::class,
            self::CLINIC => \App\Models\Clinic::class,
            self::PERSON => \Webkul\Contact\Models\Person::class,
        };
    }
}
