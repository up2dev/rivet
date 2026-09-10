<?php
/**
 * SendmailQueueTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Mailing
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Mailing;

use Rivet\Tests\TestCase;
use Rivet\Tests\Fixtures\Services\ConcreteSendmailService;
use Rivet\Data\Models\Mailing\Sendmail;
use Rivet\Jobs\ProcessSendmail;
use Rivet\Mail\BaseMail;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

/**
 * Covers the async Sendmail queue: SendmailService as the dispatcher,
 * ProcessSendmail as the worker.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Mailing
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class SendmailQueueTest extends TestCase
{
    /**
     * @return void
     */
    public function testPendingEmailsAreEnqueuedAsJobs(): void
    {
        Bus::fake();

        $pending = $this->_createPendingSendmail();

        $enqueued = (new ConcreteSendmailService())->send();

        $this->assertSame(1, $enqueued);
        Bus::assertDispatched(ProcessSendmail::class, 1);
        Bus::assertDispatched(
            ProcessSendmail::class,
            fn ($job) => $job->mail->id === $pending->id
        );
    }

    /**
     * @return void
     */
    public function testAlreadySentEmailsAreNotEnqueuedAgain(): void
    {
        Bus::fake();

        Sendmail::create([
            'token'      => Sendmail::tokenize(),
            'from'       => 'noreply@example.test',
            'to'         => 'user@example.test',
            'subject'    => 'Already sent',
            'content'    => 'Rendered body',
            'sent_at'    => now(),
            'is_success' => true
        ]);

        $enqueued = (new ConcreteSendmailService())->send();

        $this->assertSame(0, $enqueued);
        Bus::assertNotDispatched(ProcessSendmail::class);
    }

    /**
     * The worker half: sends and marks the row as sent. Uses
     * Mail::fake(), which bypasses MessageSendingListener entirely -
     * this passing confirms Sendmail::send() marks 'sent_at' itself,
     * not via that listener.
     *
     * @return void
     */
    public function testProcessingTheJobSendsTheMailAndMarksTheRowAsSent(): void
    {
        Mail::fake();

        $pending = $this->_createPendingSendmail();

        (new ProcessSendmail($pending))->handle();

        Mail::assertSent(BaseMail::class);
        $this->assertNotNull($pending->fresh()->sent_at);
    }

    /**
     * Create a pending (not yet sent) Sendmail row with a valid
     * template/attributes content shape, as Sendmail::send() expects.
     *
     * @return Sendmail
     */
    private function _createPendingSendmail(): Sendmail
    {
        return Sendmail::create([
            'token'   => Sendmail::tokenize(),
            'from'    => 'noreply@example.test',
            'to'      => 'user@example.test',
            'subject' => 'Pending',
            'content' => [
                'template'   => 'rivet::emails.default',
                'attributes' => []
            ]
        ]);
    }
}
