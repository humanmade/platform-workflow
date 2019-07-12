<?php
/**
 * Workflow Module.
 *
 * @package altis/workflow
 */

namespace Altis\Workflow; // @codingStandardsIgnoreLine

use function Altis\register_module;

require_once __DIR__ . '/inc/namespace.php';

// Do not initialise if plugin.php hasn't been included yet.
if ( ! function_exists( 'add_action' ) ) {
	return;
}

add_action( 'altis.modules.init', function () {
	$default_settings = [
		'enabled' => true,
		'posts-workflow' => false,
		'editorial-workflow' => false,
		'publication-checklist' => [
			'enabled' => true,
			'block_on_failing' => false,
			'hide_column' => false,
		],
	];
	register_module( 'workflow', __DIR__, 'Workflow', $default_settings, __NAMESPACE__ . '\\bootstrap' );
} );
