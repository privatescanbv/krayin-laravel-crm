<?php

namespace Webkul\Automation\Helpers\Entity;

use App\Actions\Activities\CreateActivityForLeadOrSalesAction;
use App\Actions\Activities\DuplicateException;
use App\Enums\ActivityType;
use App\Enums\PipelineStage;
use App\Repositories\SalesLeadRepository;
use Exception;
use Illuminate\Support\Facades\Mail;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Notifications\Common;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Automation\Repositories\WebhookRepository;
use Webkul\Automation\Services\WebhookService;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\EmailTemplate\Repositories\EmailTemplateRepository;
use Webkul\Lead\Models\Pipeline;
use Webkul\Tag\Repositories\TagRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Webkul\Attribute\Repositories\AttributeValueRepository;

class SalesLead extends AbstractEntity
{
    /**
     * Define the entity type.
     */
    protected string $entityType = 'saleslead';

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository                       $attributeRepository,
        protected EmailTemplateRepository                   $emailTemplateRepository,
        protected SalesLeadRepository                       $salesRepository,
        protected ActivityRepository                        $activityRepository,
        protected PersonRepository                          $personRepository,
        protected TagRepository                             $tagRepository,
        protected WebhookRepository                         $webhookRepository,
        protected WebhookService                            $webhookService,
        protected AttributeValueRepository                  $attributeValueRepository,
        private readonly CreateActivityForLeadOrSalesAction $createActivityForLeadOrSalesAction,
    )
    {
    }

    /**
     * Listing of the entities.
     */
    public function getEntity(mixed $entity)
    {
        if (! $entity instanceof \App\Models\SalesLead) {
            $entity = $this->salesRepository->find($entity);
        }

        return $entity;
    }

    /**
     * Returns attributes.
     */
    public function getAttributes(string $entityType, array $skipAttributes = ['textarea', 'image', 'file', 'address']): array
    {
        $pipelines = Pipeline::all()
            ->pluck('name', 'id');
        $stages = collect(PipelineStage::cases())->map(function ($stage) use ($pipelines) {
            if ($pipelines->has($stage->pipeline())) {
                return [
                    'id' => (string)$stage->id(),
                    'name' => $pipelines[$stage->pipeline()] . ' | ' . $stage->name(),
                ];
            }
            return [];
        });
        $attributes = [
            [
                'id' => 'pipeline_stage_id',
                'type' => 'select',
                'name' => 'Status code',
                'lookup_type' => null,
                'options' => $stages,
            ],
            [
                'id' => 'description',
                'type' => 'text',
                'name' => 'Omschrijving'
            ]
        ];
        return array_merge($attributes, parent::getAttributes($entityType, $skipAttributes));
    }

    /**
     * Returns workflow actions.
     */
    public function getActions(): array
    {
        $emailTemplates = $this->emailTemplateRepository->all(['id', 'name']);

        $webhooksOptions = $this->webhookRepository->all(['id', 'name']);

        return [
            [
                'id' => 'update_sales',
                'name' => trans('admin::app.settings.workflows.helpers.update-lead'),
                'attributes' => $this->getAttributes('sales'),
            ], [
                'id' => 'update_person',
                'name' => trans('admin::app.settings.workflows.helpers.update-person'),
                'attributes' => $this->getAttributes('persons'),
            ], [
                'id' => 'send_email_to_person',
                'name' => trans('admin::app.settings.workflows.helpers.send-email-to-person'),
                'options' => $emailTemplates,
            ], [
                'id' => 'send_email_to_sales_owner',
                'name' => trans('admin::app.settings.workflows.helpers.send-email-to-sales-owner'),
                'options' => $emailTemplates,
            ], [
                'id' => 'add_note_as_activity',
                'name' => trans('admin::app.settings.workflows.helpers.add-note-as-activity'),
            ], [
                'id' => 'trigger_webhook',
                'name' => trans('admin::app.settings.workflows.helpers.add-webhook'),
                'options' => $webhooksOptions,
            ],
            [
                'id' => 'create_activity',
                'name' => 'Activiteit aanmaken',
                'attributes' => [
                    [
                        'id' => 'title',
                        'name' => 'Titel',
                        'type' => 'text',
                    ],
                    [
                        'id' => 'description',
                        'name' => 'Beschrijving',
                        'type' => 'textarea',
                    ],
                    [
                        'id' => 'type',
                        'name' => 'Type',
                        'type' => 'select',
                        'options' => [
                            ['id' => ActivityType::CALL->value, 'name' => ActivityType::CALL->label()],
                            ['id' => ActivityType::TASK->value, 'name' => ActivityType::TASK->label()],
                        ]
                    ]
                ],
            ],
        ];
    }

    /**
     * Execute workflow actions.
     */
    public function executeActions(mixed $workflow, mixed $sales): void
    {
        foreach ($workflow->actions as $action) {
            Log::info("workflow start sales, {$action['id']}");
            switch ($action['id']) {
                case 'create_activity':
                    $title = $action['attributes']['title'];
                    $type = $action['attributes']['type'];
                    $comment = $action['attributes']['comment'] ?? '';
                    try {
                        $this->createActivityForLeadOrSalesAction->executeForSales($sales, false,
                            [
                                'title' => $title,
                                'type' => $type,
                                'comment' => $comment,
                                'user_id' => null,
                                'schedule_from' => now(),
                                'schedule_to' => now()->addWeek(),
                                'file' => null,
                            ]
                        );
                    } catch (DuplicateException $e) {
                        logger()->error('Could not automatically add activity for lead, duplication', ['error' => $e->getMessage()]);
                    }
                    break;
                case 'update_sales':
                    // Check if the value is actually different from current value
                    $currentValue = $sales->{$action['attribute']};
                    $newValue = $action['value'];

                    if ($currentValue != $newValue) {
                        Log::info('Updating lead attribute', [
                            'sales_id' => $sales->id,
                            'attribute' => $action['attribute'],
                            'old_value' => $currentValue,
                            'new_value' => $newValue,
                        ]);

                        $sales = $this->salesRepository->update(
                            [
                                'entity_type' => 'leads',
                                $action['attribute'] => $action['value'],
                            ],
                            $sales->id,
                            [$action['attribute']]
                        );

                        Event::dispatch('lead.workflows.after', $sales);
                    } else {
                        Log::info('Skipping sales update - no change detected', [
                            'sales_id' => $sales->id,
                            'attribute' => $action['attribute'],
                            'value' => $newValue,
                        ]);
                    }

                    break;

                case 'update_person':
                    $this->personRepository->update([
                        'entity_type' => 'persons',
                        $action['attribute'] => $action['value'],
                    ], $sales->person_id);

                    break;

                case 'send_email_to_person':
                    $emailTemplate = $this->emailTemplateRepository->find($action['value']);

                    if (!$emailTemplate) {
                        break;
                    }

                    try {
                        // Get all email addresses from all associated persons
                        $emailAddresses = [];
                        foreach ($sales->persons as $person) {
                            $emailAddresses = array_merge($emailAddresses, data_get($person->emails, '*.value', []));
                        }

                        if (!empty($emailAddresses)) {
                            Mail::queue(new Common([
                                'to' => $emailAddresses,
                                'subject' => $this->replacePlaceholders($sales, $emailTemplate->subject),
                                'body' => $this->replacePlaceholders($sales, $emailTemplate->content),
                            ]));
                        }
                    } catch (Exception $e) {
                    }

                    break;

                case 'send_email_to_sales_owner':
                    $emailTemplate = $this->emailTemplateRepository->find($action['value']);

                    if (!$emailTemplate) {
                        break;
                    }

                    try {
                        Mail::queue(new Common([
                            'to' => $sales->user->email,
                            'subject' => $this->replacePlaceholders($sales, $emailTemplate->subject),
                            'body' => $this->replacePlaceholders($sales, $emailTemplate->content),
                        ]));
                    } catch (Exception $e) {
                    }

                    break;

                case 'add_note_as_activity':
                    $activity = $this->activityRepository->create([
                        'type' => 'note',
                        'comment' => $action['value'],
                        'is_done' => 1,
                        'user_id' => auth()->guard('user')->user()->id,
                        'sales_lead_id' => $sales->id,
                    ]);

                    break;

                case 'trigger_webhook':
                    try {
                        $this->triggerWebhook($action['value'], $sales);
                    } catch (\Exception $e) {
                        report($e);
                    }

                    break;
                default:
                    Log::warning('Unknown action type encountered in workflow', [
                        'action_id' => $action['id'],
                        'workflow_id' => $workflow->id,
                        'sales_lead_id' => $sales->id,
                    ]);
                    break;
            }
        }
    }
}
