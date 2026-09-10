<?php

namespace Rivet\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Rivet\Data\Models\Mailing\Sendmail;

class BaseMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The User instances.
     *
     * @var \Illuminate\Foundation\Auth\User
     */
    protected $user;

    /**
     * The template used for the mail.
     *
     * @var string
     */
    protected $template;

    /**
     * The attributes of the mail.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The token of the mail. Used to identify it during the send process.
     *
     * @var string
     */
    protected $sendmail_token = null;

    /**
     * The sender address of the mail.
     *
     * @var string
     */
    protected $from_address = null;

    /**
     * The sender name of the mail.
     *
     * @var string
     */
    protected $from_name = null;

    /**
     * The reciver address of the mail.
     *
     * @var array
     */
    protected $to_addresses = null;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $template, array $attributes = [])
    {
        $this->user = auth()->user();
        $this->template = $template;
        $this->attributes = $attributes;

        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }

        $this->sendmail_token = (
            is_null($this->sendmail_token)?
                Sendmail::tokenize(): $this->sendmail_token
        );

        if (!key_exists('subject', $attributes)) {
            // env() calls outside config/*.php return null once
            // `php artisan config:cache` has run in production.
            // config('app.name') wraps the same env var via Laravel's
            // own config/app.php.
            $this->subject = trans(
                'rivet::mail.subject_default',
                [ 'app' => config('app.name', 'Laravel') ]
            );
            $this->attributes['subject'] = $this->subject;
        }

        if (!key_exists('lproc', $attributes)) {
            $this->attributes['lproc'] = config('logs.process');
        }

        if (!key_exists('from_address', $attributes)) {
            $this->from_address = config('mail.from.address');
            $this->attributes['from_address'] = $this->from_address;
        }

        if (!key_exists('from_name', $attributes)) {
            $this->from_name = config('mail.from.name');
            $this->attributes['from_name'] = $this->from_name;
        }

        $this->from_address = new Address(
            $this->from_address, $this->from_name
        );

        if (!key_exists('to_addresses', $attributes)) {
            $this->to_addresses = [ (
                is_null($this->user)? [
                    'email' => config('mail.from.address'),
                    'name' => config('mail.from.name')
                ]: [
                    'email' => $this->user->email,
                    'name' => $this->user->login
                ]
            ) ];
            $this->attributes['to_addresses'] = $this->to_addresses;
        }

        foreach ($this->to_addresses as $key => $to_address) {
            $this->to_addresses[$key] = new Address(
                $to_address['email'], $to_address['name']
            );
        }

        if (!key_exists('user', $attributes)) {
            $this->attributes['user'] = $this->user;
        }
    }

    /**
     * Get the message envelope.
     *
     * @return Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->from_address,
            to: $this->to_addresses,
            subject: $this->subject,
            metadata: [
                'sendmail-token' => $this->sendmail_token
            ]
        );
    }

    /**
     * Get the message content definition.
     *
     * @return Content
     */
    public function content(): Content
    {
        return new Content(
            view: $this->template,
            with: $this->attributes
        );
    }

    // /**
    //  * Get the attachments for the message.
    //  *
    //  * @return array
    //  */
    // public function attachments()
    // {
    //     return [];
    // }

    // /**
    //  * Build the message.
    //  *
    //  * @return $this
    //  */
    // public function build()
    // {
    //     return $this->subject(
    //         'Vos identifiants'
    //     )->view('mails.users.forgot_pseudo');
    // }
}
