<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\AssociateDocument;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * acta2.txt [11:39]: "subir documentación física escaneada... generando
 * un PDF digitalizado". A browser can't drive a real scanner, so this
 * treats a photo of the document (from a phone or a flatbed scanner's own
 * app) the same as an already-digital PDF: both end up stored as a single
 * PDF file, so what associates.show links to is always one document type.
 */
class AssociateDocumentService
{
    public function upload(Associate $associate, UploadedFile $file, string $type, int $uploadedBy): AssociateDocument
    {
        $directory = "associates/{$associate->id}/documents";
        $isPdf = strtolower($file->getClientOriginalExtension()) === 'pdf' || $file->getMimeType() === 'application/pdf';

        if ($isPdf) {
            $path = $file->store($directory, 'public');
            $size = (int) $file->getSize();
        } else {
            [$path, $size] = $this->imageToPdf($file, $directory);
        }

        return AssociateDocument::create([
            'associate_id' => $associate->id,
            'type' => $type,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'size' => $size,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public function delete(AssociateDocument $document): void
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();
    }

    /** @return array{0: string, 1: int} [stored path, byte size] */
    private function imageToPdf(UploadedFile $file, string $directory): array
    {
        $options = new DompdfOptions;
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $imageData = base64_encode(file_get_contents($file->getRealPath()));
        $dompdf->loadHtml(
            '<html><body style="margin:0;padding:0;">'
            .'<img src="data:'.$file->getMimeType().';base64,'.$imageData.'" style="max-width:100%;">'
            .'</body></html>'
        );
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();
        $content = $dompdf->output();

        $path = $directory.'/'.Str::random(40).'.pdf';
        Storage::disk('public')->put($path, $content);

        return [$path, strlen($content)];
    }
}
