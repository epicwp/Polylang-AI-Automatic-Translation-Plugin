import ContentTypeCard from "./ContentTypeCard";

const ContentTypeGrid = ({
  contentTypes,
  onStartTranslation,
  onCancelTranslation,
}) => {
  return (
    <div className="pllat-dashboard__tabs">
      <div className="pllat-tabs__header">
        <button className="pllat-tabs__tab pllat-tabs__tab--active">
          <span className="dashicons dashicons-admin-page"></span>
          Overview
        </button>
        <button className="pllat-tabs__tab">
          <span className="dashicons dashicons-list-view"></span>
          Translation Logs
        </button>
      </div>

      <div className="pllat-tabs__content">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-6">
          {Object.entries(contentTypes).map(([key, contentType]) => (
            <ContentTypeCard
              key={key}
              contentKey={key}
              label={contentType.label}
              icon={contentType.icon}
              languages={contentType.languages}
              onStart={(isDryRun) => onStartTranslation(key, isDryRun)}
              onCancel={() => onCancelTranslation(contentType.runId)}
              translationState={contentType.translationState}
              runId={contentType.runId}
              autoTranslateEnabled={false}
            />
          ))}
        </div>
      </div>
    </div>
  );
};

export default ContentTypeGrid;
