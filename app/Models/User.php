<?php

namespace App\Models;

class User extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'mangopay_users';
    protected $fillable = ['active','uuid','name','email','ukey','wkey','bkey'];
    protected $casts = [
        'active' => 'integer',
        'uuid' => 'string',
        'name' => 'string',
        'email' => 'string',
        'ukey' => 'string', // user-id
        'wkey' => 'string', // wallet-id
        'bkey' => 'string'  // bank-id
    ];

    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function buyers()
    {
        return $this->hasMany('App\Models\Buyer');
    }

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

}
