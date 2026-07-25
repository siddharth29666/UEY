<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $otp) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'UEY Password Reset OTP Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2>UEY Premium Mobility</h2>
                    <p>You requested a password reset for your UEY account.</p>
                    <p>Your 6-digit password reset OTP code is:</p>
                    <div style='font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #0056b3; margin: 20px 0;'>
                        {$this->otp}
                    </div>
                    <p>This code is valid for <strong>10 minutes</strong>.</p>
                    <p style='color: #888; font-size: 12px;'>If you did not request a password reset, please ignore this email or contact support.</p>
                </div>
            ",
        );
    }
}
