<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\Address\StoreAddressRequest;
use App\Http\Requests\Api\V1\UserApp\Address\UpdateAddressRequest;
use App\Http\Resources\Api\V1\UserApp\AddressResource;
use App\Models\User;
use App\Services\Address\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request, AddressService $addressService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(AddressResource::collection($addressService->list($user)));
    }

    public function store(StoreAddressRequest $request, AddressService $addressService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $address = $addressService->create($user, $request->validated());

        return self::success(new AddressResource($address), status: 201);
    }

    public function update(UpdateAddressRequest $request, int $id, AddressService $addressService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $address = $addressService->update($user, $id, $request->validated());

        return self::success(new AddressResource($address));
    }

    public function destroy(Request $request, int $id, AddressService $addressService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $addressService->delete($user, $id);

        return self::success([], null, 204);
    }
}
