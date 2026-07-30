<?php

namespace App\Http\Controllers\Api\V1\RiderApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\Device\UpdateDeviceRequest;
use App\Http\Resources\Api\V1\UserApp\DeviceResource;
use App\Models\User;
use App\Services\Device\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index(Request $request, DeviceService $deviceService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(DeviceResource::collection($deviceService->list($user)));
    }

    public function update(UpdateDeviceRequest $request, int $id, DeviceService $deviceService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $device = $deviceService->update($user, $id, $request->validated());

        return self::success(new DeviceResource($device));
    }

    public function destroy(Request $request, int $id, DeviceService $deviceService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deviceService->delete($user, $id);

        return self::success([], null, 204);
    }
}
