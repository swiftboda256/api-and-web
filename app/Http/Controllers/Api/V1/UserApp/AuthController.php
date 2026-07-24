<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\Auth\RequestOtpRequest;
use App\Http\Requests\Api\V1\UserApp\Auth\VerifyOtpRequest;
use App\Http\Resources\Api\V1\UserApp\UserResource;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $otpService->verify($phone, $code, self::PURPOSE);

        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            ['login_type' => self::OTP_CHANNEL],
        );

        if ($user->wasRecentlyCreated) {
            $user->refresh();
            $user->phone_verified_at = now();
            $user->assignRole('customer');
        }

        $user->last_login_at = now();
        $user->save();

        // Revoke all previous tokens
        $user->tokens()->delete();

        $token = $user->createToken('user-app')->plainTextToken;

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
}
