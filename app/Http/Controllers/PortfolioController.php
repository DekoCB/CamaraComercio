<?php

namespace App\Http\Controllers;

use App\Models\Associate;
use App\Services\PortfolioService;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function __construct(private readonly PortfolioService $portfolio) {}

    public function index(): View
    {
        return view('portfolio.index', ['associates' => $this->portfolio->debtSummary()]);
    }

    public function debtors(): View
    {
        return view('portfolio.debtors', ['associates' => $this->portfolio->debtors()]);
    }

    public function statement(Associate $associate): View
    {
        return view('portfolio.statement', [
            'associate' => $associate,
        ] + $this->portfolio->statement($associate));
    }
}
