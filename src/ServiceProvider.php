<?php

namespace RebelCode\Bookings\WordPress\Storage;

use Dhii\Exception\CreateInvalidArgumentExceptionCapableTrait;
use Dhii\I18n\StringTranslatingTrait;
use Dhii\Util\Normalization\NormalizeArrayCapableTrait;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use RebelCode\Bookings\Storage\Module\Migration\WpdbEventMigrator;
use RebelCode\Bookings\WordPress\Storage\Cqrs\BookingsSelectResourceModel;
use RebelCode\Bookings\WordPress\Storage\Cqrs\BookingStatusWpdbSelectResourceModel;
use RebelCode\Bookings\WordPress\Storage\Cqrs\SessionsSelectResourceModel;
use RebelCode\Bookings\WordPress\Storage\Cqrs\SessionsWpdbInsertResourceModel;
use RebelCode\Bookings\WordPress\Storage\Cqrs\UnbookedSessionsWpdbSelectResourceModel;
use RebelCode\Bookings\WordPress\Storage\Handlers\AutoMigrationsHandler;
use RebelCode\Bookings\WordPress\Storage\Handlers\MigrationErrorNoticeHandler;
use RebelCode\Bookings\WordPress\Storage\Manager\BookingsEntityManager;
use RebelCode\Bookings\WordPress\Storage\Manager\ResourcesEntityManager;
use RebelCode\Bookings\WordPress\Storage\Migration\Migrator;
use RebelCode\Expression\EntityFieldTerm;
use RebelCode\Storage\Resource\WordPress\Wpdb\WpdbDeleteResourceModel;
use RebelCode\Storage\Resource\WordPress\Wpdb\WpdbInsertResourceModel;
use RebelCode\Storage\Resource\WordPress\Wpdb\WpdbSelectResourceModel;
use RebelCode\Storage\Resource\WordPress\Wpdb\WpdbUpdateResourceModel;

class ServiceProvider implements ServiceProviderInterface
{
    /* @since [*next-version*] */
    use NormalizeArrayCapableTrait;

    /* @since [*next-version*] */
    use CreateInvalidArgumentExceptionCapableTrait;

    /* @since [*next-version*] */
    use StringTranslatingTrait;

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    public function getFactories()
    {
        return [
            /*==============================================================*
             *   Booking RMs                                                |
             *==============================================================*/

            /*
             * The bookings entity manager.
             *
             * @since [*next-version*]
             */
            'bookings_entity_manager'                      => function (ContainerInterface $c) {
                return new BookingsEntityManager(
                    $c->get('bookings_select_rm'),
                    $c->get('bookings_insert_rm'),
                    $c->get('bookings_update_rm'),
                    $c->get('bookings_delete_rm'),
                    $c->get('booking_resources_insert_rm'),
                    $c->get('booking_resources_delete_rm'),
                    $c->get('sql_order_factory'),
                    $c->get('sql_expression_builder')
                );
            },

            /*
             * The SELECT resource model for bookings.
             *
             * @since [*next-version*]
             */
            'bookings_select_rm'                           => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/bookings/select');

                $joinsCfg = $this->_normalizeArray($config->get('joins'));
                $joinArrays = array_map(function ($key) use ($c) {
                    return $c->get($key);
                }, $joinsCfg);

                $joins = [];
                foreach ($joinArrays as $joinArray) {
                    $joins = array_merge($joins, $this->_normalizeArray($joinArray));
                }

                $fieldColumnMap = [];
                foreach ($config->get('field_column_map') as $_field => $_column) {
                    $fieldColumnMap[$_field] = is_array($_column)
                        ? new EntityFieldTerm($_column[0], $_column[1])
                        : $_column;
                }

                return new BookingsSelectResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $c->get('map_factory'),
                    $this->_normalizeArray($config->get('tables')),
                    $fieldColumnMap,
                    $c->get('config')->get('wp_bookings_db/cqrs/booking_resources/table'),
                    $c->get('sql_expression_builder'),
                    $joins,
                    $c->get('bookings_select_rm_grouping')
                );
            },

            /*
             * The INSERT resource model for bookings.
             *
             * @since [*next-version*]
             */
            'bookings_insert_rm'                           => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/bookings/insert');

                return new WpdbInsertResourceModel(
                    $c->get('wpdb'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map')),
                    $config->get('insert_bulk')
                );
            },

            /*
             * The UPDATE resource model for bookings.
             *
             * @since [*next-version*]
             */
            'bookings_update_rm'                           => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/bookings/update');

                return new WpdbUpdateResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map'))
                );
            },

            /*
             * The DELETE resource model for bookings.
             *
             * @since [*next-version*]
             */
            'bookings_delete_rm'                           => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/bookings/delete');

                return new WpdbDeleteResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map'))
                );
            },

            /**
             * The JOIN condition for bookings and their resources.
             *
             * @since [*next-version*]
             */
            'bookings_select_rm_resources_join'            => function (ContainerInterface $c) {
                $exp = $c->get('sql_expression_builder');
                $bkr = $c->get('config')->get('wp_bookings_db/cqrs/booking_resources/table');

                return [
                    $bkr => $exp->eq(
                        $exp->ef('booking', 'id'),
                        $exp->ef($bkr, 'booking_id')
                    ),
                ];
            },

            /**
             * The grouping for the bookings SELECT RM.
             *
             * @since [*next-version*]
             */
            'bookings_select_rm_grouping'                  => function (ContainerInterface $c) {
                $e = $c->get('sql_expression_builder');

                return [$e->ef('booking', 'id')];
            },

            /*==============================================================*
             *   Resources RMs                                              |
             *==============================================================*/

            /*
             * The resources entity manager.
             *
             * @since [*next-version*]
             */
            'resources_entity_manager'                     => function (ContainerInterface $c) {
                return new ResourcesEntityManager(
                    $c->get('resources_select_rm'),
                    $c->get('resources_insert_rm'),
                    $c->get('resources_update_rm'),
                    $c->get('resources_delete_rm'),
                    $c->get('session_rules_select_rm'),
                    $c->get('session_rules_insert_rm'),
                    $c->get('session_rules_update_rm'),
                    $c->get('session_rules_delete_rm'),
                    $c->get('sql_order_factory'),
                    $c->get('sql_expression_builder')
                );
            },

            /*
             * The SELECT resource model for resources.
             *
             * @since [*next-version*]
             */
            'resources_select_rm'                          => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/resources/select');

                return new WpdbSelectResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $c->get('map_factory'),
                    $this->_normalizeArray($config->get('tables')),
                    $this->_normalizeArray($config->get('field_column_map')),
                    $this->_normalizeArray($config->get('joins'))
                );
            },

            /*
             * The INSERT resource model for resources.
             *
             * @since [*next-version*]
             */
            'resources_insert_rm'                          => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/resources/insert');

                return new WpdbInsertResourceModel(
                    $c->get('wpdb'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map')),
                    $config->get('insert_bulk')
                );
            },

            /*
             * The UPDATE resource model for resources.
             *
             * @since [*next-version*]
             */
            'resources_update_rm'                          => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/resources/update');

                return new WpdbUpdateResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map'))
                );
            },

            /*
             * The DELETE resource model for resources.
             *
             * @since [*next-version*]
             */
            'resources_delete_rm'                          => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/resources/delete');

                return new WpdbDeleteResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map'))
                );
            },

            /*==============================================================*
             *   Booking-Resources RMs                                      |
             *==============================================================*/

            /*
             * The SELECT resource model for booking-resources.
             *
             * @since [*next-version*]
             */
            'booking_resources_select_rm'                  => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/booking_resources/select');

                return new WpdbSelectResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $c->get('map_factory'),
                    $this->_normalizeArray($config->get('tables')),
                    $this->_normalizeArray($config->get('field_column_map')),
                    $this->_normalizeArray($config->get('joins'))
                );
            },

            /*
             * The INSERT resource model for booking-resources.
             *
             * @since [*next-version*]
             */
            'booking_resources_insert_rm'                  => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/booking_resources/insert');

                return new WpdbInsertResourceModel(
                    $c->get('wpdb'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map')),
                    $config->get('insert_bulk')
                );
            },

            /*
             * The UPDATE resource model for booking-resources.
             *
             * @since [*next-version*]
             */
            'booking_resources_update_rm'                  => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/booking_resources/update');

                return new WpdbUpdateResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map'))
                );
            },

            /*
             * The DELETE resource model for booking-resources.
             *
             * @since [*next-version*]
             */
            'booking_resources_delete_rm'                  => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/booking_resources/delete');

                return new WpdbDeleteResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map'))
                );
            },

            /*==============================================================*
             *   Booking Status RMs                                         |
             *==============================================================*/

            /*
             * The SELECT resource model for booking statuses and their counts.
             *
             * @since [*next-version*]
             */
            'booking_status_select_rm'                     => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/bookings/select');

                return new BookingStatusWpdbSelectResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $c->get('map_factory'),
                    $this->_normalizeArray($config->get('tables')),
                    [
                        'status'       => 'status',
                        'status_count' => $c->get('sql_expression_builder')->fn(
                            'count', $c->get('sql_expression_builder')->ef('booking', 'status')
                        ),
                    ],
                    ['status'],
                    []
                );
            },

            /*==============================================================*
             *   Session RMs                                                |
             *==============================================================*/

            /*
             * The SELECT resource model for sessions.
             *
             * @since [*next-version*]
             */
            'sessions_select_rm'                           => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/sessions/select');

                $joinsCfg = $this->_normalizeArray($config->get('joins'));
                $joinArrays = array_map(function ($key) use ($c) {
                    return $c->get($key);
                }, $joinsCfg);

                $joins = [];
                foreach ($joinArrays as $joinArray) {
                    $joins = array_merge($joins, $this->_normalizeArray($joinArray));
                }

                return new SessionsSelectResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $c->get('map_factory'),
                    $this->_normalizeArray($config->get('tables')),
                    $this->_normalizeArray($config->get('field_column_map')),
                    $c->get('sql_expression_builder'),
                    $joins
                );
            },

            /**
             * The JOIN condition for sessions and their resources.
             *
             * @since [*next-version*]
             */
            'sessions_select_rm_resources_join'            => function (ContainerInterface $c) {
                $exp = $c->get('sql_expression_builder');
                $ssr = $c->get('config')->get('wp_bookings_db/cqrs/session_resources/table');

                return [
                    // Join with session_resources table
                    // On session.id = session_resources.session_id
                    $ssr => $exp->eq(
                        $exp->ef('session', 'id'),
                        $exp->ef($ssr, 'session_id')
                    ),
                ];
            },

            /*
             * The SELECT resource model for unbooked sessions.
             *
             * @since [*next-version*]
             */
            'unbooked_sessions_select_rm'                  => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/unbooked_sessions/select');

                $joinsCfg = $this->_normalizeArray($config->get('joins'));
                $joinArrays = array_map(function ($key) use ($c) {
                    return $c->get($key);
                }, $joinsCfg);

                $joins = [];
                foreach ($joinArrays as $joinArray) {
                    $joins = array_merge($joins, $this->_normalizeArray($joinArray));
                }

                return new UnbookedSessionsWpdbSelectResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $c->get('map_factory'),
                    $this->_normalizeArray($config->get('tables')),
                    $this->_normalizeArray($config->get('field_column_map')),
                    $joins,
                    $c->get('wp_unbooked_sessions_condition'),
                    $c->get('wp_unbooked_sessions_grouping_fields'),
                    $c->get('sql_expression_builder')
                );
            },

            /*
             * The condition for the unbooked sessions SELECT resource model.
             *
             * @since [*next-version*]
             */
            'wp_unbooked_sessions_condition'               => function (ContainerInterface $c) {
                $b = $c->get('sql_expression_builder');
                $bt = $c->get('config')->get('wp_bookings_db/cqrs/bookings/table');

                return $b->is(
                    $b->ef($bt, 'id'),
                    $b->lit(null)
                );
            },

            /*
             * The fields to group by for the unbooked sessions SELECT resource model.
             *
             * @since [*next-version*]
             */
            'wp_unbooked_sessions_grouping_fields'         => function (ContainerInterface $c) {
                $e = $c->get('sql_expression_builder');

                return [$e->ef('session', 'id')];
            },

            /*
             * The join conditions for unbooked sessions SELECT resource model.
             *
             * @since [*next-version*]
             */
            'unbooked_sessions_select_join_conditions'     => function (ContainerInterface $c) {
                // Expression builder
                $exp = $c->get('sql_expression_builder');
                // The table names
                $bk = $c->get('config')->get('wp_bookings_db/cqrs/bookings/table');
                $br = $c->get('config')->get('wp_bookings_db/cqrs/booking_resources/table');
                $sn = 'session';
                // Booking start and end fields
                $b_s = $exp->ef($bk, 'start');
                $b_e = $exp->ef($bk, 'end');
                // Session start and end fields
                $s_s = $exp->ef($sn, 'start');
                $s_e = $exp->ef($sn, 'end');

                return [
                    // Join with booking table
                    $bk => $exp->and(
                    // With bookings that overlap
                    // (Booking starts during session period or session starts during booking period)
                        $exp->or(
                            $exp->and($exp->gte($b_s, $s_s), $exp->lt($b_s, $s_e)),
                            $exp->and($exp->gte($s_s, $b_s), $exp->lt($s_s, $b_e))
                        )
                    ),
                    // Join with booking resources table
                    $br => $exp->and(
                    // Booking ID from booking table and booking-resources table are equal
                        $exp->eq(
                            $exp->ef($bk, 'id'),
                            $exp->ef($br, 'booking_id')
                        ),
                        // The booking resource ID is in the session's resource ID list
                        $exp->fn(
                            'FIND_IN_SET',
                            $exp->ef($br, 'resource_id'),
                            $exp->ef($sn, 'resource_ids')
                        )
                    ),
                ];
            },

            /*
             * The INSERT resource model for sessions.
             *
             * @since [*next-version*]
             */
            'sessions_insert_rm'                           => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/sessions/insert');

                return new SessionsWpdbInsertResourceModel(
                    $c->get('wpdb'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map')),
                    $config->get('insert_bulk')
                );
            },

            /*
             * The UPDATE resource model for sessions.
             *
             * @since [*next-version*]
             */
            'sessions_update_rm'                           => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/sessions/update');

                return new WpdbUpdateResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map'))
                );
            },

            /*
             * The DELETE resource model for sessions.
             *
             * @since [*next-version*]
             */
            'sessions_delete_rm'                           => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/sessions/delete');

                return new WpdbDeleteResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map'))
                );
            },

            /*==============================================================*
             *   Session Rules RMs                                          |
             *==============================================================*/

            /*
             * The SELECT resource model for session rules.
             *
             * @since [*next-version*]
             */
            'session_rules_select_rm'                      => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/session_rules/select');

                return new WpdbSelectResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $c->get('map_factory'),
                    $this->_normalizeArray($config->get('tables')),
                    $this->_normalizeArray($config->get('field_column_map')),
                    $this->_normalizeArray($config->get('joins'))
                );
            },

            /*
             * The INSERT resource model for session rules.
             *
             * @since [*next-version*]
             */
            'session_rules_insert_rm'                      => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/session_rules/insert');

                return new WpdbInsertResourceModel(
                    $c->get('wpdb'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map')),
                    $config->get('insert_bulk')
                );
            },

            /*
             * The UPDATE resource model for session rules.
             *
             * @since [*next-version*]
             */
            'session_rules_update_rm'                      => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/session_rules/update');

                return new WpdbUpdateResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map'))
                );
            },

            /*
             * The DELETE resource model for session rules.
             *
             * @since [*next-version*]
             */
            'session_rules_delete_rm'                      => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/cqrs/session_rules/delete');

                return new WpdbDeleteResourceModel(
                    $c->get('wpdb'),
                    $c->get('sql_expression_template'),
                    $config->get('table'),
                    $this->_normalizeArray($config->get('field_column_map'))
                );
            },

            /*==============================================================*
             *   Migration Services                                         |
             *==============================================================*/

            /*
             * The migrator instance.
             *
             * @since [*next-version*]
             */
            'wp_bookings_db_migrator'                         => function (ContainerInterface $c) {
                $config = $c->get('config')->get('wp_bookings_db/migrations');

                return new WpdbEventMigrator(
                    $c->get('wpdb'),
                    $config->get('log_table'),
                    $c->get('event_manager'),
                    $c->get('event_factory'),
                    $config->get('event_prefix')
                );
            },

            /*
             * The auto migrations handlers.
             *
             * @since [*next-version*]
             */
            'wp_bookings_db_auto_migrations_handler'     => function (ContainerInterface $c) {
                return new AutoMigrationsHandler(
                    $c->get('wp_bookings_db_migrator'),
                    $c->get('config')->get('wp_bookings_db/migrations/target_version')
                );
            },

            /*
             * The migration error notice handler.
             *
             * @since [*next-version*]
             */
            'wp_bookings_db_migration_error_notice_handler'   => function (ContainerInterface $c) {
                return new MigrationErrorNoticeHandler(
                    $c->get('event_manager'),
                    $c->get('event_factory')
                );
            },
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    public function getExtensions()
    {
        return [];
    }
}
