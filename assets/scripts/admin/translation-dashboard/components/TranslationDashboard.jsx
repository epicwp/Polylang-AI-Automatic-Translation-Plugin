import { useState } from "@wordpress/element";
import { useDashboardPolling } from "../hooks/useDashboardPolling";
import { useDashboardActions } from "../hooks/useDashboardActions";
import TabNavigation from "./TabNavigation";
import ContentTypeCard from "./ContentTypeCard";
import TranslationLogs from "./TranslationLogs";
import DashboardHeader from "./DashboardHeader";
import { DiscoveryOverlay } from "./DiscoveryOverlay";

const TranslationDashboard = () => {
  const { data, isFetching, isPolling, refetch } = useDashboardPolling();
  const { startContentTranslation, cancelRun, isProcessing } = useDashboardActions(refetch);
  const [autoTranslate, setAutoTranslate] = useState(false);
  const [activeTab, setActiveTab] = useState("overview");

  const handleAutoTranslateToggle = (enabled) => {
    setAutoTranslate(enabled);
  };

  return (
    <div className="wrap">
      {/* Discovery Overlay */}
      {data?.discovery && <DiscoveryOverlay discoveryCheck={data.discovery} />}

      {/* Dashboard Content - blur when overlay is active */}
      <div className={`translation-dashboard ${data?.discovery?.needed ? 'blur-sm pointer-events-none' : ''}`}>
        <DashboardHeader
          data={data}
          autoTranslate={autoTranslate}
          autoTranslateToggleHandler={handleAutoTranslateToggle}
        />

        <TabNavigation activeTab={activeTab} onTabChange={setActiveTab} />

        {activeTab === "overview" ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-6">
            {Object.entries(data.contentTypes).map(([key, contentType]) => (
              <ContentTypeCard
                key={key}
                title={contentType.label}
                icon={contentType.icon}
                languageStats={contentType.languages}
                onStartTranslation={({ isDryRun }) =>
                  startContentTranslation(key, contentType, isDryRun)
                }
                onCancelRun={() => cancelRun(contentType.type, key)}
                currentStatus={contentType.translationState}
                activeRunId={contentType.runId}
                runProgress={contentType.runProgress}
                isAutoTranslateEnabled={autoTranslate}
              />
            ))}
          </div>
        ) : (
          <TranslationLogs />
        )}

        {autoTranslate && (
          <div className="mt-6 bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded-lg">
            <div className="flex items-center">
              <span className="dashicons dashicons-update-alt mr-2 animate-spin"></span>
              <span>
                Auto-translate is running. New content will be translated
                automatically.
              </span>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default TranslationDashboard;
