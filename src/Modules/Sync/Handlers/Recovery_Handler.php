<?php
/**
 * Recovery_Handler class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Sync
 */

declare(strict_types=1);

namespace PLLAT\Sync\Handlers;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Sync\Constants\Sync_Constants;
use PLLAT\Sync\Services\Health_Service;
use PLLAT\Sync\Services\Recovery_Service;
use PLLAT\Translator\Enums\RunStatus;
use PLLAT\Translator\Repositories\Run_Repository;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

/**
 * Handles periodic recovery of stale jobs and runs.
 *
 * Runs hourly via cron to ensure system health without performing translations.
 * Critical for recovery from crashes, fatal errors, and stuck processes.
 *
 * Context: CTX_CRON - only loads in cron context for performance.
 */
#[Handler(
    tag: 'init',
    priority: 16,
    context: Handler::CTX_CRON | Handler::CTX_ADMIN | Handler::CTX_AJAX | Handler::CTX_CLI,
)]
class Recovery_Handler {
    /**
     * Constructor.
     *
     * @param Recovery_Service $recovery_service Recovery service.
     * @param Run_Repository   $run_repository   Run repository.
     * @param Health_Service   $health_service   Health tracking service.
     */
    public function __construct(
        private Recovery_Service $recovery_service,
        private Run_Repository $run_repository,
        private Health_Service $health_service,
    ) {
        $this->ensure_cron_scheduled();
    }

    /**
     * Recover stale jobs and runs (runs hourly).
     *
     * Critical for system health - prevents jobs from being stuck forever.
     *
     * Process:
     * 1. Find all active runs
     * 2. For each run, recover stale jobs
     * 3. Check if run itself is stale
     * 4. Record health metrics
     *
     * @return void
     */
    #[Action( tag: Sync_Constants::HOOK_RECOVERY_CRON )]
    public function recover_stale_entities(): void {
        $active_runs = $this->find_active_runs();

        foreach ( $active_runs as $run ) {
            $this->recover_run( $run );
        }
    }

    /**
     * Find all active runs (pending or running status).
     *
     * @return array<\PLLAT\Translator\Models\Run> Active runs.
     */
    private function find_active_runs(): array {
        return $this->run_repository->find_by_status( array( 'pending', 'running' ) );
    }

    /**
     * Recover a single run and its jobs.
     *
     * @param \PLLAT\Translator\Models\Run $run The run to recover.
     * @return void
     */
    private function recover_run( $run ): void {
        $run_id = $run->get_id();

        // Step 1: Recover stale jobs.
        $result = $this->recovery_service->recover_stale_jobs_for_run( $run_id );

        if ( $result->has_changes() ) {
            $this->health_service->record_recovery_event( $run_id, $result->to_array() );
        }

        // Step 2: Check if run itself is stale.
        if ( ! $run->is_stale( Sync_Constants::STALE_RUN_TIMEOUT ) ) {
            return;
        }

        $this->handle_stale_run( $run );
    }

    /**
     * Handle a stale run (no heartbeat for 15+ minutes).
     *
     * Strategy:
     * - If no active jobs → mark run as completed
     * - If has active jobs → restart heartbeat to resume processing
     *
     * @param \PLLAT\Translator\Models\Run $run The stale run.
     * @return void
     */
    private function handle_stale_run( $run ): void {
        if ( ! $run->has_non_terminal_jobs() ) {
            $this->complete_stale_run( $run );
            return;
        }

        $this->restart_run_heartbeat( $run );
    }

    /**
     * Complete a stale run that has no more active jobs.
     *
     * @param \PLLAT\Translator\Models\Run $run The run to complete.
     * @return void
     */
    private function complete_stale_run( $run ): void {
        $run->complete();
        $this->run_repository->save( $run );
    }

    /**
     * Restart heartbeat for a stale run that still has active jobs.
     *
     * This allows the run to continue processing.
     *
     * @param \PLLAT\Translator\Models\Run $run The run to restart.
     * @return void
     */
    private function restart_run_heartbeat( $run ): void {
        $run->update_heartbeat();
        $this->run_repository->save( $run );
    }

    /**
     * Ensure recovery cron is scheduled.
     *
     * Runs once during handler construction to set up the hourly cron.
     *
     * @return void
     */
    private function ensure_cron_scheduled(): void {
        if ( \as_next_scheduled_action( Sync_Constants::HOOK_RECOVERY_CRON ) ) {
            return;
        }

        \as_schedule_recurring_action(
            \time(),
            Sync_Constants::RECOVERY_CRON_INTERVAL,
            Sync_Constants::HOOK_RECOVERY_CRON,
            array(),
            'pllat-sync',
        );
    }
}
