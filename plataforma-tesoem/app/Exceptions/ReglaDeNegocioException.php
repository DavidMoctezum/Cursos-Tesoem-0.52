<?php

namespace App\Exceptions;

use DomainException;

class ReglaDeNegocioException extends DomainException
{
    public function __construct(public readonly string $codigo, string $mensaje)
    {
        parent::__construct($mensaje);
    }
}