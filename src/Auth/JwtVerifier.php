<?php

namespace CimpressJwtAuth\Auth;

use CimpressJwtAuth\Exceptions\JwtException;
use Firebase\JWT\CachedKeySet;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use DomainException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use InvalidArgumentException;
use UnexpectedValueException;

class JwtVerifier
{
    protected array $headers = [];
    protected array $payload = [];

    /**
     * Constructor for the Token Verifier class.
     *
     */
    public function __construct(
        private readonly Configuration $config
    ) {
    }

    /**
     * @param string $token
     * @return void
     * @throws JwtException
     */
    public function decode(string $token): JwtVerifier
    {
        if (empty($token)) {
            throw new JwtException($this, ["Empty token passed"], "Unauthorised", 401);
        }

        try {
            // The URI for the JWKS you wish to cache the results from
            $jwksUri = $this->config->getJwksUri();

            // Create an HTTP client (can be any PSR-7 compatible HTTP client)
            $httpClient = new Client();

            // Create an HTTP request factory (can be any PSR-17 compatible HTTP request factory)
            $httpFactory = new HttpFactory();

            // Create a cache item pool (can be any PSR-6 compatible cache item pool)
            $cacheItemPool = $this->config->getJwksCache();

            $keySet = new CachedKeySet(
                $jwksUri,
                $httpClient,
                $httpFactory,
                $cacheItemPool,
                null, // $expiresAfter int seconds to set the JWKS to expire
                true  // $rateLimit    true to enable rate limit of 10 RPS on lookup of invalid keys
            );

            $headers = new \stdClass();
            $decoded = JWT::decode($token, $keySet, $headers);

            $this->headers = (array) $headers;
            $this->payload = (array) $decoded;
        } catch (InvalidArgumentException $e) {
            throw new JwtException($this, ["Invalid token passed", $e->getMessage()], "Unauthorised", 401, $e);
        } catch (DomainException $e) {
            throw new JwtException($this, [$e->getMessage()], "Domain error", 500, $e);
        } catch (SignatureInvalidException $e) {
            // provided JWT signature verification failed
            throw new JwtException($this, ["Provided JWT signature verification failed", $e->getMessage()], "Unauthorised", 401, $e);
        } catch (BeforeValidException $e) {
            // provided JWT is trying to be used before "nbf" claim OR
            // provided JWT is trying to be used before "iat" claim.
            $this->payload = (array) $e->getPayload();
            throw new JwtException($this, ["Provided JWT is trying to be used before nbf or iat claim", $e->getMessage()], "Unauthorised", 401, $e);
        } catch (ExpiredException $e) {
            // provided JWT is trying to be used after "exp" claim.
            $this->payload = (array) $e->getPayload();
            throw new JwtException($this, ["Provided JWT is trying to be used after exp claim", $e->getMessage()], "Unauthorised", 401, $e);
        } catch (\LogicException $e) {
            // errors having to do with environmental setup or malformed JWT Keys
        } catch (UnexpectedValueException $e) {
            // provided JWT is malformed OR
            // provided JWT is missing an algorithm / using an unsupported algorithm OR
            // provided JWT algorithm does not match provided key OR
            // provided key ID in key/key-array is empty or invalid.
            throw new JwtException($this, [$e->getMessage()], "Unexpected value error", 500, $e);
        } catch (\Throwable $e) {
            throw new JwtException($this, [$e->getMessage()], "Unexpected server error", 500, $e);
        }

        if (empty($this->payload["iss"]) || !in_array($this->payload["iss"], $this->config->getAllowedAuthIssuers())) {
            throw new JwtException($this, ["Unsupported issuer"], "Unauthorised", 401);
        }
        return $this;
    }

    /**
     * @return array|null
     */
    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    /**
     * @return array|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }
}