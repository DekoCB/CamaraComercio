@extends('layouts.app')

@section('title', 'Generar facturación del mes')

@section('content')
    <x-page-header title="Generar facturación del mes"
        subtitle="Se creará una factura para cada asociado activo que aún no tenga una en el período indicado." />

    <div class="card-surface" style="max-width: 640px">
        <ul class="wizard-steps" id="wizardSteps">
            <li class="is-active" data-step="1"><span class="step-dot">1</span> <span class="step-label">Período</span></li>
            <span class="step-connector"></span>
            <li data-step="2"><span class="step-dot">2</span> <span class="step-label">Monto</span></li>
            <span class="step-connector"></span>
            <li data-step="3"><span class="step-dot">3</span> <span class="step-label">Fecha límite</span></li>
            <span class="step-connector"></span>
            <li data-step="4"><span class="step-dot">4</span> <span class="step-label">Confirmar</span></li>
        </ul>

        <form method="POST" action="{{ route('invoices.store') }}" novalidate id="generateForm"
              data-confirm="Se generarán facturas para {{ $activeAssociatesCount }} asociado(s) activo(s). Esta acción no se puede deshacer."
              data-confirm-title="¿Generar facturas?">
            @csrf

            <div class="wizard-panel is-active" data-panel="1">
                <div class="field">
                    <label class="field-label" for="period">Período a facturar <span class="required">*</span></label>
                    <input type="month" class="form-control @error('period') is-invalid @enderror" id="period" name="period" required
                           value="{{ old('period', $defaultPeriod) }}">
                    @error('period')
                        <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="wizard-panel" data-panel="2">
                <div class="field">
                    <label class="field-label" for="amount">Monto por factura <span class="required">*</span></label>
                    <div class="input-money">
                        <span class="currency-prefix">S/</span>
                        <input type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror"
                               id="amount" name="amount" required value="{{ old('amount') }}">
                    </div>
                    <div class="field-help">Se aplicará el mismo monto a cada asociado activo.</div>
                    @error('amount')
                        <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="wizard-panel" data-panel="3">
                <div class="field">
                    <label class="field-label" for="issue_date">Fecha de emisión <span class="required">*</span></label>
                    <input type="date" class="form-control @error('issue_date') is-invalid @enderror" id="issue_date" name="issue_date" required
                           value="{{ old('issue_date', now()->toDateString()) }}">
                </div>
                <div class="field">
                    <label class="field-label" for="due_date">Fecha límite de pago <span class="required">*</span></label>
                    <input type="date" class="form-control @error('due_date') is-invalid @enderror" id="due_date" name="due_date" required
                           value="{{ old('due_date', now()->addDays(15)->toDateString()) }}">
                    @error('due_date')
                        <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="wizard-panel" data-panel="4">
                <p class="text-secondary" style="font-size: 0.875rem; margin-bottom: var(--space-4);">
                    Revisa los datos antes de generar la facturación.
                </p>
                <dl style="margin: 0 0 var(--space-5);">
                    <div class="wizard-summary-row">
                        <dt>Período</dt>
                        <dd id="summaryPeriod">—</dd>
                    </div>
                    <div class="wizard-summary-row">
                        <dt>Monto por factura</dt>
                        <dd id="summaryAmount">—</dd>
                    </div>
                    <div class="wizard-summary-row">
                        <dt>Fecha límite</dt>
                        <dd id="summaryDueDate">—</dd>
                    </div>
                    <div class="wizard-summary-row">
                        <dt>Asociados activos a facturar</dt>
                        <dd>{{ $activeAssociatesCount }}</dd>
                    </div>
                </dl>
                <div class="form-check field">
                    <input class="form-check-input @error('confirm') is-invalid @enderror" type="checkbox" id="confirm" name="confirm" value="1">
                    <label for="confirm" style="font-size: 0.875rem;">
                        Confirmo que deseo generar la facturación masiva para este período.
                    </label>
                </div>
                @error('confirm')
                    <div class="field-error">{{ icon('alert-triangle', 'icon', 14) }} {{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-between" style="margin-top: var(--space-6);">
                <div>
                    <button type="button" class="btn btn-secondary" id="wizardBack" style="display: none;">{{ icon('chevron-left', 'icon', 15) }} Atrás</button>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('invoices.index') }}" class="btn btn-ghost">Cancelar</a>
                    <button type="button" class="btn btn-primary" id="wizardNext">Continuar</button>
                    <button type="submit" class="btn btn-primary" id="wizardSubmit" style="display: none;">
                        <span class="spinner"></span>
                        <span class="btn-label-idle">{{ icon('check', 'icon', 16) }} Generar facturas</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var totalSteps = 4;
    var current = 1;
    var form = document.getElementById('generateForm');
    var stepsList = document.querySelectorAll('#wizardSteps li');
    var panels = document.querySelectorAll('.wizard-panel');
    var backBtn = document.getElementById('wizardBack');
    var nextBtn = document.getElementById('wizardNext');
    var submitBtn = document.getElementById('wizardSubmit');

    function fieldsForStep(step) {
        return document.querySelector('.wizard-panel[data-panel="' + step + '"]').querySelectorAll('input[required]');
    }

    function validateStep(step) {
        var valid = true;
        fieldsForStep(step).forEach(function (field) {
            if (!field.checkValidity()) {
                field.reportValidity();
                valid = false;
            }
        });
        return valid;
    }

    function render() {
        panels.forEach(function (panel) {
            panel.classList.toggle('is-active', parseInt(panel.dataset.panel, 10) === current);
        });
        stepsList.forEach(function (li) {
            var step = parseInt(li.dataset.step, 10);
            li.classList.toggle('is-active', step === current);
            li.classList.toggle('is-done', step < current);
        });
        backBtn.style.display = current === 1 ? 'none' : 'inline-flex';
        nextBtn.style.display = current === totalSteps ? 'none' : 'inline-flex';
        submitBtn.style.display = current === totalSteps ? 'inline-flex' : 'none';

        if (current === totalSteps) {
            updateSummary();
        }
    }

    function updateSummary() {
        var period = document.getElementById('period').value;
        var amount = parseFloat(document.getElementById('amount').value || '0');
        var dueDate = document.getElementById('due_date').value;

        document.getElementById('summaryPeriod').textContent = period || '—';
        document.getElementById('summaryAmount').textContent = isNaN(amount) ? '—' : 'S/ ' + amount.toLocaleString('es-PE', { minimumFractionDigits: 2 });
        document.getElementById('summaryDueDate').textContent = dueDate ? new Date(dueDate + 'T00:00:00').toLocaleDateString('es-PE') : '—';
    }

    nextBtn.addEventListener('click', function () {
        if (!validateStep(current)) { return; }
        current = Math.min(current + 1, totalSteps);
        render();
    });

    backBtn.addEventListener('click', function () {
        current = Math.max(current - 1, 1);
        render();
    });

    // A hidden submit button still responds to Enter in a text field by
    // default — route Enter through "next" on every step except the
    // last, where submitting is exactly what should happen.
    form.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && current < totalSteps) {
            event.preventDefault();
            nextBtn.click();
        }
    });

    render();
})();
</script>
@endpush
