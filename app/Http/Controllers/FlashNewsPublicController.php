<?php

namespace App\Http\Controllers;

use App\Models\FlashNews;

class FlashNewsPublicController extends Controller
{
    public function show($id)
    {
        $item = FlashNews::findOrFail($id);
        return redirect($item->url);
    }
}
