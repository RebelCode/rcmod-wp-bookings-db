<?php

return [
    'wp_bookings_db' => [
        'table_prefix' => '${wpdb_prefix}',
        'migrations'   => [
            'target_version' => 3,
            'log_table'      => '${rc_bookings_db/table_prefix}migrations',
            'event_prefix'   => 'rc_bookings',
        ],
        'cqrs'         => [
            // Bookings CQRS RM config
            'bookings'          => [
                'table'            => '${rc_bookings_db/table_prefix}bookings',
                'field_column_map' => $bookingsFieldColumnMap = [
                    'id'          => ['booking', 'id'],
                    'start'       => ['booking', 'start'],
                    'end'         => ['booking', 'end'],
                    'service_id'  => ['booking', 'service_id'],
                    'payment_id'  => ['booking', 'payment_id'],
                    'client_id'   => ['booking', 'client_id'],
                    'client_tz'   => ['booking', 'client_tz'],
                    'admin_notes' => ['booking', 'admin_notes'],
                    'status'      => ['booking', 'status'],
                ],
                'select'           => [
                    'tables'           => [
                        'booking' => '${cqrs/bookings/table}',
                    ],
                    'field_column_map' => $bookingsFieldColumnMap,
                    'joins'            => [
                        'bookings_select_rm_resources_join',
                    ],
                ],
                'insert'           => [
                    'table'            => '${cqrs/bookings/table}',
                    'field_column_map' => [
                        'id'          => 'id',
                        'start'       => 'start',
                        'end'         => 'end',
                        'service_id'  => 'service_id',
                        'payment_id'  => 'payment_id',
                        'client_id'   => 'client_id',
                        'client_tz'   => 'client_tz',
                        'admin_notes' => 'admin_notes',
                        'status'      => 'status',
                    ],
                    'insert_bulk'      => false,
                ],
                'update'           => [
                    'table'            => '${cqrs/bookings/table}',
                    'field_column_map' => $bookingsFieldColumnMap,
                ],
                'delete'           => [
                    'table'            => '${cqrs/bookings/table}',
                    'field_column_map' => $bookingsFieldColumnMap,
                ],
            ],

            // Resources CQRS RM config
            'resources'         => [
                'table'            => '${rc_bookings_db/table_prefix}resources',
                'field_column_map' => $resourcesFcMap = [
                    'id'       => 'id',
                    'type'     => 'type',
                    'name'     => 'name',
                    'data'     => 'data',
                    'timezone' => 'timezone',
                ],
                'select'           => [
                    'tables'           => [
                        'resource' => '${cqrs/resources/table}',
                    ],
                    'field_column_map' => $resourcesFcMap,
                    'joins'            => [],
                ],
                'insert'           => [
                    'table'            => '${cqrs/resources/table}',
                    'field_column_map' => $resourcesFcMap,
                    'insert_bulk'      => false,
                ],
                'update'           => [
                    'table'            => '${cqrs/resources/table}',
                    'field_column_map' => $resourcesFcMap,
                ],
                'delete'           => [
                    'table'            => '${cqrs/resources/table}',
                    'field_column_map' => $resourcesFcMap,
                ],
            ],

            // Booking-Resources CQRS RM config
            'booking_resources' => [
                'table'            => '${rc_bookings_db/table_prefix}booking_resources',
                'field_column_map' => $bookingResourcesFcMap = [
                    'id'          => 'id',
                    'booking_id'  => 'booking_id',
                    'resource_id' => 'resource_id',
                ],
                'select'           => [
                    'tables'           => [
                        'booking_resource' => '${cqrs/booking_resources/table}',
                    ],
                    'field_column_map' => $bookingResourcesFcMap,
                    'joins'            => [],
                ],
                'insert'           => [
                    'table'            => '${cqrs/booking_resources/table}',
                    'field_column_map' => $bookingResourcesFcMap,
                    'insert_bulk'      => true,
                ],
                'update'           => [
                    'table'            => '${cqrs/booking_resources/table}',
                    'field_column_map' => $bookingResourcesFcMap,
                ],
                'delete'           => [
                    'table'            => '${cqrs/booking_resources/table}',
                    'field_column_map' => $bookingResourcesFcMap,
                ],
            ],

            // Sessions CQRS RM config
            'sessions'          => [
                [
                    'table'            => '${rc_bookings_db/table_prefix}sessions',
                    'field_column_map' => $sessionsFieldColumnMap = [
                        'id'           => 'id',
                        'start'        => 'start',
                        'end'          => 'end',
                        'service_id'   => 'service_id',
                        'resource_ids' => 'resource_ids',
                    ],
                    'select'           => [
                        'tables'           => ['session' => '${cqrs/sessions/table}'],
                        'field_column_map' => $sessionsFieldColumnMap,
                        'joins'            => [],
                    ],
                    'insert'           => [
                        'table'            => '${cqrs/sessions/table}',
                        'field_column_map' => $sessionsFieldColumnMap,
                        'insert_bulk'      => false,
                    ],
                    'update'           => [
                        'table'            => '${cqrs/sessions/table}',
                        'field_column_map' => $sessionsFieldColumnMap,
                    ],
                    'delete'           => [
                        'table'            => '${cqrs/sessions/table}',
                        'field_column_map' => $sessionsFieldColumnMap,
                    ],
                ],
            ],

            // Unbooked sessions CQRS RM config
            'unbooked_sessions' => [
                'table'            => '${rc_bookings_db/table_prefix}sessions',
                'field_column_map' => $sessionsFieldColumnMap = [
                    'id'             => ['session', 'id'],
                    'start'          => ['session', 'start'],
                    'end'            => ['session', 'end'],
                    'service_id'     => ['session', 'service_id'],
                    'resource_ids'   => ['session', 'resource_ids'],
                    // Temporary solution for "unknown column" errors in SQL queries
                    'booking_id'     => ['${cqrs/bookings/table}', 'id'],
                    'booking_status' => ['${cqrs/bookings/table}', 'status'],
                ],
                'select'           => [
                    'tables'           => ['session' => '${cqrs/sessions/table}'],
                    'field_column_map' => $sessionsFieldColumnMap,
                    'joins'            => [
                        'unbooked_sessions_select_join_conditions',
                    ],
                ],
            ],

            // Session rules CQRS RM config
            'session_rules'     => [
                'table'            => '${rc_bookings_db/table_prefix}session_rules',
                'field_column_map' => $sessionRulesFieldColumnMap = [
                    'id'                  => 'id',
                    'resource_id'         => 'resource_id',
                    'start'               => 'start',
                    'end'                 => 'end',
                    'all_day'             => 'all_day',
                    'repeat'              => 'repeat',
                    'repeat_period'       => 'repeat_period',
                    'repeat_unit'         => 'repeat_unit',
                    'repeat_until'        => 'repeat_until',
                    'repeat_until_date'   => 'repeat_until_date',
                    'repeat_until_period' => 'repeat_until_period',
                    'repeat_weekly_on'    => 'repeat_weekly_on',
                    'repeat_monthly_on'   => 'repeat_monthly_on',
                    'exclude_dates'       => 'exclude_dates',
                ],
                'select'           => [
                    'tables'           => ['session_rule' => '${cqrs/session_rules/table}'],
                    'field_column_map' => $sessionRulesFieldColumnMap,
                    'joins'            => [],
                ],
                'insert'           => [
                    'table'            => '${cqrs/session_rules/table}',
                    'field_column_map' => $sessionRulesFieldColumnMap,
                    'insert_bulk'      => true,
                ],
                'update'           => [
                    'table'            => '${cqrs/session_rules/table}',
                    'field_column_map' => $sessionRulesFieldColumnMap,
                ],
                'delete'           => [
                    'table'            => '${cqrs/session_rules/table}',
                    'field_column_map' => $sessionRulesFieldColumnMap,
                ],
            ],
        ],
    ],
];
