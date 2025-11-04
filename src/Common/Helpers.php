<?php
declare(strict_types=1);

namespace PLLAT\Common;

/**
 * Helper functions for the plugin.
 */
class Helpers {
    /**
     * Get the active Polylang post types
     *
     * @return array The active Polylang post types.
     */
    public static function get_active_post_types(): array {
        $post_types = \array_filter(
            \get_post_types(),
            static fn( $post_type ) => \pll_is_translated_post_type( $post_type ),
        );
        return \array_values( $post_types );
    }

    /**
     * Get the available Polylang post types that are not excluded
     *
     * @return array The available Polylang post types.
     */
    public static function get_available_post_types(): array {
        $exclude_post_types = array(
            'wp_block',
            'wp_template_part',
            'wp_navigation',
            'shop_order_placehold',
            'shop_order',
        );
        $post_types         = \array_filter(
            self::get_active_post_types(),
            static fn( $post_type ) => ! \in_array( $post_type, $exclude_post_types ),
        );
        return \array_values( $post_types );
    }

    /**
     * Get the active Polylang taxonomies
     *
     * @return array The active Polylang taxonomies.
     */
    public static function get_active_taxonomies(): array {
        $taxonomies = \array_filter(
            \get_taxonomies(),
            static fn( $taxonomy ) => \pll_is_translated_taxonomy( $taxonomy ),
        );
        return \array_values( $taxonomies );
    }

    /**
     * Get the available Polylang taxonomies that are not excluded
     *
     * @return array The available Polylang taxonomies.
     */
    public static function get_available_taxonomies(): array {
        $exclude_taxonomies = array();
        $taxonomies         = \array_filter(
            self::get_active_taxonomies(),
            static fn( $taxonomy ) => ! \in_array( $taxonomy, $exclude_taxonomies ),
        );
        return \array_values( $taxonomies );
    }

    /**
     * Get all meta data as a flatten array
     *
     * @param int $post_id The post ID.
     * @return array The flatten post meta.
     */
    public static function get_flatten_post_meta( int $post_id ): array {
        $post_meta = \get_post_meta( $post_id );
        if ( ! $post_meta ) { // phpcs:ignore SlevomatCodingStandard.ControlStructures.DisallowEmpty.DisallowedEmpty
            return array();
        }
        // @phpstan-ignore-next-line - array_map preserves keys if the input array keys are strings.
        return \array_map(
            static fn( $row ) => $row[0] ?? null,
            $post_meta,
        );
    }

    /**
     * Get all meta data as a flatten array
     *
     * @param int $term_id The term ID.
     * @return array The flatten term meta.
     */
    public static function get_flatten_term_meta( int $term_id ): array {
        $term_meta = \get_term_meta( $term_id );
        if ( ! $term_meta ) { // phpcs:ignore SlevomatCodingStandard.ControlStructures.DisallowEmpty.DisallowedEmpty
            return array();
        }
        // @phpstan-ignore-next-line - array_map preserves keys if the input array keys are strings.
        return \array_map(
            static fn( $row ) => $row[0] ?? null,
            $term_meta,
        );
    }

    /**
     * Helper function to find the changed fields between two arrays
     *
     * @param array $before_array The before array.
     * @param array $after_array The after array.
     * @param array $available_fields The available fields.
     * @return array The changed fields.
     */
    public static function get_changed_fields( array $before_array, array $after_array, array $available_fields ): array {
        $changes = array();
        foreach ( $available_fields as $field ) {
            $before_value = $before_array[ $field ] ?? null;
            $after_value  = $after_array[ $field ] ?? null;

            if ( $before_value === $after_value ) {
                continue;
            }

            $changes[] = $field;
        }
        return $changes;
    }

    /**
     * Adds indexes from starting from 1
     *
     * @param array $strings The strings.
     * @return array The numbered strings.
     */
    public static function number_strings_array( array $strings ): array {
        if ( array() === $strings ) {
            return array();
        }
        // @phpstan-ignore-next-line - array_combine can return false but not here.
        return \array_combine( \range( 1, \count( $strings ) ), $strings );
    }

    /**
     * Encodes an array to json
     *
     * @param array $data The data.
     * @param int   $options The options.
     * @return string The encoded json.
     */
    public static function encode_json( $data, int $options = \JSON_UNESCAPED_UNICODE ): string {
        $json = \wp_json_encode( $data, $options );
        if ( false === $json ) {
            // It's okay to not escape json_last_error_msg in an exception message.
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new \Exception( 'Error encoding JSON: ' . \json_last_error_msg() );
        }
        return $json;
    }

    /**
     * Decodes a json string to an array
     *
     * @param string $json The json.
     * @param bool   $associative The associative.
     * @return array The decoded json.
     */
    public static function decode_json( string $json, bool $associative = true ): array {
        $data = \json_decode( $json, $associative );
        if ( null === $data && JSON_ERROR_NONE !== \json_last_error() ) {
            // It's okay to not escape json_last_error_msg in an exception message.
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new \Exception( 'Error decoding JSON: ' . \json_last_error_msg() );
        }
        // If $data is null after decoding (e.g., decoding the string "null"), return empty array.
        return (array) ( $data ?? array() );
    }

    /**
     * Set the max execution time for the current request.
     *
     * @param int $time_limit The time limit.
     * @param int $desired_limit The desired limit.
     * @return int The max execution time.
     */
    public static function set_max_execution_time( int $time_limit, int $desired_limit = 120 ): int {
        $php_max_execution = (int) \ini_get( 'max_execution_time' );

        // If PHP has unlimited execution time.
        if ( 0 === $php_max_execution ) {
            return $desired_limit;
        }

        // If PHP timeout is very low (less than 30 seconds), keep original.
        if ( $php_max_execution < 30 ) {
            return $time_limit;
        }

        // Calculate safe timeout (leave 20% buffer or minimum 10 seconds).
        $buffer   = \max( 10, (int) ( $php_max_execution * 0.2 ) );
        $safe_max = $php_max_execution - $buffer;

        // Return the minimum of the desired timeout and the safe max.
        return \min( $desired_limit, $safe_max );
    }
}
