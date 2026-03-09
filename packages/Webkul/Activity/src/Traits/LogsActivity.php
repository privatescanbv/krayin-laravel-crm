<?php

namespace Webkul\Activity\Traits;

use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Attribute\Contracts\AttributeValue;
use Webkul\Attribute\Repositories\AttributeValueRepository;

trait LogsActivity
{
    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function ($model) {
            $entity = $model->entity ?? $model;
            $isPerson = method_exists($entity, 'getTable') && $entity->getTable() === 'persons';
            if (! $isPerson && ! method_exists($entity, 'activities')) {
                return;
            }

            if (! $model instanceof AttributeValue) {
                $activityData = [
                    'type'    => 'system',
                    'title'   => trans('admin::app.activities.created'),
                    'is_done' => 1,
                    'user_id' => auth()->check()
                        ? auth()->id()
                        : null,
                ];

                // Add lead_id and optionally set group_id if this is a Lead model
                if (method_exists($model, 'getTable') && $model->getTable() === 'leads') {
                    $activityData['lead_id'] = $model->id;

                    // Try to get group_id from lead's department (optional for system activities)
                    try {
                        $activityData['group_id'] = \App\Models\Department::getGroupIdForLead($model);
                    } catch (\Exception $e) {
                        // System activities don't require group_id, so ignore error
                    }
                }

                $activity = app(ActivityRepository::class)->create($activityData);

                static::linkActivityToEntity($activity, $model);

                return;
            }

            static::logActivity($model);
        });

        static::updated(function ($model) {
            $entity = $model->entity ?? $model;
            $isPerson = method_exists($entity, 'getTable') && $entity->getTable() === 'persons';
            if (! $isPerson && ! method_exists($entity, 'activities')) {
                return;
            }

            static::logActivity($model);
        });

        static::deleting(function ($model) {
            if (! method_exists($model->entity ?? $model, 'activities')) {
                return;
            }

            $model->activities()->delete();
        });
    }

    /**
     * Create activity.
     */
    protected static function logActivity($model)
    {
        $customAttributes = [];

        if (method_exists($model, 'getCustomAttributes')) {
            $customAttributes = $model->getCustomAttributes()->pluck('code')->toArray();
        }

        $updatedAttributes = static::getUpdatedAttributes($model);

        foreach ($updatedAttributes as $attributeCode => $attributeData) {
            if (in_array($attributeCode, $customAttributes)) {
                continue;
            }

            $attributeCode = $model->attribute?->name ?: $attributeCode;

            // Check if this is a Lead model and use the new direct relationship
            $activityData = [
                'type'       => 'system',
                'title'      => trans('admin::app.activities.updated', ['attribute' => $attributeCode]),
                'is_done'    => 1,
                'additional' => json_encode([
                    'attribute' => $attributeCode,
                    'new'       => [
                        'value' => $attributeData['new'],
                        'label' => static::getAttributeLabel($attributeData['new'], $model->attribute),
                    ],
                    'old'       => [
                        'value' => $attributeData['old'],
                        'label' => static::getAttributeLabel($attributeData['old'], $model->attribute),
                    ],
                ]),
                'user_id'    => auth()->id(),
            ];

            // Add lead_id and optionally set group_id if this is a Lead model
            if (method_exists($model, 'getTable') && $model->getTable() === 'leads') {
                $activityData['lead_id'] = $model->id;

                // Try to get group_id from lead's department (optional for system activities)
                try {
                    $activityData['group_id'] = \App\Models\Department::getGroupIdForLead($model);
                } catch (\Exception $e) {
                    // System activities don't require group_id, so ignore error
                }
            } elseif ($model instanceof AttributeValue && method_exists($model->entity, 'getTable') && $model->entity->getTable() === 'leads') {
                $activityData['lead_id'] = $model->entity->id;

                // Try to get group_id from lead's department (optional for system activities)
                try {
                    $activityData['group_id'] = \App\Models\Department::getGroupIdForLead($model->entity);
                } catch (\Exception $e) {
                    // System activities don't require group_id, so ignore error
                }
            }

            $activity = app(ActivityRepository::class)->create($activityData);

            if ($model instanceof AttributeValue) {
                static::linkActivityToEntity($activity, $model->entity);
            } else {
                static::linkActivityToEntity($activity, $model);
            }
        }
    }

    /**
     * Link an activity to an entity using the correct path:
     * - Person entities: update person_id FK directly
     * - Lead entities: already handled via lead_id in activityData, skip attach
     * - All others with activities(): attach via pivot
     */
    private static function linkActivityToEntity(\Webkul\Activity\Models\Activity $activity, $entity): void
    {
        if (method_exists($entity, 'getTable') && $entity->getTable() === 'persons') {
            $activity->update(['person_id' => $entity->id]);
        } elseif (method_exists($entity, 'getTable') && $entity->getTable() === 'leads') {
            // lead_id already set in activityData, nothing to attach
        } elseif (method_exists($entity, 'activities')) {
            $entity->activities()->attach($activity->id);
        }
    }

    /**
     * Get attribute label.
     */
    protected static function getAttributeLabel($value, $attribute)
    {
        return app(AttributeValueRepository::class)->getAttributeLabel($value, $attribute);
    }

    /**
     * Create activity.
     */
    protected static function getUpdatedAttributes($model)
    {
        $updatedAttributes = [];

        foreach ($model->getDirty() as $key => $value) {
            if (in_array($key, [
                'id',
                'attribute_id',
                'entity_id',
                'entity_type',
                'updated_at',
            ])) {
                continue;
            }

            $newValue = static::decodeValueIfJson($value);

            $oldValue = static::decodeValueIfJson($model->getOriginal($key));

            if ($newValue != $oldValue) {
                $updatedAttributes[$key] = [
                    'new' => $newValue,
                    'old' => $oldValue,
                ];
            }
        }

        return $updatedAttributes;
    }

    /**
     * Convert value if json.
     */
    protected static function decodeValueIfJson($value)
    {
        // Only attempt json_decode on strings
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (! is_array($value)) {
            return $value;
        }

        static::ksortRecursive($value);

        return $value;
    }

    /**
     * Sort array recursively.
     */
    protected static function ksortRecursive(&$array)
    {
        if (! is_array($array)) {
            return;
        }

        ksort($array);

        foreach ($array as &$value) {
            if (! is_array($value)) {
                continue;
            }

            static::ksortRecursive($value);
        }
    }
}
