<?php

require __DIR__ . '/../vendor/autoload.php';

// PHP 8.6 warns when a SessionHandlerInterface class has no create_sid(), and
// Laravel's session handlers do not have it yet. Loaded here, before Laravel
// installs its error handler, the warning is only printed; loaded during a
// request, Laravel turns it into an exception and that request answers 500.
// Remove once Laravel ships the method.
if (PHP_VERSION_ID >= 80600) {
    class_exists(\Illuminate\Session\ArraySessionHandler::class);
}
