<?php

namespace App\Http\Controllers;

use App\Models\Associate;
use App\Services\PortfolioService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function __construct(private readonly PortfolioService $portfolio) {}

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        return view('portfolio.index', [
            'associates' => $this->portfolio->debtSummary($term !== '' ? $term : null),
            'term' => $term,
        ]);
    }

    public function debtors(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        return view('portfolio.debtors', [
            'associates' => $this->portfolio->debtors($term !== '' ? $term : null),
            'term' => $term,
        ]);
    }

    public function statement(Associate $associate): View
    {
        return view('portfolio.statement', [
            'associate' => $associate,
        ] + $this->portfolio->statement($associate));
    }
}
