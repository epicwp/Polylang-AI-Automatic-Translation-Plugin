# SOP: Adding AI Translation Providers

Standard operating procedure for adding new AI translation providers to PLLAT.

**Related docs:**
- [Project Architecture](../System/project_architecture.md)
- [Translation Workflow](../System/translation_workflow.md)

---

## Overview

PLLAT uses a provider-based architecture for AI translation services. The system supports multiple AI providers through a unified interface, making it easy to add new providers without modifying core translation logic.

**Current Providers:**
- Claude (Anthropic)
- OpenAI (GPT-3.5, GPT-4)
- Gemini (Google)
- OpenRouter (Multi-model proxy)

---

## Provider Architecture

```
AI_Provider_Registry (Global Registry)
├── register_provider($id, $config)
└── get_provider($id)

AI_Provider_Factory
└── create_from_settings($settings) → AI_Provider
    └── get_client() → AI_Client

AI_Provider (Base Class)
├── get_client() → AI_Client
├── get_default_model() → string
└── get_available_models() → array

Translator (Consumer)
└── translate($tasks, $context)
    └── AI_Client::chat()->create($request)
```

---

## Step-by-Step Guide

### Step 1: Create Provider Class

Create a new provider class that extends the base `AI_Provider` class.

**File:** `src/Modules/Translator/Providers/YourProvider_Provider.php`

```php
<?php
declare(strict_types=1);

namespace PLLAT\Translator\Providers;

use PLLAT\Settings\Services\Settings_Service;
use PLLAT\Translator\Services\AI_Client;
use OpenAI;

/**
 * YourProvider AI translation provider.
 */
class YourProvider_Provider extends AI_Provider {
    /**
     * Provider ID.
     */
    public const PROVIDER_ID = 'yourprovider';

    /**
     * Constructor.
     *
     * @param Settings_Service $settings_service Settings service.
     */
    public function __construct(
        private Settings_Service $settings_service
    ) {
        parent::__construct( $settings_service );
    }

    /**
     * Get the AI client instance.
     *
     * @return AI_Client
     */
    public function get_client(): AI_Client {
        $api_key = $this->settings_service->get( 'yourprovider_api_key' );

        // Option 1: Use OpenAI client with custom base URI
        return OpenAI::factory()
            ->withApiKey( $api_key )
            ->withBaseUri( 'https://api.yourprovider.com/v1' )
            ->withHttpHeader( 'X-Custom-Header', 'value' )
            ->make();

        // Option 2: Use custom HTTP client (Guzzle)
        // return new \GuzzleHttp\Client( array(
        //     'base_uri' => 'https://api.yourprovider.com/v1',
        //     'headers'  => array(
        //         'Authorization' => 'Bearer ' . $api_key,
        //         'Content-Type'  => 'application/json',
        //     ),
        // ) );
    }

    /**
     * Get the default model for this provider.
     *
     * @return string
     */
    public function get_default_model(): string {
        return 'yourprovider-model-v1';
    }

    /**
     * Get available models for this provider.
     *
     * @return array<string,string> Model ID => Model Name
     */
    public function get_available_models(): array {
        return array(
            'yourprovider-model-v1'    => 'YourProvider Model v1 (Fast)',
            'yourprovider-model-v2'    => 'YourProvider Model v2 (Accurate)',
            'yourprovider-model-large' => 'YourProvider Large (Premium)',
        );
    }

    /**
     * Get the provider display name.
     *
     * @return string
     */
    public function get_provider_name(): string {
        return 'YourProvider';
    }
}
```

---

### Step 2: Register Provider

Register the provider in the `AI_Provider_Registry`.

**File:** `src/Modules/Translator/Services/AI_Provider_Registry.php`

```php
public function __construct() {
    // Register all providers
    $this->register_provider( 'claude', Claude_Provider::class );
    $this->register_provider( 'openai', OpenAI_Provider::class );
    $this->register_provider( 'gemini', Gemini_Provider::class );
    $this->register_provider( 'openrouter', OpenRouter_Provider::class );
    $this->register_provider( 'yourprovider', YourProvider_Provider::class );  // ← Add here
}
```

---

### Step 3: Add Settings Fields

Add settings fields for API key and model selection.

**File:** `src/Modules/Settings/Services/Settings_Form.php`

**Add Provider Tab:**

```php
/**
 * Get settings tabs.
 *
 * @return array<string,string>
 */
private function get_tabs(): array {
    return array(
        'general'      => __( 'General', 'polylang-ai-autotranslate' ),
        'claude'       => __( 'Claude', 'polylang-ai-autotranslate' ),
        'openai'       => __( 'OpenAI', 'polylang-ai-autotranslate' ),
        'gemini'       => __( 'Gemini', 'polylang-ai-autotranslate' ),
        'openrouter'   => __( 'OpenRouter', 'polylang-ai-autotranslate' ),
        'yourprovider' => __( 'YourProvider', 'polylang-ai-autotranslate' ),  // ← Add tab
    );
}
```

**Add Settings Fields:**

```php
/**
 * Get settings fields.
 *
 * @return array<string,array<string,mixed>>
 */
private function get_fields(): array {
    $fields = array(
        // ... existing fields ...

        // YourProvider Settings
        'yourprovider_api_key' => array(
            'label'       => __( 'API Key', 'polylang-ai-autotranslate' ),
            'type'        => 'password',
            'tab'         => 'yourprovider',
            'description' => __( 'Enter your YourProvider API key.', 'polylang-ai-autotranslate' ),
            'sanitize'    => 'sanitize_text_field',
        ),

        'yourprovider_model' => array(
            'label'       => __( 'Model', 'polylang-ai-autotranslate' ),
            'type'        => 'select',
            'tab'         => 'yourprovider',
            'options'     => $this->get_yourprovider_models(),
            'default'     => 'yourprovider-model-v1',
            'description' => __( 'Select the YourProvider model to use.', 'polylang-ai-autotranslate' ),
        ),
    );

    return $fields;
}

/**
 * Get YourProvider models.
 *
 * @return array<string,string>
 */
private function get_yourprovider_models(): array {
    try {
        $provider = new YourProvider_Provider( $this->settings_service );
        return $provider->get_available_models();
    } catch ( \Exception $e ) {
        return array(
            'yourprovider-model-v1' => 'YourProvider Model v1 (Default)',
        );
    }
}
```

---

### Step 4: Update Provider Factory

Update the factory to support the new provider.

**File:** `src/Modules/Translator/Services/AI_Provider_Factory.php`

```php
/**
 * Create AI provider from settings.
 *
 * @param Settings_Service $settings_service Settings service.
 * @return AI_Provider
 * @throws \Exception If provider cannot be created.
 */
public static function create_from_settings( Settings_Service $settings_service ): AI_Provider {
    $provider_id = $settings_service->get( 'ai_provider', 'claude' );

    $registry = new AI_Provider_Registry();

    $provider_class = $registry->get_provider( $provider_id );
    if ( ! $provider_class ) {
        throw new \Exception( "Unknown AI provider: {$provider_id}" );
    }

    return new $provider_class( $settings_service );
}

/**
 * Check if provider can be created from settings.
 *
 * @param Settings_Service $settings_service Settings service.
 * @return bool
 */
public static function can_create_from_settings( Settings_Service $settings_service ): bool {
    $provider_id = $settings_service->get( 'ai_provider', '' );

    if ( empty( $provider_id ) ) {
        return false;
    }

    // Check if API key is set for the provider
    $api_key_field = $provider_id . '_api_key';
    $api_key = $settings_service->get( $api_key_field, '' );

    return ! empty( $api_key );
}
```

---

### Step 5: Test Provider Integration

Test the new provider using WP-CLI.

**1. Set API Key:**

```bash
wp option update pllat_settings --format=json '{"ai_provider":"yourprovider","yourprovider_api_key":"your-api-key-here","yourprovider_model":"yourprovider-model-v1"}'
```

**2. Test Translation:**

```bash
wp eval '
$settings_service = \XWP\DI\App::make(\PLLAT\Settings\Services\Settings_Service::class);
$factory = \PLLAT\Translator\Services\AI_Provider_Factory::class;

if ( $factory::can_create_from_settings( $settings_service ) ) {
    $provider = $factory::create_from_settings( $settings_service );
    $client = $provider->get_client();

    echo "Provider: " . $provider->get_provider_name() . "\n";
    echo "Model: " . $provider->get_default_model() . "\n";
    echo "Client: " . get_class( $client ) . "\n";

    // Test translation
    $response = $client->chat()->create(array(
        "model" => $provider->get_default_model(),
        "messages" => array(
            array("role" => "user", "content" => "Translate \"Hello\" to Spanish"),
        ),
    ));

    echo "Translation: " . $response->choices[0]->message->content . "\n";
} else {
    echo "Provider not configured\n";
}
'
```

---

## Advanced: Custom Request Formatting

If your provider requires custom request formatting (not OpenAI-compatible), you'll need to override the translation logic.

### Option 1: Custom Client Wrapper

Create a wrapper class that adapts your provider's API to the OpenAI client interface.

**File:** `src/Modules/Translator/Services/YourProvider_Client.php`

```php
<?php
declare(strict_types=1);

namespace PLLAT\Translator\Services;

use GuzzleHttp\Client;

/**
 * Custom client wrapper for YourProvider.
 */
class YourProvider_Client {
    /**
     * Constructor.
     *
     * @param Client $http_client HTTP client.
     * @param string $api_key API key.
     */
    public function __construct(
        private Client $http_client,
        private string $api_key
    ) {}

    /**
     * Create a chat completion.
     *
     * @param array<string,mixed> $request Request data.
     * @return object Response object.
     */
    public function chat_create( array $request ): object {
        // Transform request to provider format
        $provider_request = $this->transform_request( $request );

        // Make API call
        $response = $this->http_client->post( '/chat/completions', array(
            'json' => $provider_request,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
        ) );

        // Transform response to OpenAI format
        $data = json_decode( $response->getBody()->getContents() );
        return $this->transform_response( $data );
    }

    /**
     * Transform request to provider format.
     *
     * @param array<string,mixed> $request OpenAI format request.
     * @return array<string,mixed> Provider format request.
     */
    private function transform_request( array $request ): array {
        // Example: Convert OpenAI format to provider format
        return array(
            'model_id' => $request['model'],
            'prompt' => $request['messages'][0]['content'] ?? '',
            'temperature' => $request['temperature'] ?? 0.3,
        );
    }

    /**
     * Transform response to OpenAI format.
     *
     * @param object $response Provider format response.
     * @return object OpenAI format response.
     */
    private function transform_response( object $response ): object {
        // Example: Convert provider format to OpenAI format
        return (object) array(
            'choices' => array(
                (object) array(
                    'message' => (object) array(
                        'content' => $response->output ?? '',
                    ),
                ),
            ),
        );
    }
}
```

**Update Provider Class:**

```php
public function get_client(): AI_Client {
    $api_key = $this->settings_service->get( 'yourprovider_api_key' );

    $http_client = new \GuzzleHttp\Client( array(
        'base_uri' => 'https://api.yourprovider.com/v1',
    ) );

    return new YourProvider_Client( $http_client, $api_key );
}
```

---

## Testing Checklist

Before deploying a new provider:

- [ ] Provider class created and extends `AI_Provider`
- [ ] Provider registered in `AI_Provider_Registry`
- [ ] Settings fields added (API key, model selection)
- [ ] Settings tab added to UI
- [ ] Factory updated to support provider
- [ ] Tested API key validation
- [ ] Tested model selection
- [ ] Tested translation request/response
- [ ] Error handling implemented
- [ ] Documentation updated

---

## Example: Real Provider Implementation

### Claude Provider

**File:** `src/Modules/Translator/Providers/Claude_Provider.php`

```php
<?php
declare(strict_types=1);

namespace PLLAT\Translator\Providers;

use PLLAT\Settings\Services\Settings_Service;
use PLLAT\Translator\Services\AI_Client;
use OpenAI;

/**
 * Claude AI translation provider (via OpenRouter or direct API).
 */
class Claude_Provider extends AI_Provider {
    public const PROVIDER_ID = 'claude';

    public function __construct(
        private Settings_Service $settings_service
    ) {
        parent::__construct( $settings_service );
    }

    public function get_client(): AI_Client {
        $api_key = $this->settings_service->get( 'claude_api_key' );
        $use_openrouter = $this->settings_service->get( 'claude_use_openrouter', false );

        if ( $use_openrouter ) {
            // Use OpenRouter proxy
            return OpenAI::factory()
                ->withApiKey( $api_key )
                ->withBaseUri( 'https://openrouter.ai/api/v1' )
                ->make();
        }

        // Use direct Anthropic API
        return OpenAI::factory()
            ->withApiKey( $api_key )
            ->withBaseUri( 'https://api.anthropic.com/v1' )
            ->withHttpHeader( 'anthropic-version', '2023-06-01' )
            ->make();
    }

    public function get_default_model(): string {
        return 'claude-3-5-sonnet-20241022';
    }

    public function get_available_models(): array {
        return array(
            'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Recommended)',
            'claude-3-opus-20240229'     => 'Claude 3 Opus (Most Capable)',
            'claude-3-haiku-20240307'    => 'Claude 3 Haiku (Fast)',
        );
    }

    public function get_provider_name(): string {
        return 'Claude (Anthropic)';
    }
}
```

---

## Common Pitfalls

### Pitfall 1: API Compatibility

**Problem:** Provider API is not OpenAI-compatible.

**Solution:** Create custom client wrapper (see "Custom Request Formatting" above).

---

### Pitfall 2: Missing Error Handling

**Problem:** API errors crash translation jobs.

**Solution:** Wrap API calls in try-catch blocks.

```php
public function get_client(): AI_Client {
    $api_key = $this->settings_service->get( 'yourprovider_api_key' );

    if ( empty( $api_key ) ) {
        throw new \Exception( 'YourProvider API key not configured' );
    }

    try {
        return OpenAI::factory()
            ->withApiKey( $api_key )
            ->withBaseUri( 'https://api.yourprovider.com/v1' )
            ->make();
    } catch ( \Exception $e ) {
        throw new \Exception( 'Failed to create YourProvider client: ' . $e->getMessage() );
    }
}
```

---

### Pitfall 3: Model Availability

**Problem:** Provider changes available models, breaking settings.

**Solution:** Use fallback default model.

```php
public function get_default_model(): string {
    $model = $this->settings_service->get( 'yourprovider_model', 'yourprovider-model-v1' );

    // Validate model exists
    $available_models = $this->get_available_models();
    if ( ! isset( $available_models[ $model ] ) ) {
        // Fallback to first available model
        $model = array_key_first( $available_models );
    }

    return $model;
}
```

---

**Last Updated:** 2025-10-24
**Version:** 1.0.0
**Status:** ✅ Production-Ready
