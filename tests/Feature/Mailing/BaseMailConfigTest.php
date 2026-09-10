<?php
/**
 * BaseMailConfigTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Mailing
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Mailing;

use Rivet\Tests\TestCase;
use Rivet\Mail\BaseMail;

/**
 * Regression guard for BaseMail reading its from-address/app-name via
 * config() rather than env(): env() calls outside config/*.php return
 * null once `php artisan config:cache` has run in production.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Mailing
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class BaseMailConfigTest extends TestCase
{
    /**
     * @return void
     */
    public function testFromAddressComesFromConfigNotEnvDirectly(): void
    {
        config([
            'mail.from.address' => 'configured@example.test',
            'mail.from.name'    => 'Configured Sender'
        ]);

        $mail = new BaseMail('rivet::emails.default', []);
        $envelope = $mail->envelope();

        $this->assertSame(
            'configured@example.test', $envelope->from->address
        );
        $this->assertSame('Configured Sender', $envelope->from->name);
    }

    /**
     * @return void
     */
    public function testDefaultSubjectUsesConfiguredAppName(): void
    {
        config([
            'app.name'           => 'My Configured App',
            'mail.from.address'  => 'configured@example.test',
            'mail.from.name'     => 'Configured Sender'
        ]);

        $mail = new BaseMail('rivet::emails.default', []);
        $envelope = $mail->envelope();

        $this->assertStringContainsString(
            'My Configured App', $envelope->subject
        );
    }
}
