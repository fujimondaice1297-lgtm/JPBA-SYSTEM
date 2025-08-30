<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class AdminHomeController extends Controller
{
    public function index()
    {
        return 'Admin dashboard OK'; // 一旦これでOK（後でビューに差し替え）
    }
}
