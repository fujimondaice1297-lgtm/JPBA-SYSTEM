<?php

namespace App\Http\Controllers;

use App\Models\FlashNews;
use Illuminate\Http\Request;

class FlashNewsController extends Controller
{
    public function index()
    {
        $list = FlashNews::orderByDesc('created_at')->get();
        return view('flash_news.index', compact('list'));
    }

    public function create()
    {
        return view('flash_news.create');
    }

    public function store(Request $req)
    {
        $req->validate([
            'title' => 'required|string',
            'url' => 'required|url',
        ]);

        FlashNews::create($req->only(['title', 'url']));

        return redirect()->route('flash_news.index');
    }

    public function edit($id)
    {
        $item = FlashNews::findOrFail($id);
        return view('flash_news.edit', compact('item'));
    }

    public function update(Request $req, $id)
    {
        $req->validate([
            'url' => 'required|url',
        ]);

        $item = FlashNews::findOrFail($id);
        $item->update(['url' => $req->url]);

        return redirect()->route('flash_news.index');
    }
}
