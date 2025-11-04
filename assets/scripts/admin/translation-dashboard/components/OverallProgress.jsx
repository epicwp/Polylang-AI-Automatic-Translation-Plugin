const OverallProgress = ({ data }) => {
  const { translated = 0, total = 0 } = data;
  const percentage = total > 0 ? Math.round((translated / total) * 100) : 0;
  const remaining = total - translated;

  return (
    <div className="mt-4">
      <div className="flex items-center justify-between mb-2">
        <span className="text-sm font-medium text-gray-700">
          Overall Translation Progress
        </span>
        <span className="text-sm text-gray-500">
          {translated} of {total} items translated
        </span>
      </div>

      <div className="relative">
        <div className="w-full bg-gray-200 rounded-full h-4">
          <div
            className="bg-blue-600 h-4 rounded-full transition-all duration-500 relative"
            style={{ width: `${percentage}%` }}
          >
            <div className="absolute inset-0 rounded-full overflow-hidden">
              <div className="h-full w-full bg-gradient-to-r from-transparent via-white/20 to-transparent animate-shimmer" />
            </div>
          </div>
        </div>

        <div className="absolute inset-0 flex items-center justify-center">
          <span className="text-xs font-semibold text-white">
            {percentage}%
          </span>
        </div>
      </div>

      <div className="flex justify-between mt-2 text-xs text-gray-600">
        <span>
          <span className="font-medium">{remaining}</span> items remaining
        </span>
        <span>
          <span className="font-medium">{translated}</span> completed
        </span>
      </div>
    </div>
  );
};

export default OverallProgress;
