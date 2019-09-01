<?php

namespace App\Models;

class Event extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'mangopay_events';
    protected $fillable = ['status','token','amount','product','pikey','trkey','pokey'];
    protected $casts = [
        'status' => 'string',
        'token' => 'string',
        'amount' => 'integer',
        'product' => 'string',
        'pikey' => 'string',    // payins-id
        'trkey' => 'string',    // transfers-id
        'pokey' => 'string'     // payout-id
    ];

    public function buyer()
    {
        return $this->belongsTo('App\Models\Buyer');
    }

}
