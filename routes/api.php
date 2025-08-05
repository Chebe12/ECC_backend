<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
// routes/api.php

Route::group(
    ['namespace' => 'App\Http\Controllers', 'middleware' => 'throttle:500,10'],
    function () {

        //gneral API Routes
        Route::group(['prefix' => 'auth'], function () {

            //user authentication routes
            Route::group(['prefix' => 'user'], function () {
                Route::post('/register',  'Auth\UserLoginController@register');
                Route::post('/login', action: 'Auth\UserLoginController@login');
                Route::post('send_otp', 'Auth\UserLoginController@sendOtp');
                Route::post('verify_otp', 'Auth\UserLoginController@verifyOtp');
                Route::middleware(['auth:api'])->group(function () {
                    Route::post('/logout',  'Auth\UserLoginController@logout');
                    Route::post('/refresh',  'Auth\UserLoginController@refresh');
                });
            });

            //customer authentication routes
            Route::group(['prefix' => 'customer'], function () {
                Route::post('/register',  'Auth\CustomerLoginController@register');
                Route::post('/login', action: 'Auth\CustomerLoginController@login');
                Route::post('send_otp', 'Auth\CustomerLoginController@sendOtp');
                Route::post('verify_otp', 'Auth\CustomerLoginController@verifyOtp');
                Route::middleware(['auth:api'])->group(function () {
                    Route::post('/logout',  'Auth\CustomerLoginController@logout');
                    Route::post('/refresh',  'Auth\CustomerLoginController@refresh');
                });
            });


            // Admin authentication routes
            Route::group(['prefix' => 'admin'], function () {
                Route::post('/register',  'Auth\AdminLoginController@register');
                Route::post('/login', action: 'Auth\AdminLoginController@login');
                Route::post('send_otp', 'Auth\AdminLoginController@sendOtp');
                Route::post('verify_otp', 'Auth\AdminLoginController@verifyOtp');
                Route::middleware(['auth:api'])->group(function () {
                    Route::post('/logout',  'Auth\AdminLoginController@logout');
                    Route::post('/refresh',  'Auth\AdminLoginController@refresh');
                });
            });
        });

        // User Profile
        Route::group(['prefix' => 'profile'], function () {
            // User Profile
            Route::group(['prefix' => 'user', 'middleware' => ['auth', 'auth.guard:user']], function () {
                Route::get('/fetch',  'User\UserController@me');
                Route::get('/view/{id}',  'User\UserController@show');
                Route::post('/update/{id}',  'User\UserController@update');
                // Route::delete('/delete/{id}',  'User\UserController@delete');
                Route::post('/change_password',  'User\UserController@changePassword');
            });
            // Customer Profile
            Route::group(['prefix' => 'customer', 'middleware' => ['auth', 'auth.guard:customer']], function () {
                Route::get('/fetch',  'Customer\CustomerController@me');
                Route::get('/view/{id}',  'Customer\CustomerController@show');
                Route::post('/update',  'Customer\CustomerController@update');
                Route::delete('/delete/{id}',  'Customer\CustomerController@delete');
                Route::post('/change_password',  'Customer\CustomerController@changePassword');
            });
            // Admin Profile
            Route::group(['prefix' => 'admin', 'middleware' => ['auth', 'auth.guard:admin']], function () {
                Route::get('/fetch',  'Admin\AdminController@me');
                Route::get('/view/{id}',  'Admin\AdminController@show');
                Route::post('/update/{id}',  'Admin\AdminController@update');
                Route::delete('/delete/{id}',  'Admin\AdminController@delete');
                Route::post('/change_password',  'Admin\AdminController@changePassword');
            });
        });


        // Auction Routes

        //Admin only Routes

        Route::group(['prefix' => 'auction'], function () {
            // User Management
            Route::group(['prefix' => 'admin', 'middleware' => ['auth', 'auth.guard:admin']], function () {

                Route::post('/post',  'Admin\AuctionController@store');
                Route::get('/view/{id}',  'Admin\AuctionController@show');
                Route::post('/update/{id}',  'Admin\AuctionController@update');
                Route::delete('/delete/{id}',  'Admin\AuctionController@destroy');
                Route::get('/fetch',  'Admin\AuctionController@fetchAuctions');


                // Route::get('/fetch_active',  'AuctionController@activeAuctions');
                // Route::get('/fetch_completed',  'AuctionController@completedAuctions');
                // Route::get('/fetch_upcoming',  'AuctionController@upcomingAuctions');
                // Route::post('/fetch_user_auctions_listing',  'AuctionController@userAuctionsListing');

                Route::post('/extend_deadline/{id}',  'Admin\AuctionController@extendDeadline');
                Route::post('/restart/{id}',  'Admin\AuctionController@restartAuction');
                Route::post('/cancel/{id}',  'Admin\AuctionController@cancelAuction');
                Route::post('/approve/{id}',  'Admin\AuctionController@approve');
                Route::post('/reject/{id}',  'Admin\AuctionController@rejectUserAuctionListing');
            });

            // Customer Routes
            Route::group(['prefix' => 'customer'], function () {
                Route::get('/view/{id}',  'AuctionController@show');
                Route::post('/bid/{id}',  'AuctionController@placeBid');
                Route::get('/fetch',  'AuctionController@fetchAuctions');
                Route::get('/fetch_active',  'AuctionController@activeAuctions');
                Route::get('/fetch_completed',  'AuctionController@completedAuctions');
                Route::get('/fetch_upcoming',  'AuctionController@upcomingAuctions');

                Route::post('/fetch_user_auctions_listing',  'AuctionController@userAuctionsListing');
                Route::post('/cancel_bid/{id}',  'AuctionController@cancelBid');
            });


            // User Routes
            Route::group(['prefix' => 'user', 'middleware' => ['auth', 'auth.guard:user']], function () {


                Route::post('/post',  'Admin\AuctionController@store');
                Route::get('/view/{id}',  'Admin\AuctionController@show');
                Route::post('/update/{id}',  'Admin\AuctionController@update');
                Route::delete('/delete/{id}',  'Admin\AuctionController@destroy');
                Route::get('/fetch',  'Admin\AuctionController@fetchAuctions');
                Route::get('/fetch_autcions_cards', 'Bid\BidController@getAllAuctions');
                Route::get('/view/card/{auctionId}', 'Bid\BidController@getSingleAuction');


                //Route::get('/view/{id}',  'AuctionController@show');
                Route::post('/bid/{id}',  'AuctionController@placeBid');

                Route::get('/fetch_active',  'AuctionController@activeAuctions');
                Route::get('/fetch_completed',  'AuctionController@completedAuctions');
                Route::get('/fetch_upcoming',  'AuctionController@upcomingAuctions');

                Route::post('/fetch_user_auctions_listing',  'AuctionController@userAuctionsListing');
                Route::post('/cancel_bid/{id}',  'AuctionController@cancelBid');
            });
        });

        Route::group(['prefix' => 'bid', 'middleware' => ['auth', 'auth.guard:user']], function () {
            Route::post('/place/{auctionId}', 'Bid\BidController@placeBid');


            Route::get('/by_auction/{auctionId}', 'Bid\BidController@getBidsByAuction');
            Route::get('/by_user', 'Bid\BidController@getBidsByUser');
            Route::get('/all_auctions_with_bids', 'Bid\BidController@getAllAuctionsWithBids');
            Route::get('/public_bid_history/{auctionId}', 'Bid\BidController@publicBidHistory');
        });


        //admin bid routes
        Route::group(['prefix' => 'bid_admin', 'middleware' => ['auth', 'auth.guard:admin']], function () {
            Route::get('/all', 'Bid\BidController@getAllAuctionsForAdmin');
            Route::get('/by_auction/{auctionId}', 'Bid\BidController@getBidsByAuction');
            // Route::get('/by_user', 'Bid\BidController@getBidsByUser');
            //  Route::get('/all_auctions_with_bids', 'Bid\BidController@getAllAuctionsWithBids');
            Route::get('/view/{auctionId}', 'Bid\BidController@getSingleAuctionForAdmin');
        });
    }
);
