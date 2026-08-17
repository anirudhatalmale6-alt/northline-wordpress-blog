<?php
/**
 * Northline theme functions.
 *
 * @package Northline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NORTHLINE_VERSION', '1.0.0' );

/**
 * Load the child stylesheet after the parent's.
 */
function northline_enqueue_styles() {
	wp_enqueue_style(
		'northline-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'twentytwentyfive-style' ),
		NORTHLINE_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'northline_enqueue_styles', 20 );

/**
 * Use the same stylesheet inside the block editor so the canvas matches the front end.
 */
function northline_editor_styles() {
	add_editor_style( 'style.css' );
}
add_action( 'after_setup_theme', 'northline_editor_styles' );

/**
 * Theme supports that the parent does not already declare.
 */
function northline_setup() {
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'style', 'script' ) );

	// A 3:2 crop used by the card grid, and a wide crop for the lead story.
	add_image_size( 'northline-card', 800, 533, true );
	add_image_size( 'northline-lead', 1400, 933, true );
}
add_action( 'after_setup_theme', 'northline_setup' );

/**
 * Block templates and patterns are rendered by do_blocks() without ever passing
 * through the_content(), so a core/shortcode block inside one prints its own
 * source. Expand it here instead. Post content is unaffected: by the time
 * the_content() runs do_shortcode(), there is nothing left to expand.
 *
 * @param string $content Rendered block HTML.
 * @param array  $block   Parsed block.
 * @return string
 */
function northline_render_shortcode_block( $content, $block ) {
	if ( isset( $block['blockName'] ) && 'core/shortcode' === $block['blockName'] ) {
		return do_shortcode( $content );
	}

	return $content;
}
add_filter( 'render_block', 'northline_render_shortcode_block', 10, 2 );

/**
 * Estimated reading time for a post, in whole minutes (minimum 1).
 *
 * @param int $post_id Post ID.
 * @return int Minutes.
 */
function northline_reading_minutes( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post ) {
		return 1;
	}

	$text  = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	$words = preg_match_all( '/[\p{L}\p{N}\'’-]+/u', $text );

	if ( ! $words ) {
		return 1;
	}

	// 220 wpm is a common average for on-screen prose.
	return max( 1, (int) round( $words / 220 ) );
}

/**
 * Block binding source so a paragraph in a template can print "6 min read".
 *
 * Usage in a template:
 *   <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"northline/reading-time"}}}} -->
 */
function northline_register_bindings() {
	if ( ! function_exists( 'register_block_bindings_source' ) ) {
		return;
	}

	register_block_bindings_source(
		'northline/reading-time',
		array(
			'label'              => __( 'Reading time', 'northline' ),
			'get_value_callback' => static function ( $source_args, $block_instance ) {
				$post_id = isset( $block_instance->context['postId'] )
					? (int) $block_instance->context['postId']
					: get_the_ID();

				if ( ! $post_id ) {
					return '';
				}

				$minutes = northline_reading_minutes( $post_id );

				/* translators: %d: number of minutes. */
				return sprintf( _n( '%d min read', '%d min read', $minutes, 'northline' ), $minutes );
			},
			'uses_context'       => array( 'postId' ),
		)
	);
}
add_action( 'init', 'northline_register_bindings' );

/**
 * ID of a Contact Form 7 form looked up by title, if the plugin is active.
 *
 * @param string $title Form title.
 * @return int Form ID, or 0.
 */
function northline_cf7_form_id( $title ) {
	if ( ! post_type_exists( 'wpcf7_contact_form' ) ) {
		return 0;
	}

	$forms = get_posts(
		array(
			'post_type'      => 'wpcf7_contact_form',
			'title'          => $title,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	return $forms ? (int) $forms[0] : 0;
}

/**
 * Convenience wrapper used by the newsletter pattern.
 *
 * @return int
 */
function northline_newsletter_form_id() {
	return northline_cf7_form_id( 'Newsletter' );
}

/**
 * [northline_contact_form] — renders the CF7 form titled "Contact" without
 * hard-coding an ID into page content, so the page survives a re-import.
 *
 * @return string
 */
function northline_contact_form_shortcode() {
	$id = northline_cf7_form_id( 'Contact' );

	if ( ! $id ) {
		return '<p>' . esc_html__( 'The contact form is not set up yet.', 'northline' ) . '</p>';
	}

	return do_shortcode( sprintf( '[contact-form-7 id="%d" title="Contact"]', $id ) );
}
add_shortcode( 'northline_contact_form', 'northline_contact_form_shortcode' );

/**
 * "Keep reading" on a single post: same category first, current post excluded,
 * topped up with recent posts when the category is thin.
 *
 * Bound to the query block whose namespace is northline/related.
 *
 * @param array    $query Query vars built from the block.
 * @param WP_Block $block Block instance.
 * @return array
 */
function northline_related_query_vars( $query, $block ) {
	if ( ! is_singular( 'post' ) ) {
		return $query;
	}

	// The filter runs on the inner post-template block, so the marker has to
	// travel in the query context rather than as a query-block attribute.
	$namespace = $block->context['query']['namespace'] ?? '';

	if ( 'northline/related' !== $namespace ) {
		return $query;
	}

	$current_id = get_queried_object_id();

	$query['post__not_in']    = array_merge( (array) ( $query['post__not_in'] ?? array() ), array( $current_id ) );
	$query['ignore_sticky_posts'] = true;

	$categories = wp_get_post_categories( $current_id );

	if ( $categories ) {
		$per_page  = isset( $query['posts_per_page'] ) ? (int) $query['posts_per_page'] : 3;
		$in_cat    = get_posts(
			array(
				'category__in'        => $categories,
				'post__not_in'        => array( $current_id ),
				'posts_per_page'      => $per_page,
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		// Only narrow the query when the category actually fills the row.
		if ( count( $in_cat ) >= $per_page ) {
			$query['category__in'] = $categories;
		}
	}

	return $query;
}
add_filter( 'query_loop_block_query_vars', 'northline_related_query_vars', 10, 2 );

/**
 * Register the theme's own pattern categories.
 */
function northline_register_pattern_categories() {
	register_block_pattern_category(
		'northline',
		array(
			'label'       => __( 'Northline', 'northline' ),
			'description' => __( 'Blog sections built for the Northline layout.', 'northline' ),
		)
	);
}
add_action( 'init', 'northline_register_pattern_categories' );

/**
 * Excerpt length and ellipsis tuned for the card grid.
 */
function northline_excerpt_length() {
	return 26;
}
add_filter( 'excerpt_length', 'northline_excerpt_length' );

function northline_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'northline_excerpt_more' );
