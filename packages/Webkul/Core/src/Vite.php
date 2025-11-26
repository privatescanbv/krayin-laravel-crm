<?php

namespace Webkul\Core;

use Illuminate\Support\Facades\Vite as BaseVite;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Webkul\Core\Exceptions\ViterNotFound;

/**
 * Webkul Vite wrapper — FIXED VERSION
 *
 * Lost op:
 * - verkeerde hotfile locatie
 * - 0.0.0.0 host
 * - verkeerde asset paden
 * - Admin Vite en CRM Vite tegelijk draaien
 * - HTTPS host correct vervangen
 */
class Vite
{
    /**
     * Normalize path (for hotfile + manifests)
     */
    protected function hotFilePath(array $cfg): string
    {
        $path = $cfg['hot_file'];

        // Als het pad al een absoluut pad is (storage_path() of base_path()), gebruik het direct
        if (str_starts_with($path, '/') || str_starts_with($path, base_path())) {
            return $path;
        }

        // Als het pad onder public/ zit, maar hotfile in storage/framework hoort:
        if (str_contains($path, 'public/')) {
            return base_path('storage/framework/' . basename($path));
        }

        // Als het pad relatief is, maak het absoluut
        if (!str_starts_with($path, base_path())) {
            return base_path($path);
        }

        return $path;
    }

    /**
     * Override the Laravel Vite hot URL
     */
    protected function fixHotUrl(string $hotUrl, array $cfg): string
    {
        // Default laravel = http://0.0.0.0:5173
        if (str_contains($hotUrl, '0.0.0.0')) {

            $host = env('VITE_HMR_HOST', 'localhost');
            $port = env('VITE_PORT', '5173');

            // Detect port based on hot file name
            $hotFileName = basename($cfg['hot_file']);
            if (str_contains($hotFileName, 'admin-vite.hot')) {
                $port = env('VITE_ADMIN_PORT', '5174');
            }

            // Force HTTPS
            return "https://{$host}:{$port}";
        }

        return $hotUrl;
    }

    /**
     * Asset resolver
     */
    public function asset(string $filename, string $namespace = 'admin')
    {
        $cfg = config("krayin-vite.viters.$namespace");

        if (!$cfg) {
            throw new ViterNotFound($namespace);
        }

        $hotFile = $this->hotFilePath($cfg);

        $url = trim($filename, '/');
        
        // For admin namespace, assets are relative to the Admin package directory
        // Vite runs from packages/Webkul/Admin, so we need paths relative to that directory
        if ($namespace === 'admin') {
            // Remove the package path prefix if present
            $relative = $url;
            if (str_starts_with($url, 'packages/Webkul/Admin/src/Resources/assets/')) {
                $relative = str_replace('packages/Webkul/Admin/src/Resources/assets/', 'src/Resources/assets/', $url);
            } elseif (!str_starts_with($url, 'src/Resources/assets/')) {
                // Prepend the assets directory if not already present
                $relative = 'src/Resources/assets/' . $url;
            }
        } else {
            $relative = trim($cfg['package_assets_directory'], '/') . '/' . $url;
        }

        // Fix devserver host in hot file before using it
        if (File::exists($hotFile)) {
            $raw = File::get($hotFile);
            $fixed = $this->fixHotUrl($raw, $cfg);
            // Temporarily write the fixed URL to the hot file
            File::put($hotFile, $fixed);
        }

        $vite = BaseVite::useHotFile($hotFile)
            ->useBuildDirectory($cfg['build_directory']);

        // In development mode, try to serve assets directly via hot file if not in manifest
        if (File::exists($hotFile)) {
            try {
                return $vite->asset($relative);
            } catch (\Exception $e) {
                // If asset not in manifest, serve directly via hot file URL
                $hotUrl = File::get($hotFile);
                return rtrim($hotUrl, '/') . '/' . ltrim($relative, '/');
            }
        }

        return $vite->asset($relative);
    }

    /**
     * For <link>, <script>, bundled Vite entrypoints
     */
    public function set(mixed $entryPoints, string $namespace = 'admin')
    {
        $cfg = config("krayin-vite.viters.$namespace");

        if (!$cfg) {
            throw new ViterNotFound($namespace);
        }

        $hotFile = $this->hotFilePath($cfg);

        // Fix devserver host in hot file before using it
        if (File::exists($hotFile)) {
            $raw = File::get($hotFile);
            $fixed = $this->fixHotUrl($raw, $cfg);
            // Temporarily write the fixed URL to the hot file
            File::put($hotFile, $fixed);
        }

        $vite = BaseVite::useHotFile($hotFile)
            ->useBuildDirectory($cfg['build_directory']);

        // Laat de HTML ongewijzigd, inclusief @vite/client en HMR‑scripts.
        return $vite->withEntryPoints($entryPoints);
    }
}
