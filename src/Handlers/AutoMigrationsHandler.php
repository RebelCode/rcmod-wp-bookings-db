<?php

namespace RebelCode\Bookings\WordPress\Storage\Handlers;

use Dhii\Exception\CreateInvalidArgumentExceptionCapableTrait;
use Dhii\Exception\CreateOutOfRangeExceptionCapableTrait;
use Dhii\I18n\StringTranslatingTrait;
use Dhii\Invocation\InvocableInterface;
use Dhii\Util\Normalization\NormalizeIntCapableTrait;
use Dhii\Util\Normalization\NormalizeStringCapableTrait;
use Dhii\Util\String\StringableInterface as Stringable;
use RebelCode\Storage\Migration\Sql\MigratorInterface;

/**
 * The handler for invoking automatic database migrations.
 *
 * @since [*next-version*]
 */
class AutoMigrationsHandler implements InvocableInterface
{
    /* @since [*next-version*] */
    use NormalizeIntCapableTrait;

    /* @since [*next-version*] */
    use NormalizeStringCapableTrait;

    /* @since [*next-version*] */
    use CreateOutOfRangeExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateInvalidArgumentExceptionCapableTrait;

    /* @since [*next-version*] */
    use StringTranslatingTrait;

    /**
     * The migrator instance.
     *
     * @since [*next-version*]
     *
     * @var MigratorInterface
     */
    protected $migrator;

    /**
     * The DB version to migrate to.
     *
     * @since [*next-version*]
     *
     * @var int|string|Stringable
     */
    protected $targetVersion;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param MigratorInterface     $migrator      The migrator instance.
     * @param Stringable|int|string $targetVersion The target DB version to migrate to.
     */
    public function __construct(
        MigratorInterface $migrator,
        $targetVersion
    ) {
        $this->migrator = $migrator;
        $this->targetVersion = $this->_normalizeInt($targetVersion);
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    public function __invoke()
    {
        $this->migrator->migrate($this->targetVersion);
    }
}
