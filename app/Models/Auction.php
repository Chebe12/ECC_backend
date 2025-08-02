<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auction extends Model
{
    protected $fillable = [
        'creator_id',
        'creator_type',
        'title',
        'description',
        'auction_start_time',
        'auction_end_time',
        'starting_bid',
        'reserve_price',
        'buy_now_price',
        'bid_increment',
        'user_id',
        'auto_extend',
        'featured',
        'promotional_tags',
        'auth_certificate',

        'status'

    ];

    protected $casts = [
        'auction_start_time' => 'datetime',
        'auction_end_time' => 'datetime',
        'auto_extend' => 'boolean',
        'featured' => 'boolean',
        'promotional_tags' => 'array',
    ];

    public function creator()
    {
        return $this->morphTo();
    }

    protected $appends = ['auth_certificate_url'];

    public function getAuthCertificateUrlAttribute()
    {
        return $this->auth_certificate
            ? asset('storage/' . $this->auth_certificate)
            : null;
    }

    public function media()
    {
        return $this->hasMany(AuctionMedia::class, 'auction_id');
    }

    // public function bids()
    // {
    //     return $this->hasMany(Auction::class);
    // }

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function getStatusLabelAttribute()
    {
        $now = now();

        switch ($this->status) {
            case 'suspended':
                return 'suspended';
            case 'rejected':
                return 'rejected';
            case 'canceled':
                return 'canceled';
            case 'pending':
                return 'pending_approval';
            case 'approved':
                if ($this->auction_start_time > $now) {
                    return 'future';
                } elseif ($this->auction_start_time <= $now && $this->auction_end_time > $now) {
                    return 'live';
                } elseif ($this->auction_end_time <= $now) {
                    return 'ended';
                }
                break;
        }

        return 'unknown';
    }


    public function bids()
    {
        return $this->hasMany(Bid::class);
    }
    public function highestBid()
    {
        return $this->hasOne(Bid::class)->latest('amount');
    }
}
