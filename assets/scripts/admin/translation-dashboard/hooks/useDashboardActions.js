import { useState } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";

/**
 * Extract error message from various error formats
 */
const extractErrorMessage = (error) => {
  if (error.error) {
    return error.error;
  }
  if (error.message) {
    return error.message;
  }
  return "Unknown error occurred";
};

/**
 * Dashboard actions hook
 *
 * Handles user actions like starting and canceling translations.
 * Simplified to use refetch pattern instead of optimistic updates.
 *
 * @param {Function} refetch - Function to refetch dashboard data
 * @returns {Object} - { startContentTranslation, cancelRun, isProcessing }
 */
export const useDashboardActions = (refetch) => {
  const [isProcessing, setIsProcessing] = useState(false);

  /**
   * Start a translation run for a specific content type
   *
   * @param {string} contentTypeSlug - The content type slug
   * @param {Object} contentTypeData - Content type metadata
   * @param {boolean} isDryRun - Whether this is a dry run
   * @returns {Promise<Object>} - { success, runId?, error? }
   */
  const startContentTranslation = async (
    contentTypeSlug,
    contentTypeData,
    isDryRun = false
  ) => {
    if (isProcessing) {
      return { success: false, error: "Another action is in progress" };
    }

    setIsProcessing(true);

    try {
      // Determine type: 'post' for post types, 'term' for taxonomies
      const type = contentTypeData.type === "post" ? "post" : "term";
      const dryRunParam = isDryRun ? "1" : "0";

      const response = await apiFetch({
        path: `/pllat/v1/dashboard/content/${type}/${contentTypeSlug}?dry_run=${dryRunParam}`,
        method: "POST",
      });

      if (response.success === false) {
        throw new Error(response.error || "Failed to start translation");
      }

      // Immediate refetch for faster UI feedback (don't wait for next poll)
      refetch();

      return { success: true, runId: response.data.runId };
    } catch (error) {
      const errorMessage = extractErrorMessage(error);
      alert(`Failed to start translation: ${errorMessage}`);
      return { success: false, error: errorMessage };
    } finally {
      setIsProcessing(false);
    }
  };

  /**
   * Cancel an active translation run
   *
   * @param {string} type - Content type ('post' or 'term')
   * @param {string} entity - Post type or taxonomy name
   * @returns {Promise<Object>} - { success, error? }
   */
  const cancelRun = async (type, entity) => {
    if (!type || !entity || isProcessing) {
      return { success: false, error: "Invalid parameters or action in progress" };
    }

    if (!confirm("Are you sure you want to cancel this translation run?")) {
      return { success: false, error: "Cancelled by user" };
    }

    setIsProcessing(true);

    try {
      const response = await apiFetch({
        path: `/pllat/v1/dashboard/content/${type}/${entity}/cancel`,
        method: "POST",
      });

      if (!response.success) {
        throw new Error(response.error || "Failed to cancel run");
      }

      // Immediate refetch for faster UI feedback (don't wait for next poll)
      refetch();

      return { success: true };
    } catch (error) {
      const errorMessage = extractErrorMessage(error);
      alert(`Failed to cancel translation: ${errorMessage}`);
      return { success: false, error: errorMessage };
    } finally {
      setIsProcessing(false);
    }
  };

  return {
    startContentTranslation,
    cancelRun,
    isProcessing,
  };
};
