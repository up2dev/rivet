<?php
/**
 * SendmailService class file
 *
 * PHP Version 8.1
 *
 * @category Service
 * @package  Rivet\Services
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Services;

use Illuminate\Database\Eloquent\Collection;
use Rivet\Data\Models\Mailing\Sendmail;
use Rivet\Jobs\ProcessSendmail;

/**
 * The dispatcher half of the Sendmail queue: fetches pending emails
 * and enqueues one ProcessSendmail job per email. Meant to be run
 * periodically (e.g. a scheduled command in the host app extending
 * this abstract class), not on the request/response cycle.
 *
 * @category Service
 * @package  Rivet\Services
 * @license  https://opensource.org/licenses/MIT MIT License
 */
abstract class SendmailService
{
    /**
     * The emails we need to send
     *
     * @var Collection
     */
    protected $emails = null;

    /**
     * Retrive the mails to send.
     */
    public function __construct()
    {
        $this->emails = Sendmail::whereNull('sent_at')->orderBy(
            'created_at', 'DESC'
        )->limit(config('sendmail.max_sent'))->get();
    }

    /**
     * Get the emails.
     *
     * @return Collection
     */
    public function getEmails(): Collection
    {
        return $this->emails;
    }

    /**
     * Enqueue the pending emails - each one is sent asynchronously by
     * a queue worker running ProcessSendmail, not synchronously here.
     *
     * @return int The number of emails enqueued
     */
    public function send(): int
    {
        $enqueued = 0;

        foreach ($this->emails as $email) {
            // dispatch() calls the Dispatcher immediately.
            // ProcessSendmail::dispatch() would return a PendingDispatch
            // that only actually dispatches in its own __destruct().
            dispatch(new ProcessSendmail($email));
            $enqueued++;
        }

        return $enqueued;
    }
}
