<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuctionMedia extends Model
{
    protected $fillable = [
        'auction_id',
        'image_path',
        'media_type',
        'media_path'
    ];

    /**
     * Get the auction that owns the image.
     */
    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    protected $appends = ['media_url'];

    public function getMediaUrlAttribute()
    {
        return asset('storage/' . $this->media_path);
    }
}
