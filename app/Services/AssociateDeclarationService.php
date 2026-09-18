<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\AssociateDocument;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates the "Declaración Jurada" as a PDF — unlike the Ficha de
 * Inscripción, this one is explicitly a print-and-sign document (acta
 * follow-up: the user confirmed it should stay marked that way rather
 * than pretend to be fully digital). Firma/huella digital are optional
 * scanned-image uploads; when given, they're embedded in place of the
 * blank boxes, otherwise the PDF is generated with both left blank for
 * a wet signature after printing.
 */
class AssociateDeclarationService
{
    private const ASSOCIATE_FIELDS = [
        'name', 'ruc', 'billing_address', 'billing_district', 'billing_province', 'billing_department',
        'legal_rep_name', 'legal_rep_dni',
    ];

    public function generate(
        Associate $associate,
        array $data,
        ?UploadedFile $signature,
        ?UploadedFile $fingerprint,
        int $userId
    ): AssociateDocument {
        return DB::transaction(function () use ($associate, $data, $signature, $fingerprint, $userId) {
            $associate->update(array_intersect_key($data, array_flip(self::ASSOCIATE_FIELDS)));
            $associate->refresh();

            $membershipLabel = $data['membership_status'] === 'ASPIRANTE' ? 'aspirante' : 'asociado';
            $declarationDate = Carbon::parse($data['declaration_date']);

            $html = view('associates.pdf.declaracion-jurada', [
                'associate' => $associate,
                'declarantName' => $associate->legal_rep_name,
                'declarantDni' => $associate->legal_rep_dni,
                'isPersonaNatural' => $associate->person_type === 'PERSONA NATURAL',
                'membershipLabel' => $membershipLabel,
                'declarationDate' => $declarationDate,
                'signatureDataUri' => $signature ? $this->toDataUri($signature) : null,
                'fingerprintDataUri' => $fingerprint ? $this->toDataUri($fingerprint) : null,
            ])->render();

            $options = new DompdfOptions;
            $options->set('isRemoteEnabled', false);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('a4', 'portrait');
            $dompdf->render();
            $content = $dompdf->output();

            $path = "associates/{$associate->id}/documents/".Str::random(40).'.pdf';
            Storage::disk('public')->put($path, $content);

            return AssociateDocument::create([
                'associate_id' => $associate->id,
                'type' => AssociateDocument::TYPE_DECLARACION_JURADA,
                'original_name' => 'declaracion-jurada-'.$declarationDate->format('Y-m-d').'.pdf',
                'file_path' => $path,
                'size' => strlen($content),
                'uploaded_by' => $userId,
            ]);
        });
    }

    private function toDataUri(UploadedFile $file): string
    {
        $data = base64_encode(file_get_contents($file->getRealPath()));

        return 'data:'.$file->getMimeType().';base64,'.$data;
    }
}
