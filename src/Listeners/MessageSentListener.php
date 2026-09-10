<?php

namespace Rivet\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Rivet\Data\Repositories\Mailing\SendmailRepository;

class MessageSentListener
{
    /**
     * SendmailRepository
     *
     * @var SendmailRepository
     */
    private $repo;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        $this->repo = new SendmailRepository();
    }

    /**
     * Handle the event.
     *
     * @param MessageSent $event
     *
     * @return void
     */
    public function handle(MessageSent $event): void
    {
        $token = $event->message->getHeaders()->getHeaderBody(
            'x-metadata-sendmail-token'
        );

        if (!is_null($token)) {
            $this->repo->updateWhereToken([ 'is_success' => true ], $token);
        }
    }

    /**
     * Handle a job failure.
     *
     * @param MessageSent $event
     * @param \Throwable  $exception
     *
     * @return void
     */
    public function failed(MessageSent $event, \Throwable $exception): void
    {
        $token = $event->message->getHeaders()->getHeaderBody(
            'x-metadata-sendmail-token'
        );

        if (!is_null($token)) {
            $this->repo->updateWhereToken([ 'is_success' => false ], $token);
        }
    }
}
