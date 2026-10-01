<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthCodeIssuer;
use App\Services\WidgetToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetCodeController extends Controller
{
    public function __invoke(Request $request, WidgetToken $tokens, AuthCodeIssuer $issuer): JsonResponse
    {
        $token = (string) $request->query('id_ad', '');
        $idAd = $tokens->verify($token);

        if ($idAd === null) {
            return response()->json([
                'message' => 'Недействительный или просроченный id_ad.',
            ], 403);
        }

        try {
            return response()->json($issuer->current($idAd));
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Сотрудник с таким id_ad не найден.',
            ], 404);
        }
    }
}
