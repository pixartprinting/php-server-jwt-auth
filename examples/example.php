<?php
$loader = require 'vendor/autoload.php';
$loader->add('AppName', __DIR__.'/../src/');

$token = "<token>";

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
        "issuer_domain_url_1",
        "issuer_domain_url_2"
    ]
);

$jwtVerifyer = new \CimpressJwtAuth\Auth\JwtVerifier($config);
try {
    $jwtVerifyer->decode($token);
    echo "\nCode: 200";
    echo "\nHeader: ".json_encode($jwtVerifyer->getHeaders());
    echo "\nPayload: ".json_encode($jwtVerifyer->getPayload());
} catch (\CimpressJwtAuth\Exceptions\JwtException $throwable) {
    echo "\nCode: {$throwable->getCode()}";
    echo "\nMessage: {$throwable->getMessage()}";
    echo "\nErrors: ".print_r($throwable->getErrors(), true);;
    if ($throwable->getVerifier()) {
        echo "\nHeader: ".print_r($throwable->getVerifier()->getHeaders(), true);
        echo "\nPayload: ".print_r($throwable->getVerifier()->getPayload(), true);
    }
    var_dump($throwable->getPrevious());
} catch (\Throwable $throwable) {
    echo $throwable->getTraceAsString();
}