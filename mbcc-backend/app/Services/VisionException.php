<?php

namespace App\Services;

use RuntimeException;

/** Error de la comparación con IA con un mensaje en español listo para mostrarse y el código HTTP a responder. */
class VisionException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 502)
    {
        parent::__construct($message);
    }
}
