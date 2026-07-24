<?php

namespace App\Services\Auth;

use App\Models\Configuration;
use App\Models\Otp;
use App\Models\User;
use App\Notifications\OtpCodeNotification;
use App\Services\Sms\Contracts\SmsGateway;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

readonly class OtpService
{
    public function __construct(
        private SmsGateway $smsGateway,
    ) {}

    public function generateOTP(string $phone, ?string $email, string $channel, string $purpose): void
    {

        if ($this->isMockOtpEnabled()) {
            return;
        }

        $throttleKey = "otp-request:{$phone}:{$purpose}";

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            throw ValidationException::withMessages([
                'phone' => 'Please wait before requesting another code.',
            ]);
        }

        RateLimiter::hit($throttleKey, (int) config('otp.resend_throttle_seconds'));

        $user = User::query()->where('phone', $phone)->first();

        if ($user && in_array($user->login_type, ['sms', 'email'], true)) {
            $channel = $user->login_type;
        }

        $code = (string) random_int(10000, 99999);

        Otp::query()->create([
            'channel' => $channel,
            'phone' => $phone,
            'email' => $email,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes((int) config('otp.expiry_minutes')),
            'attempts' => 0,
        ]);

        $message = "Your Swift Boda verification code is {$code}. It expires in ".config('otp.expiry_minutes').' minutes.';

        if ($channel === 'sms') {
            $this->smsGateway->send($phone, $message);
        } else {
            Notification::route('mail', $email)->notify(new OtpCodeNotification($code));
        }
    }

    public function verify(string $phone, string $code, string $purpose): void
    {
        if ($this->isMockOtpEnabled()) {
            return;
        }

        $otp = Otp::query()->where('phone', $phone)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (! $otp) {
            throw ValidationException::withMessages([
                'code' => 'No pending verification code for this phone number.',
            ]);
        }

        if ($otp->expires_at->isPast()) {
            $otp->delete();

            throw ValidationException::withMessages([
                'code' => 'This code has expired. Please request a new one.',
            ]);
        }

        if ($otp->attempts >= (int) config('otp.max_attempts')) {
            $otp->delete();

            throw ValidationException::withMessages([
                'code' => 'Too many incorrect attempts. Please request a new code.',
            ]);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            $remainingAttempts = (int) config('otp.max_attempts') - $otp->attempts;
            throw ValidationException::withMessages([
                'code' => "The code you entered is incorrect. You have {$remainingAttempts} attempts remaining.",
            ]);
        }

        $otp->delete();
    }

    private function isMockOtpEnabled(): bool
    {
        return ! app()->isProduction() && Configuration::get('mock_otp', false);
    }
}
