# PHP Server JWT Auth

PHP library for supporting Cimpress and Auth0 JWT on server side

## Dependencies

* PHP >= 8.1
* ext-json: *,
* firebase/php-jwt: ^6.8
* psr/cache: ^2.0.0
* guzzlehttp/guzzle: ^7.7.0

## Installation via Composer

```json
{
  "repositories": [
    {
      "url": "https://github.com/pixartprinting/php-server-jwt-auth.git",
      "type": "git"
    }
  ],
  "require": {
    "pixartprinting/php-server-jwt-auth": "master"
  }
}
```

## Usage

`CimpressJwtAuth\Auth\JwtVerifier` is the main class which decodes and validate the token and provides header and payload after successfully validating the token.
`JwtVerifier` class provides three public functions
1. `decode(string $token): JwtVerifier`: Takes incoming token validates it and forces issuer check in the token.
2. `getHeaders(): array`: This function returns associative array containing decoded token headers as key value pair. Function `decode(string $token)` should be called before using this function.
3. `getPayload(): array`: This function returns associative array containing decoded token body as key value pair. Function `decode(string $token)` should be called before using this function.

### Create configuration object
Constructor of `CimpressJwtAuth\Auth\JwtVerifier` class takes in `CimpressJwtAuth\Auth\Configuration` object.

Hence to use `JwtVerifier` you need to create first `Configuration` object. Below are parameters needed in the constructor of `Configuration` class. 
1. `string $jwksUri`: Url of the jwks file for verifying the signatures
2. `CacheItemPoolInterface $cache`: Object of cache adaptor class implementing interface `Psr\Cache\CacheItemPoolInterface` for caching jwsk file keys to prevent jwks file content every time.
3. `int $jwksExpiresAfter`: Expiry time of JWKS file cache. Default is 24 hours.
4. `array $allowedAuthIssuers`: Array of issuer strings. If passed token issuer from this list in order pass token validation.

```php
$cache = new \Symfony\Component\Cache\Adapter\ApcuAdapter(

    // a string prefixed to the keys of the items stored in this cache
    $namespace = 'jwks',

    // the default lifetime (in seconds) for cache items that do not define their
    // own lifetime, with a value 0 causing items to be stored indefinitely (i.e.
    // until the APCu memory is cleared)
    $defaultLifetime = 0,

    // when set, all keys prefixed by $namespace can be invalidated by changing
    // this $version string
    $version = null
);

$config = new \CimpressJwtAuth\Auth\Configuration(
    "<jwks_uri>",
    $cache,
    86400,
    [
        "<issuer_domain_url_1>",
        "<issuer_domain_url_2>"
    ]
);
```

### Decode token

```php
$jwtVerifyer = new \CimpressJwtAuth\Auth\JwtVerifier($config);
try {
    $jwtVerifyer->decode($token);
    echo "\nCode: 200";

    //getHeaders() returns associative array containing decoded token headers as key value pair. 
    echo "\nHeader: ".json_encode($jwtVerifyer->getHeaders());

    //getPayload() returns associative array containing decoded token payload as key value pair.
    echo "\nPayload: ".json_encode($jwtVerifyer->getPayload());

} catch (\CimpressJwtAuth\Exceptions\JwtException $exception) {
    var_dump($exception);
}
```

### Catch exceptions
`JwtVerifier:decode()` function throws exception of class `CimpressJwtAuth\Exceptions\JwtException` if fails to validate and decode the token.

```php
try {
    $jwtVerifyer->decode($token);
} catch (\CimpressJwtAuth\Exceptions\JwtException $exception) {
    
    //getCode() return which can be directly used as HTTP status
    echo "\nCode: {$exception->getCode()}";
    
    //getMessage() returns one line message for the exception
    echo "\nMessage: {$exception->getMessage()}";
    
    //getErrors() returns array of messages with details which can be used internally by the application.
    //Avoid exposing these detailed messages in your reposnse
    echo "\nDetail Error Messages: ".print_r($exception->getErrors(), true);;

    // JwtException also has instance of JwtVerifier which can be fetched using getVerifier()
    if ($exception->getVerifier()) {
        echo "\nHeader: ".print_r($exception->getVerifier()->getHeaders(), true);
        echo "\nPayload: ".print_r($exception->getVerifier()->getPayload(), true);
    }

    //You can also get underlying exception from Firebase\JWT\JWT class in JwtException instance
    var_dump($exception->getPrevious());

    //You can also response json using render function of JwtException. Below is example for Laravel
    return response()->json($exception->render(), $exception->getCode());

}
```

You can refer [examples\example.php](examples\example.php) too.