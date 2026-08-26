<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserApp\ServiceCatalogResource;
use App\Services\ServiceCatalog\ServiceCatalogService;
use Illuminate\Http\JsonResponse;

class ServiceCatalogController extends Controller
{
    public function index(ServiceCatalogService $serviceCatalogService): JsonResponse
    {
        return self::success(ServiceCatalogResource::collection($serviceCatalogService->list()));
    }
}
