<?php
/**
 * Plugin Name: Northline demo — local avatars
 * Description: Serves generated local avatars instead of Gravatar. This exists so the demo renders identically without outbound network access; it is NOT part of the theme and should not be installed on the live site.
 * Version: 1.0.0
 *
 * Drop into wp-content/mu-plugins/ for the demo only.
 *
 * @package Northline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deterministic initials avatar, written once as an SVG file under uploads.
 *
 * A data: URI would be simpler but esc_url() strips the data protocol, so
 * get_avatar() would render src="".
 *
 * @param string $seed Email or name.
 * @return string Public URL, or '' on failure.
 */
function northline_demo_avatar_uri( $seed ) {
	$uploads = wp_get_upload_dir();
	$dir     = $uploads['basedir'] . '/northline-demo-avatars';
	$key     = substr( md5( strtolower( trim( (string) $seed ) ) ), 0, 12 );
	$file    = $dir . '/' . $key . '.svg';
	$url     = $uploads['baseurl'] . '/northline-demo-avatars/' . $key . '.svg';

	if ( file_exists( $file ) ) {
		return $url;
	}

	if ( ! wp_mkdir_p( $dir ) ) {
		return '';
	}

	$svg = northline_demo_avatar_svg( $seed );

	return false === file_put_contents( $file, $svg ) ? '' : $url;
}

/**
 * The SVG markup for an initials avatar. Drawn on a 100×100 viewBox so one
 * file serves every requested size.
 *
 * @param string $seed Email or name.
 * @return string
 */
function northline_demo_avatar_svg( $seed ) {
	$palette = array(
		array( '#B14A26', '#FDFCFA' ),
		array( '#1F3A5F', '#FDFCFA' ),
		array( '#5E6E54', '#FDFCFA' ),
		array( '#CE8460', '#16181C' ),
		array( '#5E636C', '#FDFCFA' ),
	);

	$hash  = crc32( strtolower( trim( (string) $seed ) ) );
	$pair  = $palette[ $hash % count( $palette ) ];
	$label = northline_demo_avatar_initials( $seed );

	$svg = sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 100 100" role="img" aria-hidden="true">'
			. '<rect width="100" height="100" rx="50" fill="%2$s"/>'
			. '<text x="50" y="50" dy="0.35em" text-anchor="middle" fill="%3$s" '
			. 'font-family="Manrope, Helvetica, Arial, sans-serif" font-size="40" font-weight="600" letter-spacing="1">%4$s</text>'
			. '</svg>',
		100,
		$pair[0],
		$pair[1],
		esc_html( $label )
	);

	return $svg;
}

/**
 * Initials for the avatar label.
 *
 * @param string $seed Email or name.
 * @return string
 */
function northline_demo_avatar_initials( $seed ) {
	$seed = (string) $seed;

	if ( strpos( $seed, '@' ) !== false ) {
		$seed = strstr( $seed, '@', true );
	}

	$parts = preg_split( '/[^\p{L}]+/u', $seed, -1, PREG_SPLIT_NO_EMPTY );

	if ( ! $parts ) {
		return '·';
	}

	$first = mb_substr( $parts[0], 0, 1 );
	$last  = count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '';

	return mb_strtoupper( $first . $last );
}

/**
 * Resolve the identifying string for whatever get_avatar was handed.
 *
 * @param mixed $id_or_email Avatar subject.
 * @return string
 */
function northline_demo_avatar_seed( $id_or_email ) {
	if ( is_numeric( $id_or_email ) ) {
		$user = get_userdata( (int) $id_or_email );
		return $user ? ( $user->display_name ?: $user->user_email ) : 'user';
	}

	if ( is_string( $id_or_email ) ) {
		return $id_or_email;
	}

	if ( $id_or_email instanceof WP_User ) {
		return $id_or_email->display_name ?: $id_or_email->user_email;
	}

	if ( $id_or_email instanceof WP_Post ) {
		$user = get_userdata( (int) $id_or_email->post_author );
		return $user ? ( $user->display_name ?: $user->user_email ) : 'user';
	}

	if ( $id_or_email instanceof WP_Comment ) {
		return $id_or_email->comment_author ?: $id_or_email->comment_author_email;
	}

	return 'user';
}

add_filter(
	'pre_get_avatar_data',
	function ( $args, $id_or_email ) {
		$args['url']          = northline_demo_avatar_uri( northline_demo_avatar_seed( $id_or_email ) );
		$args['found_avatar'] = true;

		return $args;
	},
	10,
	2
);
