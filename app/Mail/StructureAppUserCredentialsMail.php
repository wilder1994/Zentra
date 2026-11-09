<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\StructureAppUser;
use App\Support\Resident\ResidentAppUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class StructureAppUserCredentialsMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly StructureAppUser $appUser,
        public readonly string $plainPassword,
        public readonly string $memberName,
        public readonly string $clientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu acceso a la app — '.$this->clientName);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.structure-app-user-credentials',
            with: [
                'login' => $this->appUser->login_email,
                'password' => $this->plainPassword,
                'memberName' => $this->memberName,
                'clientName' => $this->clientName,
                'isIosWeb' => $this->appUser->isIosWeb(),
                'webUrl' => ResidentAppUrl::web(),
                'channelLabel' => $this->appUser->channelLabel(),
            ],
        );
    }
}
