<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjProduct extends Model
{
    protected $table = 'xxx_proj_products';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = ['project_id', 'product_id'];

    protected $fillable = [
        'project_id',
        'product_id',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
