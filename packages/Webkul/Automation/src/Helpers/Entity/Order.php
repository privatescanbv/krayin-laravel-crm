<?php

namespace Webkul\Automation\Helpers\Entity;

use App\Actions\Activities\CreateActivityForLeadOrSalesAction;
use App\Actions\Activities\DuplicateException;
use App\Enums\ActivityType;
use App\Enums\PipelineStage;
use App\Models\Order as OrderModel;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Automation\Repositories\WebhookRepository;
use Webkul\Automation\Services\WebhookService;
use Webkul\Lead\Models\Pipeline;

class Order extends AbstractEntity
{
    /**
     * Define the entity type.
     */
    protected string $entityType = 'orders';

    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected OrderRepository $orderRepository,
        protected ActivityRepository $activityRepository,
        protected WebhookRepository $webhookRepository,
        protected WebhookService $webhookService,
        private readonly CreateActivityForLeadOrSalesAction $createActivityForLeadOrSalesAction,
    ) {}

    public function getEntity(mixed $entity)
    {
        if (! $entity instanceof OrderModel) {
            $entity = $this->orderRepository->find($entity);
        }

        return $entity;
    }

    public function getAttributes(string $entityType, array $skipAttributes = ['textarea', 'image', 'file', 'address']): array
    {
        $pipelines = Pipeline::all()->pluck('name', 'id');
        $stages = collect(PipelineStage::cases())
            ->filter(fn ($stage) => $stage->isOrder())
            ->map(function ($stage) use ($pipelines) {
                if ($pipelines->has($stage->pipeline())) {
                    return [
                        'id'   => (string) $stage->id(),
                        'name' => $pipelines[$stage->pipeline()].' | '.$stage->name(),
                    ];
                }

                return [];
            })
            ->filter();

        return [
            [
                'id'          => 'pipeline_stage_id',
                'type'        => 'select',
                'name'        => 'Status code',
                'lookup_type' => null,
                'options'     => $stages,
            ],
        ];
    }

    public function getActions(): array
    {
        return [
            [
                'id'         => 'create_activity',
                'name'       => 'Activiteit aanmaken',
                'attributes' => [
                    [
                        'id'   => 'title',
                        'name' => 'Titel',
                        'type' => 'text',
                    ],
                    [
                        'id'   => 'description',
                        'name' => 'Beschrijving',
                        'type' => 'textarea',
                    ],
                    [
                        'id'      => 'type',
                        'name'    => 'Type',
                        'type'    => 'select',
                        'options' => [
                            ['id' => ActivityType::CALL->value, 'name' => ActivityType::CALL->label()],
                            ['id' => ActivityType::TASK->value, 'name' => ActivityType::TASK->label()],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function executeActions(mixed $workflow, mixed $order): void
    {
        foreach ($workflow->actions as $action) {
            Log::info("workflow start orders, {$action['id']}");
            switch ($action['id']) {
                case 'create_activity':
                    $title   = $action['attributes']['title'];
                    $type    = $action['attributes']['type'];
                    $comment = $action['attributes']['comment'] ?? '';
                    try {
                        $this->createActivityForLeadOrSalesAction->executeForOrder(
                            $order,
                            false,
                            [
                                'title'         => $title,
                                'type'          => $type,
                                'comment'       => $comment,
                                'user_id'       => null,
                                'schedule_from' => now(),
                                'schedule_to'   => now()->addWeek(),
                            ]
                        );
                    } catch (DuplicateException $e) {
                        logger()->error('Could not automatically add activity for order, duplication', ['error' => $e->getMessage()]);
                    }
                    break;
                default:
                    Log::warning('Unknown action type encountered in order workflow', [
                        'action_id'   => $action['id'],
                        'workflow_id' => $workflow->id,
                        'order_id'    => $order->id,
                    ]);
                    break;
            }
        }
    }
}
