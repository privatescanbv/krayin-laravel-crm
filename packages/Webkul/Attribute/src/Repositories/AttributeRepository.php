<?php

namespace Webkul\Attribute\Repositories;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\Core\Eloquent\Repository;

class AttributeRepository extends Repository
{
    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeOptionRepository $attributeOptionRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\\Attribute\\Contracts\\Attribute';
    }

    /**
     * @return \Webkul\Attribute\Contracts\Attribute
     */
    public function create(array $data)
    {
        $options = isset($data['options']) ? $data['options'] : [];

        $attribute = $this->model->create($data);

        if (in_array($attribute->type, ['select', 'multiselect', 'checkbox']) && count($options)) {
            $sortOrder = 1;

            foreach ($options as $optionInputs) {
                $this->attributeOptionRepository->create(array_merge([
                    'attribute_id' => $attribute->id,
                    'sort_order'   => $sortOrder++,
                ], $optionInputs));
            }
        }

        return $attribute;
    }

    /**
     * @param  int  $id
     * @param  string  $attribute
     * @return \Webkul\Attribute\Contracts\Attribute
     */
    public function update(array $data, $id, $attribute = 'id')
    {
        $attribute = $this->find($id);

        $attribute->update($data);

        if (! in_array($attribute->type, ['select', 'multiselect', 'checkbox'])) {
            return $attribute;
        }

        if (! isset($data['options'])) {
            return $attribute;
        }

        foreach ($data['options'] as $optionId => $optionInputs) {
            $isNew = $optionInputs['isNew'] == 'true';

            if ($isNew) {
                $this->attributeOptionRepository->create(array_merge([
                    'attribute_id' => $attribute->id,
                ], $optionInputs));
            } else {
                $isDelete = $optionInputs['isDelete'] == 'true';

                if ($isDelete) {
                    $this->attributeOptionRepository->delete($optionId);
                } else {
                    $this->attributeOptionRepository->update($optionInputs, $optionId);
                }
            }
        }

        return $attribute;
    }

    /**
     * @param  string  $code
     * @return \Webkul\Attribute\Contracts\Attribute
     */
    public function getAttributeByCode($code)
    {
        static $attributes = [];

        if (array_key_exists($code, $attributes)) {
            return $attributes[$code];
        }

        return $attributes[$code] = $this->findOneByField('code', $code);
    }

    /**
     * @param  int  $lookup
     * @param  string  $query
     * @param  array  $columns
     * @return array
     */
    public function getLookUpOptions($lookup, $query = '', $columns = [])
    {
        $lookup = config('attribute_lookups.'.$lookup);

        if (Str::contains($lookup['repository'], 'UserRepository')) {
            $userRepository = app($lookup['repository'])->where('status', 1);

            $currentUser = auth()->guard('user')->user();
            
            $searchTerm = '%'.urldecode($query).'%';

            if ($currentUser?->view_permission === 'group') {
                return $userRepository->leftJoin('user_groups', 'users.id', '=', 'user_groups.user_id')
                    ->where(function($q) use ($searchTerm) {
                        $q->where('users.first_name', 'like', $searchTerm)
                          ->orWhere('users.last_name', 'like', $searchTerm)
                          ->orWhereRaw("CONCAT(users.first_name, ' ', users.last_name) LIKE ?", [$searchTerm]);
                    })
                    ->get();
            } elseif ($currentUser?->view_permission === 'individual') {
                return $userRepository->where('users.id', $currentUser->id);
            }

            return $userRepository->where(function($q) use ($searchTerm) {
                $q->where('users.first_name', 'like', $searchTerm)
                  ->orWhere('users.last_name', 'like', $searchTerm)
                  ->orWhereRaw("CONCAT(users.first_name, ' ', users.last_name) LIKE ?", [$searchTerm]);
            })->get();
        }

        // For leads: use Eloquent models and getNameAttribute accessor
        if (isset($lookup['repository']) && Str::contains($lookup['repository'], 'LeadRepository')) {
            $leads = app($lookup['repository'])->scopeQuery(function ($q) use ($query) {
                $term = '%'.urldecode($query).'%';
                return $q->where(function ($qb) use ($term) {
                    $qb->where('first_name', 'like', $term)
                       ->orWhere('last_name', 'like', $term)
                       ->orWhere(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'like', $term);
                });
            })->all();

            // Transform to use the name accessor
            return $leads->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'name' => $lead->name
                ];
            });
        }

        // Default columns for other repositories
        if (! count($columns)) {
            $columns = [($lookup['value_column'] ?? 'id').' as id', ($lookup['label_column'] ?? 'name').' as name'];
        }

        return app($lookup['repository'])->findWhere([
            [$lookup['label_column'] ?? 'name', 'like', '%'.urldecode($query).'%'],
        ], $columns);
    }

    /**
     * @param  string  $lookup
     * @param  int|array  $entityId
     * @param  array  $columns
     * @return mixed
     */
    public function getLookUpEntity($lookup, $entityId = null, $columns = [])
    {
        if (! $entityId) {
            return;
        }

        $lookup = config('attribute_lookups.'.$lookup);

        // For leads: use Eloquent models and getNameAttribute accessor
        if (isset($lookup['repository']) && Str::contains($lookup['repository'], 'LeadRepository')) {
            if (is_array($entityId)) {
                $leads = app($lookup['repository'])->findWhereIn('id', $entityId);
            } else {
                $leads = collect([app($lookup['repository'])->find($entityId)]);
            }

            // Transform to use the name accessor
            return $leads->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'name' => $lead->name
                ];
            });
        }

        // Default columns for other repositories
        if (! count($columns)) {
            $columns = [($lookup['value_column'] ?? 'id').' as id', ($lookup['label_column'] ?? 'name').' as name'];
        }

        if (is_array($entityId)) {
            return app($lookup['repository'])->findWhereIn(
                'id',
                $entityId,
                $columns
            );
        } else {
            return app($lookup['repository'])->find($entityId, $columns);
        }
    }
}
