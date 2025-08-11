<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Auction;
use Illuminate\Support\Facades\Mail;

class AutoDeclareAuctionWinners extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:auto-declare-auction-winners';
    protected $description = 'Automatically declare highest bidder as winner for ended auctions';


    /**
     * The console command description.
     *
     * @var string
     */
    // protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();

        // Get auctions that ended, have no winner, and are approved
        $auctions = Auction::with(['bids.bidder'])
            ->whereNull('winner_id')
            ->where('status', 'approved')
            ->whereNotNull('auction_end_time')
            ->where('auction_end_time', '<=', $now)
            ->get();

        foreach ($auctions as $auction) {
            // Skip auctions with no bids
            if ($auction->bids->isEmpty()) {
                continue;
            }

            // Find highest bid
            $highestBid = $auction->bids->sortByDesc('amount')->first();

            // Ensure highest bid meets starting bid & reserve price
            if (
                $highestBid->amount < $auction->starting_bid ||
                ($auction->reserve_price && $highestBid->amount < $auction->reserve_price)
            ) {
                continue; // Does not meet minimum requirements
            }

            // Declare winner
            $auction->winner_id = $highestBid->bidder_id;
            $auction->winner_type = $highestBid->bidder_type;
            $auction->save();

            $winner = $highestBid->bidder;

            // Notify Winner
            if ($winner && $winner->email) {
                Mail::send('mails.winner', [
                    'name' => $winner->name,
                    'auctionTitle' => $auction->title,
                    'bidAmount' => $highestBid->amount
                ], function ($message) use ($winner) {
                    $message->to($winner->email)
                        ->subject('🎉 You Won the Auction!');
                });
            }

            // Notify Losers
            foreach ($auction->bids as $bid) {
                if ($bid->bidder_id != $winner->id && $bid->bidder_type === $highestBid->bidder_type) {
                    $loser = $bid->bidder;
                    if ($loser && $loser->email) {
                        Mail::send('mails.loser', [
                            'name' => $loser->name,
                            'auctionTitle' => $auction->title
                        ], function ($message) use ($loser) {
                            $message->to($loser->email)
                                ->subject('Auction Result – Better Luck Next Time!');
                        });
                    }
                }
            }

            $this->info("Winner declared for Auction ID: {$auction->id}");
        }

        return Command::SUCCESS;
    }
}
