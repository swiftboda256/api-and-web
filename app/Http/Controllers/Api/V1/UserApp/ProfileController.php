<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\Profile\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserApp\UserResource;
use App\Models\User;
use App\Services\Auth\UserAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return self::success(new UserResource($request->user()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validated();

        $user->fill(Arr::except($data, ['avatar_url']));

        if (! empty($data['avatar'])) {
            $user->avatar_url = $this->storeAvatar($user, $data['avatar']);
        }

        $user->profile_completed = filled($user->first_name) && filled($user->last_name);
        $user->save();

        return self::success(new UserResource($user));
    }

    private function storeAvatar(User $user, UploadedFile $file): string
    {
        Storage::disk('public')->deleteDirectory("avatars/{$user->id}");

        $path = $file->store("avatars/{$user->id}", 'public');

        return Storage::disk('public')->url($path);
    }

    public function destroy(Request $request, UserAccountService $userAccountService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $userAccountService->delete($user, $request->string('reason')->value() ?: null);

        return self::success(null, 'Your account and all associated data are being permanently deleted.');
    }
}
