/**
 * Hook for polling active translations.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { isActiveStatus } from '../constants/jobStatuses';

const POLL_INTERVAL = 3000; // 3 seconds

/**
 * Custom hook to poll for active translation progress.
 *
 * @param {boolean} enabled - Whether polling is enabled
 * @param {Function} onPoll - Callback to refresh main status on each poll
 * @param {Function} onComplete - Optional callback when all translations complete
 * @returns {Object} Polling state and methods
 */
export function useActiveTranslations(enabled, onPoll = null, onComplete = null) {
	const [polling, setPolling] = useState(false);
	const [hasActive, setHasActive] = useState(false);

	/**
	 * Poll for active translations.
	 */
	const poll = useCallback(async () => {
		if (!enabled) {
			return;
		}

		try {
			// Call onPoll to refresh main status
			if (onPoll) {
				const data = await onPoll();

				// Check if there are active translations
				const active = data.languages.filter((lang) => isActiveStatus(lang.status));

				const hasActiveNow = active.length > 0;
				setHasActive(hasActiveNow);

				// If no active translations, stop polling and notify completion
				if (!hasActiveNow && polling) {
					setPolling(false);
					if (onComplete) {
						onComplete();
					}
				}
			}
		} catch (err) {
			// Fail silently during polling
			console.error('Failed to poll translation status:', err);
		}
	}, [enabled, polling, onPoll, onComplete]);

	/**
	 * Start polling immediately.
	 */
	const startPolling = useCallback(() => {
		setPolling(true);
		// Poll immediately instead of waiting for interval
		poll();
	}, [poll]);

	/**
	 * Stop polling.
	 */
	const stopPolling = useCallback(() => {
		setPolling(false);
		setHasActive(false);
	}, []);

	/**
	 * Set up polling interval.
	 */
	useEffect(() => {
		if (!enabled || !polling) {
			return;
		}

		// Initial poll
		poll();

		// Set up interval
		const interval = setInterval(poll, POLL_INTERVAL);

		return () => {
			clearInterval(interval);
		};
	}, [enabled, polling, poll]);

	return {
		hasActive,
		polling,
		startPolling,
		stopPolling,
	};
}

export default useActiveTranslations;
