<?php

namespace Rivet\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Queue\InteractsWithQueue;
use Rivet\Data\Models\Log\Log;

class RequestSentListner
{
    /**
     * Handle the event.
     *
     * @param ResponseReceived $event
     *
     * @return void
     */
    public function handle(ResponseReceived $event): void
    {
        if (config('logs.is_logged')) {
            $keyed = spl_object_id($event->request->toPsrRequest());
            $log = new Log();
            $log->code = "REQUEST-{$keyed}-SENT";
            $log->data = $event->response;
            $log->save();
        }
    }
}
