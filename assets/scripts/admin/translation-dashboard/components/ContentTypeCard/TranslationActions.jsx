import IdleState from "./IdleState";
import ActiveState from "./ActiveState";
import RestartableState from "./RestartableState";
import AutoTranslateActive from "./AutoTranslateActive";
import AllTranslatedMessage from "./AllTranslatedMessage";

const TranslationActions = ({
  hasUntranslatedItems,
  isAutoTranslateEnabled,
  currentStatus,
  activeRunId,
  runProgress,
  onStartTranslation,
  onCancelRun,
  dryRunItemCount,
}) => {
  if (!hasUntranslatedItems) {
    return <AllTranslatedMessage />;
  }

  if (isAutoTranslateEnabled) {
    return <AutoTranslateActive />;
  }

  return (
    <div className="space-y-2">
      {currentStatus === "idle" && (
        <IdleState
          onStartTranslation={onStartTranslation}
          dryRunItemCount={dryRunItemCount}
        />
      )}

      {(currentStatus === "pending" || currentStatus === "translating") && (
        <ActiveState
          status={currentStatus}
          runId={activeRunId}
          runProgress={runProgress}
          onCancelRun={onCancelRun}
        />
      )}

      {(currentStatus === "cancelled" || currentStatus === "failed") && (
        <RestartableState
          status={currentStatus}
          onStartTranslation={onStartTranslation}
        />
      )}
    </div>
  );
};

export default TranslationActions;
