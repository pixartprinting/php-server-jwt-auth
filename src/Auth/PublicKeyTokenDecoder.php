<?php

namespace CimpressJwtAuth\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class PublicKeyTokenDecoder
{
    /**
     * Decode a JWT token using the provided public key
     */
    public function decode(string $token, string $keyId, string $publicKey): \stdClass
    {
        $key = new Key($publicKey, "RS256");
        return JWT::decode($token, [$keyId => $key]);
    }
}
