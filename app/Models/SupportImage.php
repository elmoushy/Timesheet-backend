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
            'id', 'mime_type', 'size', 'original_name', 'created_at', 'updated_at'
        ]);
    }

    /**
     * Create a query that includes the image data.
     * Use this only when you specifically need the binary image data.
     */
    public static function withImageData()
    {
        return static::query()->select('*');
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
        if (!$this->image) {
            return null;
        }

        try {
            $mimeType = $this->mime_type ?: 'image/jpeg';
            return 'data:' . $mimeType . ';base64,' . base64_encode($this->image);
        } catch (\Exception $e) {
            \Log::error('Error encoding image to base64: ' . $e->getMessage());
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
        if (!isset($this->attributes['image'])) {
            $imageData = static::withImageData()->find($this->id);
            if (!$imageData || !$imageData->image) {
                return null;
            }
            $this->attributes['image'] = $imageData->image;
        }

        if (!$this->image) {
            return null;
        }

        try {
            $mimeType = $this->mime_type ?: 'image/jpeg';
            return 'data:' . $mimeType . ';base64,' . base64_encode($this->image);
        } catch (\Exception $e) {
            \Log::error('Error encoding image to base64: ' . $e->getMessage());
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
        if (!$this->image) {
            return null;
        }

        try {
            return base64_encode($this->image);
        } catch (\Exception $e) {
            \Log::error('Error encoding image to base64: ' . $e->getMessage());
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
            return !empty($this->image);
        }

        // If image data is not loaded, check if we have a size > 0
        // This is more memory efficient than loading the actual image
        return $this->size > 0;
    }

    /**
     * Validate and sanitize image data before storage.
     *
     * @param mixed $imageData
     * @return string|null
     */
    public static function sanitizeImageData($imageData)
    {
        if (empty($imageData)) {
            return null;
        }

        // Ensure the data is a string
        if (!is_string($imageData)) {
            return null;
        }

        // Check if it's valid binary data
        if (mb_check_encoding($imageData, 'UTF-8') && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $imageData)) {
            // If it's already UTF-8 text (base64), decode it
            $decoded = base64_decode($imageData, true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $imageData;
    }

    /**
     * Override the create method to sanitize image data.
     */
    public static function create(array $attributes = [])
    {
        if (isset($attributes['image'])) {
            $attributes['image'] = static::sanitizeImageData($attributes['image']);
        }

        return parent::create($attributes);
    }
}
