<?php

namespace CimpressJwtAuth\Tests\Auth;

use CimpressJwtAuth\Auth\PublicKeyTokenDecoder;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

class PublicKeyTokenDecoderTest extends TestCase
{
    private string $privateKeyPem;
    private string $publicKeyPem;
    private string $kid = 'test-key-2048';

    protected function setUp(): void
    {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $privateKeyPem = '';
        openssl_pkey_export($res, $privateKeyPem);
        $this->privateKeyPem = $privateKeyPem;

        $details = openssl_pkey_get_details($res);
        $this->publicKeyPem = $details['key'];
    }

    public function testDecodeReturnsStdClassPayload(): void
    {
        $token = JWT::encode([
            'sub' => 'user-1',
            'email' => 'user@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ], $this->privateKeyPem, 'RS256', $this->kid);

        $decoder = new PublicKeyTokenDecoder();
        $payload = $decoder->decode($token, $this->kid, $this->publicKeyPem);

        $this->assertInstanceOf(\stdClass::class, $payload);
        $this->assertSame('user-1', $payload->sub);
        $this->assertSame('user@example.com', $payload->email);
    }

    public function testDecodeWithWrongKidThrowsException(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        $token = JWT::encode([
            'sub' => 'user-1',
            'iat' => time(),
            'exp' => time() + 3600,
        ], $this->privateKeyPem, 'RS256', $this->kid);

        $decoder = new PublicKeyTokenDecoder();
        $decoder->decode($token, 'wrong-key-id', $this->publicKeyPem);
    }
}
