<?php

namespace App\Actions\Activities;

use Webkul\Activity\Contracts\Activity;
use Webkul\Activity\Repositories\ActivityRepository;

abstract class AbstractCreateActivityAction
{
    public function __construct(
        protected readonly ActivityRepository $activityRepository,
    ) {}

    /**
     * @throws DuplicateException
     */
    protected function createActivity(
        string $fkField,
        mixed $fkValue,
        int $groupId,
        bool $isDone,
        array $activityData,
    ): Activity {
        $isDuplicate = $this->activityRepository
            ->where($fkField, $fkValue)
            ->where('title', $activityData['title'] ?? null)
            ->where('is_done', 0)
            ->exists();

        if ($isDuplicate) {
            throw new DuplicateException('Duplicate activity: same title already exists and is not done.');
        }

        return $this->activityRepository->create(array_merge($activityData, [
            'is_done'  => $isDone ? 1 : 0,
            'user_id'  => $activityData['user_id'] ?? null,
            'group_id' => $groupId,
        ]));
    }
}
