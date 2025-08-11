<?php

namespace App\Http\Controllers\Bid;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bid;
use App\Models\Auction;
use Illuminate\Support\Facades\DB;
use App\Helpers\ResponseData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Jobs\TriggerAutoBiddingJob;
use App\Events\BidPlaced;

class BidController extends Controller
{
    public function placeBid(Request $request, $auctionId)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'is_auto' => 'boolean',
            'auto_max_bid' => 'nullable|numeric',
            'auto_increment' => 'nullable|numeric|min:0.01',
        ]);

        $user = $request->user();

        // Only customers and users can bid
        if (!($user instanceof \App\Models\User || $user instanceof \App\Models\Customer)) {
            return ResponseData::error([], 'Only users and customers can place bids.', 403);
        }

        $auction = Auction::findOrFail($auctionId);

        // Prevent users/customers from bidding on their own auction
        if ($auction->user_id === $user->id) {
            return ResponseData::error([], 'You cannot bid on your own auction.', 403);
        }

        // Get current highest bid
        $currentHighestBid = $auction->bids()->max('amount');

        // If no one has bid yet, use starting_price as reference
        $minimumAcceptableBid = $currentHighestBid ? $currentHighestBid : $auction->starting_price;

        if ($request->amount <= $minimumAcceptableBid) {
            return ResponseData::error([], 'Your bid must be higher than ' . number_format($minimumAcceptableBid, 2), 422);
        }

        DB::beginTransaction();
        try {
            // Create the bid
            $bid = new Bid();
            $bid->auction_id = $auction->id;
            $bid->bidder_id = $user->id;
            $bid->bidder_type = get_class($user);
            $bid->amount = $request->amount;
            $bid->is_auto = $request->is_auto ?? false;
            $bid->auto_max_bid = $request->auto_max_bid;
            $bid->auto_increment = $request->auto_increment;
            $bid->save();

            // Dispatch auto-bid processing to queue
            TriggerAutoBiddingJob::dispatch($auction->id)->onQueue('auto-bid');

            event(new BidPlaced($bid));


            DB::commit();
            return ResponseData::success($bid, 'Bid placed successfully.', 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseData::error([], 'Failed to place bid: ' . $e->getMessage(), 500);
        }
    }

    public function getBidsByAuction($auctionId)
    {
        $bids = Bid::where('auction_id', $auctionId)->with(['bidder', 'auction'])->get();

        if ($bids->isEmpty()) {
            return ResponseData::error([], 'No bids found for this auction.', 404);
        }

        return ResponseData::success($bids, 'Bids retrieved successfully.');
    }
    public function getBidsByUser(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return ResponseData::error([], 'Unauthorized', 401);
        }

        $bids = Bid::where('bidder_id', $user->id)
            ->where('bidder_type', get_class($user))
            ->with(['auction'])
            ->get();

        if ($bids->isEmpty()) {
            return ResponseData::error([], 'No bids found for this user.', 404);
        }

        return ResponseData::success($bids, 'User bids retrieved successfully.');
    }

    public function getAllAuctionsWithBids()
    {
        try {
            $auctions = Auction::with([
                'creator:id,name,email',
                'bids.bidder:id,name,email'
            ])->get();

            return ResponseData::success($auctions, 'Fetched auctions with bids.');
        } catch (\Throwable $e) {
            return ResponseData::error([], 'Failed to fetch auctions: ' . $e->getMessage(), 500);
        }
    }

    public function publicBidHistory($auctionId)
    {
        try {
            $auction = Auction::findOrFail($auctionId);

            // Fetch all bids with bidder relation
            $bids = $auction->bids()
                ->latest()
                ->with('bidder')
                ->get();

            // Map unique bidder identity (e.g., Bidder#1, Bidder#2...)
            $uniqueBidders = [];
            $bidderIdentities = [];
            $counter = 1;

            foreach ($bids as $bid) {
                $key = $bid->bidder_type . '_' . $bid->bidder_id;
                if (!isset($uniqueBidders[$key])) {
                    $uniqueBidders[$key] = "Bidder#$counter";
                    $counter++;
                }
                $bidderIdentities[$bid->id] = $uniqueBidders[$key];
            }

            // Format bid history with masked identity
            $formattedBids = $bids->map(function ($bid) use ($bidderIdentities) {
                return [
                    'identity' => $bidderIdentities[$bid->id],
                    'amount' => $bid->amount,
                    'created_at' => $bid->created_at->toDateTimeString(),
                ];
            });

            // Highest bid (first from sorted bids)
            $highestBid = $bids->sortByDesc('amount')->first();
            $highestBidderKey = $highestBid ? $highestBid->bidder_type . '_' . $highestBid->bidder_id : null;

            $highestBidData = $highestBid ? [
                'identity' => $uniqueBidders[$highestBidderKey],
                'amount' => $highestBid->amount,
                'created_at' => $highestBid->created_at->toDateTimeString(),
            ] : null;

            return ResponseData::success([
                'auction_id' => $auction->id,
                'title' => $auction->title,
                'total_active_bidders' => count($uniqueBidders),
                'highest_bid' => $highestBidData,
                'bids' => $formattedBids,
            ], 'Public bid history retrieved.');
        } catch (\Throwable $e) {
            return ResponseData::error([], 'Failed to retrieve bid history: ' . $e->getMessage(), 500);
        }
    }
    public function getBidDetails($bidId)
    {
        $bid = Bid::with(['bidder', 'auction'])->findOrFail($bidId);

        return ResponseData::success($bid, 'Bid details retrieved successfully.');
    }
    // public function getBidHistory($auctionId)
    // {
    //     try {
    //         $bids = Bid::where('auction_id', $auctionId)
    //             ->with(['bidder', 'auction'])
    //             ->orderBy('created_at', 'desc')
    //             ->get();

    //         if ($bids->isEmpty()) {
    //             return ResponseData::error([], 'No bids found for this auction.', 404);
    //         }

    //         return ResponseData::success($bids, 'Bid history retrieved successfully.');
    //     } catch (\Throwable $e) {
    //         return ResponseData::error([], 'Failed to retrieve bid history: ' . $e->getMessage(), 500);
    //     }
    // }

    public function getAllAuctions(Request $request)
    {
        $now = now();
        $filter = $request->query('filter');

        // Only fetch auctions with 'approved' status
        $query = Auction::with([
            'media',
            'bids.user:id,name,email',
            'creator:id,name,email',
        ])->where('status', 'approved');

        switch ($filter) {
            case 'live':
                $query->where('auction_start_time', '<=', $now)
                    ->where('auction_end_time', '>', $now);
                break;

            case 'upcoming':
                $query->where('auction_start_time', '>', $now);
                break;

            case 'past':
                $query->where('auction_end_time', '<=', $now);
                break;

            case 'featured':
                $query->where('is_featured', true);
                break;

            case 'ending_soon':
                $query->whereBetween('auction_end_time', [$now, $now->copy()->addHour()]);
                break;

            case 'all':
            default:
                // Already filtered by approved above
                break;
        }

        // Order by latest created first
        $auctions = $query
            ->orderBy('created_at', 'desc')
            ->get();

        $auctionsData = $auctions->map(function ($auction) {
            $highestBid = $auction->bids->max('amount');
            $totalActiveBidders = $auction->bids->unique('user_id')->count();
            $highestBidder = $auction->bids->where('amount', $highestBid)->first()?->user;

            return [
                'auction' => [$auction],
                'stage' => $auction->status_label,
                'total_bids' => $auction->bids->count(),
                'total_active_bidders' => $totalActiveBidders,
                'highest_bid' => $highestBid,
                'highest_bidder' => $highestBidder ? [
                    'id' => $highestBidder->id,
                    'name' => $highestBidder->name,
                    'email' => $highestBidder->email,
                ] : null,
            ];
        });

        return ResponseData::success($auctionsData, 'Auctions fetched successfully.');
    }





    // public function getSingleAuction($id)
    // {
    //     $auction = Auction::with([
    //         'bids.user:id,name,email',
    //         'creator:id,name,email',
    //     ])->findOrFail($id);

    //     $bids = $auction->bids;

    //     $highestBid = $bids->max('amount');
    //     $totalActiveBidders = $bids->unique('user_id')->count();
    //     $highestBidder = $bids->firstWhere('amount', $highestBid)?->user;

    //     $response = [
    //         'auction' => $auction,
    //         'total_bids' => $bids->count(),
    //         'total_active_bidders' => $totalActiveBidders,
    //         'highest_bid' => $highestBid,
    //         'highest_bidder' => $highestBidder ? [
    //             'id' => $highestBidder->id,
    //             'name' => $highestBidder->name,
    //             'email' => $highestBidder->email,
    //         ] : null,
    //     ];

    //     return ResponseData::success($response, 'Auction details retrieved successfully.', 200);
    // }

    public function getSingleAuction($id)
    {
        $auction = Auction::with([
            'media',
            'bids.bidder:id,name', // use bidder instead of user
            'creator:id,name',
            'winner:id,name',
        ])->findOrFail($id);

        $bids = $auction->bids->filter(fn($bid) => $bid->bidder !== null);

        $highestBid = $bids->max('amount');

        $highestBidRecord = $bids
            ->where('amount', $highestBid)
            ->first();

        $highestBidder = $highestBidRecord?->bidder;

        $response = [
            'auction' => $auction,
            'stage' => $auction->status_label,
            'total_bids' => $bids->count(),
            'total_active_bidders' => $bids->unique(fn($b) => $b->bidder_type . '-' . $b->bidder_id)->count(),
            'highest_bid' => $highestBid,
            'highest_bidder' => $highestBidder ? [
                'id' => $highestBidder->id,
                'name' => $highestBidder->name,
                //'email' => $highestBidder->email,
            ] : null,
            // 'winner' => $auction->winner ? [
            //     'id' => $auction->winner->id,
            //     'name' => $auction->winner->name,
            //     // 'email' => $auction->winner->email,
            // ] : null,
        ];

        return ResponseData::success($response, 'Auction details retrieved successfully.', 200);
    }



    public function getMyBids($auctionId)
    {
        $userId = auth()->id();

        // Fetch the auction
        $auction = Auction::findOrFail($auctionId);

        // All bids in this auction, ordered highest first
        $allBids = Bid::where('auction_id', $auctionId)
            ->orderBy('amount', 'desc')
            ->get();

        // User's all bids
        $userBids = $allBids->where('bidder_id', $userId)->values();

        // Latest bid by user (most recent timestamp)
        $latestBid = $userBids->sortByDesc('created_at')->first();

        // Highest bid in the auction
        $highestBid = $allBids->first();

        // Is the user winning?
        $isWinning = $highestBid && $highestBid->bidder_id === $userId;

        // Number of people ahead of user
        $peopleAhead = 0;
        if (!$isWinning && $latestBid) {
            // Count unique bidders with bids higher than user's latest bid
            $peopleAhead = $allBids
                ->filter(fn($bid) => $bid->amount > $latestBid->amount)
                ->pluck('bidder_id')
                ->unique()
                ->count();
        }
        return ResponseData::success([
            // 'auction' => $auction,
            'user_bids' => $userBids,
            'latest_bid' => $latestBid,
            'highest_bid' => $highestBid,
            'is_winning' => $isWinning,
            'people_ahead' => $peopleAhead
        ], 'User bids retrieved successfully.');
    }



    public function getBidHistory($auctionId)
    {
        $bids = Bid::where('auction_id', $auctionId)
            ->with(['bidder:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->get();

        if ($bids->isEmpty()) {
            return ResponseData::error([], 'No bids found for this auction.', 404);
        }

        return ResponseData::success($bids, 'Bid history retrieved successfully.');
    }
}
