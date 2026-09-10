<?php
/**
 * LaravelServiceProvider class file
 *
 * PHP Version 8.1
 *
 * @category Controller
 * @package  Rivet\Providers
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Rivet\Listeners;

/**
 * Registers the package's event listener bindings.
 *
 * @category Service
 * @package  Rivet\Providers
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        MessageSending::class => [ Listeners\MessageSendingListener::class ],
        MessageSent::class => [ Listeners\MessageSentListener::class ],
        RequestSending::class => [ Listeners\RequestSendingListner::class ],
        ResponseReceived::class => [ Listeners\RequestSentListner::class ],
    ];
// protected $listen = [
//     'Illuminate\Http\Client\Events\ConnectionFailed' => [
//         'App\Listeners\LogConnectionFailed',
//     ],
// ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
