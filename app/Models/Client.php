<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $table = 'xxx_clients';

    protected $fillable = [
        'name',
        'alias',
        'region',
        'address',
        'business_sector',
        'notes',
    ];

    public function projects()
    {
        return $this->hasMany(Project::class, 'client_id');
    }

    public function contactNumbers()
    {
        return $this->hasMany(ClientNumber::class, 'client_id');
    }
}
