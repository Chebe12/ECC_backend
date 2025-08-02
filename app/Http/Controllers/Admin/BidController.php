<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BidController extends Controller
{
    public function placeBid(Request $request)
    {
        $request->validate([
            'auction_id' => 'required|exists:auctions,id',
            'amount' => 'required|numeric|min:1',
            'is_auto' => 'boolean',
            'auto_max_bid' => 'nullable|numeric|min:1',
            'auto_increment' => 'nullable|numeric|min:1',
        ]);

        $user = auth()->user();
        $auction = Auction::findOrFail($request->auction_id);

        // 1. Prevent expired auctions
        if (now()->greaterThan($auction->end_time)) {
            return response()->json(['message' => 'Auction has ended.'], 422);
        }

        DB::beginTransaction();

        // 2. Get current highest bid
        $highestBid = $auction->bids()->orderByDesc('amount')->first();
        $minBid = $highestBid ? $highestBid->amount + 1 : $auction->start_price;

        // 3. Ensure bid is higher
        if ($request->amount < $minBid) {
            return response()->json(['message' => "Your bid must be greater than ₦$minBid."], 422);
        }

        // 4. Save bid
        $bid = Bid::create([
            'auction_id' => $auction->id,
            'user_id' => $user->id,
            'amount' => $request->amount,
            'is_auto' => $request->is_auto ?? false,
            'auto_max_bid' => $request->auto_max_bid,
            'auto_increment' => $request->auto_increment,
        ]);

        // 5. Extend bid if close to end
        if ($auction->end_time->diffInSeconds(now()) <= 60) {
            $auction->end_time = $auction->end_time->addMinutes(2);
            $auction->save();
        }

        DB::commit();

        // 6. Dispatch job to check auto-bidders
        AutoBidJob::dispatch($auction->id);

        return response()->json(['message' => 'Bid placed successfully!', 'data' => $bid]);
    }
}
