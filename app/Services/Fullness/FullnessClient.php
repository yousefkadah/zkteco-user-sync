<?php

declare(strict_types=1);

namespace App\Services\Fullness;

use App\Models\FullnessConnection;
use App\Support\Fullness\FullnessCipher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP client for the Fullness CRM API used by the Connectors feature.
 *
 * - {@see self::login()} exchanges the business owner's email + password for a
 *   Sanctum token and the list of tenants (businesses) they belong to.
 * - {@see self::assignedUsers()} pulls a tenant's attendance device users in the
 *   push-ready shape returned by the CRM's
 *   `GET /api/v1/desktop/attendance/assigned-users` endpoint.
 *
 * The CRM wraps every v1 request/response body with AES-256-CBC as
 * `{"data": "<base64>"}` ({@see FullnessCipher}); we encrypt outgoing bodies and
 * decrypt incoming ones. The tenant context is carried on every authenticated
 * call via the CRM's (historically misnamed) `expiration-time` header.
 */
class FullnessClient
{
    private const TIMEOUT = 30;

    /**
     * Log in and return the token + the staff tenants the owner can manage.
     *
     * @return array{token: string, tenants: list<array<string, mixed>>, owner_email: ?string}
     *
     * @throws RuntimeException on any failure, with a user-facing message.
     */
    public function login(string $baseUrl, string $email, string $password, string $deviceName): array
    {
        $baseUrl = $this->normaliseBaseUrl($baseUrl);

        try {
            $response = Http::acceptJson()
                ->timeout(self::TIMEOUT)
                ->post($baseUrl.'/api/v1/sanctum/token', $this->encryptBody([
                    'email' => $email,
                    'password' => $password,
                    'device_name' => $deviceName,
                ]));
        } catch (ConnectionException) {
            throw new RuntimeException("Could not reach Fullness at {$baseUrl}. Check the address and your internet connection.");
        }

        $body = $this->decodeBody($response);

        if ($response->status() === 429) {
            $retry = (int) $response->header('Retry-After') ?: (int) ($body['retry_after'] ?? 0);
            throw new RuntimeException($retry > 0
                ? "Too many login attempts. Please wait {$retry} seconds and try again."
                : 'Too many login attempts. Please wait a moment and try again.');
        }

        if ($response->failed()) {
            throw new RuntimeException(
                $body['message'] ?? $body['error'] ?? $body['info']
                ?? 'Login failed. Please check your email and password.'
            );
        }

        $token = $body['token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Fullness did not return a valid session token.');
        }

        // Staff associations only — the owner/managers of a business. Client and
        // vendor associations cannot manage attendance devices.
        $tenants = collect($body['tenant_associations'] ?? [])
            ->filter(fn ($t) => is_array($t) && ($t['access_type'] ?? null) === 'staff')
            ->map(fn ($t) => [
                'id' => (string) ($t['tenant_id'] ?? ''),
                'name' => (string) ($t['tenant_name'] ?? $t['tenant_id'] ?? ''),
                'role' => $t['role'] ?? null,
                'role_id' => isset($t['role_id']) ? (int) $t['role_id'] : null,
                'logo_url' => $t['logo_url'] ?? null,
            ])
            ->filter(fn ($t) => $t['id'] !== '')
            ->values()
            ->all();

        return [
            'token' => $token,
            'tenants' => $tenants,
            'owner_email' => $email,
        ];
    }

    /**
     * Fetch the selected tenant's assigned device users.
     *
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException on any failure, with a user-facing message.
     */
    public function assignedUsers(FullnessConnection $connection): array
    {
        $baseUrl = $this->normaliseBaseUrl($connection->base_url);

        try {
            $response = Http::acceptJson()
                ->timeout(self::TIMEOUT)
                ->withToken($connection->token)
                ->withHeaders([
                    'expiration-time' => (string) $connection->tenant_id,
                    'X-Access-Type' => 'staff',
                ])
                ->get($baseUrl.'/api/v1/desktop/attendance/assigned-users', array_filter([
                    'device_id' => $connection->fullness_device_id, // scope to the chosen device
                ]));
        } catch (ConnectionException) {
            throw new RuntimeException("Could not reach Fullness at {$baseUrl}. Check your internet connection.");
        }

        if ($response->status() === 401) {
            throw new RuntimeException('Your Fullness session has expired. Please reconnect.');
        }

        if ($response->status() === 403) {
            throw new RuntimeException('This account does not have permission to manage attendance devices for this business.');
        }

        $body = $this->decodeBody($response);

        if ($response->failed()) {
            throw new RuntimeException(
                $body['info'] ?? $body['message'] ?? 'Failed to fetch users from Fullness.'
            );
        }

        $users = $body['assigned_users'] ?? [];

        return is_array($users) ? array_values($users) : [];
    }

    /**
     * Fetch the selected tenant's attendance devices so the operator can pick
     * which one's assigned users to sync.
     *
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException on any failure, with a user-facing message.
     */
    public function devices(FullnessConnection $connection): array
    {
        $baseUrl = $this->normaliseBaseUrl($connection->base_url);

        try {
            $response = Http::acceptJson()
                ->timeout(self::TIMEOUT)
                ->withToken($connection->token)
                ->withHeaders([
                    'expiration-time' => (string) $connection->tenant_id,
                    'X-Access-Type' => 'staff',
                ])
                ->get($baseUrl.'/api/v1/desktop/attendance/devices');
        } catch (ConnectionException) {
            throw new RuntimeException("Could not reach Fullness at {$baseUrl}. Check your internet connection.");
        }

        if ($response->status() === 401) {
            throw new RuntimeException('Your Fullness session has expired. Please reconnect.');
        }

        if ($response->status() === 403) {
            throw new RuntimeException('This account does not have permission to manage attendance devices for this business.');
        }

        $body = $this->decodeBody($response);

        if ($response->failed()) {
            throw new RuntimeException(
                $body['info'] ?? $body['message'] ?? 'Failed to load devices from Fullness.'
            );
        }

        $devices = $body['devices'] ?? [];

        return is_array($devices) ? array_values($devices) : [];
    }

    /**
     * Wrap a request payload the way the CRM's EncryptDecryptMiddleware expects
     * (`{"data": "<AES base64>"}`). When decryption is disabled server-side the
     * middleware is a no-op, but sending the wrapper is still accepted, so this
     * is safe either way.
     *
     * @param  array<string, mixed>  $payload
     * @return array{data: string}
     */
    private function encryptBody(array $payload): array
    {
        return ['data' => FullnessCipher::encrypt((string) json_encode($payload))];
    }

    /**
     * Decode a CRM response, transparently unwrapping the `{"data": "<AES>"}`
     * envelope when present and falling back to a plain JSON body otherwise.
     *
     * @return array<string, mixed>
     */
    private function decodeBody(Response $response): array
    {
        $json = $response->json();

        if (is_array($json) && isset($json['data']) && is_string($json['data'])) {
            $plaintext = FullnessCipher::decrypt($json['data']);
            $decoded = $plaintext !== null ? json_decode($plaintext, true) : null;

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($json) ? $json : [];
    }

    /**
     * Normalise a user-entered base URL: default to https, strip trailing slash.
     */
    private function normaliseBaseUrl(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);

        if (! preg_match('#^https?://#i', $baseUrl)) {
            $baseUrl = 'https://'.$baseUrl;
        }

        return rtrim($baseUrl, '/');
    }
}
