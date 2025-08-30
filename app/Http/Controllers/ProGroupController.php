<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProGroupController extends Controller
{
    public function index()
    {
        return view('pro_groups.index');
    }
}
