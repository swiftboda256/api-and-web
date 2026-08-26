<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\PromoCode\VerifyPromoCodeRequest;
use App\Http\Resources\Api\V1\UserApp\PromoCodeResource;
use App\Models\User;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\JsonResponse;

class PromoCodeController extends Controller
{
    public function verify(VerifyPromoCodeRequest $request, CheckoutService $checkoutService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $promo = $checkoutService->resolvePromoCode($request->validated('code'), $user);

        return self::success(new PromoCodeResource($promo));
    }
}
