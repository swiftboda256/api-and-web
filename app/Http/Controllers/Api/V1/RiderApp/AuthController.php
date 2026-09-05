<?php

namespace App\Http\Controllers\Api\V1\RiderApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\Auth\RequestOtpRequest;
use App\Http\Requests\Api\V1\UserApp\Auth\VerifyOtpRequest;
use App\Http\Resources\Api\V1\UserApp\UserResource;
use App\Models\RiderProfile;
use App\Models\User;
use App\Models\Zone;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const string PURPOSE = 'login';

    private const string OTP_CHANNEL = 'sms';

    public function login(RequestOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $phone = $request->string('phone')->toString();

        $otpService->generateOTP($phone, null, self::OTP_CHANNEL, self::PURPOSE);

        return self::success(null, 'OTP has been sent');
    }

    public function verifyOtp(VerifyOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $phone = $request->string('phone')->toString();
        $code = $request->string('code')->toString();
        $device_id = $request->string('device_id');
        $device_type = $request->string('device_type');
        $fcm_token = $request->string('fcm_token');
        $app_version = $request->input('app_version');

        $otpService->verify($phone, $code, self::PURPOSE);

        $user = User::query()->with('riderProfile')->firstOrCreate(
            ['phone' => $phone],
            ['login_type' => self::OTP_CHANNEL],
        );

        if ($user->wasRecentlyCreated) {
            $user->refresh();
            $user->phone_verified_at = now();
            $user->assignRole('rider');

            RiderProfile::query()->create([
                'user_id' => $user->id,
                'rider_ref' => $this->generateRiderRef(),
                'home_zone_id' => Zone::query()->where('name', 'Kampala')->value('id'),
            ]);
        }

        $user->last_login_at = now();
        $user->save();

        $user->devices()->update([
            'active' => false,
        ]);

        $user->devices()->updateOrCreate(
            [
                'device_id' => $device_id,
            ], [
                'device_type' => $device_type,
                'fcm_token' => $fcm_token,
                'app_version' => $app_version,
                'active' => true,
            ]);

        // Revoke all previous tokens
        $user->tokens()->delete();

        $token = $user->createToken('rider-app')->plainTextToken;

        return self::success([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return self::success();
    }

    private function generateRiderRef(): string
    {
        do {
            $riderRef = 'RDR-'.now()->format('ymd').strtoupper(Str::random(6));
        } while (RiderProfile::query()->where('rider_ref', $riderRef)->exists());

        return $riderRef;
    }
}
