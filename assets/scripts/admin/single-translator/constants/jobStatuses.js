/**
 * Job status constants and helpers.
 */

export const JOB_STATUS = {
	PENDING: 'pending',
	QUEUED: 'queued',
	IN_PROGRESS: 'in_progress',
	COMPLETED: 'completed',
	FAILED: 'failed',
	CANCELLED: 'cancelled',
	COMPLETED_WITH_ERRORS: 'completed_with_errors'
};

/**
 * Statuses that indicate active translation work.
 */
export const ACTIVE_STATUSES = [
	JOB_STATUS.PENDING,
	JOB_STATUS.QUEUED,
	JOB_STATUS.IN_PROGRESS
];

/**
 * Check if a status indicates active translation.
 *
 * @param {string} status - The job status to check
 * @returns {boolean} True if status is active
 */
export function isActiveStatus(status) {
	return ACTIVE_STATUSES.includes(status);
}

/**
 * Check if a status indicates completion (success or failure).
 *
 * @param {string} status - The job status to check
 * @returns {boolean} True if status is terminal
 */
export function isTerminalStatus(status) {
	return status === JOB_STATUS.COMPLETED
		|| status === JOB_STATUS.FAILED
		|| status === JOB_STATUS.COMPLETED_WITH_ERRORS;
}
