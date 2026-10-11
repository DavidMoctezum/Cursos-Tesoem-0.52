<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RestablecerContrasenaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $url)
    {
    }

    public function build(): self
    {
        return $this->subject('Restablece tu contraseña - TESOEM')->html(
            '<p>Solicitaste restablecer tu contraseña en la plataforma TESOEM.</p>'
            . '<p><a href="' . e($this->url) . '">Elige una nueva contraseña</a></p>'
            . '<p>El enlace expira en 60 minutos. Si tú no lo solicitaste, ignora este mensaje.</p>'
        );
    }
}