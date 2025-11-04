const RestartableState = ({ status, onStartTranslation }) => {
  const statusConfig = {
    cancelled: {
      icon: "dashicons-controls-repeat",
      label: "Start new translation",
    },
    failed: {
      icon: "dashicons-warning",
      label: "Retry translation",
    },
  };

  const config = statusConfig[status];

  return (
    <button
      className="w-full !px-4 !py-2 !rounded !font-medium button button-primary cursor-pointer"
      onClick={() => onStartTranslation({ isDryRun: false })}
    >
      <span className="flex items-center justify-center">
        <span className={`dashicons ${config.icon} mr-2`}></span>
        {config.label}
      </span>
    </button>
  );
};

export default RestartableState;
