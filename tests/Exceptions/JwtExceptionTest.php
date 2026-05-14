<?php

namespace CimpressJwtAuth\Tests\Exceptions;

use CimpressJwtAuth\Auth\JwtVerifier;
use CimpressJwtAuth\Exceptions\JwtException;
use PHPUnit\Framework\TestCase;

class JwtExceptionTest extends TestCase
{
    private JwtVerifier $verifier;

    protected function setUp(): void
    {
        $this->verifier = $this->createMock(JwtVerifier::class);
    }

    public function testCode401IsPreserved(): void
    {
        $e = new JwtException($this->verifier, ['Unauthorised'], 'Unauthorised', 401);
        $this->assertSame(401, $e->getCode());
    }

    public function testCode500IsPreserved(): void
    {
        $e = new JwtException($this->verifier, ['Server error'], 'Internal Server Error', 500);
        $this->assertSame(500, $e->getCode());
    }

    public function testInvalidCodeFallsBackTo500(): void
    {
        $e = new JwtException($this->verifier, ['error'], 'Error', 0);
        $this->assertSame(500, $e->getCode());
    }

    public function testInvalidCodeUsesValidPreviousExceptionCode(): void
    {
        $previous = new \RuntimeException('inner', 403);
        $e = new JwtException($this->verifier, ['error'], 'Forbidden', 0, $previous);
        $this->assertSame(403, $e->getCode());
    }

    public function testInvalidCodeWithInvalidPreviousCodeFallsBackTo500(): void
    {
        $previous = new \RuntimeException('inner', 99);
        $e = new JwtException($this->verifier, ['error'], 'Error', 0, $previous);
        $this->assertSame(500, $e->getCode());
    }

    public function testGetMessage(): void
    {
        $e = new JwtException($this->verifier, [], 'Test Message', 401);
        $this->assertSame('Test Message', $e->getMessage());
    }

    public function testGetErrors(): void
    {
        $errors = ['Detail 1', 'Detail 2'];
        $e = new JwtException($this->verifier, $errors, 'Error', 401);
        $this->assertSame($errors, $e->getErrors());
    }

    public function testGetVerifierReturnsPassedInstance(): void
    {
        $e = new JwtException($this->verifier, [], 'Error', 401);
        $this->assertSame($this->verifier, $e->getVerifier());
    }

    public function testGetPreviousReturnsChainedException(): void
    {
        $previous = new \RuntimeException('cause');
        $e = new JwtException($this->verifier, [], 'Error', 500, $previous);
        $this->assertSame($previous, $e->getPrevious());
    }

    public function testRenderStructure(): void
    {
        $e = new JwtException($this->verifier, ['detail error'], 'Unauthorised', 401);
        $rendered = $e->render();

        $this->assertSame(0, $rendered['success']);
        $this->assertArrayHasKey('error', $rendered);
        $this->assertSame('Unauthorised', $rendered['error']['msg']);
        $this->assertSame(['detail error'], $rendered['error']['detail']);
        $this->assertSame(401, $rendered['error']['code']);
    }

    public function testRenderWithNullRequest(): void
    {
        $e = new JwtException($this->verifier, [], 'Error', 500);
        $rendered = $e->render(null);

        $this->assertIsArray($rendered);
        $this->assertArrayHasKey('success', $rendered);
        $this->assertArrayHasKey('error', $rendered);
    }

    public function testExceptionIsThrowable(): void
    {
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Unauthorised');

        throw new JwtException($this->verifier, [], 'Unauthorised', 401);
    }
}
