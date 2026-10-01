<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\TgId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckTelegramIdsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_tg' => ['required', 'array', 'min:1'],
            'id_tg.*' => ['required', 'string', 'max:32'],
        ]);

        $ids = array_values(array_unique(array_map('strval', $data['id_tg'])));
        $rows = TgId::query()
            ->with('authCode')
            ->whereIn('id_tg', $ids)
            ->get()
            ->keyBy('id_tg');

        $linked = [];
        $unlinked = [];
        $fired = [];

        foreach ($ids as $id) {
            $row = $rows->get($id);

            if (! $row) {
                $unlinked[] = $id;

                continue;
            }

            if ($row->authCode?->status_employee === EmployeeStatus::Fired) {
                $fired[] = $id;

                continue;
            }

            $linked[] = $id;
        }

        return response()->json([
            'linked' => $linked,
            'unlinked' => $unlinked,
            'fired' => $fired,
        ]);
    }
}
