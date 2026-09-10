<?php

namespace App\Http\Controllers\Api\V1\RiderApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RiderApp\Profile\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserApp\UserResource;
use App\Models\User;
use App\Services\Auth\UserAccountService;
use App\Services\Rider\RiderProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new UserResource($user->load(['riderProfile.vehicle.vehicleType', 'riderProfile.documents'])));
    }

    public function update(UpdateProfileRequest $request, RiderProfileService $riderProfileService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user = $riderProfileService->updateProfile($user, $request->validated());
        $user->load(['riderProfile.vehicle.vehicleType', 'riderProfile.documents']);

        return self::success(new UserResource($user));
    }

    public function destroy(Request $request, UserAccountService $userAccountService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $userAccountService->delete($user, $request->string('reason')->value() ?: null);

        return self::success(null, 'Your account and all associated data are being permanently deleted.');
    }
}
