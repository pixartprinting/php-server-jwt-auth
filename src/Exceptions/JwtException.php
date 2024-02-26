<?php

namespace CimpressJwtAuth\Exceptions;

use CimpressJwtAuth\Auth\JwtVerifier;

class JwtException extends \Exception
{
    public function __construct(
        private readonly JwtVerifier $verifier,
        protected array $errors,
        protected $message,
        protected $code = 0,
        \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        if (!$this->isHttpCode($code)) {
            $this->code = $previous && $this->isHttpCode($previous->getCode()) ? $previous->getCode() : 500;
        }
    }

    /**
     * Laravel compatible render function to default exception handling
     * @param $request
     * @return array
     */
    public function render($request = null): array
    {
        return [
            'success' => 0,
            'error' => [
                "msg" => $this->getMessage(),
                "errors" => $this->getErrors(),
                "code" => $this->getCode()
            ]
        ];
    }

    private function isHttpCode($code): bool
    {
        $code = intval($code / 100);
        return ($code >= 2 && $code <= 5);
    }

    /**
     * @return JwtVerifier
     */
    public function getVerifier(): JwtVerifier
    {
        return $this->verifier;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}