<?php

namespace App\Jobs;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class TriggerAutoBiddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $auctionId;

    public function __construct($auctionId)
    {
        $this->auctionId = $auctionId;
    }

    public function handle()
    {
        $lockKey = 'auto-bid-lock-auction-' . $this->auctionId;

        // Attempt to acquire lock for this auction, wait up to 5 seconds if necessary
        Cache::lock($lockKey, 10)->block(5, function () {
            $this->processAutoBids();
        });
    }

    protected function processAutoBids()
    {
        $auction = Auction::with('bids')->find($this->auctionId);
        if (!$auction) return;

        $maxIterations = 100;
        $iteration = 0;

        do {
            $iteration++;

            $currentHighestBid = $auction->bids()->latest('amount')->first();

            // Get all auto-bidders (exclude current highest bidder)
            $autoBidders = Bid::where('auction_id', $this->auctionId)
                ->where('is_auto', true)
                ->where(function ($query) use ($currentHighestBid) {
                    $query->where('bidder_id', '!=', $currentHighestBid->bidder_id)
                        ->orWhere('bidder_type', '!=', $currentHighestBid->bidder_type);
                })
                ->orderBy('id') // or created_at
                ->get()
                ->unique(fn($bid) => $bid->bidder_id . $bid->bidder_type);

            $newAutoBidPlaced = false;

            foreach ($autoBidders as $bidder) {
                $nextBidAmount = $currentHighestBid->amount + $bidder->auto_increment;

                if ($nextBidAmount <= $bidder->auto_max_bid) {
                    DB::transaction(function () use ($auction, $bidder, $nextBidAmount) {
                        Bid::create([
                            'auction_id'     => $auction->id,
                            'bidder_id'      => $bidder->bidder_id,
                            'bidder_type'    => $bidder->bidder_type,
                            'amount'         => $nextBidAmount,
                            'is_auto'        => true,
                            'auto_max_bid'   => $bidder->auto_max_bid,
                            'auto_increment' => $bidder->auto_increment,
                        ]);
                    });

                    // Update auction bid relationship to reflect the new bid
                    $auction->load('bids');
                    $newAutoBidPlaced = true;
                    break; // Trigger next loop cycle
                }
            }
        } while ($newAutoBidPlaced && $iteration < $maxIterations);
    }
}
