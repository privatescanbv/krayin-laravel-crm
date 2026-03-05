<?php

namespace Webkul\Activity\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Webkul\Activity\Contracts\File as FileContract;

class File extends Model implements FileContract
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'activity_files';

    /**
     * The attributes that should be appended to the model.
     *
     * @var array
     */
    protected $appends = ['url'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'path',
        'activity_id',
    ];

    /**
     * Get image url for the product image.
     */
    public function url()
    {
        return Storage::url($this->path);
    }

    /**
     * Get image url for the product image.
     */
    public function getUrlAttribute()
    {
        return $this->url();
    }

    /**
     * Resolve the storage disk this file lives on.
     * Returns the first disk that contains the file, or null if not found on any.
     */
    public function resolveDisk(): ?string
    {
        $candidateDisks = array_values(array_unique(array_filter([
            config('filesystems.default'),
            'public',
            'local',
        ])));

        foreach ($candidateDisks as $disk) {
            try {
                if (Storage::disk($disk)->exists($this->path)) {
                    return $disk;
                }
            } catch (Exception) {
                continue;
            }
        }

        return null;
    }

    /**
     * Get the activity that owns the file.
     */
    public function activity()
    {
        return $this->belongsTo(ActivityProxy::modelClass());
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        $array['url'] = $this->url;

        return $array;
    }
}
