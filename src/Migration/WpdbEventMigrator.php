<?php

namespace RebelCode\Bookings\Storage\Module\Migration;

use Dhii\Event\EventFactoryInterface;
use Psr\EventManager\EventManagerInterface;
use RebelCode\Modular\Events\EventsConsumerTrait;
use RebelCode\Storage\Migration\Sql\AbstractDistributedMigrator;
use RebelCode\Storage\Migration\Sql\MigrationLogTableAwareTrait;
use RuntimeException;
use wpdb;

/**
 * A WPDB implementation of a database migrator that uses events to retrieve the local migrations, and migrates
 * downwards using the migrations saved in the database.
 *
 * @since [*next-version*]
 */
class WpdbEventMigrator extends AbstractDistributedMigrator
{
    /* @since [*next-version*] */
    use MigrationLogTableAwareTrait;

    /* @since [*next-version*] */
    use EventsConsumerTrait;

    /**
     * The WPDB instance.
     *
     * @since [*next-version*]
     *
     * @var wpdb
     */
    protected $wpdb;

    /**
     * Optional string to prefix events with.
     *
     * @since [*next-version*]
     *
     * @var string
     */
    protected $eventPrefix;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param wpdb                  $wpdb         The WPDB instance.
     * @param string                $logTable     The name of the migrations log table, which will be created if
     *                                            necessary.
     * @param EventManagerInterface $eventManager The event manager instance.
     * @param EventFactoryInterface $eventFactory The event factory instance.
     * @param string                $eventPrefix  Optional string to prefix events with.
     */
    public function __construct(
        $wpdb,
        $logTable,
        EventManagerInterface $eventManager,
        EventFactoryInterface $eventFactory,
        $eventPrefix = ''
    ) {
        $this->setMigrationLogTable($logTable);
        $this->_setEventManager($eventManager);
        $this->_setEventFactory($eventFactory);

        $this->wpdb = $wpdb;
        $this->eventPrefix = $eventPrefix;
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    public function migrate($targetVer)
    {
        $currVer = $this->getCurrentVersion();
        $eventData = [
            'version' => $targetVer,
            'current' => $currVer,
        ];

        $this->_trigger($this->eventPrefix . 'before_migration', $eventData);
        $this->_trigger($this->eventPrefix . 'before_migration_' . $currVer, $eventData);

        parent::migrate($targetVer);

        $this->_trigger($this->eventPrefix . 'after_migration_' . $targetVer, $eventData);
        $this->_trigger($this->eventPrefix . 'after_migration_', $eventData);
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    protected function getLocalMigrations($version)
    {
        return $this->_filter($this->eventPrefix, 'migrations', []);
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    protected function onMigrationsError($version, $direction, $migrations, RuntimeException $exception)
    {
        $this->_trigger($this->eventPrefix . 'on_migration_error', [
            'version'    => $version,
            'direction'  => $direction,
            'migrations' => $migrations,
            'exception'  => $exception,
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    protected function runQuery($query)
    {
        $this->wpdb->query($query);

        return $this->wpdb->last_result;
    }
}
