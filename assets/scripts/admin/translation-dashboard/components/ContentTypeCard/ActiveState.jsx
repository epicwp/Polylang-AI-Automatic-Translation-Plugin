const ActiveState = ({ status, runId, runProgress, onCancelRun }) => {
  const dotsLoader = window.pllat?.assets?.icons?.dotsLoader;
  const ringLoader = window.pllat?.assets?.icons?.ringLoader;

  const statusConfig = {
    pending: {
      iconSrc: dotsLoader,
      label: "Starting...",
      cancelIcon: "dashicons-no-alt",
      iconFilter: "invert(31%) sepia(100%) saturate(2080%) hue-rotate(197deg) brightness(96%) contrast(93%)", // Blue
    },
    translating: {
      iconSrc: ringLoader,
      label: "Translating...",
      cancelIcon: "dashicons-dismiss",
      iconFilter: "invert(48%) sepia(79%) saturate(2476%) hue-rotate(86deg) brightness(92%) contrast(101%)", // Green
    },
  };

  const config = statusConfig[status];

  // Helper function to format time duration
  const formatTime = (seconds) => {
    if (!seconds || seconds < 60) return `${seconds || 0}s`;
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return secs > 0 ? `${mins}m ${secs}s` : `${mins}m`;
  };

  // Build progress label
  let progressLabel = config.label;
  if (runProgress && runProgress.total > 0) {
    progressLabel = `${runProgress.completed}/${runProgress.total} (${runProgress.percentage}%)`;
  }

  return (
    <div className="space-y-2">
      <div className="flex gap-2">
        <button
          className="flex-1 !px-4 !py-2 !rounded !font-medium button button-secondary cursor-default"
          disabled
        >
          <span className="flex items-center justify-center">
            <img
              src={config.iconSrc}
              width="16"
              height="16"
              alt=""
              className="mr-2"
              style={{ filter: config.iconFilter }}
            />
            {progressLabel}
          </span>
        </button>
        <button
          className="!px-3 !py-2 !rounded button button-link-delete cursor-pointer hover:!bg-red-50"
          onClick={() => onCancelRun(runId)}
          title="Cancel translation"
        >
          <span className={`dashicons ${config.cancelIcon}`}></span>
        </button>
      </div>

      {/* Progress details */}
      {runProgress && runProgress.total > 0 && (
        <div className="text-xs text-gray-600 space-y-1">
          {/* Progress bar */}
          <div className="w-full bg-gray-200 rounded-full h-1.5">
            <div
              className="bg-green-600 h-1.5 rounded-full transition-all duration-300"
              style={{ width: `${runProgress.percentage}%` }}
            ></div>
          </div>

          {/* Time and ETA */}
          <div className="flex justify-between items-center">
            <span>
              {runProgress.elapsedTime > 0 && (
                <span className="mr-3">
                  ⏱ {formatTime(runProgress.elapsedTime)}
                </span>
              )}
              {runProgress.estimatedTime && runProgress.pending > 0 && (
                <span className="text-gray-500">
                  ~{formatTime(runProgress.estimatedTime)} remaining
                </span>
              )}
            </span>
            {runProgress.failed > 0 && (
              <span className="text-red-600">
                {runProgress.failed} failed
              </span>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default ActiveState;
