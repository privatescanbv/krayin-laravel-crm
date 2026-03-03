<?php

return [
    'trigger_entities' => [

        'leads' => [
            'name'   => 'Leads',
            'class'  => 'Webkul\Automation\Helpers\Entity\Lead',
            'events' => [
                [
                    'event' => 'lead.create.after',
                    'name'  => 'Created',
                ], [
                    'event' => 'lead.update.after',
                    'name'  => 'Updated',
                ], [
                    'event' => 'lead.delete.before',
                    'name'  => 'Deleted',
                ],
                [   'event' => 'lead.update_stage.after',
                    'name'  => 'Status',
                ]
            ],
        ],

        'saleslead' => [
            'name'   => 'Sales',
            'class'  => 'Webkul\Automation\Helpers\Entity\SalesLead',
            'events' => [
                [
                    'event' => 'sale.create.after',
                    'name'  => 'Created',
                ], [
                    'event' => 'sale.update.after',
                    'name'  => 'Updated',
                ], [
                    'event' => 'sale.delete.before',
                    'name'  => 'Deleted',
                ],
                [   'event' => 'sale.update_stage.after',
                    'name'  => 'Status',
                ]
            ],
        ],

        'activities' => [
            'name'   => 'Activities',
            'class'  => 'Webkul\Automation\Helpers\Entity\Activity',
            'events' => [
                [
                    'event' => 'activity.create.after',
                    'name'  => 'Created',
                ], [
                    'event' => 'activity.update.after',
                    'name'  => 'Updated',
                ], [
                    'event' => 'activity.delete.before',
                    'name'  => 'Deleted',
                ],
            ],
        ],

        'persons' => [
            'name'   => 'Persons',
            'class'  => 'Webkul\Automation\Helpers\Entity\Person',
            'events' => [
                [
                    'event' => 'contacts.person.create.after',
                    'name'  => 'Created',
                ], [
                    'event' => 'contacts.person.update.after',
                    'name'  => 'Updated',
                ], [
                    'event' => 'contacts.person.delete.before',
                    'name'  => 'Deleted',
                ],
            ],
        ],

        'orders' => [
            'name'   => 'Orders',
            'class'  => 'Webkul\Automation\Helpers\Entity\Order',
            'events' => [
                [
                    'event' => 'order.update_stage.after',
                    'name'  => 'Status',
                ],
            ],
        ],

        // Removed quotes entity: class no longer exists
    ],
];
