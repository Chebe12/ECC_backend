<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Auction;
use App\Medels\User;
use App\Models\AuctionMedia;
use App\Helpers\ResponseData;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;



class AuctionController extends Controller
{
    public function fetchAuctions(Request $request)
    {
        $filter = $request->query('filter');
        $now = now();

        $query = Auction::with(['media', 'creator']);

        switch ($filter) {
            case 'admin':
                $query->where('creator_type', 'admin');
                break;

            case 'user':
                $query->where('creator_type', 'user');
                break;

            case 'upcoming':
                $query->where('status', 'approved')
                    ->where('auction_start_time', '>', $now);
                break;

            case 'active':
                $query->where('status', 'approved')
                    ->where('auction_start_time', '<=', $now)
                    ->where('auction_end_time', '>', $now);
                break;

            case 'completed':
                $query->where('status', 'approved')
                    ->where('auction_end_time', '<=', $now);
                break;

            case 'pending':
                $query->where('status', 'pending');
                break;

            case 'rejected':
                $query->where('status', 'rejected');
                break;

            case 'suspended':
                $query->where('status', 'suspended');
                break;

            case 'cancelled':
                $query->where('status', 'cancelled');
                break;

            case 'all':
            default:
                // No additional filters
                break;
        }

        $auctions = $query->get()->map(function ($auction) {
            $auction->stage = $auction->status_label;
            return $auction;
        });

        return ResponseData::success($auctions, 'Auctions fetched successfully.');
    }



    public function show($id)
    {
        $auction = Auction::with('media', 'creator')->findOrFail($id);

        $auction->stage = $auction->status_label;

        return ResponseData::success($auction, 'Auction details retrieved successfully.');
    }



    // public function store(Request $request)
    // {
    //     try {
    //         $validated = $request->validate([
    //             'title' => 'required|string|max:255',
    //             'description' => 'nullable|string',
    //             'auction_start_time' => 'required|date',
    //             'auction_end_time' => 'required|date|after:auction_start_time',
    //             'starting_bid_price' => 'required|numeric|min:0',
    //             'reserve_price' => 'nullable|numeric|min:0',
    //             'buy_now_price' => 'nullable|numeric|min:0',
    //             'bid_increment' => 'nullable|in:Auto,fixed',
    //             'auto_extend' => 'nullable|boolean',
    //             'featured' => 'nullable|boolean',
    //             'promotional_tags' => 'nullable|array',
    //             'promotional_tags.*' => 'string|max:100',

    //             'media' => 'nullable|array',
    //             'media.*' => 'file|mimes:jpeg,png,jpg,gif,svg,mp4,mov,avi,webm|max:10240',

    //             'auth_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
    //         ]);

    //         $auctionData = $validated;
    //         $auctionData['creator_id'] = auth()->id();

    //         // Upload auth certificate
    //         if ($request->hasFile('auth_certificate')) {
    //             $certificatePath = $request->file('auth_certificate')->store('auction_certificates', 'public');
    //             $auctionData['auth_certificate'] = $certificatePath;
    //         }

    //         // Create the auction
    //         $auction = Auction::create($auctionData);

    //         // Handle media files
    //         if ($request->hasFile('media')) {
    //             foreach ($request->file('media') as $file) {
    //                 try {
    //                     $mime = $file->getMimeType();
    //                     $fileType = str_starts_with($mime, 'video') ? 'video' : 'photo';
    //                     $path = $file->store('auction_media', 'public');

    //                     AuctionMedia::create([
    //                         'auction_id' => $auction->id,
    //                         'media_type' => $fileType,
    //                         'media_path' => $path,
    //                     ]);
    //                 } catch (\Exception $mediaEx) {
    //                     Log::error('Media upload failed: ' . $mediaEx->getMessage());
    //                     continue;
    //                 }
    //             }
    //         }

    //         return ResponseData::success(
    //             $auction->load('media'),
    //             'Auction created successfully.',
    //             201
    //         );
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         return ResponseData::error('Validation failed', 422, $e->errors());
    //     } catch (\Exception $e) {
    //         \Log::error('Auction creation failed: ' . $e->getMessage());

    //         return ResponseData::error(
    //             'An unexpected error occurred while creating the auction.',
    //             $e->getMessage(),
    //             500
    //         );
    //     }
    // }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'auction_start_time' => 'required|date',
                'auction_end_time' => 'required|date|after:auction_start_time',
                'starting_bid' => 'required|numeric|min:0',
                'reserve_price' => 'nullable|numeric|min:0',
                'buy_now_price' => 'nullable|numeric|min:0',
                'bid_increment' => 'nullable|in:Auto,fixed',
                'auto_extend' => 'nullable|boolean',
                'featured' => 'nullable|boolean',
                'promotional_tags' => 'nullable|array',
                'promotional_tags.*' => 'string|max:100',

                'media' => 'nullable|array',
                'media.*' => 'file|mimes:jpeg,png,jpg,gif,svg,mp4,mov,avi,webm|max:10240',

                'auth_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ]);

            $user = auth()->user();

            $auctionData = $validated;
            $auctionData['creator_id'] = $user->id;

            // Dynamically detect creator_type from guard
            if (auth('admin')->check()) {
                $auctionData['creator_type'] = 'App\Models\Admin';
            } elseif (auth('user')->check()) {
                $auctionData['creator_type'] = 'App\Models\User';
            } else {
                return ResponseData::error('Unauthorized: Unknown user type', 403);
            }

            $auctionData['promotional_tags'] = json_encode($validated['promotional_tags'] ?? []);
            $auctionData['bid_increment'] = $validated['bid_increment'] ?? null;
            $auctionData['auto_extend'] = $validated['auto_extend'] ?? false;
            $auctionData['featured'] = $validated['featured'] ?? false;

            // Upload auth certificate
            if ($request->hasFile('auth_certificate')) {
                $certificatePath = $request->file('auth_certificate')->store('auction_certificates', 'public');
                $auctionData['auth_certificate'] = $certificatePath;
            }

            // Create the auction
            $auction = Auction::create($auctionData);

            // Handle media files
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $mime = $file->getMimeType();
                    $fileType = str_starts_with($mime, 'video') ? 'video' : 'photo';
                    $path = $file->store('auction_media', 'public');

                    AuctionMedia::create([
                        'auction_id' => $auction->id,
                        'media_type' => $fileType,
                        'media_path' => $path,
                    ]);
                }
            }

            return ResponseData::success(
                $auction->load('media'),
                'Auction created successfully.',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseData::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            \Log::error('Auction creation failed: ' . $e->getMessage());
            return ResponseData::error(
                'An unexpected error occurred while creating the auction.',
                $e->getMessage(),
                500
            );
        }
    }


    public function update(Request $request, $id)
    {
        try {
            $auction = Auction::findOrFail($id);

            // Ensure the authenticated user is the owner
            // if ($auction->user_id !== auth()->id()) {
            //     return ResponseData::error([], 'Unauthorized to update this auction.', 403);
            // }

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'auction_start_time' => 'sometimes|required|date',
                'auction_end_time' => 'sometimes|required|date|after:auction_start_time',
                'starting_bid_price' => 'sometimes|required|numeric|min:0',
                'reserve_price' => 'nullable|numeric|min:0',
                'buy_now_price' => 'nullable|numeric|min:0',
                'bid_increment' => 'nullable|in:Auto,fixed',
                'auto_extend' => 'nullable|boolean',
                'featured' => 'nullable|boolean',
                'promotional_tags' => 'nullable|array',
                'promotional_tags.*' => 'string|max:100',

                'media' => 'nullable|array',
                'media.*' => 'file|mimes:jpeg,png,jpg,gif,svg,mp4,mov,avi,webm|max:10240',

                'auth_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ]);

            // Update auction data
            $auction->update($validated);

            // Replace auth certificate
            if ($request->hasFile('auth_certificate')) {
                if ($auction->auth_certificate && Storage::disk('public')->exists($auction->auth_certificate)) {
                    Storage::disk('public')->delete($auction->auth_certificate);
                }
                $certificatePath = $request->file('auth_certificate')->store('auction_certificates', 'public');
                $auction->auth_certificate = $certificatePath;
                $auction->save();
            }

            // Handle media update (delete old ones and add new)
            if ($request->hasFile('media')) {
                // Delete old media files
                foreach ($auction->media as $media) {
                    if (Storage::disk('public')->exists($media->media_path)) {
                        Storage::disk('public')->delete($media->media_path);
                    }
                    $media->delete();
                }

                // Upload new media
                foreach ($request->file('media') as $file) {
                    $mime = $file->getMimeType();
                    $fileType = str_starts_with($mime, 'video') ? 'video' : 'photo';
                    $path = $file->store('auction_media', 'public');

                    AuctionMedia::create([
                        'auction_id' => $auction->id,
                        'media_type' => $fileType,
                        'media_path' => $path,
                    ]);
                }
            }

            return ResponseData::success(
                $auction->load('media'),
                'Auction updated successfully.',
                200
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseData::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            \Log::error('Auction update failed: ' . $e->getMessage());

            return ResponseData::error(
                'An unexpected error occurred while updating the auction.',
                $e->getMessage(),
                500
            );
        }
    }



    public function destroy($id)
    {
        $auction = Auction::findOrFail($id);
        $auction->delete();

        return ResponseData::success([], 'Auction deleted successfully.');
    }

    public function extendDeadline(Request $request, $id)
    {
        $auction = Auction::findOrFail($id);

        $validated = $request->validate([
            'new_end_time' => 'required|date|after:now',
        ]);

        $auction->auction_end_time = $validated['new_end_time'];
        $auction->save();

        return ResponseData::success($auction, 'Auction deadline extended.', 200);
    }

    public function restartAuction(Request $request, $id)
    {
        $auction = Auction::findOrFail($id);

        $validated = $request->validate([
            'new_start_time' => 'required|date|after:now',
            'new_end_time'   => 'required|date|after:new_start_time',
        ]);

        $auction->auction_start_time = $validated['new_start_time'];
        $auction->auction_end_time = $validated['new_end_time'];
        $auction->status = 'pending';

        $auction->save();

        return ResponseData::success($auction, 'Auction restarted successfully.', 200);
    }


    public function cancelAuction($id)
    {
        $auction = Auction::findOrFail($id);

        if (in_array($auction->status, ['ended', 'cancelled'])) {
            return ResponseData::error([], 'Auction already ended or cancelled.', 400);
        }

        $auction->status = 'cancelled';
        $auction->save();
        return ResponseData::success([], 'Auction cancelled successfully.', 200);
    }


    public function approve($id)
    {
        $auction = Auction::findOrFail($id);

        // Ensure only pending or appropriate status can be approved
        if ($auction->status !== 'pending') {
            return ResponseData::error([], 'Only pending auctions can be approved.', 400);
        }

        $auction->status = 'approved';
        // $auction->approved_at = now(); 
        // $auction->approved_by = auth()->id(); 
        $auction->save();

        return ResponseData::success($auction, 'Auction approved successfully.');
    }


    public function rejectUserAuctionListing($id)
    {
        $auction = Auction::findOrFail($id);

        if ($auction->status !== 'pending') {
            return ResponseData::error([], 'only pending Auctions can be rejected', 400);
        }

        $auction->status = 'cancelled';
        $auction->save();
        return ResponseData::success([], 'Auction Lising Rejected', 200);
    }

    public function fetchMyAuctions(Request $request)
    {
        $user = $request->user(); // Authenticated user (either Admin or User)

        $auctions = Auction::with('media')
            ->where('creator_id', $user->id)
            ->where('creator_type', get_class($user)) // Ensures type matches (App\Models\User or App\Models\Admin)
            ->latest()
            ->get()
            ->map(function ($auction) {
                $auction->stage = $auction->status_label;
                return $auction;
            });

        return ResponseData::success($auctions, 'Your auctions fetched successfully.');
    }
}
