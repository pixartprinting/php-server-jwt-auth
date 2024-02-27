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
        "<issuer_domain_url_1>",
        "<issuer_domain_url_2>"
    ]
);

$jwtVerifyer = new \CimpressJwtAuth\Auth\JwtVerifier($config);
try {
    $jwtVerifyer->decode($token);
    echo "\nCode: 200";

    //getHeaders() returns associative array containing decoded token headers as key value pair.
    echo "\nHeader: ".json_encode($jwtVerifyer->getHeaders());

    //getPayload() returns associative array containing decoded token payload as key value pair.
    echo "\nPayload: ".json_encode($jwtVerifyer->getPayload());

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

} catch (\Throwable $throwable) {
    echo $throwable->getTraceAsString();
}