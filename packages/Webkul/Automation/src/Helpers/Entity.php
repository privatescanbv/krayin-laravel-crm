<?php

namespace Webkul\Automation\Helpers;

use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\EmailTemplate\Repositories\EmailTemplateRepository;

class Entity
{
    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected EmailTemplateRepository $emailTemplateRepository
    ) {}

    /**
     * Returns events to match for the entity
     *
     * @return array
     */
    public function getEvents()
    {
        $entities = config('workflows.trigger_entities');

        $events = [];


        foreach ($entities as $key => $entity) {
            $object = app($entity['class']);

            $events[$key] = [
                'id'     => $key,
                'name'   => $entity['name'],
                'events' => $entity['events'],
            ];
        }
        return $events;
    }

    /**
     * Returns conditions to match for the entity
     *
     * @return array
     */
    public function getConditions()
    {
        $entities = config('workflows.trigger_entities');

        $conditions = [];

        foreach ($entities as $key => $entity) {
            $object = app($entity['class']);

            $conditions[$key] = $object->getConditions();
        }
        return $conditions;
    }

    /**
     * Returns workflow actions
     *
     * @return array
     */
    public function getActions()
    {
        $entities = config('workflows.trigger_entities');

        $conditions = [];

        foreach ($entities as $key => $entity) {
            $object = app($entity['class']);

            $conditions[$key] = $object->getActions();
        }

        return $conditions;
    }

    /**
     * Returns placeholders for email templates
     *
     * @return array
     */
    public function getEmailTemplatePlaceholders(): array
    {
        $entities = config('workflows.trigger_entities');

        $output = [];

        foreach ($entities as $config) {
            $object = app($config['class']);

            $raw = method_exists($object, 'getEmailTemplatePlaceholders')
                ? $object->getEmailTemplatePlaceholders($config)
                : [];

            // NORMALISEREN
            $output[] = [
                'text' => $raw['text'] ?? ($config['name'] ?? ''),   // fallback label
                'menu' => array_map(function ($item) {
                    return [
                        'text'  => $item['text'] ?? '',
                        'value' => $item['value'] ?? '',
                    ];
                }, $raw['menu'] ?? []),
            ];
        }
        return $output;
    }
}
