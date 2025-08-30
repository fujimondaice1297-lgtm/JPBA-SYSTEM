<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PlayerBallController extends Controller
{
    public function index()
    {
        return view('player_balls.index');
    }
}
