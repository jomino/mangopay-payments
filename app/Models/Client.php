<?php

namespace App\Models;

class Client extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'mangopay_clients';
    protected $fillable = ['active','uuid','pwd','name','email','ckey','akey'];
    protected $casts = [
        'active' => 'integer',
        'uuid' => 'string',
        'pwd' => 'string',
        'name' => 'string',
        'email' => 'string',
        'ckey' => 'string', // client-id
        'akey' => 'string' // api-key
    ];

    public function users()
    {
        return $this->hasMany('App\Models\User');
    }

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

}
