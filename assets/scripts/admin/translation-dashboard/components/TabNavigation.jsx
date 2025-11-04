const TabNavigation = ({ activeTab, onTabChange }) => {
  const tabs = [
    { id: "overview", label: "Overview", icon: "dashicons-admin-site-alt3" },
    { id: "logs", label: "Translation Logs", icon: "dashicons-list-view" },
  ];

  return (
    <div className="bg-white border border-[#c3c4c7] rounded-lg shadow mb-6">
      <div className="flex border-b border-gray-200">
        {tabs.map((tab, index) => (
          <button
            key={tab.id}
            className={`
              relative flex items-center px-5 py-3 text-sm font-medium transition-colors duration-150
              border-0 rounded-none shadow-none outline-none cursor-pointer
              ${index !== 0 ? "border-l border-gray-200" : ""}
              ${
                activeTab === tab.id
                  ? "text-blue-600 bg-gray-50"
                  : "text-gray-600 hover:text-gray-900 hover:bg-gray-50"
              }
            `}
            style={{
              boxShadow: "none",
              WebkitAppearance: "none",
              appearance: "none",
              background: activeTab === tab.id ? "#f9fafb" : "transparent",
            }}
            onClick={() => onTabChange(tab.id)}
          >
            <span
              className={`dashicons ${tab.icon} mr-2 ${
                activeTab === tab.id ? "text-blue-600" : "text-gray-400"
              }`}
            ></span>
            {tab.label}
            {activeTab === tab.id && (
              <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-600"></span>
            )}
          </button>
        ))}
      </div>
    </div>
  );
};

export default TabNavigation;
