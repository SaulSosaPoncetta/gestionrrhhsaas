<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SolicitudDemoMail extends Mailable
{
    public function __construct(public array $datos)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->datos['email'], $this->datos['nombre'])],
            subject: 'Solicitud de demo — ' . $this->datos['empresa'] . ' (' . $this->datos['empleados'] . ' empleados)',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.solicitud_demo');
    }
}
