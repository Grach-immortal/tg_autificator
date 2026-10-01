<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EmployeeFiredException;
use App\Http\Controllers\Controller;
use App\Services\CodeVerificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyCodeController extends Controller
{
    public function __invoke(Request $request, CodeVerificationService $verifier): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'id_tg' => ['required', 'string', 'max:32'],
        ]);

        try {
            return response()->json($verifier->verify($data['code'], $data['id_tg']));
        } catch (EmployeeFiredException) {
            return response()->json([
                'message' => 'Сотрудник уволен.',
            ], 403);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Код не найден.',
            ], 404);
        }
    }
}
