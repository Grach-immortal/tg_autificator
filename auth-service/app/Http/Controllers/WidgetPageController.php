<?php

namespace App\Http\Controllers;

use App\Models\AuthCode;
use App\Services\AppSettings;
use App\Services\WidgetToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class WidgetPageController extends Controller
{
    public function __invoke(Request $request, WidgetToken $tokens, AppSettings $settings): View|RedirectResponse
    {
        $employees = AuthCode::query()->orderBy('id_ad')->get();
        $param = $request->query('id_ad');

        if (is_string($param) && $param !== '') {
            $verified = $tokens->verify($param);

            if ($verified !== null) {
                $current = $employees->firstWhere('id_ad', $verified);

                if ($current) {
                    return $this->page($employees, $current, $param, $settings);
                }

                return $this->page($employees, null, null, $settings, 'Подписанный id_ad не найден в таблице auth_codes.');
            }

            $current = $employees->firstWhere('id_ad', $param);

            if ($current) {
                return redirect()->route('widget', ['id_ad' => $tokens->issue($current->id_ad)]);
            }

            return $this->page($employees, null, null, $settings, 'Неизвестный или неподписанный id_ad.');
        }

        $first = $employees->first();

        if ($first) {
            return redirect()->route('widget', ['id_ad' => $tokens->issue($first->id_ad)]);
        }

        return $this->page($employees, null, null, $settings);
    }

    /**
     * @param  Collection<int, AuthCode>  $employees
     */
    private function page(Collection $employees, ?AuthCode $current, ?string $token, AppSettings $settings, ?string $error = null): View
    {
        return view('widget', [
            'employees' => $employees,
            'current' => $current,
            'token' => $token,
            'lifetime_seconds' => $settings->codeLifetimeSeconds(),
            'error' => $error,
        ]);
    }
}
