# Agent Context: php-server-jwt-auth

## Project Overview

**Package**: `pixartprinting/php-server-jwt-auth`
**Type**: PHP Composer library
**Purpose**: Server-side JWT verification for Cimpress and Auth0 tokens. Validates JWTs against remote JWKS endpoints with PSR-6 cache support.
**Namespace**: `CimpressJwtAuth\`
**PHP Requirement**: >= 8.1
**Repository**: https://github.com/pixartprinting/php-server-jwt-auth

---

## Directory Structure

```
php-server-jwt-auth/
├── src/
│   ├── Auth/
│   │   ├── Configuration.php     # Config DTO: JWKS URI, cache, TTL, allowed issuers
│   │   └── JwtVerifier.php       # Main class: decodes & validates JWT tokens
│   └── Exceptions/
│       └── JwtException.php      # Custom exception with HTTP codes and Laravel render support
├── examples/
│   └── example.php               # Full usage demonstration (ApcuAdapter cache)
├── tests/                        # PHPUnit test directory (expected but currently empty/missing)
├── composer.json
├── phpunit.xml                   # PHPUnit 9.x config; bootstrap=vendor/autoload.php
└── run_unit_tests.sh             # Runs: ./vendor/phpunit/phpunit/phpunit --testsuite Unit
```

---

## Core Classes

### `CimpressJwtAuth\Auth\Configuration`

Immutable-style config object. All properties have getters and fluent setters.

| Property              | Type                      | Default  | Description                                           |
| --------------------- | ------------------------- | -------- | ----------------------------------------------------- |
| `$jwksUri`            | `string`                  | required | URL to the JWKS endpoint                              |
| `$jwksCache`          | `?CacheItemPoolInterface` | `null`   | PSR-6 cache pool (required in production)             |
| `$jwksExpiresAfter`   | `int`                     | `86400`  | JWKS cache TTL in seconds (default 24h)               |
| `$allowedAuthIssuers` | `array`                   | `[]`     | Allowed `iss` claim values; empty = skip issuer check |

---

### `CimpressJwtAuth\Auth\JwtVerifier`

Main entry point. Stateful — call `decode()` first, then `getHeaders()` / `getPayload()`.

| Method                  | Returns       | Description                                                  |
| ----------------------- | ------------- | ------------------------------------------------------------ |
| `decode(string $token)` | `JwtVerifier` | Validates JWT via JWKS; throws `JwtException` on any failure |
| `getHeaders(): ?array`  | `array\|null` | Decoded JWT headers (set after `decode()`)                   |
| `getPayload(): ?array`  | `array\|null` | Decoded JWT payload/claims (set after `decode()`)            |

**Internals**: Uses `Firebase\JWT\CachedKeySet` with a `GuzzleHttp\Client` + `GuzzleHttp\Psr7\HttpFactory` to fetch and cache JWKS keys. Rate limiting (10 RPS) is enabled on invalid key lookups.

**Issuer validation**: After decode, if `$allowedAuthIssuers` is non-empty, the `iss` claim in the payload must match one of the allowed values, otherwise a 401 `JwtException` is thrown.

---

### `CimpressJwtAuth\Exceptions\JwtException`

Extends `\Exception`. Carries structured error data and a reference back to the `JwtVerifier` instance.

| Method                    | Returns       | Description                                                                            |
| ------------------------- | ------------- | -------------------------------------------------------------------------------------- |
| `getCode()`               | `int`         | HTTP-compatible status code (401 or 500)                                               |
| `getMessage()`            | `string`      | Short human-readable error message                                                     |
| `getErrors()`             | `array`       | Detailed internal error messages array (do not expose)                                 |
| `getVerifier()`           | `JwtVerifier` | The verifier instance (may contain partial headers/payload on token expiry/nbf errors) |
| `render($request = null)` | `array`       | Laravel-compatible JSON response array                                                 |
| `getPrevious()`           | `\Throwable`  | Underlying `firebase/php-jwt` exception if applicable                                  |

**HTTP Code logic**: If the passed `$code` is not a valid HTTP status (2xx–5xx), it falls back to the previous exception's code, or 500.

---

## Exception Mapping (`JwtVerifier::decode`)

| Caught Exception            | JwtException Code  | Message                                           |
| --------------------------- | ------------------ | ------------------------------------------------- |
| Empty token string          | 401                | "Unauthorised" / "Empty token passed"             |
| `InvalidArgumentException`  | 401                | "Invalid token passed"                            |
| `SignatureInvalidException` | 401                | "Provided JWT signature verification failed"      |
| `BeforeValidException`      | 401                | "nbf/iat claim violation" (payload partially set) |
| `ExpiredException`          | 401                | "exp claim violation" (payload partially set)     |
| `DomainException`           | 500                | "Domain error"                                    |
| `UnexpectedValueException`  | 500                | "Unexpected value error"                          |
| `\LogicException`           | (silently ignored) | Environmental/malformed key errors                |
| `\Throwable`                | 500                | "Unexpected server error"                         |

---

## Dependencies

### Production

| Package             | Version | Purpose                                  |
| ------------------- | ------- | ---------------------------------------- |
| `firebase/php-jwt`  | ^6.8    | JWT decode, signature verification, JWKS |
| `guzzlehttp/guzzle` | ^7.7.0  | HTTP client for fetching JWKS            |
| `psr/cache`         | ^2.0.0  | PSR-6 cache interface                    |

### Development

| Package           | Version | Purpose                                                    |
| ----------------- | ------- | ---------------------------------------------------------- |
| `phpunit/phpunit` | ^9.0    | Unit testing                                               |
| `symfony/cache`   | ^6.4    | Cache adapters for tests (e.g., ApcuAdapter, ArrayAdapter) |

---

## Development Setup

```bash
# Install dependencies
composer install

# Run unit tests
bash run_unit_tests.sh
# or directly:
./vendor/phpunit/phpunit/phpunit --testsuite Unit
```

---

## Testing

- **Framework**: PHPUnit 9.x
- **Test directory**: `./tests/` (files must match `*Test.php`)
- **Namespace**: `CimpressJwtAuth\Tests\` (PSR-4 autoloaded from `tests/`)
- **Bootstrap**: `vendor/autoload.php`
- **Coverage**: Configured for `./src` directory
- **Env vars set by phpunit.xml**: `APP_ENV=testing`, `CACHE_DRIVER=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`

> **Note**: The `tests/` directory does not yet exist. Creating test files here will be auto-discovered by PHPUnit.

---

## Known Issues / Gaps

- `\LogicException` is caught silently in `JwtVerifier::decode()` with no error propagation — this may hide malformed JWT key issues.
- No mock/test infrastructure exists yet (`tests/` directory is absent).
- `$jwksExpiresAfter` is stored in `Configuration` but not passed to `CachedKeySet` constructor (the `null` argument is used instead, meaning JWKS cache TTL is not enforced by this library — it relies on the cache pool's default TTL).
- `composer.lock` is gitignored, so `composer install` may produce different dependency versions across environments.

---

## Common Future Tasks

- Add unit tests under `tests/` using PHPUnit mocks for `GuzzleHttp\Client` and PSR-6 cache
- Fix `$jwksExpiresAfter` not being passed to `CachedKeySet`
- Fix silent swallow of `\LogicException` in `JwtVerifier::decode()`
- Add support for additional token validation claims (audience, subject)
- Consider adding a `verify(string $token): bool` convenience method
- CI/CD pipeline setup (GitHub Actions for PHP matrix testing)
- Publish to Packagist if public distribution is intended
