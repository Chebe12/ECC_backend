<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Mail\EmailVerificationOtpMail;

class OtpService
{
    /**
     * Generate OTP for both User and Customer.
     */
    public function generateOtp($model, string $type = 'email'): Otp
    {
        if (!in_array(get_class($model), [User::class, Customer::class])) {
            throw new \Exception('Invalid model type');
        }

        // Delete any existing OTP if it hasn't been used
        Otp::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('type', $type)
            ->where('is_used', false)
            ->delete();

        // Generate a new OTP
        $code = random_int(100000, 999999);

        // Create a new OTP entry
        return Otp::create([
            'model_type' => get_class($model),
            'model_id'   => $model->id,
            'code'       => $code,
            'type'       => $type,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);
    }


    /**
     * Send OTP via email for both User and Customer.
     */
    public function sendEmailOtp($model)
    {
        // Generate OTP for the model (User or Customer)
        $otp = $this->generateOtp($model);

        // Send the OTP via email
        Mail::to($model->email)->send(new EmailVerificationOtpMail($model, $otp->code));
    }


    /**
     * Verify OTP for both User and Customer.
     */
    public function verifyOtp($model, string $code, string $type = 'email'): bool
    {
        if (!in_array(get_class($model), [User::class, Customer::class])) {
            throw new \Exception('Invalid model type');
        }

        // Find the OTP for the specific model (User or Customer)
        $otp = Otp::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('code', $code)
            ->where('type', $type)
            ->first();

        if (!$otp || $otp->expires_at->isPast()) {
            return false;
        }

        // Mark the model as email verified
        if ($type === 'email') {
            $model->email_verified = true;
            $model->email_verified_at = now();
        }

        // Save the updated model
        $model->save();

        return true;
    }
}
