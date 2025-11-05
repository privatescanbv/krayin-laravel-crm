<?php

namespace Webkul\User\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Webkul\Core\Eloquent\Repository;

class UserRepository extends Repository
{
    /**
     * Searchable fields
     */
    protected $fieldSearchable = [
        'first_name',
        'last_name',
        'email',
        'status',
        'view_permission',
        'role_id',
    ];

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\User\Contracts\User';
    }

    /**
     * This function will return user ids of current user's groups
     *
     * @return array
     */
    public function getCurrentUserGroupsUserIds()
    {
        $userIds = $this->scopeQuery(function ($query) {
            return $query->select('users.*')
                ->leftJoin('user_groups', 'users.id', '=', 'user_groups.user_id')
                ->leftJoin('groups', 'user_groups.group_id', 'groups.id')
                ->whereIn('groups.id', auth()->guard('user')->user()->groups()->pluck('id'));
        })->get()->pluck('id')->toArray();

        return $userIds;
    }

    public function allActiveUsers(): Collection
    {
        return $this->scopeQuery(function ($query) {
            return $query
                ->where('status', 1)
                ->orderBy('first_name', 'asc')
                ->orderBy('last_name', 'asc');
        })->all();

    }
}
