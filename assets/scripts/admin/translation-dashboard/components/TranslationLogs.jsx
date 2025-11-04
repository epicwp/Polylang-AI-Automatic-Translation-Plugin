import { useState, useEffect } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";

const TranslationLogs = () => {
  const [logs, setLogs] = useState([]);
  const [filter, setFilter] = useState("all");
  const [isLoading, setIsLoading] = useState(true);
  const [selectedDate, setSelectedDate] = useState(() => {
    const now = new Date();
    return now.toISOString().split('T')[0]; // YYYY-MM-DD format
  });
  const [availableDates, setAvailableDates] = useState([]);
  const [isPolling, setIsPolling] = useState(true);

  // Fetch available log dates
  const fetchAvailableDates = async () => {
    try {
      const response = await apiFetch({
        path: '/pllat/v1/logs/dates',
      });
      setAvailableDates(response.dates || []);
    } catch (error) {
      console.error("Error fetching available dates:", error);
    }
  };

  // Fetch logs from API for selected date
  const fetchLogs = async (date = selectedDate, currentFilter = filter) => {
    try {
      const response = await apiFetch({
        path: `/pllat/v1/logs?date=${date}&type=${currentFilter}`,
      });

      setLogs(response.logs || []);
      setIsLoading(false);
    } catch (error) {
      console.error("Error fetching logs:", error);
      setIsLoading(false);
    }
  };

  // Initial load - fetch available dates and logs
  useEffect(() => {
    fetchAvailableDates();
    fetchLogs(selectedDate, filter);
  }, []);

  // Refetch logs when date or filter changes
  useEffect(() => {
    fetchLogs(selectedDate, filter);
  }, [selectedDate, filter]);

  // Polling: Refresh logs every 5 seconds
  useEffect(() => {
    if (!isPolling) return;

    const interval = setInterval(() => {
      fetchLogs(selectedDate, filter);
      fetchAvailableDates(); // Refresh available dates (new day might have appeared)
    }, 5000);

    return () => clearInterval(interval);
  }, [isPolling, selectedDate, filter]);

  // Handle filter change
  const handleFilterChange = (newFilter) => {
    setFilter(newFilter);
  };

  // Handle date change
  const handleDateChange = (newDate) => {
    setSelectedDate(newDate);
    setIsLoading(true);
  };

  // Quick date shortcuts
  const setToday = () => {
    const now = new Date();
    handleDateChange(now.toISOString().split('T')[0]);
  };

  const setYesterday = () => {
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    handleDateChange(yesterday.toISOString().split('T')[0]);
  };

  // Format date for display
  const formatDateDisplay = (dateStr) => {
    try {
      const date = new Date(dateStr + 'T00:00:00'); // Add time to avoid timezone issues
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    } catch {
      return dateStr;
    }
  };

  // Handle clear logs
  const handleClearLogs = async () => {
    if (
      !confirm(
        "Are you sure you want to clear old log files? This will delete logs older than 30 days."
      )
    ) {
      return;
    }

    try {
      const response = await apiFetch({
        path: "/pllat/v1/logs/clear",
        method: "POST",
        data: {
          days: 30,
        },
      });

      if (response.success) {
        alert(response.message);
        fetchAvailableDates(); // Refresh available dates
        fetchLogs(selectedDate, filter); // Refresh current view
      }
    } catch (error) {
      alert("Error clearing logs: " + error.message);
    }
  };

  // Get dot color for log type
  const getLogDotColor = (type) => {
    switch (type) {
      case "success":
        return "bg-green-500";
      case "error":
        return "bg-red-500";
      case "warning":
        return "bg-amber-500";
      case "info":
      default:
        return "bg-blue-500";
    }
  };

  // Get border color for log type
  const getLogBorderColor = (type) => {
    switch (type) {
      case "success":
        return "border-l-green-500";
      case "error":
        return "border-l-red-500";
      case "warning":
        return "border-l-amber-500";
      case "info":
      default:
        return "border-l-blue-500";
    }
  };

  // Format timestamp
  const formatTimestamp = (timestamp) => {
    try {
      const date = new Date(timestamp);
      return date.toLocaleString();
    } catch {
      return timestamp;
    }
  };

  return (
    <div className="translation-logs">
      {/* Controls Bar */}
      <div className="bg-white p-4 rounded-lg border border-gray-200 mb-4">
        <div className="flex items-center justify-between flex-wrap gap-3">
          <div className="flex items-center space-x-3 flex-wrap gap-2">
            {/* Date Picker */}
            <div className="flex items-center space-x-2">
              <select
                className="border border-gray-200 rounded-md px-3 py-1.5 text-sm bg-gray-50 hover:bg-white focus:outline-none focus:ring-1 focus:ring-gray-300 transition-colors"
                value={selectedDate}
                onChange={(e) => handleDateChange(e.target.value)}
              >
                {availableDates.length > 0 ? (
                  availableDates.map((date) => (
                    <option key={date} value={date}>
                      {formatDateDisplay(date)}
                    </option>
                  ))
                ) : (
                  <option value={selectedDate}>
                    {formatDateDisplay(selectedDate)}
                  </option>
                )}
              </select>

              {/* Quick shortcuts */}
              <button
                onClick={setToday}
                className="text-xs px-2 py-1 border border-gray-200 rounded hover:bg-gray-50 transition-colors"
                title="Jump to today"
              >
                Today
              </button>
              <button
                onClick={setYesterday}
                className="text-xs px-2 py-1 border border-gray-200 rounded hover:bg-gray-50 transition-colors"
                title="Jump to yesterday"
              >
                Yesterday
              </button>
            </div>

            <div className="border-l border-gray-200 h-6"></div>

            {/* Type Filter */}
            <select
              className="border border-gray-200 rounded-md px-3 py-1.5 text-sm bg-gray-50 hover:bg-white focus:outline-none focus:ring-1 focus:ring-gray-300 transition-colors"
              value={filter}
              onChange={(e) => handleFilterChange(e.target.value)}
            >
              <option value="all">All Types</option>
              <option value="info">Info</option>
              <option value="success">Success</option>
              <option value="warning">Warnings</option>
              <option value="error">Errors</option>
            </select>

            <span className="text-xs text-gray-500">
              {logs.length} {logs.length === 1 ? 'entry' : 'entries'}
            </span>

            {/* Polling Toggle */}
            <label className="flex items-center space-x-2 text-xs text-gray-600 cursor-pointer">
              <input
                type="checkbox"
                checked={isPolling}
                onChange={(e) => setIsPolling(e.target.checked)}
                className="rounded"
              />
              <span>Auto-refresh</span>
            </label>
          </div>

          <button
            onClick={handleClearLogs}
            className="text-sm text-gray-600 hover:text-gray-900 transition-colors"
          >
            Clear old logs
          </button>
        </div>
      </div>

      {/* Log List */}
      <div className="bg-white rounded-lg border border-gray-200">
        <div className="overflow-y-auto" style={{ maxHeight: "500px" }}>
          {isLoading ? (
            <div className="p-12 text-center">
              <div className="text-gray-400 mb-2">
                <span className="dashicons dashicons-update animate-spin text-2xl"></span>
              </div>
              <p className="text-sm text-gray-500">Loading logs...</p>
            </div>
          ) : logs.length > 0 ? (
            <div className="divide-y divide-gray-200">
              {logs.map((log) => (
                <div
                  key={log.id}
                  className={`px-4 py-3 border-l-2 ${getLogBorderColor(log.type)} hover:bg-gray-50 transition-colors`}
                >
                  <div className="flex items-start space-x-3">
                    <span
                      className={`w-2 h-2 rounded-full mt-1.5 ${getLogDotColor(log.type)}`}
                    ></span>
                    <div className="flex-1">
                      <div className="flex items-center space-x-2 mb-1">
                        <span className="text-xs text-gray-400">
                          {formatTimestamp(log.timestamp)}
                        </span>
                        {log.contentType !== "System" && (
                          <>
                            <span className="text-xs px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded">
                              {log.contentType}
                            </span>
                            {log.language !== "-" && (
                              <span className="text-xs px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded">
                                {log.language}
                              </span>
                            )}
                          </>
                        )}
                      </div>
                      <p className="text-sm text-gray-700 m-0">{log.message}</p>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="p-12 text-center">
              <div className="text-gray-400 mb-2">
                <span className="dashicons dashicons-list-view text-2xl"></span>
              </div>
              <p className="text-sm text-gray-500">
                No logs found for the selected filter
              </p>
            </div>
          )}
        </div>
      </div>

      {/* Live Update Indicator */}
      <div className="mt-3 flex justify-end">
        <span className="inline-flex items-center text-xs text-gray-400">
          {isPolling ? (
            <>
              <span className="relative flex h-1.5 w-1.5 mr-1.5">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-1.5 w-1.5 bg-green-500"></span>
              </span>
              Live
            </>
          ) : (
            <>
              <span className="relative flex h-1.5 w-1.5 mr-1.5">
                <span className="relative inline-flex rounded-full h-1.5 w-1.5 bg-gray-400"></span>
              </span>
              Paused
            </>
          )}
        </span>
      </div>
    </div>
  );
};

export default TranslationLogs;
