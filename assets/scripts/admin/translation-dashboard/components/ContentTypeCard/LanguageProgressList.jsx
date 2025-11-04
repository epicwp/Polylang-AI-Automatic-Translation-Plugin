import LanguageProgress from "../LanguageProgress";

const LanguageProgressList = ({ languageStats }) => {
  return (
    <div
      className="space-y-3 mb-4 flex-grow overflow-y-auto"
      style={{ maxHeight: "280px" }}
    >
      {Object.entries(languageStats).map(([languageCode, stats]) => (
        <LanguageProgress
          key={languageCode}
          code={languageCode}
          translated={stats.translated}
          total={stats.total}
        />
      ))}
    </div>
  );
};

export default LanguageProgressList;
