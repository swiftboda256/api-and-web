<?php

namespace App\Http\Controllers\Api\V1\RiderApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserApp\SupportCategoryResource;
use App\Services\SupportCategory\SupportCategoryService;
use Illuminate\Http\JsonResponse;

class SupportCategoryController extends Controller
{
    public function index(SupportCategoryService $supportCategoryService): JsonResponse
    {
        return self::success(SupportCategoryResource::collection($supportCategoryService->list()));
    }
}
