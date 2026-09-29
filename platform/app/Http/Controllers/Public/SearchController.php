<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicSearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request, PublicSearchService $search): Response
    {
        $query = trim((string) $request->query('q', ''));

        return Inertia::render('Public/Search', [
            'query' => $query,
            'results' => $search->search($query),
        ]);
    }
}
