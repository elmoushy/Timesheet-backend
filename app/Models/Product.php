<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'xxx_products';

    protected $fillable = [
        'name',
    ];

    public function projects()
    {
        return $this->belongsToMany(
            Project::class,
            'xxx_proj_products',
            'product_id',
            'project_id'
        );
    }
}
