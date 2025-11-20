<?php
declare(strict_types=1);

namespace PLLAT\Translator\Services;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use OpenAI;
use OpenAI\Client;

/**
 * Simple AI Client for OpenAI API interactions.
 */
class AI_Client {
    /**
     * The OpenAI client.
     *
     * @var Client
     */
    private Client $client;

    /**
     * Parse the JSON response content.
     *
     * - Remove ```json and ``` from the content.
     *
     * @param string $content The content to parse.
     * @return array The parsed content.
     */
    public static function parse_json_response_content( string $content ): array {
        $content = \preg_replace( '/```json\s*(.*?)\s*```/s', '$1', $content );
        return \json_decode( $content, true );
    }

    /**
     * Constructor.
     *
     * @param string $api_key The API key.
     * @param string $base_url The base URL.
     * @param string $model The model.
     */
    public function __construct(
        private string $api_key,
        private string $base_url,
        private string $model,
    ) {
        $this->client = OpenAI::factory()
            ->withApiKey( $this->api_key )
            ->withBaseUri( $this->base_url )
            ->make();
    }

    /**
     * Execute chat completion with optional tool calling.
     *
     * @param array $messages     Chat messages.
     * @param array $params       Parameters for the chat completion
     *                            - temperature: The temperature for the chat completion
     *                            - response_format: The response format for the chat completion
     * @return array Response data
     */
    public function chat_completion( array $messages, $params = array() ): array {
        $params = array(
            'messages'    => $messages,
            'model'       => $this->model,
            'temperature' => $params['temperature'] ?? 0.7,
        );

        if ( isset( $params['response_format'] ) ) {
            $params['response_format'] = array(
                'json_schema' => $params['response_format'],
                'type'        => 'json_schema',
            );
        }

        $response       = $this->client->chat()->create( $params );
        $response_array = $response->toArray();
        return $response_array;
    }

    /**
     * Get the configured model.
     *
     * @return string The model.
     */
    public function get_model(): string {
        return $this->model;
    }

    /**
     * Get raw OpenAI client.
     *
     * @return Client The OpenAI client.
     */
    public function get_client(): Client {
        return $this->client;
    }
}
