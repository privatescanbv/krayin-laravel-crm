<?php

namespace Webkul\Activity\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ViewService
{
    /**
     * Get all available views for activities
     *
     * @return array
     */
    public function getAvailableViews(): array
    {
        return [
            'for_me' => [
                'key' => 'for_me',
                'label' => 'Voor mij',
                'description' => 'Activiteiten toegewezen aan mij',
                'is_default' => true,
                'filters' => $this->getForMeFilters(),
            ],
            'for_me_or_groups' => [
                'key' => 'for_me_or_groups',
                'label' => 'Voor mij of mijn groep(en)',
                'description' => 'Activiteiten toegewezen aan mij of mijn groepen',
                'is_default' => false,
                'filters' => $this->getForMeOrGroupsFilters(),
            ],
            'hernia' => [
                'key' => 'hernia',
                'label' => 'Hernia',
                'description' => 'Activiteiten van afdeling Hernia',
                'is_default' => false,
                'filters' => $this->getHerniaFilters(),
            ],
            'privatescan' => [
                'key' => 'privatescan',
                'label' => 'Privatescan',
                'description' => 'Activiteiten van afdeling Privatescan',
                'is_default' => false,
                'filters' => $this->getPrivatescanFilters(),
            ],
        ];
    }

    /**
     * Get view by key
     *
     * @param string $key
     * @return array|null
     */
    public function getView(string $key): ?array
    {
        $views = $this->getAvailableViews();
        
        return $views[$key] ?? null;
    }

    /**
     * Get default view
     *
     * @return array
     */
    public function getDefaultView(): array
    {
        $views = $this->getAvailableViews();
        
        foreach ($views as $view) {
            if ($view['is_default']) {
                return $view;
            }
        }
        
        return reset($views);
    }

    /**
     * Get filters for "Voor mij" view
     *
     * @return array
     */
    protected function getForMeFilters(): array
    {
        $currentUserId = Auth::guard('user')->id();
        
        return [
            [
                'column' => 'assigned_user_id',
                'operator' => 'eq',
                'value' => $currentUserId,
            ],
            [
                'column' => 'is_done',
                'operator' => 'eq',
                'value' => 0,
            ],
        ];
    }

    /**
     * Get filters for "Voor mij of mijn groepen" view
     *
     * @return array
     */
    protected function getForMeOrGroupsFilters(): array
    {
        $currentUserId = Auth::guard('user')->id();
        $currentUser = Auth::guard('user')->user();
        $userGroupIds = $currentUser ? $currentUser->groups()->pluck('id')->toArray() : [];
        
        return [
            [
                'column' => 'user_or_groups',
                'operator' => 'custom',
                'value' => [
                    'user_id' => $currentUserId,
                    'group_ids' => $userGroupIds,
                ],
            ],
            [
                'column' => 'is_done',
                'operator' => 'eq',
                'value' => 0,
            ],
        ];
    }

    /**
     * Get filters for "Hernia" view
     *
     * @return array
     */
    protected function getHerniaFilters(): array
    {
        return [
            [
                'column' => 'group',
                'operator' => 'eq',
                'value' => 'Hernia', // Hernia group name
            ],
            [
                'column' => 'is_done',
                'operator' => 'eq',
                'value' => 0,
            ],
        ];
    }

    /**
     * Get filters for "Privatescan" view
     *
     * @return array
     */
    protected function getPrivatescanFilters(): array
    {
        return [
            [
                'column' => 'group',
                'operator' => 'eq',
                'value' => 'Privatescan', // Privatescan group name
            ],
            [
                'column' => 'is_done',
                'operator' => 'eq',
                'value' => 0,
            ],
        ];
    }

    /**
     * Apply view filters to query builder
     *
     * @param mixed $queryBuilder
     * @param string $viewKey
     * @return mixed
     */
    public function applyViewFilters($queryBuilder, string $viewKey)
    {
        $view = $this->getView($viewKey);
        
        if (!$view) {
            return $queryBuilder;
        }
        
        foreach ($view['filters'] as $filter) {
            $queryBuilder = $this->applyFilter($queryBuilder, $filter);
        }
        
        return $queryBuilder;
    }

    /**
     * Apply individual filter to query builder
     *
     * @param mixed $queryBuilder
     * @param array $filter
     * @return mixed
     */
    protected function applyFilter($queryBuilder, array $filter)
    {
        $column = $filter['column'];
        $operator = $filter['operator'];
        $value = $filter['value'];
        
        switch ($column) {
            case 'assigned_user_id':
                $queryBuilder->where('activities.user_id', $value);
                break;
                
            case 'is_done':
                $queryBuilder->where('activities.is_done', $value);
                break;
                
            case 'group':
                $queryBuilder->where('groups.name', $value);
                break;
                
            case 'user_or_groups':
                $queryBuilder->where(function ($query) use ($value) {
                    $query->where('activities.user_id', $value['user_id']);
                    
                    if (!empty($value['group_ids'])) {
                        $query->orWhereIn('activities.group_id', $value['group_ids']);
                        
                        // Also include activities where user is a participant and activity belongs to user's groups
                        $query->orWhere(function ($subQuery) use ($value) {
                            $subQuery->whereIn('activities.group_id', $value['group_ids'])
                                ->whereExists(function ($existsQuery) use ($value) {
                                    $existsQuery->select(DB::raw(1))
                                        ->from('activity_participants')
                                        ->whereColumn('activity_participants.activity_id', 'activities.id')
                                        ->where('activity_participants.user_id', $value['user_id']);
                                });
                        });
                    }
                });
                break;
        }
        
        return $queryBuilder;
    }
}