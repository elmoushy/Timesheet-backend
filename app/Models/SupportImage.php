<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportImage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'image',
        'mime_type',
        'size',
        'original_name',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'size' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'image', // Hide raw image data from JSON serialization
    ];

    /**
     * The accessors to append to the model's array form.
     * Removed image_url to prevent automatic loading of large binary data
     *
     * @var array<int, string>
     */
    protected $appends = [
        'has_image',
    ];

    /**
     * Get a new query builder that excludes the image column by default.
     * This prevents loading large binary data unless explicitly requested.
     */
    public function newQuery()
    {
        return parent::newQuery()->select([
            'id', 'mime_type', 'size', 'original_name', 'created_at', 'updated_at',
        ]);
    }

    /**
     * Create a query that includes the image data.
     * Use this only when you specifically need the binary image data.
     */
    public static function withImageData()
    {
        // Select all columns including image data
        return static::query()->select(['*']);
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Ensure image data is properly handled when creating
        static::creating(function ($model) {
            if (isset($model->image) && is_string($model->image)) {
                // Ensure we have valid binary data
                $model->image = static::sanitizeImageData($model->image);
            }
        });

        // Ensure image data is properly handled when updating
        static::updating(function ($model) {
            if (isset($model->image) && is_string($model->image)) {
                // Ensure we have valid binary data
                $model->image = static::sanitizeImageData($model->image);
            }
        });
    }

    /**
     * Get the support records that use this image.
     */
    public function supportRecords()
    {
        return $this->hasMany(XxSupport::class, 'support_image_id');
    }

    /**
     * Get the image URL as a data URI (only when explicitly requested).
     *
     * @return string|null
     */
    public function getImageUrlAttribute()
    {
        if (! $this->image) {
            return null;
        }

        try {
            $mimeType = $this->mime_type ?: 'image/jpeg';

            // Check if image is already base64 encoded
            if (base64_decode($this->image, true) !== false) {
                // Already base64 encoded, use directly
                return 'data:'.$mimeType.';base64,'.$this->image;
            } else {
                // It's binary data, encode it
                return 'data:'.$mimeType.';base64,'.base64_encode($this->image);
            }
        } catch (\Exception $e) {
            \Log::error('Error encoding image to base64: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Get the image URL only when explicitly requested (memory efficient).
     *
     * @return string|null
     */
    public function getImageDataUri()
    {
        // If image data is not loaded, load it specifically
        if (! isset($this->attributes['image'])) {
            // Use a smaller select to reduce memory usage
            $imageData = static::withImageData()
                ->select(['id', 'image', 'mime_type'])
                ->find($this->id);

            if (! $imageData || ! $imageData->image) {
                return null;
            }

            // Only assign the image attribute, not the entire model
            $this->attributes['image'] = $imageData->image;
        }

        if (! $this->image) {
            return null;
        }

        try {
            $mimeType = $this->mime_type ?: 'image/jpeg';

            // Check if image is already base64 encoded
            if (base64_decode($this->image, true) !== false) {
                // Already base64 encoded, use directly
                $result = 'data:'.$mimeType.';base64,'.$this->image;

                // Clear the image from memory as we no longer need it
                unset($this->attributes['image']);
                gc_collect_cycles();

                return $result;
            } else {
                // It's binary data, encode it
                $encoded = base64_encode($this->image);
                $result = 'data:'.$mimeType.';base64,'.$encoded;

                // Clear the image from memory as we no longer need it
                unset($this->attributes['image'], $encoded);
                gc_collect_cycles();

                return $result;
            }
        } catch (\Exception $e) {
            // Clear the image from memory on error
            unset($this->attributes['image']);
            gc_collect_cycles();

            \Log::error('Error encoding image to base64: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Get image metadata without loading the actual image data.
     *
     * @return array
     */
    public function getImageMetadata()
    {
        return [
            'id' => $this->id,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'original_name' => $this->original_name,
            'has_image' => $this->has_image,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get the base64 encoded image data.
     *
     * @return string|null
     */
    public function getImageBase64Attribute()
    {
        if (! $this->image) {
            return null;
        }

        try {
            // Check if image is already base64 encoded
            if (base64_decode($this->image, true) !== false) {
                // Already base64 encoded, return directly
                return $this->image;
            } else {
                // It's binary data, encode it
                return base64_encode($this->image);
            }
        } catch (\Exception $e) {
            \Log::error('Error encoding image to base64: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Check if the image exists.
     *
     * @return bool
     */
    public function getHasImageAttribute()
    {
        // Check if image data is loaded
        if (isset($this->attributes['image'])) {
            return ! empty($this->image);
        }

        // If image data is not loaded, check if we have a size > 0
        // This is more memory efficient than loading the actual image
        return $this->size > 0;
    }

    /**
     * Create a SupportImage from an uploaded file.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return static
     */
    public static function createFromUploadedFile($file)
    {
        // Get file contents
        $imageData = file_get_contents($file->getPathname());

        // Create the support image
        return static::create([
            'image' => $imageData,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * Get the image URL as a data URI (only when explicitly requested).
     *
     * @return string|null
     */
    public function getImageUrl()
    {
        if (! isset($this->attributes['image'])) {
            // Load the image data if not already loaded
            $imageData = static::withImageData()
                ->select(['id', 'image', 'mime_type'])
                ->find($this->id);

            if (! $imageData || ! $imageData->image) {
                return null;
            }

            $this->attributes['image'] = $imageData->image;
        }

        if (! $this->image) {
            return null;
        }

        try {
            $mimeType = $this->mime_type ?: 'image/jpeg';

            // Check if image is already base64 encoded
            if (base64_decode($this->image, true) !== false) {
                // Already base64 encoded, use directly
                return 'data:'.$mimeType.';base64,'.$this->image;
            } else {
                // It's binary data, encode it
                return 'data:'.$mimeType.';base64,'.base64_encode($this->image);
            }
        } catch (\Exception $e) {
            \Log::error('Error encoding image to base64: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Validate and sanitize image data before storage.
     *
     * @param  mixed  $imageData
     * @return string|null
     */
    public static function sanitizeImageData($imageData)
    {
        if (empty($imageData)) {
            return null;
        }

        // Ensure the data is a string
        if (! is_string($imageData)) {
            return null;
        }

        // Check if it's already base64 encoded
        if (base64_decode($imageData, true) !== false) {
            // It's already base64 encoded, return as is
            return $imageData;
        }

        // If it's binary data, encode it as base64
        return base64_encode($imageData);
    }

    /**
     * Override the create method to optimize memory usage.
     * Use smaller chunks when dealing with large files.
     *
     * @return static
     */
    public static function create(array $attributes = [])
    {
        // Set higher memory limit temporarily for this operation
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '384M');

        try {
            if (isset($attributes['image'])) {
                $attributes['image'] = static::sanitizeImageData($attributes['image']);
            }

            $instance = new static($attributes);
            $instance->save();

            // Free memory
            if (isset($attributes['image'])) {
                unset($attributes['image']);
                gc_collect_cycles();
            }

            // Restore memory limit
            ini_set('memory_limit', $originalMemoryLimit);

            return $instance;
        } catch (\Exception $e) {
            // Restore memory limit on error
            ini_set('memory_limit', $originalMemoryLimit);
            throw $e;
        }
    }

    /**
     * Override the save method to optimize memory usage.
     *
     * @return bool
     */
    public function save(array $options = [])
    {
        // Set higher memory limit temporarily for this operation
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '384M');

        try {
            $result = parent::save($options);

            // Free memory from image data immediately after save
            if (isset($this->attributes['image'])) {
                unset($this->attributes['image']);
                gc_collect_cycles();
            }

            // Restore memory limit
            ini_set('memory_limit', $originalMemoryLimit);

            return $result;
        } catch (\Exception $e) {
            // Restore memory limit on error
            ini_set('memory_limit', $originalMemoryLimit);
            throw $e;
        }
    }
}
