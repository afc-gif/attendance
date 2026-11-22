<?php

namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public string $activationUrl,
        public int $expiresInDays = 7,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Activate your '.$this->employee->company.' Attendance account');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.employee-invitation');
    }

    public function attachments(): array
    {
        return [];
    }
}
