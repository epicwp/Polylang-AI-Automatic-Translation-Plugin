import { getLanguageData } from "../../shared/utils/languages";

const LanguageProgress = ({ code, translated, total }) => {
  const progress = total > 0 ? Math.round((translated / total) * 100) : 0;
  const remaining = total - translated;
  const isComplete = translated === total;

  // Get language data from Polylang
  const langData = getLanguageData(code);
  const label = langData?.name || langData?.label || code.toUpperCase();
  const flagUrl = langData?.flag;

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between text-sm">
        <div className="flex items-center space-x-2">
          {flagUrl && <img src={flagUrl} alt={label} className="w-4 h-auto" />}
          <span className="font-medium">{label}</span>
        </div>
        <span
          className={`text-xs ${
            isComplete ? "text-green-600" : "text-gray-500"
          }`}
        >
          {translated}/{total}
        </span>
      </div>

      <div className="relative">
        {/* Progress bar background */}
        <div className="w-full bg-gray-200 rounded-full h-2">
          <div
            className={`h-2 rounded-full transition-all duration-500 ${
              isComplete ? "bg-green-500" : "bg-blue-600"
            }`}
            style={{ width: `${progress}%` }}
          />
        </div>

        {/* Percentage label */}
        {!isComplete && (
          <div
            className="absolute -top-0.5 text-[10px] text-gray-600 font-medium"
            style={{ left: `${Math.min(progress, 90)}%` }}
          >
            {progress}%
          </div>
        )}

        {/* Complete checkmark */}
        {isComplete && (
          <div className="absolute right-0 -top-1">
            <span className="dashicons dashicons-yes text-green-600 text-sm"></span>
          </div>
        )}
      </div>

      {/* Remaining count - always show to maintain consistent height */}
      <div className="text-xs text-gray-500">{remaining} remaining</div>
    </div>
  );
};

export default LanguageProgress;
