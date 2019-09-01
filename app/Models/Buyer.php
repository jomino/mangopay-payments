<?php

namespace App\Models;

class Buyer extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'mangopay_buyers';
    protected $fillable = ['active','uuid','name','email','ukey','wkey','bkey'];
    protected $casts = [
        'name' => 'string',
        'email' => 'string',
        'ukey' => 'string', // user-id
        'wkey' => 'string', // wallet-id
        'bkey' => 'string'  // bank-id
    ];

    public function events()
    {
        return $this->hasMany('App\Models\Event');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

}
