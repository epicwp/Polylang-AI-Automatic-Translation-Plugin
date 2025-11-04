import { useState } from "@wordpress/element";

const AutoTranslateToggle = ({ enabled, onToggle }) => {
  const [isChanging, setIsChanging] = useState(false);

  const handleToggle = () => {
    setIsChanging(true);
    onToggle(!enabled);

    // Simulate API call
    setTimeout(() => {
      setIsChanging(false);
    }, 500);
  };

  return (
    <div className="flex items-center space-x-3">
      <span className="text-sm font-medium text-gray-700">Auto-Translate:</span>

      <div
        onClick={handleToggle}
        disabled={isChanging}
        className={`
                    relative inline-flex h-6 w-11 items-center rounded-full shadow-sm b-0 px-1
                    transition-colors focus:outline-none focus:ring-1 focus:ring-gray-300
                    ${enabled ? "bg-green-500" : "bg-gray-300"}
                    ${
                      isChanging
                        ? "opacity-50 cursor-not-allowed"
                        : "cursor-pointer"
                    }
                `}
        role="switch"
        aria-checked={enabled}
      >
        <span className="sr-only">Toggle auto-translate</span>
        <span
          className={`
                        inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform
                        ${enabled ? "translate-x-6" : "translate-x-1"}
                    `}
        />
      </div>

      <span
        className={`text-sm font-medium ${
          enabled ? "text-green-600" : "text-gray-500"
        }`}
      >
        {enabled ? "ON" : "OFF"}
      </span>

      {enabled && (
        <span className="flex items-center text-xs text-gray-500">
          <span className="dashicons dashicons-info-outline mr-1"></span>
          Processing automatically
        </span>
      )}
    </div>
  );
};

export default AutoTranslateToggle;
