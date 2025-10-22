<?php

namespace Webkul\Email\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Email\Contracts\Folder as FolderContract;

class Folder extends Model implements FolderContract
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'folders';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'parent_id',
        'order',
        'is_deletable',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_deletable' => 'boolean',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array
     */
    protected $attributes = [
        'is_deletable' => true,
    ];

    /**
     * Get the parent folder.
     */
    public function parent()
    {
        return $this->belongsTo(FolderProxy::modelClass(), 'parent_id');
    }

    /**
     * Get the child folders.
     */
    public function children()
    {
        return $this->hasMany(FolderProxy::modelClass(), 'parent_id');
    }

    /**
     * Get the emails in this folder.
     */
    public function emails()
    {
        return $this->hasMany(EmailProxy::modelClass(), 'folder_id');
    }

    /**
     * Get all descendants of this folder.
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get the full path of this folder.
     */
    public function getFullPathAttribute()
    {
        $path = collect([$this->name]);
        $parent = $this->parent;

        while ($parent) {
            $path->prepend($parent->name);
            $parent = $parent->parent;
        }

        return $path->implode(' / ');
    }
}