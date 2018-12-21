<?php

namespace RebelCode\Bookings\WordPress\Storage;

use Psr\Container\ContainerInterface;
use RebelCode\Modular\Events\EventsConsumerTrait;
use RebelCode\Modular\Module\AbstractModule;

/**
 * The WordPress Bookings CQRS Module.
 *
 * @since [*next-version*]
 */
class Module extends AbstractModule
{
    /* @since [*next-version*] */
    use EventsConsumerTrait;

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    public function run(ContainerInterface $c = null)
    {
        $this->setEventManager($c->get('event_manager'));

        // Handler to auto migrate to the latest DB version
        $this->attach('init', $c->get('wp_bookings_db_auto_migrations_handler'));

        // The migration error notice handler
        $this->attach('wp_bookings_db_on_migration_error', $c->get('wp_bookings_db_migration_error_notice_handler'));
    }
}
