<?php
/**
 * Seeds the Northline demo blog: categories, media, eight posts, pages,
 * menus, forms and the site settings that go with them.
 *
 * Run with WP-CLI from the WordPress root:
 *
 *   wp eval-file tools/seed-content.php
 *
 * Safe to re-run: everything is looked up by slug/title first and updated
 * rather than duplicated.
 *
 * @package Northline
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( "Run this through WP-CLI: wp eval-file tools/seed-content.php\n" );
}

$content_dir = __DIR__ . '/content';
$media_dir   = __DIR__ . '/media';

if ( ! is_dir( $content_dir ) ) {
	WP_CLI::error( "Cannot find {$content_dir} — run this script from where it lives, next to its content/ and media/ folders." );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

/* ---------------------------------------------------------------------
 * 1. Site settings
 * ------------------------------------------------------------------ */

update_option( 'blogname', 'Northline Journal' );
update_option( 'blogdescription', 'Notes on making things carefully' );
update_option( 'timezone_string', 'Europe/London' );
update_option( 'date_format', 'j F Y' );
update_option( 'time_format', 'H:i' );
update_option( 'start_of_week', 1 );
update_option( 'posts_per_page', 10 );
update_option( 'blog_public', 1 );
update_option( 'default_comment_status', 'open' );
update_option( 'comment_registration', 0 );
update_option( 'require_name_email', 1 );
update_option( 'thread_comments', 1 );
update_option( 'thread_comments_depth', 3 );
update_option( 'show_on_front', 'posts' );

WP_CLI::log( '· settings updated' );

/* ---------------------------------------------------------------------
 * 2. Author profile
 * ------------------------------------------------------------------ */

$author_id = 1;
wp_update_user(
	array(
		'ID'           => $author_id,
		'display_name' => 'Rowan Hale',
		'first_name'   => 'Rowan',
		'last_name'    => 'Hale',
		'nickname'     => 'Rowan Hale',
		'description'  => 'Writes here most Thursdays about process, tools and the parts of building things that nobody puts in the case study. Previously a developer, still one on weekends.',
	)
);
wp_update_user( array( 'ID' => $author_id, 'user_url' => home_url( '/about/' ) ) );

WP_CLI::log( '· author profile updated' );

/* ---------------------------------------------------------------------
 * 3. Categories
 * ------------------------------------------------------------------ */

$categories = array(
	'craft'       => array(
		'name'        => 'Craft',
		'description' => 'How the work actually gets made — drafting, revising, and the habits that survive contact with a real deadline.',
	),
	'process'     => array(
		'name'        => 'Process',
		'description' => 'Cadence, constraints and the systems that keep a long project moving when enthusiasm runs out.',
	),
	'tools'       => array(
		'name'        => 'Tools',
		'description' => 'Small, sharp, boring software. What earns a place on the workbench and what quietly costs more than it returns.',
	),
	'field-notes' => array(
		'name'        => 'Field Notes',
		'description' => 'Reports from things actually tried, including the numbers and the parts that did not work.',
	),
);

$cat_ids = array();

foreach ( $categories as $slug => $data ) {
	$term = get_term_by( 'slug', $slug, 'category' );

	if ( $term ) {
		wp_update_term( $term->term_id, 'category', array( 'name' => $data['name'], 'description' => $data['description'] ) );
		$cat_ids[ $slug ] = $term->term_id;
	} else {
		$new = wp_insert_term( $data['name'], 'category', array( 'slug' => $slug, 'description' => $data['description'] ) );
		$cat_ids[ $slug ] = is_wp_error( $new ) ? 0 : $new['term_id'];
	}
}

// Point the default category at Craft so nothing lands in "Uncategorized".
if ( ! empty( $cat_ids['craft'] ) ) {
	update_option( 'default_category', $cat_ids['craft'] );
}

WP_CLI::log( '· categories: ' . implode( ', ', array_keys( $cat_ids ) ) );

/* ---------------------------------------------------------------------
 * 4. Media
 * ------------------------------------------------------------------ */

/**
 * Sideload a local file into the media library once, keyed by its filename.
 *
 * @param string $path File path.
 * @param string $alt  Alt text.
 * @return int Attachment ID, or 0.
 */
function northline_seed_media( $path, $alt ) {
	$name = basename( $path );

	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_northline_seed_file',
			'meta_value'     => $name,
		)
	);

	if ( $existing ) {
		return (int) $existing[0];
	}

	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "missing image: {$path}" );
		return 0;
	}

	$tmp = wp_tempnam( $name );
	copy( $path, $tmp );

	$id = media_handle_sideload(
		array( 'name' => $name, 'tmp_name' => $tmp ),
		0,
		$alt
	);

	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		WP_CLI::warning( "sideload failed for {$name}: " . $id->get_error_message() );
		return 0;
	}

	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	update_post_meta( $id, '_northline_seed_file', $name );

	return (int) $id;
}

/* ---------------------------------------------------------------------
 * 5. Posts
 * ------------------------------------------------------------------ */

$posts = array(
	array(
		'file'     => '01-first-draft-is-a-map.html',
		'title'    => 'The first draft is a map, not a monument',
		'slug'     => 'first-draft-is-a-map',
		'category' => 'craft',
		'tags'     => array( 'drafting', 'revision', 'writing' ),
		'excerpt'  => 'Treating an early draft as a survey rather than a structure changes what you are allowed to do with it — and makes finishing a threshold you can actually cross.',
		'image'    => 'cover-contour.jpg',
		'alt'      => 'Abstract contour lines drifting across a warm paper background',
		'days_ago' => 3,
	),
	array(
		'file'     => '02-small-tools-sharpened.html',
		'title'    => 'Small tools, sharpened: building a personal workbench',
		'slug'     => 'small-tools-sharpened',
		'category' => 'tools',
		'tags'     => array( 'workflow', 'automation', 'plain text' ),
		'excerpt'  => 'Four unremarkable tools do ninety per cent of my work. The gain came from shaving the first two seconds off each one, not from finding better software.',
		'image'    => 'cover-bars.jpg',
		'alt'      => 'Vertical rounded bars in terracotta, navy and moss on a pale background',
		'days_ago' => 10,
	),
	array(
		'file'     => '03-why-weekly-beats-daily.html',
		'title'    => 'Why weekly beats daily',
		'slug'     => 'why-weekly-beats-daily',
		'category' => 'process',
		'tags'     => array( 'cadence', 'habits', 'publishing' ),
		'excerpt'  => 'A four-hundred-day daily streak taught me to start. Switching to a weekly cadence is what taught me to finish — and the mechanism is buffer, not motivation.',
		'image'    => 'cover-arcs.jpg',
		'alt'      => 'Concentric arcs radiating from the lower edge of the frame',
		'days_ago' => 17,
	),
	array(
		'file'     => '04-writing-in-public.html',
		'title'    => 'Notes from three months of writing in public',
		'slug'     => 'three-months-writing-in-public',
		'category' => 'field-notes',
		'tags'     => array( 'publishing', 'feedback', 'numbers' ),
		'excerpt'  => 'Thirty-one posts, six replies that changed something, and one cost nobody warned me about: publishing a half-formed idea makes it much harder to abandon.',
		'image'    => 'cover-grid.jpg',
		'alt'      => 'A grid of dots on a dark ground, growing larger from left to right',
		'days_ago' => 24,
	),
	array(
		'file'     => '05-constraints-you-choose.html',
		'title'    => 'Constraints you choose beat constraints you inherit',
		'slug'     => 'constraints-you-choose',
		'category' => 'process',
		'tags'     => array( 'constraints', 'decisions', 'design' ),
		'excerpt'  => 'An inherited limit tells you where you cannot go. A chosen one arrives with an argument attached — and the argument, not the limit, is what does the work.',
		'image'    => 'cover-panels.jpg',
		'alt'      => 'Overlapping translucent rectangles in terracotta, navy and green',
		'days_ago' => 31,
	),
	array(
		'file'     => '06-reading-like-a-builder.html',
		'title'    => 'Reading like a builder',
		'slug'     => 'reading-like-a-builder',
		'category' => 'craft',
		'tags'     => array( 'reading', 'notes', 'thinking' ),
		'excerpt'  => 'Highlighting left no trace. A three-column note — claim, mechanism, test — cut my reading speed by two thirds and my retention rose by more than an order of magnitude.',
		'image'    => 'cover-spokes.jpg',
		'alt'      => 'Radiating spokes of varying length around a small terracotta disc',
		'days_ago' => 38,
	),
	array(
		'file'     => '07-quiet-cost-of-a-good-idea.html',
		'title'    => 'The quiet cost of a good idea',
		'slug'     => 'quiet-cost-of-a-good-idea',
		'category' => 'field-notes',
		'tags'     => array( 'decisions', 'projects', 'stopping' ),
		'excerpt'  => 'Bad ideas fail cheaply. Good ideas at the wrong scale succeed slightly, indefinitely — which is why a kill criterion has to be written before you are invested.',
		'image'    => 'cover-halves.jpg',
		'alt'      => 'Half discs balanced above and below a horizontal rule',
		'days_ago' => 45,
	),
	array(
		'file'     => '08-plain-text-stack.html',
		'title'    => 'A plain-text stack that survives your enthusiasm',
		'slug'     => 'plain-text-stack',
		'category' => 'tools',
		'tags'     => array( 'plain text', 'notes', 'workflow' ),
		'excerpt'  => 'Design for the bored version of yourself. Structure imposed at write time is a bet that you know now what you will want later — and you place it every single time you write.',
		'image'    => 'cover-weave.jpg',
		'alt'      => 'Diagonal woven stripes in navy, sand and terracotta',
		'days_ago' => 52,
	),
);

$created = 0;

foreach ( $posts as $spec ) {
	$path = $content_dir . '/' . $spec['file'];

	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "missing content file: {$spec['file']}" );
		continue;
	}

	$body = file_get_contents( $path );

	$existing = get_page_by_path( $spec['slug'], OBJECT, 'post' );

	$date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$spec['days_ago']} days 09:15" ) );

	$postarr = array(
		'post_title'    => $spec['title'],
		'post_name'     => $spec['slug'],
		'post_content'  => $body,
		'post_excerpt'  => $spec['excerpt'],
		'post_status'   => 'publish',
		'post_type'     => 'post',
		'post_author'   => $author_id,
		'post_date_gmt' => $date,
		'post_date'     => get_date_from_gmt( $date ),
		'comment_status' => 'open',
	);

	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
		$post_id       = wp_update_post( $postarr, true );
	} else {
		$post_id = wp_insert_post( $postarr, true );
	}

	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( "{$spec['slug']}: " . $post_id->get_error_message() );
		continue;
	}

	wp_set_post_categories( $post_id, array( $cat_ids[ $spec['category'] ] ), false );
	wp_set_post_tags( $post_id, $spec['tags'], false );

	$image_id = northline_seed_media( $media_dir . '/' . $spec['image'], $spec['alt'] );
	if ( $image_id ) {
		set_post_thumbnail( $post_id, $image_id );
	}

	$created++;
	WP_CLI::log( "· post: {$spec['title']}" );
}

WP_CLI::log( "· {$created} posts in place" );

/* ---------------------------------------------------------------------
 * 6. Comments on the two newest posts
 * ------------------------------------------------------------------ */

$comment_seed = array(
	'first-draft-is-a-map' => array(
		array( 'Maya Oduya', 'The spine-read-aloud step is the bit I have been missing. I write the outline and then never test it, which explains why the middle always collapses.' ),
		array( 'Tom Beecher', 'Curious how you handle research-heavy pieces — does the spine come before or after you have read the sources?' ),
	),
	'small-tools-sharpened' => array(
		array( 'Priya Raman', 'The friction log is such a simple idea and I cannot believe I have never kept one. Started mine this morning.' ),
	),
);

foreach ( $comment_seed as $slug => $items ) {
	$post = get_page_by_path( $slug, OBJECT, 'post' );

	if ( ! $post ) {
		continue;
	}

	foreach ( $items as $i => $item ) {
		list( $name, $text ) = $item;

		$dupes = get_comments(
			array(
				'post_id'      => $post->ID,
				'author_email' => sanitize_title( $name ) . '@example.com',
				'count'        => true,
			)
		);

		if ( $dupes ) {
			continue;
		}

		wp_insert_comment(
			array(
				'comment_post_ID'      => $post->ID,
				'comment_author'       => $name,
				'comment_author_email' => sanitize_title( $name ) . '@example.com',
				'comment_content'      => $text,
				'comment_approved'     => 1,
				'comment_date'         => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $post->post_date_gmt . " +{$i} day +3 hours" ) ) ),
			)
		);
	}
}

WP_CLI::log( '· comments seeded' );

/* ---------------------------------------------------------------------
 * 7. Pages
 * ------------------------------------------------------------------ */

$pages = array(
	'about'   => array(
		'title'   => 'About',
		'content' => '<!-- wp:paragraph {"style":{"typography":{"fontSize":"1.1875rem","lineHeight":"1.65"}},"textColor":"contrast-2"} -->
<p class="has-contrast-2-color has-text-color" style="font-size:1.1875rem;line-height:1.65">Northline Journal is a weekly piece about making things carefully — the drafting, the tooling, and the unglamorous middle of a project that nobody writes up afterwards.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>It is written by Rowan Hale. Most posts come out of something actually attempted rather than something read about, which is why quite a few of them include the numbers and the parts that did not work.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">What you will find here</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Four running threads, each with its own archive:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list">
<li><strong>Craft</strong> — drafting, revising and the habits that survive a real deadline.</li>
<li><strong>Process</strong> — cadence, constraints, and keeping a long project moving.</li>
<li><strong>Tools</strong> — small, sharp, boring software.</li>
<li><strong>Field Notes</strong> — reports from things actually tried, numbers included.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">The schedule</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>One piece most Thursdays. Roughly one week in seven there is nothing, because the draft was not ready and shipping it anyway would have made it worse. There is a newsletter if you would rather it came to you.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Corrections are genuinely welcome — <a href="/contact/">send them here</a>. Anything substantial gets a dated note at the top of the post rather than a silent edit.</p>
<!-- /wp:paragraph -->',
	),
	'contact' => array(
		'title'   => 'Contact',
		'content' => '<!-- wp:paragraph {"style":{"typography":{"fontSize":"1.1875rem","lineHeight":"1.65"}},"textColor":"contrast-2"} -->
<p class="has-contrast-2-color has-text-color" style="font-size:1.1875rem;line-height:1.65">Corrections, disagreements and pointers to things I should read are all welcome. I read everything and reply to most of it, usually within a week.</p>
<!-- /wp:paragraph -->

<!-- wp:shortcode -->
[northline_contact_form]
<!-- /wp:shortcode -->',
	),
	'privacy' => array(
		'title'   => 'Privacy',
		'content' => '<!-- wp:paragraph -->
<p>This is a placeholder privacy notice. Replace it with your own before the site goes live — what follows describes only what this installation does out of the box.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Comments</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>When you leave a comment, the name, email address and comment text you submit are stored, along with your IP address and browser user agent string. Comments may be checked through an automated spam detection service.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Contact form</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Messages sent through the contact form are emailed to the site owner. They are not stored in the site database unless a storage plugin is added.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Cookies</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If you leave a comment you may opt in to saving your name, email address and website in cookies, so you do not have to fill those fields in again. These last one year. Logging in sets additional cookies used to keep you signed in.</p>
<!-- /wp:paragraph -->',
	),
);

$page_ids = array();

foreach ( $pages as $slug => $data ) {
	$existing = get_page_by_path( $slug, OBJECT, 'page' );

	$postarr = array(
		'post_title'     => $data['title'],
		'post_name'      => $slug,
		'post_content'   => $data['content'],
		'post_status'    => 'publish',
		'post_type'      => 'page',
		'post_author'    => $author_id,
		'comment_status' => 'closed',
	);

	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
		$page_ids[ $slug ] = wp_update_post( $postarr );
	} else {
		$page_ids[ $slug ] = wp_insert_post( $postarr );
	}
}

if ( ! empty( $page_ids['privacy'] ) ) {
	update_option( 'wp_page_for_privacy_policy', $page_ids['privacy'] );
}

WP_CLI::log( '· pages: ' . implode( ', ', array_keys( $page_ids ) ) );

/* ---------------------------------------------------------------------
 * 8. Menus
 * ------------------------------------------------------------------ */

/*
 * The theme is a block theme, so the header uses a core/navigation block.
 * That block resolves an empty ref to the most recently created wp_navigation
 * post, so we keep exactly one and rewrite it in place on re-runs.
 */

$nav_items = array();

foreach ( array( 'craft', 'process', 'tools', 'field-notes' ) as $slug ) {
	if ( empty( $cat_ids[ $slug ] ) ) {
		continue;
	}

	$term = get_term( $cat_ids[ $slug ], 'category' );

	$nav_items[] = sprintf(
		'<!-- wp:navigation-link {"label":"%s","type":"category","id":%d,"url":"%s","kind":"taxonomy"} /-->',
		esc_attr( $term->name ),
		(int) $term->term_id,
		esc_url( get_term_link( $term ) )
	);
}

foreach ( array( 'about', 'contact' ) as $slug ) {
	if ( empty( $page_ids[ $slug ] ) ) {
		continue;
	}

	$nav_items[] = sprintf(
		'<!-- wp:navigation-link {"label":"%s","type":"page","id":%d,"url":"%s","kind":"post-type"} /-->',
		esc_attr( get_the_title( $page_ids[ $slug ] ) ),
		(int) $page_ids[ $slug ],
		esc_url( get_permalink( $page_ids[ $slug ] ) )
	);
}

$nav_content = implode( "\n", $nav_items );

$nav_posts = get_posts(
	array(
		'post_type'      => 'wp_navigation',
		'post_status'    => array( 'publish', 'draft' ),
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	)
);

if ( $nav_posts ) {
	// Keep the first, rewrite it, and drop any strays so the fallback is unambiguous.
	$nav_id = (int) $nav_posts[0]->ID;
	wp_update_post(
		array(
			'ID'           => $nav_id,
			'post_title'   => 'Primary',
			'post_content' => $nav_content,
			'post_status'  => 'publish',
		)
	);

	foreach ( array_slice( $nav_posts, 1 ) as $stray ) {
		wp_delete_post( $stray->ID, true );
	}
} else {
	$nav_id = wp_insert_post(
		array(
			'post_title'   => 'Primary',
			'post_name'    => 'primary',
			'post_content' => $nav_content,
			'post_status'  => 'publish',
			'post_type'    => 'wp_navigation',
		)
	);
}

WP_CLI::log( '· navigation: Primary (' . count( $nav_items ) . ' items)' );

/* ---------------------------------------------------------------------
 * 9. Contact Form 7 forms
 * ------------------------------------------------------------------ */

if ( class_exists( 'WPCF7_ContactForm' ) ) {

	/**
	 * Create a CF7 form by title if it does not exist yet.
	 *
	 * @param string $title    Form title.
	 * @param string $form     Form template.
	 * @param string $subject  Mail subject.
	 * @param string $body     Mail body.
	 * @return int Form ID.
	 */
	function northline_seed_cf7( $title, $form, $subject, $body ) {
		$existing = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'title'          => $title,
				'posts_per_page' => 1,
				'post_status'    => 'any',
			)
		);

		$cf7 = $existing ? WPCF7_ContactForm::get_instance( $existing[0]->ID ) : WPCF7_ContactForm::get_template();

		$cf7->set_title( $title );
		$cf7->set_properties(
			array(
				'form' => $form,
				'mail' => array(
					'active'             => true,
					'subject'            => $subject,
					'sender'             => '[_site_title] <wordpress@' . wp_parse_url( home_url(), PHP_URL_HOST ) . '>',
					'recipient'          => get_option( 'admin_email' ),
					'body'               => $body,
					'additional_headers' => 'Reply-To: [your-email]',
					'attachments'        => '',
					'use_html'           => false,
					'exclude_blank'      => false,
				),
				'messages' => array_merge(
					(array) $cf7->prop( 'messages' ),
					array( 'mail_sent_ok' => 'Thanks — that came through. I read everything.' )
				),
			)
		);

		return (int) $cf7->save();
	}

	northline_seed_cf7(
		'Contact',
		"<label>Your name\n    [text* your-name autocomplete:name] </label>\n\n<label>Email\n    [email* your-email autocomplete:email] </label>\n\n<label>Subject\n    [text your-subject] </label>\n\n<label>Message\n    [textarea your-message x8] </label>\n\n[submit \"Send message\"]",
		'[_site_title] — message from [your-name]',
		"From: [your-name] <[your-email]>\nSubject: [your-subject]\n\n[your-message]\n\n-- \nSent from [_site_title] ([_site_url])"
	);

	northline_seed_cf7(
		'Newsletter',
		// CF7 stops scanning a tag when a colon-option follows a quoted one,
		// so autocomplete: has to come before placeholder "".
		"[email* your-email autocomplete:email placeholder \"you@example.com\"][submit \"Subscribe\"]",
		'[_site_title] — new subscriber',
		"New newsletter subscriber: [your-email]\n\n-- \nSent from [_site_title] ([_site_url])"
	);

	WP_CLI::log( '· Contact Form 7: Contact, Newsletter' );
} else {
	WP_CLI::warning( 'Contact Form 7 is not active — skipping form creation.' );
}

/* ---------------------------------------------------------------------
 * 10. SEOPress defaults
 * ------------------------------------------------------------------ */

if ( defined( 'SEOPRESS_VERSION' ) || is_plugin_active( 'wp-seopress/seopress.php' ) ) {
	$titles = (array) get_option( 'seopress_titles_option_name', array() );

	$titles['seopress_titles_home_site_title'] = '%%sitetitle%% — %%tagline%%';
	$titles['seopress_titles_home_site_desc']  = 'Weekly writing on drafting, process, tools and the unglamorous middle of a project.';
	$titles['seopress_titles_single_titles']   = array(
		'post' => array( 'title' => '%%post_title%% — %%sitetitle%%' ),
		'page' => array( 'title' => '%%post_title%% — %%sitetitle%%' ),
	);
	$titles['seopress_titles_archives_author_disable'] = '1';
	$titles['seopress_titles_archives_date_disable']   = '1';

	update_option( 'seopress_titles_option_name', $titles );

	$xml = (array) get_option( 'seopress_xml_sitemap_option_name', array() );
	$xml['seopress_xml_sitemap_general_enable'] = '1';
	$xml['seopress_xml_sitemap_post_types_list'] = array(
		'post' => array( 'include' => '1' ),
		'page' => array( 'include' => '1' ),
	);
	$xml['seopress_xml_sitemap_taxonomies_list'] = array(
		'category' => array( 'include' => '1' ),
		'post_tag' => array( 'include' => '1' ),
	);
	update_option( 'seopress_xml_sitemap_option_name', $xml );

	$social = (array) get_option( 'seopress_social_option_name', array() );
	$social['seopress_social_facebook_og'] = '1';
	$social['seopress_social_twitter_card'] = '1';
	$social['seopress_social_twitter_card_img_size'] = 'large';
	update_option( 'seopress_social_option_name', $social );

	update_option( 'seopress_activated', 'yes' );

	WP_CLI::log( '· SEOPress: titles, sitemap and social defaults set' );
}

flush_rewrite_rules();

WP_CLI::success( 'Demo content seeded.' );
