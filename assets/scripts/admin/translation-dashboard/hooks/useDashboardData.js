import { useState, useEffect, useRef, useCallback } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";

/**
 * Pure dashboard data fetching hook
 *
 * Handles fetching dashboard data from the REST API with proper cleanup.
 * Does NOT contain polling logic - that's handled by useDashboardPolling.
 *
 * @returns {Object} - { data, isFetching, refetch }
 */
export const useDashboardData = () => {
  const [data, setData] = useState({
    overall: { translated: 0, total: 0 },
    contentTypes: {},
    targetLanguages: [],
    discovery: null,
  });
  const [isFetching, setIsFetching] = useState(false);
  const abortControllerRef = useRef(null);

  /**
   * Fetch dashboard data from API
   * Includes abort controller to prevent memory leaks and race conditions
   */
  const fetchData = useCallback(async () => {
    // Abort previous fetch if still running
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }

    setIsFetching(true);
    const controller = new AbortController();
    abortControllerRef.current = controller;

    try {
      const response = await apiFetch({
        path: "/pllat/v1/dashboard",
        signal: controller.signal,
      });

      const newData = {
        overall: {
          translated: response.overallProgress?.total_translated || 0,
          total: response.overallProgress?.total_possible_translations || 0,
        },
        contentTypes: response.contentTypes || {},
        targetLanguages: response.targetLanguages || [],
        discovery: response.discovery || null,
      };

      setData(newData);
    } catch (error) {
      if (error.name !== "AbortError") {
        // Silent fail - polling will retry automatically
        console.error("Dashboard fetch error:", error);
      }
    } finally {
      setIsFetching(false);
      abortControllerRef.current = null;
    }
  }, []);

  // Initial fetch on mount
  useEffect(() => {
    fetchData();

    // Cleanup: abort any pending fetch on unmount
    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, [fetchData]);

  return { data, isFetching, refetch: fetchData };
};
