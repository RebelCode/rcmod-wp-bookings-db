<?php

use RebelCode\Bookings\WordPress\Storage\Module;

$key = 'wp_bookings_cqrs';
$deps = ['wp_cqrs'];
$configFile = __DIR__ . '/config.php';
$servicesFile = __DIR__ . '/services.php';

return new Module($key, $deps, $configFile, $servicesFile);
