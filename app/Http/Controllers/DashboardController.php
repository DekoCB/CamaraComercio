<?php

namespace App\Http\Controllers;

use App\Models\Associate;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $currentPeriod = now()->format('Y-m');

        return view('dashboard.index', [
            'totalAssociates' => Associate::count(),
            'billedThisPeriod' => (float) Invoice::forPeriod($currentPeriod)->sum('amount'),
            'collectedThisMonth' => (float) Payment::whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'pendingBalance' => (float) Invoice::where('status', '!=', Invoice::STATUS_PAGADA)->selectRaw('COALESCE(SUM(amount - paid_total), 0) as total')->value('total'),
            'overdueCount' => Invoice::overdue()->count(),
        ]);
    }
}
