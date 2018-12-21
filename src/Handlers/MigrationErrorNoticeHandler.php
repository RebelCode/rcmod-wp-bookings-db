<?php

namespace RebelCode\Bookings\WordPress\Storage\Handlers;

use Dhii\Event\EventFactoryInterface;
use Dhii\Invocation\InvocableInterface;
use Exception;
use Psr\EventManager\EventInterface;
use Psr\EventManager\EventManagerInterface;
use RebelCode\Modular\Events\EventsConsumerTrait;

/**
 * The migration failure error notice handler.
 *
 * @since [*next-version*]
 */
class MigrationErrorNoticeHandler implements InvocableInterface
{
    /* @since [*next-version*] */
    use EventsConsumerTrait;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param EventManagerInterface $eventManager The event manager, used to attach WordPress admin notice events.
     * @param EventFactoryInterface $eventFactory The event factory for creating event instances.
     */
    public function __construct(EventManagerInterface $eventManager, EventFactoryInterface $eventFactory)
    {
        $this->setEventManager($eventManager);
        $this->setEventFactory($eventFactory);
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    public function __invoke()
    {
        $event = func_get_arg(0);

        if (!($event instanceof EventInterface)) {
            throw $this->_createInvalidArgumentException(
                $this->__('Argument is not an event instance'), null, null, $event
            );
        }

        $exception = $event->getParam('exception');
        $version = $event->getParam('version');

        if (!($exception instanceof Exception)) {
            return;
        }

        $exceptionMsg = $exception->getMessage();
        $noticeMsg = $this->__('EDD Bookings failed to migrate to database v%d. Reason: %s', [$version, $exceptionMsg]);

        $this->attach('admin_notices', function () use ($noticeMsg) {
            printf('<div class="notice notice-error is-dismissible"><p>%s</p>', $noticeMsg);
        });
    }
}
