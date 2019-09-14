<?php

namespace App\Models;

class Product extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'mangopay_products';
    protected $fillable = ['active','amount','uuid','name'];
    protected $casts = [
        'active' => 'integer',
        'amount' => 'integer',
        'uuid' => 'string',
        'name' => 'string'
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

}
