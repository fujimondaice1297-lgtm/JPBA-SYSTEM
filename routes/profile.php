<?php

use Illuminate\Support\Facades\Route;

Route::get('/profile', function () {
    return view('profile.edit');
})->middleware(['auth'])->name('profile.edit');
