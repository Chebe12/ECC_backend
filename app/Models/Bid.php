<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }
    protected $fillable = [
        'user_id',
        'auction_id',
        'amount',
        'status',
        'bidder_id',
        'bidder_type',
        'is_auto',
        'auto_max_bid',
        'auto_increment',
        'created_at',
        'updated_at'
    ];

    public function bidder()
    {
        return $this->morphTo();
    }

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];



    //
}
