<?php

namespace Webkul\User\Repositories;

use Illuminate\Container\Container;
use Webkul\Core\Eloquent\Repository;
use Webkul\User\Services\RolePermissionNormalizer;

class RoleRepository extends Repository
{
    public function __construct(
        protected RolePermissionNormalizer $permissionNormalizer,
        Container $container,
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
        return 'Webkul\User\Contracts\Role';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes)
    {
        $attributes = $this->normalizePermissionAttributes($attributes);

        return parent::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, $id, $attribute = 'id')
    {
        $attributes = $this->normalizePermissionAttributes($attributes);

        return parent::update($attributes, $id, $attribute);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizePermissionAttributes(array $attributes): array
    {
        if (($attributes['permission_type'] ?? null) !== 'custom') {
            return $attributes;
        }

        if (! array_key_exists('permissions', $attributes)) {
            return $attributes;
        }

        $attributes['permissions'] = $this->permissionNormalizer->normalize($attributes['permissions']);

        return $attributes;
    }
}
