<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProtestRegularizeRequest;
use App\Http\Requests\ProtestRequest;
use App\Models\Associate;
use App\Models\AuditLog;
use App\Models\Protest;
use App\Services\ProtestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ProtestController extends Controller
{
    public function __construct(private readonly ProtestService $protests) {}

    public function index(Request $request): View
    {
        $type = $request->query('type');
        $channel = $request->query('channel');
        $status = $request->query('status');

        $records = Protest::query()
            ->with(['associate'])
            ->when($type && array_key_exists($type, Protest::TYPES), fn ($q) => $q->where('type', $type))
            ->when($channel && array_key_exists($channel, Protest::CHANNELS), fn ($q) => $q->where('channel', $channel))
            ->when($status && array_key_exists($status, Protest::STATUS_LABELS), fn ($q) => $q->where('status', $status))
            ->orderByDesc('registered_at')
            ->paginate(20)
            ->withQueryString();

        return view('protests.index', [
            'records' => $records,
            'filters' => ['type' => $type, 'channel' => $channel, 'status' => $status],
            'monthly' => $this->protests->monthlyCounts(),
        ]);
    }

    public function create(Request $request): View
    {
        $data = ['associates' => Associate::where('is_active', true)->orderBy('name')->get()];

        return $request->ajax() ? view('protests._form', $data) : view('protests.create', $data);
    }

    public function store(ProtestRequest $request): RedirectResponse
    {
        $record = $this->protests->register($request->validated(), $request->user()->id);

        AuditLog::record('protest.register', 'protest', (string) $record->id, 'success');

        return redirect()->route('protests.show', $record)->with('success', 'Registro creado.');
    }

    public function show(Protest $protest): View
    {
        $protest->load(['associate', 'creator', 'regularizedBy']);

        return view('protests.show', ['protest' => $protest]);
    }

    public function regularize(ProtestRegularizeRequest $request, Protest $protest): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->protests->regularize($protest, $data['notes'] ?? null, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        AuditLog::record('protest.regularize', 'protest', (string) $protest->id, 'success');

        return redirect()->route('protests.show', $protest)->with('success', 'Registro regularizado.');
    }
}
