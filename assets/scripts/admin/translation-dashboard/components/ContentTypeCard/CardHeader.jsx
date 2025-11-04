const CardHeader = ({ icon, title, completionPercentage }) => {
  return (
    <div className="flex items-center justify-between mb-4 pb-3 border-b border-gray-200">
      <div className="flex items-center">
        <span className={`dashicons ${icon} mr-2 text-gray-600`}></span>
        <h3 className="text-base font-semibold mt-0 mb-0">{title}</h3>
      </div>
      <span className="text-sm text-gray-500">{completionPercentage}%</span>
    </div>
  );
};

export default CardHeader;
