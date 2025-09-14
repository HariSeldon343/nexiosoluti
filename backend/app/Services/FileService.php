<?php

namespace App\Services;

use App\Models\File;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;

class FileService
{
    /**
     * Verifica se il tenant ha spazio di archiviazione disponibile
     */
    public function hasStorageSpace(Tenant $tenant, $files): bool
    {
        $totalSize = 0;

        if (is_array($files)) {
            foreach ($files as $file) {
                $totalSize += $file->getSize();
            }
        } else {
            $totalSize = $files->getSize();
        }

        // Calcola spazio utilizzato
        $usedSpace = $this->getTenantStorageUsed($tenant->id);

        // Verifica limite (in bytes)
        $limit = $tenant->storage_limit * 1024 * 1024 * 1024; // Converti GB in bytes

        return ($usedSpace + $totalSize) <= $limit;
    }

    /**
     * Calcola lo spazio utilizzato dal tenant
     */
    public function getTenantStorageUsed(int $tenantId): int
    {
        return File::where('tenant_id', $tenantId)
            ->where('is_folder', false)
            ->sum('size') ?? 0;
    }

    /**
     * Verifica se il file può essere visualizzato in anteprima
     */
    public function canPreview(string $extension): bool
    {
        $previewableExtensions = [
            // Immagini
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp',
            // Documenti
            'pdf', 'txt', 'md',
            // Video (con limitazioni)
            'mp4', 'webm',
            // Audio
            'mp3', 'wav', 'ogg'
        ];

        return in_array(strtolower($extension), $previewableExtensions);
    }

    /**
     * Genera anteprima del file
     */
    public function generatePreview(File $file): ?array
    {
        if (!$this->canPreview($file->extension)) {
            return null;
        }

        $path = $file->path;

        // Per immagini
        if (in_array($file->extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
            return $this->generateImagePreview($path);
        }

        // Per PDF
        if ($file->extension === 'pdf') {
            return $this->generatePdfPreview($path);
        }

        // Per testo
        if (in_array($file->extension, ['txt', 'md'])) {
            return $this->generateTextPreview($path);
        }

        // Per video/audio restituisce URL diretto
        if (in_array($file->extension, ['mp4', 'webm', 'mp3', 'wav', 'ogg'])) {
            return [
                'content' => Storage::disk('private')->get($path),
                'mime_type' => $file->mime_type
            ];
        }

        return null;
    }

    /**
     * Genera anteprima immagine
     */
    private function generateImagePreview(string $path): array
    {
        try {
            $image = Image::make(Storage::disk('private')->path($path));

            // Ridimensiona mantenendo proporzioni (max 800px)
            $image->resize(800, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Ottimizza qualità
            $image->encode('jpg', 75);

            return [
                'content' => $image->stream()->__toString(),
                'mime_type' => 'image/jpeg'
            ];
        } catch (\Exception $e) {
            return [
                'content' => Storage::disk('private')->get($path),
                'mime_type' => 'image/jpeg'
            ];
        }
    }

    /**
     * Genera anteprima PDF (prima pagina come immagine)
     */
    private function generatePdfPreview(string $path): ?array
    {
        try {
            // Richiede Imagick o simile
            // Per ora restituisce null, implementare con libreria PDF
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Genera anteprima testo
     */
    private function generateTextPreview(string $path): array
    {
        $content = Storage::disk('private')->get($path);

        // Limita a primi 10KB per anteprima
        if (strlen($content) > 10240) {
            $content = substr($content, 0, 10240) . "\n\n[...contenuto troncato...]";
        }

        return [
            'content' => $content,
            'mime_type' => 'text/plain'
        ];
    }

    /**
     * Scansiona file per virus (stub)
     */
    public function scanFile(File $file): bool
    {
        // TODO: Implementare con servizio antivirus (ClamAV, etc.)
        // Per ora restituisce sempre true
        return true;
    }

    /**
     * Genera nome file univoco
     */
    public function generateUniqueFileName(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);

        // Sanitizza nome file
        $baseName = Str::slug($baseName, '_');

        // Aggiungi timestamp per unicità
        $uniqueName = $baseName . '_' . time() . '_' . Str::random(8);

        if ($extension) {
            $uniqueName .= '.' . $extension;
        }

        return $uniqueName;
    }

    /**
     * Ottiene tipo MIME dal file
     */
    public function getMimeType(string $path): string
    {
        $mimeType = Storage::disk('private')->mimeType($path);

        // Fallback per tipi non riconosciuti
        if (!$mimeType) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $mimeType = $this->getMimeTypeFromExtension($extension);
        }

        return $mimeType ?: 'application/octet-stream';
    }

    /**
     * Mappa estensione a MIME type
     */
    private function getMimeTypeFromExtension(string $extension): string
    {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed'
        ];

        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }

    /**
     * Formatta dimensione file in formato leggibile
     */
    public function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
    }

    /**
     * Estrae metadati dal file
     */
    public function extractMetadata(UploadedFile $file): array
    {
        $metadata = [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension()
        ];

        // Per immagini, estrai dimensioni
        if (str_starts_with($file->getMimeType(), 'image/')) {
            try {
                $image = Image::make($file);
                $metadata['dimensions'] = [
                    'width' => $image->width(),
                    'height' => $image->height()
                ];
            } catch (\Exception $e) {
                // Ignora errori
            }
        }

        return $metadata;
    }

    /**
     * Crea thumbnail per immagini
     */
    public function createThumbnail(string $path, int $width = 200, int $height = 200): ?string
    {
        try {
            $image = Image::make(Storage::disk('private')->path($path));

            // Crea thumbnail
            $image->fit($width, $height);

            // Genera nome thumbnail
            $info = pathinfo($path);
            $thumbnailName = $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');
            $thumbnailPath = $info['dirname'] . '/thumbnails/' . $thumbnailName;

            // Salva thumbnail
            Storage::disk('private')->put(
                $thumbnailPath,
                $image->encode('jpg', 75)->__toString()
            );

            return $thumbnailPath;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Comprimi file se possibile
     */
    public function compressFile(string $path): bool
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // Comprimi immagini
        if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
            return $this->compressImage($path);
        }

        // Altri tipi di compressione possono essere aggiunti qui

        return false;
    }

    /**
     * Comprimi immagine
     */
    private function compressImage(string $path): bool
    {
        try {
            $image = Image::make(Storage::disk('private')->path($path));

            // Comprimi mantenendo qualità accettabile
            $image->encode(null, 85);

            // Sovrascrivi file originale
            Storage::disk('private')->put($path, $image->__toString());

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}