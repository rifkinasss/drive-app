<?php

namespace App\Http\Controllers;

use App\Services\SecuritySessionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecuritySessionController
{
    public function index(Request $request, SecuritySessionService $sessions): JsonResponse
    {
        return ApiResponse::success(['items' => $sessions->list($request->user(), $request->session()->getId())]);
    }
}
