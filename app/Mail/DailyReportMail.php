<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $ownerName,
        public readonly string $date,
        public readonly int $totalTransactions,
        public readonly int $revenue,
        public readonly int $profit,
        public readonly array $voids,
        public readonly array $lowStocks,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Laporan Harian Toko — {$this->date}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.daily-report',
        );
    }
}
