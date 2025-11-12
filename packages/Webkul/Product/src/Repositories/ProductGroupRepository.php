<?php

namespace Webkul\Product\Repositories;

use App\Enums\PathDivider;
use Webkul\Core\Eloquent\Repository;
use Webkul\Product\Models\ProductGroup;

class ProductGroupRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'name',
    ];

    /**
     * Specify Model class name
     */
    public function model(): string
    {
        return ProductGroup::class;
    }

    /**
     * Get all product groups with their parent hierarchy loaded
     */
    public function getAllWithParents()
    {
        return $this->with(['parent.parent.parent.parent.parent'])
            ->orderBy('name')
            ->all();
    }

    /**
     * Get the hierarchical path for a product group by ID
     */
    public function getGroupPathById(int $id): string
    {
        $group = $this->find($id);

        if (!$group) {
            return '';
        }

        return $this->buildGroupPath($group);
    }

    /**
     * Get the hierarchical path for a product group by row data
     */
    public function getGroupPathByRow($row): string
    {
        if (!$row->parent_id) {
            return $row->name;
        }

        // Get all parent groups
        $path = [$row->name];
        $parentId = $row->parent_id;

        while ($parentId) {
            $parent = $this->model->select('name', 'parent_id')
                ->where('id', $parentId)
                ->first();

            if ($parent) {
                array_unshift($path, $parent->name);
                $parentId = $parent->parent_id;
            } else {
                break;
            }
        }

        return implode(PathDivider::value(), $path);
    }

    /**
     * Build the hierarchical path for a product group model
     */
    public function buildGroupPath(ProductGroup $group): string
    {
        if (!$group->parent_id) {
            return $group->name;
        }

        $path = [$group->name];
        $parent = $group->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(PathDivider::value(), $path);
    }
}
