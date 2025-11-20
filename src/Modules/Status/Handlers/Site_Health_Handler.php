<?php
/**
 * Site_Health_Handler class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Status
 */

namespace PLLAT\Status\Handlers;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Status\Services\Cron_Status_Service;
use PLLAT\Status\Services\Status_Info_Service;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;

/**
 * Integrates plugin status information with WordPress Site Health.
 */
#[Handler( tag: 'init', priority: 10, context: Handler::CTX_ADMIN )]
class Site_Health_Handler {
    /**
     * Status info service.
     *
     * @var Status_Info_Service
     */
    private Status_Info_Service $status_info_service;

    /**
     * Cron status service.
     *
     * @var Cron_Status_Service
     */
    private Cron_Status_Service $cron_status_service;

    /**
     * Constructor.
     *
     * @param Status_Info_Service $status_info_service The status info service.
     * @param Cron_Status_Service $cron_status_service The cron status service.
     */
    public function __construct(
        Status_Info_Service $status_info_service,
        Cron_Status_Service $cron_status_service,
    ) {
        $this->status_info_service = $status_info_service;
        $this->cron_status_service = $cron_status_service;
    }

    /**
     * Add plugin debug information to Site Health Info tab.
     *
     * @param array<string,mixed> $debug_info Existing debug information.
     * @return array<string,mixed> Modified debug information.
     */
    #[Filter( tag: 'debug_information' )]
    public function add_debug_info( array $debug_info ): array {
        $debug_info['pllat'] = array(
            'fields' => $this->status_info_service->get_debug_info(),
            'label'  => \__( 'Polylang AI Translation', 'epicwp-ai-translation-for-polylang' ),
        );

        return $debug_info;
    }

    /**
     * Add Site Health test for cron configuration.
     *
     * @param array<string,mixed> $tests Existing Site Health tests.
     * @return array<string,mixed> Modified Site Health tests.
     */
    #[Filter( tag: 'site_status_tests' )]
    public function add_site_health_tests( array $tests ): array {
        $tests['direct']['pllat_cron_check'] = array(
            'label' => \__( 'Polylang AI Translation - Cron Configuration', 'epicwp-ai-translation-for-polylang' ),
            'test'  => array( $this, 'test_cron_configuration' ),
        );

        return $tests;
    }

    /**
     * Test WordPress cron configuration.
     *
     * @return array<string,mixed>
     */
    public function test_cron_configuration(): array {
        $cron_type = $this->cron_status_service->get_cron_type();

        if ( $cron_type['is_external'] ) {
            return array(
                'badge'       => array(
                    'color' => 'blue',
                    'label' => \__( 'Performance', 'epicwp-ai-translation-for-polylang' ),
                ),
                'description' => \sprintf(
                    '<p>%s</p>',
                    \__(
                        'Your site is using external (server) cron, which provides better performance and reliability for background tasks.',
                        'epicwp-ai-translation-for-polylang',
                    ),
                ),
                'label'       => \__( 'External cron is configured', 'epicwp-ai-translation-for-polylang' ),
                'status'      => 'good',
                'test'        => 'pllat_cron_check',
            );
        }

        return array(
            'actions'     => \sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                'https://www.epicwpsolutions.com/how-to-set-up-server-cron-for-better-plugin-performance/',
                \__( 'View Setup Guide', 'epicwp-ai-translation-for-polylang' ),
            ),
            'badge'       => array(
                'color' => 'blue',
                'label' => \__( 'Performance', 'epicwp-ai-translation-for-polylang' ),
            ),
            'description' => \sprintf(
                '<p>%s</p><p>%s</p>',
                \__(
                    'Your site is currently using WordPress\' internal cron system. For better performance and reliability of translation tasks, we strongly recommend configuring external (server) cron.',
                    'epicwp-ai-translation-for-polylang',
                ),
                \sprintf(
                    /* translators: %s: URL to external cron setup guide */
                    \__(
                        'Learn how to set up external cron: <a href="%s" target="_blank" rel="noopener noreferrer">Server Cron Setup Guide</a>',
                        'epicwp-ai-translation-for-polylang',
                    ),
                    'https://www.epicwpsolutions.com/how-to-set-up-server-cron-for-better-plugin-performance/',
                ),
            ),
            'label'       => \__(
                'Consider using external cron for better performance',
                'epicwp-ai-translation-for-polylang',
            ),
            'status'      => 'recommended',
            'test'        => 'pllat_cron_check',
        );
    }
}
