<?php

namespace Webkul\Email\Repositories;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

            // Get all unread email counts in a single query instead of N queries
            $folderIds = $allFolders->pluck('id')->toArray();

            // Single query to get unread counts for all folders
            $unreadCounts = DB::table('emails')
                ->select('folder_id', \DB::raw('COUNT(*) as unread_count'))
                ->whereIn('folder_id', $folderIds)
                ->where('is_read', false)
                ->groupBy('folder_id')
                ->pluck('unread_count', 'folder_id')
                ->toArray();

            $result = [];

            foreach ($rootFolders as $folder) {
                $children = $folderTree->get($folder->id, collect());
                $folderCount = $unreadCounts[$folder->id] ?? 0;

                $folderData = [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'count' => $folderCount,
                    'children' => []
                ];

                // Add children
                foreach ($children as $child) {
                    $childCount = $unreadCounts[$child->id] ?? 0;

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
