<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class XxSupport extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'xx_support';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'message',
        'support_image_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'employee_id' => 'integer',
        'support_image_id' => 'integer',
    ];

    /**
     * Get the employee that owns the support record.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the support image associated with this support record.
     */
    public function supportImage()
    {
        return $this->belongsTo(SupportImage::class, 'support_image_id');
    }

    /**
     * Get the support image with full image data.
     * Use this only when you specifically need the binary image data.
     */
    public function supportImageWithData()
    {
        return $this->belongsTo(SupportImage::class, 'support_image_id')
                   ->select('*');
    }

    /**
     * Scope a query to only include support records for a specific employee.
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope a query to only include support records with images.
     */
    public function scopeWithImages($query)
    {
        return $query->whereNotNull('support_image_id');
    }

    /**
     * Scope a query to only include support records without images.
     */
    public function scopeWithoutImages($query)
    {
        return $query->whereNull('support_image_id');
    }
}
