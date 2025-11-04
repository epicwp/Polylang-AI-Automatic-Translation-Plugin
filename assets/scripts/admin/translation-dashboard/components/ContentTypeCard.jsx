import CardHeader from "./ContentTypeCard/CardHeader";
import LanguageProgressList from "./ContentTypeCard/LanguageProgressList";
import TranslationActions from "./ContentTypeCard/TranslationActions";

const ContentTypeCard = ({
  title,
  icon,
  languageStats,
  onStartTranslation,
  onCancelRun,
  currentStatus = "idle",
  activeRunId = null,
  runProgress = null,
  isAutoTranslateEnabled = false,
  dryRunItemCount = 3,
}) => {
  const stats = Object.values(languageStats);
  const totalTranslated = stats.reduce((sum, lang) => sum + lang.translated, 0);
  const totalItems = stats.reduce((sum, lang) => sum + lang.total, 0);
  const completionPercentage =
    totalItems > 0 ? Math.round((totalTranslated / totalItems) * 100) : 0;
  const hasUntranslatedItems = stats.some(
    (lang) => lang.translated < lang.total
  );

  return (
    <div className="bg-white p-5 rounded-lg border border-[#c3c4c7] shadow hover:shadow-lg transition-shadow justify-between flex-col flex">
      <div>
        <CardHeader
          icon={icon}
          title={title}
          completionPercentage={completionPercentage}
        />
        <LanguageProgressList languageStats={languageStats} />
      </div>

      <TranslationActions
        hasUntranslatedItems={hasUntranslatedItems}
        isAutoTranslateEnabled={isAutoTranslateEnabled}
        currentStatus={currentStatus}
        activeRunId={activeRunId}
        runProgress={runProgress}
        onStartTranslation={onStartTranslation}
        onCancelRun={onCancelRun}
        dryRunItemCount={dryRunItemCount}
      />
    </div>
  );
};

export default ContentTypeCard;
