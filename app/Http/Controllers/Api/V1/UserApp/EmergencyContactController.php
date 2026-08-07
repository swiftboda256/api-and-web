<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\EmergencyContact\StoreEmergencyContactRequest;
use App\Http\Requests\Api\V1\UserApp\EmergencyContact\UpdateEmergencyContactRequest;
use App\Http\Resources\Api\V1\UserApp\EmergencyContactResource;
use App\Models\User;
use App\Services\EmergencyContact\EmergencyContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmergencyContactController extends Controller
{
    public function index(Request $request, EmergencyContactService $emergencyContactService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(EmergencyContactResource::collection($emergencyContactService->list($user)));
    }

    public function store(StoreEmergencyContactRequest $request, EmergencyContactService $emergencyContactService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $contact = $emergencyContactService->create($user, $request->validated());

        return self::success(new EmergencyContactResource($contact), status: 201);
    }

    public function update(UpdateEmergencyContactRequest $request, int $id, EmergencyContactService $emergencyContactService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $contact = $emergencyContactService->update($user, $id, $request->validated());

        return self::success(new EmergencyContactResource($contact));
    }

    public function destroy(Request $request, int $id, EmergencyContactService $emergencyContactService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $emergencyContactService->delete($user, $id);

        return self::success([], null, 204);
    }
}
