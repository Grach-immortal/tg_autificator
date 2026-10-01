<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TgId;
use Illuminate\Http\JsonResponse;

class ListTelegramIdsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $ids = TgId::query()
            ->orderBy('id')
            ->pluck('id_tg')
            ->map(fn ($id) => (string) $id)
            ->values();

        return response()->json([
            'id_tg' => $ids,
        ]);
    }
}
