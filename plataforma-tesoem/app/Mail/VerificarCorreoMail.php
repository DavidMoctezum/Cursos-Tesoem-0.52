<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificarCorreoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $url)
    {
    }

    public function build(): self
    {
        return $this->subject('Confirma tu correo - TESOEM')->html(
            '<p>Gracias por registrarte en la plataforma de capacitación TESOEM.</p>'
            . '<p><a href="' . e($this->url) . '">Confirma tu correo aquí</a></p>'
            . '<p>El enlace expira en 24 horas. Si tú no creaste esta cuenta, ignora este mensaje.</p>'
        );
    }
}