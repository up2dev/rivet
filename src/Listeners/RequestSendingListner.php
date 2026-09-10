<?php

namespace Rivet\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Queue\InteractsWithQueue;
use Rivet\Data\Models\Log\Log;

class RequestSendingListner
{
    // /**
    //  * SendmailRepository
    //  *
    //  * @var SendmailRepository
    //  */
    // private $repo;

    // /**
    //  * Create the event listener.
    //  *
    //  * @return void
    //  */
    // public function __construct()
    // {
    //     $this->repo = new Log();
    // }

    /**
     * Handle the event.
     *
     * @param RequestSending $event
     *
     * @return void
     */
    public function handle(RequestSending $event): void
    {
        if (config('logs.is_logged')) {
            $keyed = spl_object_id($event->request->toPsrRequest());
            $log = new Log();
            $log->code = "REQUEST-{$keyed}-SENDING";
            $log->data = $event->request;
            $log->save();
        }
    }
}
