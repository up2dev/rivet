<?php
/**
 * ConcreteSendmailService class file - TEST FIXTURE ONLY
 *
 * PHP Version 8.1
 *
 * @category Service
 * @package  Rivet\Tests\Fixtures\Services
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Fixtures\Services;

use Rivet\Services\SendmailService;

/**
 * ConcreteSendmailService
 *
 * SendmailService is abstract by design - meant to be extended by the
 * host app (see its own docblock). This is that extension, for
 * testing purposes only: nothing to add, the abstract class already
 * has everything it needs.
 *
 * @category Service
 * @package  Rivet\Tests\Fixtures\Services
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class ConcreteSendmailService extends SendmailService
{
}
