<?php
/**
 * Default Notications for Workflow Module.
 *
 * @package altis/workflow
 */

namespace Altis\Workflow\Notifications;

use Altis;
use HM\Workflows\Workflow;
use QM_Collectors;

/**
 * Interpret configuration and set up hooks.
 */
function setup() {
	$config = Altis\get_config()['modules']['workflow']['notifications'] ?? null;

	if ( ! is_array( $config ) && ! $config ) {
		return;
	}

	if ( ! is_array( $config ) ) {
		$config = [];
	}

	if ( $config['on-post-published'] ?? false ) {
		add_action( 'hm.workflows.init', __NAMESPACE__ . '\\on_post_published' );
	}

	if ( $config['on-submit-for-review'] ?? false ) {
		add_action( 'hm.workflows.init', __NAMESPACE__ . '\\on_submit_for_review' );
	}

	if ( $config['on-update-assignees'] ?? false ) {
		add_action( 'hm.workflows.init', __NAMESPACE__ . '\\on_update_assignees' );
	}

	if ( $config['on-editorial-comment'] ?? false ) {
		add_action( 'hm.workflows.init', __NAMESPACE__ . '\\on_editorial_comment' );
	}

	add_filter( 'qm/collectors', __NAMESPACE__ . '\\register_workflow_notification_qm_collector' );
	add_filter( 'qm/outputter/html', __NAMESPACE__ . '\\register_workflow_notification_qm_output_html', 10, 2 );

	require_once Altis\ROOT_DIR . '/vendor/humanmade/workflows/plugin.php';
}

/**
 * Post ready for review notification.
 */
function on_submit_for_review() {
	Workflow::register( 'post_submitted_for_review' )
		->when( 'draft_to_pending' )
		->what( __( 'Ready for review: "%title%" by %author%', 'altis' ) )
		->who( [ 'assignee', 'editor' ] )
		->where( 'email' )
		->where( 'dashboard' );
}

/**
 * Post published notification.
 */
function on_post_published() {
	Workflow::register( 'post_published' )
		->when( 'publish_post' )
		->what( __( 'Post published: %title%', 'altis' ) )
		->who( [ 'post_author', 'assignee' ] )
		->where( 'email' )
		->where( 'dashboard' );
}

/**
 * Assignee update notifications.
 */
function on_update_assignees() {
	Workflow::register( 'assignee_updated' )
		->when( [
			'action' => 'add_post_meta',
			'callback' => function ( $object_id, $meta_key, $meta_value ) {
				// Don't trigger for any other meta key.
				if ( $meta_key !== 'assignees' ) {
					return null;
				}

				return [
					'post_id' => absint( $object_id ),
					'assignee' => absint( $meta_value ),
				];
			},
			'accepted_args' => 3,
		] )
		->what(
			function ( $post_id ) {
				// translators: %s = a post title.
				return sprintf( __( '"%s" has been assigned to you', 'altis' ), get_the_title( $post_id ) );
			},
			'',
			[
				'edit' => [
					'text' => __( 'Edit post', 'altis' ),
					'callback_or_url' => function ( $post_id ) {
						return get_edit_post_link( $post_id, 'raw' );
					},
					'args' => function ( $post_id ) {
						return [ 'post_id' => $post_id ];
					},
					'schema' => [
						'post_id' => 'intval',
					],
				],
			]
		)
		->who( function ( $post_id, $assignee ) {
			return get_user_by( 'id', $assignee );
		} )
		->where( 'email' )
		->where( 'dashboard' );
}

/**
 * Editorial comment notifications.
 */
function on_editorial_comment() {
	Workflow::register( 'editorial_comment_added' )
		->when( 'new_editorial_comment' )
		->what(
			// translators: %post.title% = a post title, %comment.author% = comment author's name.
			__( 'New comment on: %post.title% from %comment.author%', 'altis' ),
			'%comment.text%'
		)
		->who( 'assignees' )
		->who( 'post_author' )
		->where( 'email' )
		->where( 'dashboard' );
}

/**
 * Register the collector to QM.
 *
 * @param array $collectors Array of Query Monitor collector objects.
 * @return array
 */
function register_workflow_notification_qm_collector( array $collectors ) : array {
	$collectors['altis-notifications'] = new QM_Collector_Notifications();
	return $collectors;
}

/**
 * Add the Collector Output.
 *
 * @param array $output Output panel objects array.
 * @param QM_Collectors $collectors Collectors factory object.
 * @return array
 */
function register_workflow_notification_qm_output_html( array $output, QM_Collectors $collectors ) : array {
	$collector = $collectors::get( 'altis-notifications' );
	if ( $collector !== null ) {
		$output['altis-notifications'] = new QM_Output_Notifications( $collector );
	}
	return $output;
}
