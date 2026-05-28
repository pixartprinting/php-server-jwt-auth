<?php

namespace CimpressJwtAuth\Tests\Auth;

use CimpressJwtAuth\Auth\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class ConfigurationTest extends TestCase
{
    public function testConstructorWithRequiredParamOnly(): void
    {
        $config = new Configuration('https://example.com/.well-known/jwks.json');

        $this->assertSame('https://example.com/.well-known/jwks.json', $config->getJwksUri());
        $this->assertNull($config->getJwksCache());
        $this->assertSame(86400, $config->getJwksExpiresAfter());
        $this->assertSame([], $config->getAllowedAuthIssuers());
    }

    public function testConstructorWithAllParams(): void
    {
        $cache = new ArrayAdapter();

        $config = new Configuration(
            'https://example.com/.well-known/jwks.json',
            $cache,
            3600,
            ['https://issuer.example.com/']
        );

        $this->assertSame('https://example.com/.well-known/jwks.json', $config->getJwksUri());
        $this->assertSame($cache, $config->getJwksCache());
        $this->assertSame(3600, $config->getJwksExpiresAfter());
        $this->assertSame(['https://issuer.example.com/'], $config->getAllowedAuthIssuers());
    }

    public function testFluentSettersReturnSameInstance(): void
    {
        $config = new Configuration('https://example.com/.well-known/jwks.json');

        $result = $config
            ->setJwksUri('https://new.example.com/jwks.json')
            ->setJwksCache(new ArrayAdapter())
            ->setJwksExpiresAfter(7200)
            ->setAllowedAuthIssuers(['https://issuer.example.com/']);

        $this->assertSame($config, $result);
    }

    public function testSetJwksUri(): void
    {
        $config = new Configuration('https://old.example.com/jwks.json');
        $config->setJwksUri('https://new.example.com/jwks.json');

        $this->assertSame('https://new.example.com/jwks.json', $config->getJwksUri());
    }

    public function testSetJwksCache(): void
    {
        $config = new Configuration('https://example.com/jwks.json');
        $cache  = new ArrayAdapter();
        $config->setJwksCache($cache);

        $this->assertSame($cache, $config->getJwksCache());
    }

    public function testSetJwksCacheAcceptsNull(): void
    {
        $config = new Configuration('https://example.com/jwks.json', new ArrayAdapter());
        $config->setJwksCache(null);

        $this->assertNull($config->getJwksCache());
    }

    public function testSetJwksExpiresAfter(): void
    {
        $config = new Configuration('https://example.com/jwks.json');
        $config->setJwksExpiresAfter(1800);

        $this->assertSame(1800, $config->getJwksExpiresAfter());
    }

    public function testSetAllowedAuthIssuers(): void
    {
        $config   = new Configuration('https://example.com/jwks.json');
        $issuers  = ['https://issuer-a.com/', 'https://issuer-b.com/'];
        $config->setAllowedAuthIssuers($issuers);

        $this->assertSame($issuers, $config->getAllowedAuthIssuers());
    }
}
