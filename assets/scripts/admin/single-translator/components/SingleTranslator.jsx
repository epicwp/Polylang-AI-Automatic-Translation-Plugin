/**
 * Main Single Translator component (orchestrator).
 */

import { useEffect, useState, useCallback } from "@wordpress/element";
import { Spinner, Notice } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

import { useTranslationStatus } from "../hooks/useTranslationStatus";
import { useTranslationActions } from "../hooks/useTranslationActions";
import { useActiveTranslations } from "../hooks/useActiveTranslations";
import { useInitialState } from "../hooks/useInitialState";
import { cleanupExpired, markAsDismissed, isDismissed } from "../utils/dismissedNotifications";

import ExclusionToggle from "./ExclusionToggle";
import LanguageSelector from "./LanguageSelector";
import InstructionsInput from "./InstructionsInput";
import ForceToggle from "./ForceToggle";
import ActionButtons from "./ActionButtons";
import ErrorBanner from "./ErrorBanner";
import ErrorSummaryBanner from "./ErrorSummaryBanner";
import ImportingMessage from "./ImportingMessage";

/**
 * Single Translator main component.
 *
 * @returns {JSX.Element} The component
 */
export function SingleTranslator() {
  // Fetch translation status
  const {
    status,
    loading: statusLoading,
    error: statusError,
    refresh,
  } = useTranslationStatus();

  // Translation actions
  const {
    translate,
    toggleExclusion,
    cancel,
    loading: actionLoading,
    error: actionError,
    clearError,
  } = useTranslationActions(refresh);

  // Initial state detection
  const initialState = useInitialState(status, statusLoading);

  // Local state
  const [selectedLanguages, setSelectedLanguages] = useState([]);
  const [instructions, setInstructions] = useState("");
  const [force, setForce] = useState(false);
  const [isExcluded, setIsExcluded] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  // NEW: UI state tracking
  const [uiState, setUiState] = useState('idle'); // 'idle' | 'translating' | 'completed' | 'failed'
  const [runningLanguages, setRunningLanguages] = useState([]);

  // Success notice dismissal state (5 minute TTL)
  const [successDismissed, setSuccessDismissed] = useState(() => {
    const notificationId = 'pllat_success_notice';
    return isDismissed(notificationId, 5 * 60 * 1000); // 5 minutes
  });

  // Handle polling completion with state tracking
  const handlePollingComplete = useCallback(() => {
    // Only update UI state if we were tracking languages
    if (runningLanguages.length === 0) {
      return;
    }

    // Check final status from current status state
    if (status) {
      const results = status.languages.filter(lang =>
        runningLanguages.includes(lang.language)
      );

      const anyFailed = results.some(lang => lang.status === 'failed');

      setUiState(anyFailed ? 'failed' : 'completed');
      setRunningLanguages([]);
    }
  }, [status, runningLanguages]);

  // Active translations polling (with completion callback)
  const { hasActive, polling, startPolling, stopPolling } =
    useActiveTranslations(true, refresh, handlePollingComplete);

  /**
   * Clean up expired notifications on mount.
   */
  useEffect(() => {
    cleanupExpired();
  }, []);

  /**
   * Initial status fetch.
   */
  useEffect(() => {
    refresh();
  }, [refresh]);

  /**
   * Update exclusion state when status changes.
   */
  useEffect(() => {
    if (status) {
      setIsExcluded(status.is_excluded);
    }
  }, [status]);

  /**
   * Start polling if there are active translations.
   */
  useEffect(() => {
    if (initialState.hasActive && !polling) {
      startPolling();
    }
  }, [initialState.hasActive, polling, startPolling]);

  /**
   * Handle translation start.
   */
  const handleTranslate = async () => {
    if (selectedLanguages.length === 0) {
      return;
    }

    setSubmitting(true);
    setUiState('translating');
    setRunningLanguages([...selectedLanguages]); // Track which languages we're starting

    try {
      await translate(selectedLanguages, force, instructions);

      // Start polling for progress (polls immediately)
      startPolling();

      // Reset form
      setSelectedLanguages([]);
      setInstructions("");
      setForce(false);
    } catch (err) {
      // Error occurred, reset state
      setUiState('failed');
      setRunningLanguages([]);
      console.error("Translation failed:", err);
    } finally {
      setSubmitting(false);
    }
  };

  /**
   * Handle exclusion toggle.
   */
  const handleExclusionToggle = async (excluded) => {
    try {
      await toggleExclusion(excluded);
      setIsExcluded(excluded);
    } catch (err) {
      // Error is already set in useTranslationActions
      console.error("Exclusion toggle failed:", err);
    }
  };

  /**
   * Handle translation cancellation.
   */
  const handleCancel = async () => {
    try {
      await cancel();

      // Stop polling
      stopPolling();

      // Reset UI state
      setUiState('idle');
      setRunningLanguages([]);
    } catch (err) {
      // Error is already set in useTranslationActions
      console.error("Cancel failed:", err);
    }
  };

  /**
   * Handle success notice dismissal.
   */
  const handleSuccessDismiss = useCallback(() => {
    const notificationId = 'pllat_success_notice';
    markAsDismissed(notificationId);
    setSuccessDismissed(true);
  }, []);

  /**
   * Render loading state.
   */
  if (statusLoading && !status) {
    return (
      <div style={{ padding: "20px", textAlign: "center" }}>
        <Spinner />
      </div>
    );
  }

  /**
   * Render error state.
   */
  if (statusError) {
    return (
      <Notice status="error" isDismissible={false}>
        {statusError}
      </Notice>
    );
  }

  /**
   * Render when no status data.
   */
  if (!status) {
    return null;
  }

  const { system_status: systemStatus, languages } = status;

  /**
   * Render system not ready state.
   */
  if (systemStatus === "not_ready") {
    return (
      <Notice status="warning" isDismissible={false}>
        {__(
          "The translation system is not ready. Please configure your AI provider in the settings.",
          "polylang-ai-autotranslate",
        )}
      </Notice>
    );
  }

  /**
   * Render importing state (blocks all UI).
   */
  if (systemStatus === "importing") {
    return <ImportingMessage />;
  }

  return (
    <div className="pllat-single-translator">
      {/* Action error banner */}
      {actionError && (
        <div style={{ marginBottom: "15px" }}>
          <Notice status="error" isDismissible={true} onRemove={clearError}>
            {actionError}
          </Notice>
        </div>
      )}

      {/* Recovery banners */}
      {initialState.hasRecentErrors && (
        <div style={{ marginBottom: "15px" }}>
          <ErrorBanner languages={languages} onDismiss={refresh} />
        </div>
      )}
      {/* Only show recovery success notice if NOT currently completing and NOT dismissed */}
      {initialState.hasRecentSuccess && !successDismissed && uiState !== 'completed' && (
        <div style={{ marginBottom: "15px" }}>
          <Notice status="success" isDismissible={true} onRemove={handleSuccessDismiss}>
            {__(
              "Recent translations completed successfully!",
              "polylang-ai-autotranslate",
            )}
          </Notice>
        </div>
      )}

      {/* Exclusion toggle */}
      <ExclusionToggle
        excluded={isExcluded}
        onChange={handleExclusionToggle}
        loading={actionLoading}
      />

      {/* Show translation UI only when not excluded */}
      {!isExcluded && (
        <>
          {/* Starting translation notice - shows during submit and initial polling */}
          {(submitting || (polling && uiState === 'translating')) && (
            <div style={{ marginBottom: "15px" }}>
              <Notice status="info" isDismissible={false}>
                {__("Starting translation...", "polylang-ai-autotranslate")}
              </Notice>
            </div>
          )}

          {/* Success notice - after translation completes */}
          {uiState === 'completed' && (
            <div style={{ marginBottom: '15px' }}>
              <Notice
                status="success"
                isDismissible={true}
                onRemove={() => setUiState('idle')}
              >
                {__('Translations completed successfully!', 'polylang-ai-autotranslate')}
              </Notice>
            </div>
          )}

          {/* Failed notice - after translation fails */}
          {uiState === 'failed' && (
            <div style={{ marginBottom: '15px' }}>
              <Notice
                status="error"
                isDismissible={true}
                onRemove={() => setUiState('idle')}
              >
                {__('Some translations failed. Check language details below.', 'polylang-ai-autotranslate')}
              </Notice>
            </div>
          )}

          {/* Error summary banners for each language */}
          {languages
            .filter((lang) => lang.error_summary)
            .map((lang) => (
              <div key={lang.language} style={{ marginBottom: "15px" }}>
                <ErrorSummaryBanner
                  language={lang.language}
                  languageName={lang.language_name}
                  errorSummary={lang.error_summary}
                  jobId={lang.job_id}
                  onDismiss={refresh}
                />
              </div>
            ))}

          {/* Translation form */}
          <div className="pllat-translation-form" style={{ marginTop: "20px" }}>
            <LanguageSelector
              languages={languages}
              selected={selectedLanguages}
              onChange={setSelectedLanguages}
              disabled={actionLoading || polling}
              runningLanguages={runningLanguages}
            />

            <InstructionsInput
              value={instructions}
              onChange={setInstructions}
              disabled={actionLoading || polling}
            />

            <ForceToggle
              value={force}
              onChange={setForce}
              disabled={actionLoading || polling}
            />

            <ActionButtons
              onTranslate={handleTranslate}
              onCancel={handleCancel}
              disabled={
                selectedLanguages.length === 0 || actionLoading || polling
              }
              canCancel={polling || hasActive}
              loading={actionLoading}
              isProcessing={polling}
            />
          </div>
        </>
      )}
    </div>
  );
}

export default SingleTranslator;
