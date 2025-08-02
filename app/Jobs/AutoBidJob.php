<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Support\Facades\DB;

class AutoBidJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public $auctionId)
    {
        // Initialize the job with the auction ID
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $auction = Auction::with('bids')->find($this->auctionId);
        $latestBid = $auction->bids()->latest('amount')->first();

        // Get all auto-bidders excluding current highest
        $autoBidders = Bid::where('auction_id', $auction->id)
            ->where('is_auto', true)
            ->where('user_id', '!=', $latestBid->user_id)
            ->get();

        foreach ($autoBidders as $autoBidder) {
            $nextBidAmount = $latestBid->amount + $autoBidder->auto_increment;

            if ($nextBidAmount <= $autoBidder->auto_max_bid) {
                DB::transaction(function () use ($auction, $autoBidder, $nextBidAmount) {
                    Bid::create([
                        'auction_id' => $auction->id,
                        'user_id' => $autoBidder->user_id,
                        'amount' => $nextBidAmount,
                        'is_auto' => true,
                        'auto_max_bid' => $autoBidder->auto_max_bid,
                        'auto_increment' => $autoBidder->auto_increment,
                    ]);

                    // Extend if less than 1 min left
                    if ($auction->end_time->diffInSeconds(now()) <= 60) {
                        $auction->end_time = $auction->end_time->addMinutes(2);
                        $auction->save();
                    }

                    // Recurse by dispatching job again
                    AutoBidJob::dispatch($auction->id);
                });

                break; // only allow one auto-bid at a time
            }
        }

        //
    }
}
