<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserApp\ConfigurationResource;
use App\Services\Configuration\ConfigurationService;
use Illuminate\Http\JsonResponse;

class ConfigurationController extends Controller
{
    public function index(ConfigurationService $configurationService): JsonResponse
    {
        return self::success(ConfigurationResource::collection($configurationService->listPublic('user_app')));
    }
}
