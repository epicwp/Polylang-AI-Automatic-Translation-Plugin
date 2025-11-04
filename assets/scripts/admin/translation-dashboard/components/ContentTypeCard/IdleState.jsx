const IdleState = ({ onStartTranslation, dryRunItemCount = 3 }) => {
  const licenseValid = window.pllat?.licenseValid || false;

  return (
    <>
      <button
        className="w-full !px-4 !py-2 !rounded !font-medium button button-primary cursor-pointer"
        onClick={() => onStartTranslation({ isDryRun: false })}
        disabled={!licenseValid}
        style={!licenseValid ? { opacity: 0.6, cursor: 'not-allowed' } : {}}
      >
        {licenseValid ? 'Translate all' : 'Translate all (Only Pro)'}
      </button>

      <button
        className="w-full !px-3 !py-1.5 !rounded !text-sm button button-secondary cursor-pointer hover:!bg-gray-100"
        onClick={() => onStartTranslation({ isDryRun: true })}
        disabled={!licenseValid}
        style={!licenseValid ? { opacity: 0.6, cursor: 'not-allowed' } : {}}
      >
        <span className="flex items-center justify-center">
          <span className="dashicons dashicons-visibility mr-1 text-sm"></span>
          {licenseValid ? `Dry Run (${dryRunItemCount} items)` : `Dry Run (Only Pro)`}
        </span>
      </button>
    </>
  );
};

export default IdleState;
