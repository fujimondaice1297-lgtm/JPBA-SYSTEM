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

    public function create(Request $request)
    {
        return view('flash_news.create', [
            'title' => trim((string) $request->query('title', '')),
        ]);
    }

    public function store(Request $req)
    {
        $req->validate([
            'title' => 'required|string|max:255',
            'url' => 'required|url',
        ]);

        FlashNews::create($req->only(['title', 'url']));

        return redirect()->route('flash_news.index')->with('success', '外部速報リンクを登録しました。');
    }

    public function edit($id)
    {
        $item = FlashNews::findOrFail($id);

        return view('flash_news.edit', compact('item'));
    }

    public function update(Request $req, $id)
    {
        $req->validate([
            'title' => 'required|string|max:255',
            'url' => 'required|url',
        ]);

        $item = FlashNews::findOrFail($id);
        $item->update($req->only(['title', 'url']));

        return redirect()->route('flash_news.index')->with('success', '外部速報リンクを更新しました。');
    }

    public function destroy($id)
    {
        FlashNews::findOrFail($id)->delete();

        return redirect()->route('flash_news.index')->with('success', '外部速報リンクを削除しました。');
    }
}
