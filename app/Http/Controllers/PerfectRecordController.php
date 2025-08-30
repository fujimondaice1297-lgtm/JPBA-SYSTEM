<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PerfectRecordController extends Controller
{
    public function index()
    {
        return view('perfect_records.index');
    }
}
