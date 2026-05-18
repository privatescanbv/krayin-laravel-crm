<?php

namespace App\Console\Commands;

use App\Services\Keycloak\KeycloakService;
use App\Support\KeycloakConfig;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestKeycloakConnection extends Command
{
    protected $signature = 'keycloak:test
                            {--repeat=1 : Call the admin-token endpoint N times to surface intermittent failures}';

    protected $description = 'Test Keycloak connectivity and admin token with current env vars';

    public function handle(KeycloakService $keycloak): int
    {
        $this->info('=== Keycloak Connection Test ===');
        $this->line('  Container: '.gethostname());
        $this->line('  Config cache: '.(file_exists(base_path('bootstrap/cache/config.php')) ? '<fg=yellow>CACHED</>' : '<fg=green>not cached</>'));
        $this->newLine();

        // 1. Config dump
        $this->line('<fg=cyan>Config:</>');
        $this->table(['Key', 'Value'], [
            ['KEYCLOAK_BASE_URL_EXTERNAL', KeycloakConfig::externalBaseUrl()],
            ['KEYCLOAK_BASE_URL_INTERNAL', KeycloakConfig::internalBaseUrl()],
            ['KEYCLOAK_REALM',             KeycloakConfig::realm()],
            ['KEYCLOAK_CLIENT_ID',         config('services.keycloak.client_id', '(not set)')],
            ['KEYCLOAK_ADMIN',             config('services.keycloak.admin_username', 'admin')],
            ['KEYCLOAK_ADMIN_PASSWORD',    str_repeat('*', strlen((string) config('services.keycloak.admin_password', ''))).' ('.strlen((string) config('services.keycloak.admin_password', '')).' chars)'],
        ]);

        // 2. Reachability check (internal base URL)
        $internalBase = KeycloakConfig::internalBaseUrl();
        $this->line('<fg=cyan>Reachability: '.$internalBase.'</>');
        try {
            $response = Http::timeout(5)->get($internalBase.'/realms/master');
            if ($response->successful()) {
                $this->line('<fg=green>  ✓ Internal base URL reachable (HTTP '.$response->status().')</>');
            } else {
                $this->line('<fg=yellow>  ! Internal base URL responded HTTP '.$response->status().'</>');
            }
        } catch (Exception $e) {
            $this->line('<fg=red>  ✗ Cannot reach internal base URL: '.$e->getMessage().'</>');
            $this->warn('  → Check KEYCLOAK_BASE_URL_INTERNAL and network/container connectivity');
        }
        $this->newLine();

        // 3. Admin token (optionally repeated to surface intermittent failures)
        $repeat  = max(1, (int) $this->option('repeat'));
        $failed  = 0;
        $tokenUrl = KeycloakConfig::internalUrl('/realms/master/protocol/openid-connect/token');
        $this->line('<fg=cyan>Admin token ('.$repeat.'×): '.$tokenUrl.'</>');

        $lastToken = null;
        for ($i = 1; $i <= $repeat; $i++) {
            $start = microtime(true);
            $token = $this->getRawAdminToken($tokenUrl);
            $ms    = round((microtime(true) - $start) * 1000);

            if ($token) {
                $this->line("  [{$i}/{$repeat}] <fg=green>✓ token obtained</> ({$ms} ms, ".strlen($token).' chars)');
                $lastToken = $token;
            } else {
                $this->line("  [{$i}/{$repeat}] <fg=red>✗ FAILED</> ({$ms} ms)");
                $failed++;
            }
        }

        if ($failed > 0) {
            $this->warn("  → {$failed}/{$repeat} token requests failed.");
            $this->warn('  → Check KEYCLOAK_ADMIN / KEYCLOAK_ADMIN_PASSWORD, Keycloak rate limiting, or network flakiness.');
            $this->newLine();
            if ($failed === $repeat) {
                return self::FAILURE;
            }
        }
        $this->newLine();

        if (! $lastToken) {
            return self::FAILURE;
        }

        // 4. Realm check
        $realm = KeycloakConfig::realm();
        $this->line("<fg=cyan>Realm '{$realm}':</>");
        $exists = $keycloak->realmExists($realm, $lastToken);
        if ($exists) {
            $this->line("<fg=green>  ✓ Realm '{$realm}' exists</>");
        } else {
            $this->line("<fg=red>  ✗ Realm '{$realm}' not found</>");
            $this->warn('  → Check KEYCLOAK_REALM');
        }
        $this->newLine();

        // 5. CRM client check
        $clientId = config('services.keycloak.client_id');
        if ($clientId) {
            $this->line("<fg=cyan>Client '{$clientId}':</>");
            $client = $keycloak->getClientById($clientId, $realm, $lastToken);
            if ($client) {
                $this->line("<fg=green>  ✓ Client '{$clientId}' found (id: ".($client['id'] ?? '?').')</>');
            } else {
                $this->line("<fg=red>  ✗ Client '{$clientId}' not found in realm '{$realm}'</>");
            }
            $this->newLine();
        }

        // 6. Round-trip: list users (simulates what PersonObserver triggers)
        $this->line('<fg=cyan>Round-trip: list users (simulates PersonObserver → PersonKeycloakService):</>');
        try {
            $url      = KeycloakConfig::internalUrl('/admin/realms/'.$realm.'/users?max=1');
            $response = Http::withToken($lastToken)->timeout(5)->get($url);
            if ($response->successful()) {
                $this->line('<fg=green>  ✓ Users endpoint reachable (HTTP '.$response->status().')</>');
            } else {
                $this->line('<fg=red>  ✗ Users endpoint returned HTTP '.$response->status().'</>');
                $this->line('    Body: '.substr($response->body(), 0, 200));
            }
        } catch (Exception $e) {
            $this->line('<fg=red>  ✗ Users endpoint error: '.$e->getMessage().'</>');
        }
        $this->newLine();

        if ($failed === 0) {
            $this->line('<fg=green>All checks passed.</>');
        } else {
            $this->warn('Some token requests failed — intermittent connectivity issue detected.');
            $this->warn('Suggestions:');
            $this->warn('  1. Restart queue workers: php artisan queue:restart');
            $this->warn('  2. Clear config cache:    php artisan config:clear');
            $this->warn('  3. Re-run with --repeat=20 to measure failure rate');
        }

        return self::SUCCESS;
    }

    /**
     * Call the token endpoint directly and return the access_token string, or null on failure.
     * Does not use KeycloakService::getAdminToken() so we get the raw HTTP response for logging.
     */
    private function getRawAdminToken(string $tokenUrl): ?string
    {
        try {
            $response = Http::timeout(5)->asForm()->post($tokenUrl, [
                'grant_type' => 'password',
                'client_id'  => 'admin-cli',
                'username'   => config('services.keycloak.admin_username', 'admin'),
                'password'   => config('services.keycloak.admin_password'),
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            $this->line('    HTTP '.$response->status().': '.substr($response->body(), 0, 300));

            return null;
        } catch (Exception $e) {
            $this->line('    Exception: '.$e->getMessage());

            return null;
        }
    }
}
