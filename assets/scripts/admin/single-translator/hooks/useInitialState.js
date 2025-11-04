/**
 * Hook for detecting initial state on component mount.
 */

import { useState, useEffect } from '@wordpress/element';

/**
 * Custom hook to detect recovery state (active translations, recent errors, recent success).
 *
 * @param {Object|null} status - Translation status data
 * @param {boolean} loading - Whether status is loading
 * @returns {Object} Initial state flags
 */
export function useInitialState(status, loading) {
	const [initialState, setInitialState] = useState({
		hasActive: false,
		hasRecentErrors: false,
		hasRecentSuccess: false,
	});
	const [initialized, setInitialized] = useState(false);

	useEffect(() => {
		// Only run once after initial status is loaded
		if (loading || initialized || !status) {
			return;
		}

		const hasActive = status.languages.some((lang) => {
			return lang.status === 'queued' || lang.status === 'in_progress';
		});

		const hasRecentErrors = status.languages.some((lang) => {
			return lang.status === 'failed' || lang.status === 'completed_with_errors';
		});

		const hasRecentSuccess = status.languages.some((lang) => {
			return lang.status === 'completed' && !lang.error_summary;
		});

		setInitialState({
			hasActive,
			hasRecentErrors,
			hasRecentSuccess,
		});

		setInitialized(true);
	}, [status, loading, initialized]);

	return initialState;
}

export default useInitialState;
