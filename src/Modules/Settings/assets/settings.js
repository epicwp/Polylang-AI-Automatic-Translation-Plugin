jQuery(document).ready(function($) {
    // Disable non-OpenAI providers if license is not valid
    function applyLicenseRestrictions() {
        const licenseValid = pllat_settings.licenseValid;

        if (!licenseValid) {
            $('#pllat_translator_api option').each(function() {
                const optionValue = $(this).val();
                if (optionValue !== 'openai') {
                    $(this).prop('disabled', true);
                    const currentText = $(this).text();
                    if (!currentText.includes('(only Pro)')) {
                        $(this).text(currentText + ' (only Pro)');
                    }
                }
            });
        }
    }

    // Show/hide API key fields based on selected provider
    function toggleApiKeyFields() {
        const activeApi = $('#pllat_translator_api').val();
        $('.api-key-row').closest('tr').hide();
        $('.api-key-row[data-api="' + activeApi + '"]').closest('tr').show();

        // Update model options
        updateModelOptions(activeApi);
    }

    function updateModelOptions(api) {
        const models = pllat_settings.models;
        const currentModels = pllat_settings.current_models;
        const defaults = pllat_settings.defaults;
        const modelSelect = $('#pllat_translation_model');
                        
        modelSelect.empty();
        
        if (models[api]) {
            // Get the current selected model for this API, fallback to default
            const currentModel = currentModels[api] || defaults[api] || Object.keys(models[api])[0];
            
            Object.entries(models[api]).forEach(([value, label]) => {
                const option = new Option(label, value, false, value === currentModel);
                modelSelect.append(option);
            });
            
            // Set the current model as selected
            modelSelect.val(currentModel);
            
            // Update the select name attribute for the current API
            modelSelect.attr('name', 'pllat_' + api + '_translation_model');
        }
    }

    // Apply license restrictions on load
    applyLicenseRestrictions();

    $('#pllat_translator_api').on('change', toggleApiKeyFields);
    toggleApiKeyFields();
});