<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User   $user,
        public string $code,
        public string $type  // 'login' | 'password_reset'
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->type === 'login'
            ? 'Votre code de vérification — ESL'
            : 'Réinitialisation de mot de passe — ESL';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $logoPath = public_path('esl-logo.png');

        return new Content(
            view: 'emails.otp',
            with: [
                'logoPath'   => $logoPath,
                'logoExists' => is_file($logoPath),
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
