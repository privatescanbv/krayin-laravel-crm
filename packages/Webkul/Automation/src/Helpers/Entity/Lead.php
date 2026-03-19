<?php

namespace Webkul\Automation\Helpers\Entity;

use App\Actions\Activities\CreateActivityForLeadAction;
use App\Actions\Activities\DuplicateException;
use App\Enums\ActivityType;
use Exception;
use Illuminate\Support\Facades\Mail;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Notifications\Common;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Automation\Repositories\WebhookRepository;
use Webkul\Automation\Services\WebhookService;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\EmailTemplate\Repositories\EmailTemplateRepository;
use Webkul\Lead\Contracts\Lead as ContractsLead;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Tag\Repositories\TagRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Webkul\Attribute\Repositories\AttributeValueRepository;

class Lead extends AbstractEntity
{
    /**
     * Define the entity type.
     */
    protected string $entityType = 'leads';

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected EmailTemplateRepository $emailTemplateRepository,
        protected LeadRepository $leadRepository,
        protected ActivityRepository $activityRepository,
        protected PersonRepository $personRepository,
        protected TagRepository $tagRepository,
        protected WebhookRepository $webhookRepository,
        protected WebhookService $webhookService,
        protected AttributeValueRepository $attributeValueRepository,
        private readonly CreateActivityForLeadAction $createActivityForLeadOrSalesAction
    ) {}

    /**
     * Listing of the entities.
     */
    public function getEntity(mixed $entity)
    {
        if (! $entity instanceof ContractsLead) {
            $entity = $this->leadRepository->find($entity);
        }

        return $entity;
    }

    /**
     * Returns attributes.
     */
    public function getAttributes(string $entityType, array $skipAttributes = ['textarea', 'image', 'file', 'address']): array
    {
        $attributes = [
            [
                'id'          => 'lead_pipeline_stage_id',
                'type'        => 'text',
                'name'        => 'Status code',
                'lookup_type' => 'stage',
                'options'     => collect(),
            ],
            [
                'id'          => 'description',
                'type'        => 'text',
                'name'        => 'Omschrijving'
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
                'id'         => 'update_lead',
                'name'       => trans('admin::app.settings.workflows.helpers.update-lead'),
                'attributes' => $this->getAttributes('leads'),
            ], [
                'id'         => 'update_person',
                'name'       => trans('admin::app.settings.workflows.helpers.update-person'),
                'attributes' => $this->getAttributes('persons'),
            ], [
                'id'      => 'send_email_to_person',
                'name'    => trans('admin::app.settings.workflows.helpers.send-email-to-person'),
                'options' => $emailTemplates,
            ], [
                'id'      => 'send_email_to_sales_owner',
                'name'    => trans('admin::app.settings.workflows.helpers.send-email-to-sales-owner'),
                'options' => $emailTemplates,
            ], [
                'id'   => 'add_note_as_activity',
                'name' => trans('admin::app.settings.workflows.helpers.add-note-as-activity'),
            ], [
                'id'      => 'trigger_webhook',
                'name'    => trans('admin::app.settings.workflows.helpers.add-webhook'),
                'options' => $webhooksOptions,
            ],
            [
                'id'      => 'create_activity',
                'name'    => 'Activiteit aanmaken',
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
                    ],
                    [
                        'id'   => 'deadline_in_days',
                        'name' => 'Deadline in dagen (optioneel)',
                        'type' => 'integer',
                    ],
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
            Log::info("workflow start leads, {$action['id']}", ['action'=>$action]);
            switch ($action['id']) {
                case 'create_activity':
                    $title = $action['attributes']['title'];
                    $type = $action['attributes']['type'];
                    $comment = $action['attributes']['comment'] ?? '';
                    $deadlineDays = $action['attributes']['deadline_in_days'] ?? null;
                    $scheduleFrom = now();
                    $scheduleTo = (is_numeric($deadlineDays) && (int) $deadlineDays >= 0)
                        ? now()->addDays((int) $deadlineDays)
                        : now()->addWeek();
                    try {
                        $this->createActivityForLeadOrSalesAction->execute($sales, false,
                            [
                                'title' => $title,
                                'type' => $type,
                                'comment' => $comment,
                                'user_id' => null,
                                'schedule_from' => $scheduleFrom,
                                'schedule_to' => $scheduleTo,
                                'file' => null,
                            ]
                        );
                    } catch (DuplicateException $e) {
                        logger()->error('Could not automatically add activity for lead, duplication',['error'=>$e->getMessage()]);
                    }
                    break;
                case 'update_lead':
                    // Check if the value is actually different from current value
                    $currentValue = $sales->{$action['attribute']};
                    $newValue = $action['value'];

                    if ($currentValue != $newValue) {
                        Log::info('Updating lead attribute', [
                            'lead_id' => $sales->id,
                            'attribute' => $action['attribute'],
                            'old_value' => $currentValue,
                            'new_value' => $newValue,
                        ]);

                    $sales = $this->leadRepository->update(
                        [
                            'entity_type'        => 'leads',
                            $action['attribute'] => $action['value'],
                        ],
                        $sales->id,
                        [$action['attribute']]
                    );

                    Event::dispatch('lead.workflows.after', $sales);
                    } else {
                        Log::info('Skipping lead update - no change detected', [
                            'lead_id' => $sales->id,
                            'attribute' => $action['attribute'],
                            'value' => $newValue,
                        ]);
                    }

                    break;

                case 'update_person':
                    $this->personRepository->update([
                        'entity_type'        => 'persons',
                        $action['attribute'] => $action['value'],
                    ], $sales->person_id);

                    break;

                case 'send_email_to_person':
                    $emailTemplate = $this->emailTemplateRepository->find($action['value']);

                    if (! $emailTemplate) {
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
                                'to'      => $emailAddresses,
                                'subject' => $this->replacePlaceholders($sales, $emailTemplate->subject),
                                'body'    => $this->replacePlaceholders($sales, $emailTemplate->content),
                            ]));
                        }
                    } catch (Exception $e) {
                        logger()->error('Could not send email to person; '.$e->getMessage(), $e);
                    }

                    break;

                case 'send_email_to_sales_owner':
                    $emailTemplate = $this->emailTemplateRepository->find($action['value']);

                    if (! $emailTemplate) {
                        break;
                    }

                    try {
                        Mail::queue(new Common([
                            'to'      => $sales->user->email,
                            'subject' => $this->replacePlaceholders($sales, $emailTemplate->subject),
                            'body'    => $this->replacePlaceholders($sales, $emailTemplate->content),
                        ]));
                    }catch (Exception $e) {
                        logger()->error('Could not send email to person; '.$e->getMessage(), $e);
                    }
                    break;

                case 'add_note_as_activity':
                    $activity = $this->activityRepository->create([
                        'type'    => 'note',
                        'comment' => $action['value'],
                        'is_done' => 1,
                        'user_id' => auth()->guard('user')->user()->id,
                        'lead_id' => $sales->id,
                    ]);

                    break;

                case 'trigger_webhook':
                    try {
                        $this->triggerWebhook($action['value'], $sales);
                    } catch (Exception $e) {
                        report($e);
                    }

                    break;
                default:
                    Log::warning('Unknown action type encountered in workflow', [
                        'action_id' => $action['id'],
                        'workflow_id' => $workflow->id,
                        'lead_id' => $sales->id,
                    ]);
                    break;
            }
        }
    }
}
