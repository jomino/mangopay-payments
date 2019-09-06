<?php

namespace App\Models;

class Buyer extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'mangopay_buyers';
    protected $fillable = ['person_type','legal_type','last_name','first_name','legal_name','birthday','nationality','residence','email','ukey','wkey','bkey'];
    protected $casts = [
        'person_type' => 'string',
        'legal_type' => 'string',
        'last_name' => 'string',
        'first_name' => 'string',
        'legal_name' => 'string',
        'birthday' => 'datetime',
        'nationality' => 'string',
        'residence' => 'string',
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
