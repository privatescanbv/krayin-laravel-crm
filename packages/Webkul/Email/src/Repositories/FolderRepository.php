<?php

namespace Webkul\Email\Repositories;

use Illuminate\Support\Collection;
use Webkul\Core\Eloquent\Repository;

class FolderRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Webkul\Email\Contracts\Folder';
    }

    /**
     * Get folder tree structure
     *
     * @return Collection
     */
    public function getTree()
    {
        $folders = $this->model->with('children')->whereNull('parent_id')->get();

        return $this->buildTree($folders);
    }

    /**
     * Build tree structure recursively
     *
     * @param Collection $folders
     * @return Collection
     */
    protected function buildTree(Collection $folders)
    {
        return $folders->map(function ($folder) {
            if ($folder->children->count() > 0) {
                $folder->children = $this->buildTree($folder->children);
            }
            return $folder;
        });
    }

    /**
     * Get flat list of folders for dropdown
     *
     * @return Collection
     */
    public function getFlatList()
    {
        $folders = $this->model->orderBy('name')->get();
        $result = collect();

        foreach ($folders as $folder) {
            $result->push([
                'id' => $folder->id,
                'name' => $folder->full_path,
                'parent_id' => $folder->parent_id,
            ]);
        }

        return $result;
    }
}