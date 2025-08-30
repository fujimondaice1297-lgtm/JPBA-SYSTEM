<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TournamentBallController extends Controller
{
    public function index()
    {
        return view('tournament_balls.index');
    }
}
