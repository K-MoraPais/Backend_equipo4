<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Token;
use Exception;

class Mailer extends Mailable
{
    use Queueable, SerializesModels;

    public $subject = "Ollas populares";
    public $body = [];


    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($body)
    {
        $this->body = $body;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        try {
            $this->body['authorEmail'];
            return $this->view('emails.donationNotificationView')->with([
                'authorEmail' => $this->body['authorEmail'],
                'donationType' => $this->body['donationType'],
            ]);
        } catch (Exception) {
            try {
                $this->body['expiration'];
                return $this->view('emails.mailView')->with([
                    'token' => $this->body['tokenValue'],
                    'expiration' => $this->body['expiration'],
                ]);
            } catch (Exception) {
            }
        }
    }
}
