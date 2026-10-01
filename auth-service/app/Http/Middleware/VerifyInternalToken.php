<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('widget.internal_token');
        $provided = (string) $request->header('X-Internal-Token', '');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Неверный внутренний токен.',
            ], 403);
        }

        return $next($request);
    }
}
