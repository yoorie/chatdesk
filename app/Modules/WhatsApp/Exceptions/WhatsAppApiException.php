<?php

namespace App\Modules\WhatsApp\Exceptions;

use Exception;

class WhatsAppApiException extends Exception
{
    protected array $responseBody;

    public function __construct(string $message, array $responseBody = [], int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->responseBody = $responseBody;
    }

    public function getResponseBody(): array
    {
        return $this->responseBody;
    }
}
