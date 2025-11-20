<?php
/**
 * Transaction_Trait trait file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Sync
 */

declare(strict_types=1);

namespace PLLAT\Sync\Traits;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Provides database transaction handling for services.
 * Ensures atomic operations with automatic rollback on failure.
 *
 * Usage:
 * ```php
 * class My_Service {
 *     use Transaction_Trait;
 *
 *     public function do_something() {
 *         $this->execute_with_transaction( function() {
 *             // Your database operations here
 *             // Will auto-commit on success
 *             // Will auto-rollback on exception
 *         } );
 *     }
 * }
 * ```
 */
trait Transaction_Trait {
	/**
	 * Execute a callback within a database transaction.
	 *
	 * Automatically commits on success, rolls back on exception.
	 * Safe for nested calls - inner transactions become part of outer transaction.
	 *
	 * @param callable $callback The function to execute within the transaction.
	 * @return mixed The result of the callback.
	 * @throws \Exception If the callback throws an exception (after rollback).
	 */
	protected function execute_with_transaction( callable $callback ): mixed {
		global $wpdb;

		// Start transaction.
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Execute callback.
			$result = $callback();

			// Commit on success.
			$wpdb->query( 'COMMIT' );

			return $result;
		} catch ( \Exception $e ) {
			// Rollback on failure.
			$wpdb->query( 'ROLLBACK' );

			// Re-throw exception for caller to handle.
			throw $e;
		}
	}
}
