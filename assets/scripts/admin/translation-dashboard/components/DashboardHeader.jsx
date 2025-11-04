import AutoTranslateToggle from "./AutoTranslateToggle";
import OverallProgress from "./OverallProgress";

const DashboardHeader = ({
  autoTranslate,
  autoTranslateToggleHandler,
  data,
}) => {
  const licenseValid = window.pllat?.licenseValid || false;

  return (
    <div className="bg-white p-6 rounded-lg border border-[#c3c4c7] shadow mb-6">
      <div className="flex items-center justify-between mb-4">
        <div>
          <h2 className="text-2xl font-semibold !pt-0 !mt-0 mb-2">
            Translation Dashboard
          </h2>
          <p className="text-gray-600">
            Manage translations across all your content types and languages
          </p>
        </div>
        {licenseValid && (
          <AutoTranslateToggle
            enabled={autoTranslate}
            onToggle={autoTranslateToggleHandler}
          />
        )}
      </div>

      <OverallProgress data={data.overall} />
    </div>
  );
};

export default DashboardHeader;
