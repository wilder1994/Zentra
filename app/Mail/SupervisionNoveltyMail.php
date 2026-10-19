<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class SupervisionNoveltyMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<array{label: string, value: string}>  $facts
     * @param  list<array{title: string, rows: list<string>}>  $sections
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $heading,
        public readonly string $companyName,
        public readonly array $facts,
        public readonly ?string $generalNote,
        public readonly array $sections,
        public readonly ?string $sheetUrl,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.supervision-novelty');
    }
}
