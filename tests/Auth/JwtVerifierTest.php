<?php

namespace CimpressJwtAuth\Tests\Auth;

use CimpressJwtAuth\Auth\Configuration;
use CimpressJwtAuth\Auth\JwtVerifier;
use CimpressJwtAuth\Exceptions\JwtException;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class JwtVerifierTest extends TestCase
{
    private \OpenSSLAsymmetricKey $privateKeyResource;
    private string $privateKeyPem;
    private string $publicKeyPem;
    private string $kid     = 'test-key-2048';
    private string $jwksUri = 'https://test.example.com/.well-known/jwks.json';
    private string $issuer  = 'https://test.example.com/';

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
        $this->publicKeyPem       = $details['key'];
        $this->privateKeyResource = $res;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function buildJwksJson(): string
    {
        $res     = openssl_pkey_get_public($this->publicKeyPem);
        $details = openssl_pkey_get_details($res);

        return json_encode([
            'keys' => [[
                'kty' => 'RSA',
                'kid' => $this->kid,
                'alg' => 'RS256',
                'use' => 'sig',
                'n'   => self::base64UrlEncode($details['rsa']['n']),
                'e'   => self::base64UrlEncode($details['rsa']['e']),
            ]],
        ]);
    }

    private function createVerifier(MockHandler $mockHandler, array $allowedIssuers = []): JwtVerifier
    {
        $config = new Configuration(
            $this->jwksUri,
            new ArrayAdapter(),
            86400,
            $allowedIssuers
        );

        return new JwtVerifier(
            $config,
            new Client(['handler' => HandlerStack::create($mockHandler)]),
            new HttpFactory()
        );
    }

    private function createToken(array $overrides = [], int $ttl = 3600): string
    {
        $payload = array_merge([
            'sub' => '123',
            'iss' => $this->issuer,
            'iat' => time(),
            'exp' => time() + $ttl,
        ], $overrides);

        return JWT::encode($payload, $this->privateKeyPem, 'RS256', $this->kid);
    }

    private function jwksResponse(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], $this->buildJwksJson());
    }

    // -------------------------------------------------------------------------
    // State before decode
    // -------------------------------------------------------------------------

    public function testGetHeadersBeforeDecodeReturnsEmptyArray(): void
    {
        $verifier = $this->createVerifier(new MockHandler([]));
        $this->assertSame([], $verifier->getHeaders());
    }

    public function testGetPayloadBeforeDecodeReturnsEmptyArray(): void
    {
        $verifier = $this->createVerifier(new MockHandler([]));
        $this->assertSame([], $verifier->getPayload());
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function testDecodeValidTokenReturnsVerifierForChaining(): void
    {
        $verifier = $this->createVerifier(new MockHandler([$this->jwksResponse()]));

        $result = $verifier->decode($this->createToken());

        $this->assertSame($verifier, $result);
    }

    public function testDecodeValidTokenPopulatesPayload(): void
    {
        $verifier = $this->createVerifier(new MockHandler([$this->jwksResponse()]));
        $verifier->decode($this->createToken(['sub' => 'user-42']));

        $payload = $verifier->getPayload();
        $this->assertIsArray($payload);
        $this->assertSame('user-42', $payload['sub']);
        $this->assertSame($this->issuer, $payload['iss']);
    }

    public function testDecodeValidTokenPopulatesHeaders(): void
    {
        $verifier = $this->createVerifier(new MockHandler([$this->jwksResponse()]));
        $verifier->decode($this->createToken());

        $headers = $verifier->getHeaders();
        $this->assertIsArray($headers);
        $this->assertSame('RS256', $headers['alg']);
        $this->assertSame($this->kid, $headers['kid']);
    }

    public function testDecodeWithMatchingAllowedIssuerSucceeds(): void
    {
        $verifier = $this->createVerifier(new MockHandler([$this->jwksResponse()]), [$this->issuer]);

        $result = $verifier->decode($this->createToken());
        $this->assertSame($this->issuer, $result->getPayload()['iss']);
    }

    public function testDecodeSkipsIssuerCheckWhenAllowedIssuersIsEmpty(): void
    {
        $verifier = $this->createVerifier(new MockHandler([$this->jwksResponse()]), []);

        // Token with an arbitrary issuer should be accepted when no restriction configured
        $result = $verifier->decode($this->createToken(['iss' => 'https://any-issuer.example.com/']));
        $this->assertSame('123', $result->getPayload()['sub']);
    }

    // -------------------------------------------------------------------------
    // 401 error cases
    // -------------------------------------------------------------------------

    public function testDecodeEmptyTokenThrows401(): void
    {
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(401);

        $verifier = $this->createVerifier(new MockHandler([]));
        $verifier->decode('');
    }

    public function testDecodeExpiredTokenThrows401(): void
    {
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(401);

        $verifier = $this->createVerifier(new MockHandler([$this->jwksResponse()]));
        $verifier->decode($this->createToken([], -3600));
    }

    public function testDecodeTokenWithWrongIssuerThrows401(): void
    {
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(401);

        $verifier = $this->createVerifier(
            new MockHandler([$this->jwksResponse()]), ['https://allowed-issuer.example.com/']
        );

        $verifier->decode($this->createToken(['iss' => 'https://other-issuer.example.com/']));
    }

    public function testDecodeTokenBeforeNbfThrows401(): void
    {
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(401);

        $verifier = $this->createVerifier(new MockHandler([$this->jwksResponse()]));
        $verifier->decode($this->createToken(['nbf' => time() + 3600]));
    }

    public function testDecodeTokenWithTamperedSignatureThrows401(): void
    {
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(401);

        $verifier = $this->createVerifier(new MockHandler([$this->jwksResponse()]));

        $parts    = explode('.', $this->createToken());
        $parts[2] = self::base64UrlEncode(str_repeat('x', 256));

        $verifier->decode(implode('.', $parts));
    }

    // -------------------------------------------------------------------------
    // 5xx error cases
    // -------------------------------------------------------------------------

    public function testDecodeMalformedTokenThrows500(): void
    {
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(500);

        $verifier = $this->createVerifier(new MockHandler([$this->jwksResponse()]));
        $verifier->decode('not.a.valid.jwt.token.at.all');
    }

    // -------------------------------------------------------------------------
    // Exception carries partial state
    // -------------------------------------------------------------------------

    public function testExpiredTokenExceptionCarriesPayloadViaVerifier(): void
    {
        $verifier = $this->createVerifier(new MockHandler([$this->jwksResponse()]));

        try {
            $verifier->decode($this->createToken(['sub' => 'expired-user'], -3600));
        } catch (JwtException $e) {
            $this->assertSame(401, $e->getCode());

            $payload = $e->getVerifier()->getPayload();
            $this->assertIsArray($payload);
            $this->assertSame('expired-user', $payload['sub']);
        }
    }

    public function testBeforeValidExceptionCarriesPayloadViaVerifier(): void
    {
        $verifier = $this->createVerifier(new MockHandler([$this->jwksResponse()]));

        try {
            $verifier->decode($this->createToken(['sub' => 'nbf-user', 'nbf' => time() + 3600]));
            $this->fail('Expected JwtException was not thrown');
        } catch (JwtException $e) {
            $this->assertSame(401, $e->getCode());

            $payload = $e->getVerifier()->getPayload();
            $this->assertIsArray($payload);
            $this->assertSame('nbf-user', $payload['sub']);
        }
    }
}
