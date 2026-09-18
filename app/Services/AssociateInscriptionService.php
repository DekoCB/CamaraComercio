<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\AssociateDocument;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates the "Ficha de Inscripción" (Ficha de Afiliación) as a PDF
 * from a form that opens pre-filled with the associate's own data. Per
 * the user's explicit decision: editing the form here also updates the
 * associate's real record (these are the same fields, not a separate
 * snapshot) — so this always writes through to `associates` before
 * rendering, rather than rendering from the submitted array directly.
 */
class AssociateInscriptionService
{
    private const ASSOCIATE_FIELDS = [
        'name', 'ruc', 'company', 'activities_started_at',
        'billing_address', 'billing_district', 'billing_province',
        'mailing_address', 'mailing_district',
        'contact_phone', 'email', 'website',
        'public_registry_entry', 'public_registry_title', 'notes',
        'legal_rep_name', 'legal_rep_dni', 'legal_rep_position', 'legal_rep_phone', 'legal_rep_email', 'legal_rep_birthday',
        'cch_rep_name', 'cch_rep_dni', 'cch_rep_position', 'cch_rep_phone', 'cch_rep_email', 'cch_rep_birthday',
        'main_activity', 'complementary_activities', 'ciiu', 'profession',
    ];

    public function generate(Associate $associate, array $data, int $userId): AssociateDocument
    {
        return DB::transaction(function () use ($associate, $data, $userId) {
            $associate->update(array_intersect_key($data, array_flip(self::ASSOCIATE_FIELDS)));

            $associate->executives()->delete();
            foreach ($data['executives'] ?? [] as $row) {
                if (trim((string) ($row['name'] ?? '')) === '') {
                    continue;
                }
                $associate->executives()->create([
                    'name' => $row['name'],
                    'position' => $row['position'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'birthday' => $row['birthday'] ?? null,
                ]);
            }

            $associate->products()->delete();
            foreach ($data['products'] ?? [] as $row) {
                if (trim((string) ($row['description'] ?? '')) === '') {
                    continue;
                }
                $associate->products()->create([
                    'description' => $row['description'],
                    'is_fabrica' => (bool) ($row['is_fabrica'] ?? false),
                    'is_produce' => (bool) ($row['is_produce'] ?? false),
                    'is_comercializa' => (bool) ($row['is_comercializa'] ?? false),
                    'is_importa' => (bool) ($row['is_importa'] ?? false),
                    'is_servicios' => (bool) ($row['is_servicios'] ?? false),
                    'is_exporta' => (bool) ($row['is_exporta'] ?? false),
                ]);
            }

            $associate->refresh()->load(['executives', 'products']);

            $options = new DompdfOptions;
            $options->set('isRemoteEnabled', false);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml(view('associates.pdf.ficha-inscripcion', ['associate' => $associate])->render());
            $dompdf->setPaper('a4', 'portrait');
            $dompdf->render();
            $content = $dompdf->output();

            $path = "associates/{$associate->id}/documents/".Str::random(40).'.pdf';
            Storage::disk('public')->put($path, $content);

            return AssociateDocument::create([
                'associate_id' => $associate->id,
                'type' => AssociateDocument::TYPE_FICHA_INSCRIPCION,
                'original_name' => 'ficha-inscripcion-'.now()->format('Y-m-d').'.pdf',
                'file_path' => $path,
                'size' => strlen($content),
                'uploaded_by' => $userId,
            ]);
        });
    }
}
