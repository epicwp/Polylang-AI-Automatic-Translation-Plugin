<?php
declare(strict_types=1);

/**
 * Bootstrap file for early plugin initialization.
 *
 * This file is loaded immediately after the autoloader and before the DI container.
 * Use for:
 * - Early hook registration
 * - Global initialization that can't wait for DI
 * - Infrastructure setup
 */


// Initialize async dispatcher for instant Action Scheduler processing.
if ( class_exists( 'PLLAT\Common\Services\Async_Dispatcher' ) ) {
    \PLLAT\Common\Services\Async_Dispatcher::init();
}
