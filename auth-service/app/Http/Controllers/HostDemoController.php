<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HostDemoController extends Controller
{
    public function __invoke(): View
    {
        return view('host-demo');
    }
}
