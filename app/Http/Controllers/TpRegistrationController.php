<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TpRegistrationController extends Controller
{
    public function index()
    {
        return view('tp_registration.index');
    }
}
