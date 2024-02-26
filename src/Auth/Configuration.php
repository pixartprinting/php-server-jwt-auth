<?php

namespace CimpressJwtAuth\Auth;

use Psr\Cache\CacheItemPoolInterface;

class Configuration
{
    public function __construct(
        private string                  $jwksUri,
        private ?CacheItemPoolInterface $jwksCache = null,
        private int                     $jwksExpiresAfter = 86400,
        private array                   $allowedAuthIssuers = []
    )
    {
    }

    /**
     * @return string
     */
    public function getJwksUri(): string
    {
        return $this->jwksUri;
    }

    /**
     * @param string $jwksUri
     * @return Configuration
     */
    public function setJwksUri(string $jwksUri): Configuration
    {
        $this->jwksUri = $jwksUri;
        return $this;
    }

    /**
     * @return CacheItemPoolInterface|null
     */
    public function getJwksCache(): ?CacheItemPoolInterface
    {
        return $this->jwksCache;
    }

    /**
     * @param CacheItemPoolInterface|null $jwksCache
     * @return Configuration
     */
    public function setJwksCache(?CacheItemPoolInterface $jwksCache): Configuration
    {
        $this->jwksCache = $jwksCache;
        return $this;
    }

    /**
     * @return int
     */
    public function getJwksExpiresAfter(): int
    {
        return $this->jwksExpiresAfter;
    }

    /**
     * @param int $jwksExpiresAfter
     * @return Configuration
     */
    public function setJwksExpiresAfter(int $jwksExpiresAfter): Configuration
    {
        $this->jwksExpiresAfter = $jwksExpiresAfter;
        return $this;
    }

    /**
     * @return array
     */
    public function getAllowedAuthIssuers(): array
    {
        return $this->allowedAuthIssuers;
    }

    /**
     * @param array $allowedAuthIssuers
     * @return Configuration
     */
    public function setAllowedAuthIssuers(array $allowedAuthIssuers): Configuration
    {
        $this->allowedAuthIssuers = $allowedAuthIssuers;
        return $this;
    }
}