<?php

namespace App\Mail;

use App\Enums\DunningReminderType;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public DunningReminderType $reminderType,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->reminderType) {
            DunningReminderType::Overdue => "Invoice #{$this->invoice->id} is overdue",
            DunningReminderType::Reminder1 => "Reminder: Invoice #{$this->invoice->id} payment past due",
            DunningReminderType::Reminder2 => "Final notice: Invoice #{$this->invoice->id} — action required",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reminder',
            with: ['reminderType' => $this->reminderType],
        );
    }
}
