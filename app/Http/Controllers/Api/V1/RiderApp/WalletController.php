<?php

namespace App\Http\Controllers\Api\V1\RiderApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\Wallet\IndexWalletHistoryRequest;
use App\Http\Requests\Api\V1\UserApp\Wallet\IndexWithdrawalRequestsRequest;
use App\Http\Requests\Api\V1\UserApp\Wallet\StoreWalletRequest;
use App\Http\Requests\Api\V1\UserApp\Wallet\TopUpWalletRequest;
use App\Http\Requests\Api\V1\UserApp\Wallet\UpdateWalletPinRequest;
use App\Http\Requests\Api\V1\UserApp\Wallet\WithdrawWalletRequest;
use App\Http\Resources\Api\V1\UserApp\TransactionCollection;
use App\Http\Resources\Api\V1\UserApp\TransactionResource;
use App\Http\Resources\Api\V1\UserApp\WalletResource;
use App\Http\Resources\Api\V1\UserApp\WithdrawalRequestCollection;
use App\Http\Resources\Api\V1\UserApp\WithdrawalRequestResource;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function store(StoreWalletRequest $request, WalletService $walletService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wallet = $walletService->create($user, $request->validated());

        return self::success(new WalletResource($wallet), status: 201);
    }

    public function balance(Request $request, WalletService $walletService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new WalletResource($walletService->balance($user)));
    }

    public function topUp(TopUpWalletRequest $request, WalletService $walletService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $transaction = $walletService->topUp($user, $request->validated());

        return self::success(new TransactionResource($transaction), status: 201);
    }

    public function updatePin(UpdateWalletPinRequest $request, WalletService $walletService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wallet = $walletService->updatePin($user, $request->validated());

        return self::success(new WalletResource($wallet));
    }

    public function withdraw(WithdrawWalletRequest $request, WalletService $walletService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $withdrawalRequest = $walletService->withdraw($user, $request->validated());

        return self::success(new WithdrawalRequestResource($withdrawalRequest), status: 201);
    }

    public function withdrawalRequests(IndexWithdrawalRequestsRequest $request, WalletService $walletService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new WithdrawalRequestCollection($walletService->withdrawalRequests($user, $request->validated())));
    }

    public function history(IndexWalletHistoryRequest $request, WalletService $walletService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new TransactionCollection($walletService->history($user, $request->validated())));
    }
}
