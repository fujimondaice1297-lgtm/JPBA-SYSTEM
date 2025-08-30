<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function index(Request $request)
    {
        return response('Compliance dashboard stub', 200);
    }

    public function notify(Request $request)
    {
        return back()->with('status', 'stub: 送信します（ダミー）');
    }
}
