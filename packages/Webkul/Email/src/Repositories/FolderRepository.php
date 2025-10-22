<?php

namespace Webkul\Email\Repositories;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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

    /**
     * Get hierarchical folders for sidebar display
     *
     * @return array
     */
    public function getHierarchicalFolders()
    {
        try {
            // Get all folders ordered by order, then by name
            $allFolders = $this->model->orderBy('order')->orderBy('name')->get();

            // Build hierarchical structure
            $folderTree = $allFolders->groupBy('parent_id');
            $rootFolders = $folderTree->get(null, collect());

            $result = [];

            foreach ($rootFolders as $folder) {
                $children = $folderTree->get($folder->id, collect());
                $folderCount = 0;

                try {
                    $folderCount = $folder->emails()->where('is_read', false)->count();
                } catch (\Exception $e) {
                    $folderCount = 0;
                }

                $folderData = [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'count' => $folderCount,
                    'children' => []
                ];

                // Add children
                foreach ($children as $child) {
                    $childCount = 0;
                    try {
                        $childCount = $child->emails()->where('is_read', false)->count();
                    } catch (\Exception $e) {
                        $childCount = 0;
                    }

                    $folderData['children'][] = [
                        'id' => $child->id,
                        'name' => $child->name,
                        'count' => $childCount,
                    ];
                }

                $result[] = $folderData;
            }

            return $result;

        } catch (Exception $e) {
            Log::warning('Failed to load hierarchical folders: ' . $e->getMessage());
            return [];
        }
    }
}
