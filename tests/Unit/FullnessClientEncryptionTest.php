<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Fullness\FullnessClient;
use App\Support\Fullness\FullnessCipher;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * The Fullness CRM wraps every v1 request/response body with AES-256-CBC as
 * {"data": "<base64>"}. These cover the connector's matching encrypt/decrypt.
 */
class FullnessClientEncryptionTest extends TestCase
{
    public function test_cipher_round_trips(): void
    {
        $plain = json_encode(['email' => 'o@x.co', 'n' => 42]);

        $this->assertSame($plain, FullnessCipher::decrypt(FullnessCipher::encrypt($plain)));
        $this->assertNull(FullnessCipher::decrypt(null));
        $this->assertNull(FullnessCipher::decrypt(''));
    }

    public function test_login_encrypts_the_request_and_decrypts_the_response(): void
    {
        Http::fake([
            '*/api/v1/sanctum/token' => Http::response([
                'data' => FullnessCipher::encrypt((string) json_encode([
                    'token' => '1|abc123',
                    'tenant_associations' => [
                        ['tenant_id' => 't1', 'tenant_name' => 'Biz One', 'access_type' => 'staff', 'role' => 'owner', 'role_id' => 2],
                        ['tenant_id' => 'c1', 'tenant_name' => 'Client Co', 'access_type' => 'client'],
                    ],
                ])),
            ], 200),
        ]);

        $result = app(FullnessClient::class)->login('https://fullness.co.il', 'owner@biz.co', 'pw', 'dev');

        $this->assertSame('1|abc123', $result['token']);
        $this->assertCount(1, $result['tenants']); // only the staff association
        $this->assertSame('t1', $result['tenants'][0]['id']);

        // The request body must be the encrypted envelope, never plain credentials.
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return is_array($body)
                && isset($body['data'])
                && ! isset($body['email'])
                && ! isset($body['password'])
                && FullnessCipher::decrypt($body['data']) !== null;
        });
    }

    public function test_login_surfaces_the_decrypted_error_message(): void
    {
        Http::fake([
            '*/api/v1/sanctum/token' => Http::response([
                'data' => FullnessCipher::encrypt((string) json_encode([
                    'message' => 'The provided credentials are incorrect.',
                ])),
            ], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The provided credentials are incorrect.');

        app(FullnessClient::class)->login('https://fullness.co.il', 'owner@biz.co', 'wrong', 'dev');
    }
}
