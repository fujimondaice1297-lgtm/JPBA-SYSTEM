<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TournamentProController extends Controller
{
    public function index()
    {
        return view('tournament_pro.index');
    }
}
