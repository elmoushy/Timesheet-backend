<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientNumber extends Model
{
    use HasFactory;

    protected $table = 'xxx_clients_numbers';

    protected $fillable = [
        'client_id',
        'project_id',
        'name',
        'number',
        'type',
        'is_primary'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
