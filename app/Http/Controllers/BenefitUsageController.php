<?php

namespace App\Http\Controllers;

use App\Http\Requests\BenefitUsageRequest;
use App\Models\Associate;
use App\Models\AuditLog;
use App\Models\Benefit;
use App\Models\BenefitUsage;
use App\Services\BenefitService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class BenefitUsageController extends Controller
{
    public function store(BenefitUsageRequest $request, Associate $associate, BenefitService $service): RedirectResponse
    {
        $data = $request->validated();
        $benefit = Benefit::findOrFail($data['benefit_id']);

        try {
            $service->register($associate, $benefit, Carbon::parse($data['used_at']), $data['notes'] ?? null, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['benefit_id' => $e->getMessage()])->withInput();
        }

        AuditLog::record('associate.benefit.register', 'associate', (string) $associate->id, 'success', [
            'benefit_id' => $benefit->id,
        ]);

        return back()->with('success', 'Uso de beneficio registrado.');
    }

    public function destroy(BenefitUsage $benefitUsage): RedirectResponse
    {
        $associateId = $benefitUsage->associate_id;

        AuditLog::record('associate.benefit.delete', 'associate', (string) $associateId, 'success', [
            'benefit_usage_id' => $benefitUsage->id,
        ]);

        $benefitUsage->delete();

        return back()->with('success', 'Registro de uso eliminado.');
    }
}
