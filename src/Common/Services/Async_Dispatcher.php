<?php
declare(strict_types=1);

namespace PLLAT\Common\Services;

/**
 * Forces immediate async dispatch for specific Action Scheduler actions.
 *
 * Why this exists:
 * Action Scheduler's normal async dispatch happens on the 'shutdown' hook,
 * which is often too late - by then, WP-Cron or concurrent requests may have
 * already claimed and processed the action. This causes 10-50 second delays
 * instead of the expected instant (1-2s) execution.
 *
 * This class hooks into 'action_scheduler_stored_action' (which fires immediately
 * after an action is saved to the database) and forces an async HTTP dispatch
 * for whitelisted hooks, ensuring the async loopback wins the race to claim
 * the action.
 *
 * How it works:
 * 1. Listen to 'action_scheduler_stored_action' hook (fires on DB insert)
 * 2. Check if action is async (NullSchedule) and whitelisted
 * 3. Use reflection to access ActionScheduler's protected async_request property
 * 4. Call maybe_dispatch() to trigger immediate HTTP loopback request
 * 5. Loopback request arrives and claims action before WP-Cron can
 *
 * Trade-offs:
 * - Uses reflection to access protected ActionScheduler internals
 * - Could break if Action Scheduler significantly changes internal structure
 * - Gracefully degrades to normal WP-Cron processing if dispatch fails
 *
 * @since 2.4.0
 */
class Async_Dispatcher {
    /**
     * Hooks that should receive forced async dispatch.
     *
     * @var array<string>
     */
    private const INSTANT_HOOKS = array(
        'pllat_process_all_runs_instant',
        'pllat_process_single_job',
    );

    /**
     * Whether the dispatcher has been initialized.
     *
     * @var bool
     */
    private static bool $initialized = false;

    /**
     * Initialize the async dispatcher.
     *
     * Must be called early in the plugin bootstrap, before Action Scheduler
     * starts processing queues.
     *
     * @return void
     */
    public static function init(): void {
        if ( self::$initialized ) {
            return;
        }

        self::$initialized = true;
        \add_action( 'action_scheduler_stored_action', array( self::class, 'maybe_force_dispatch' ), 5 );
    }

    /**
     * Maybe force async dispatch for the stored action.
     *
     * Hooked to 'action_scheduler_stored_action' which fires immediately
     * after an action is saved to the database.
     *
     * @param int $action_id The ID of the stored action.
     * @return void
     */
    public static function maybe_force_dispatch( int $action_id ): void {
        try {
            // Bail if Action Scheduler not available.
            if ( ! \class_exists( 'ActionScheduler' ) ) {
                \error_log( '[PLLAT Async] ActionScheduler class not found!' );
                return;
            }

            $action = \ActionScheduler::store()->fetch_action( $action_id );

            $schedule = $action->get_schedule();

            // Only process NullSchedule (async) actions.
            if ( ! ( $schedule instanceof \ActionScheduler_NullSchedule ) ) {
                return;
            }

            // Only process whitelisted hooks.
            if ( ! self::should_force_dispatch( $action->get_hook() ) ) {
                return;
            }

            self::force_async_dispatch();

        } catch ( \Exception $e ) {
            \error_log( '[PLLAT Async] Exception: ' . $e->getMessage() );
        }
    }

    /**
     * Check if the given hook should receive forced async dispatch.
     *
     * @param string $hook The action hook name.
     * @return bool
     */
    private static function should_force_dispatch( string $hook ): bool {
        // Check exact match first.
        if ( \in_array( $hook, self::INSTANT_HOOKS, true ) ) {
            return true;
        }

        /**
         * Filter whether to force async dispatch for a hook.
         *
         * @param bool   $should_force Whether to force dispatch.
         * @param string $hook         The hook name.
         */
        return \apply_filters( 'pllat_should_force_async_dispatch', false, $hook );
    }

    /**
     * Force immediate async dispatch via Action Scheduler's async request runner.
     *
     * Uses reflection to access the protected $async_request property on the
     * QueueRunner and directly calls maybe_dispatch() to trigger an HTTP
     * loopback request.
     *
     * @return void
     * @throws \ReflectionException If unable to access async_request property.
     */
    private static function force_async_dispatch(): void {
        $runner = \ActionScheduler::runner();

        if ( ! $runner ) {
            return;
        }

        $reflection = new \ReflectionClass( $runner );

        if ( ! $reflection->hasProperty( 'async_request' ) ) {
            return;
        }

        $property = $reflection->getProperty( 'async_request' );
        $property->setAccessible( true );
        $async_request = $property->getValue( $runner );

        if ( ! $async_request || ! \method_exists( $async_request, 'maybe_dispatch' ) ) {
            return;
        }

        $async_request->maybe_dispatch();
    }
}
