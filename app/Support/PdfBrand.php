<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Foundation\SettingsService;
use Illuminate\Support\Facades\Storage;

final class PdfBrand
{
    /**
     * Build the brand array for PDF views.
     *
     * Priority for logo: explicit $logoPath → uploaded company logo (settings) → public/images/logo.png.
     *
     * @return array{name: string, address: string, logoDataUri: string|null}
     */
    public static function make(?string $logoPath = null): array
    {
        $name    = SettingsService::get('company.name', config('app.company_name', config('app.name')));
        $address = SettingsService::get('company.address', config('app.company_address', ''));

        if ($logoPath === null) {
            $logoPath = SettingsService::get('company.logo_path', '');
        }

        $logoDataUri = null;

        if ($logoPath) {
            $disk = Storage::disk('public');
            if ($disk->exists($logoPath)) {
                $mime        = $disk->mimeType($logoPath) ?: 'image/png';
                $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode($disk->get($logoPath));
            }
        }

        if ($logoDataUri === null) {
            $staticPath = public_path('images/logo.png');
            if (file_exists($staticPath)) {
                $ext  = strtolower(pathinfo($staticPath, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif'         => 'image/gif',
                    'svg'         => 'image/svg+xml',
                    default       => 'image/png',
                };
                $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($staticPath));
            }
        }

        return ['name' => $name, 'address' => $address, 'logoDataUri' => $logoDataUri];
    }
}
