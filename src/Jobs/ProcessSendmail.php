<?php
/**
 * ProcessSendmail class file
 *
 * PHP Version 8.1
 *
 * @category Job
 * @package  Rivet\Jobs
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Rivet\Data\Models\Mailing\Sendmail;

/**
 * The worker half of the Sendmail queue: sends one pending email,
 * dispatched by SendmailService::send().
 *
 * @category Job
 * @package  Rivet\Jobs
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class ProcessSendmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The pending email to send.
     *
     * @var Sendmail
     */
    public $mail;

    /**
     * Create a new job instance.
     *
     * @param Sendmail $mail The pending email to send
     *
     * @return void
     */
    public function __construct(Sendmail $mail)
    {
        $this->mail = $mail;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->mail->send();
    }
}
