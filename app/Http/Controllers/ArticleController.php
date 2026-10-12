<?php

namespace App\Http\Controllers;

use App\Services\ArticleLibrary;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request, ArticleLibrary $library): View
    {
        // An unknown topic shows every article rather than an empty page.
        $topic = in_array($request->query('topic'), $library->topics(), true) ? $request->query('topic') : null;

        return view('public.articles.index', [
            'articles' => $library->forTopic($topic),
            'topics' => $library->topics(),
            'activeTopic' => $topic,
        ]);
    }

    public function show(string $slug, ArticleLibrary $library): View
    {
        $article = $library->find($slug);

        abort_if($article === null, 404);

        return view('public.articles.show', [
            'article' => $article,
            'related' => $library->related($article),
        ]);
    }
}
