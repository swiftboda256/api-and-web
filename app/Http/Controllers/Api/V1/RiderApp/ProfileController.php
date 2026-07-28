<?php

namespace App\Http\Controllers\Api\V1\RiderApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RiderApp\Profile\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserApp\UserResource;
use App\Models\User;
use App\Services\Rider\RiderProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new UserResource($user->load('riderProfile')));
    }

    public function update(UpdateProfileRequest $request, RiderProfileService $riderProfileService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user = $riderProfileService->updateProfile($user, $request->validated());

        return self::success(new UserResource($user));
    }
}
