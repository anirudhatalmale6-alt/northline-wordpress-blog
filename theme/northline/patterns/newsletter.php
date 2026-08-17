<?php
/**
 * Title: Newsletter band
 * Slug: northline/newsletter
 * Categories: northline, call-to-action
 * Description: Full-width sign-up band placed above the footer.
 *
 * @package Northline
 */
?>
<!-- wp:group {"className":"nl-newsletter","align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|70"},"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"10px"}},"backgroundColor":"base-2","layout":{"type":"constrained","contentSize":"620px"}} -->
<div class="wp-block-group alignwide nl-newsletter has-base-2-background-color has-background" style="border-radius:10px;margin-top:var(--wp--preset--spacing--70);padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--50)">
	<!-- wp:paragraph {"align":"center","className":"nl-eyebrow"} -->
	<p class="has-text-align-center nl-eyebrow"><?php echo esc_html_x( 'The newsletter', 'Newsletter pattern eyebrow', 'northline' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"textAlign":"center","level":2,"style":{"typography":{"fontSize":"2rem"},"spacing":{"margin":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|30"}}}} -->
	<h2 class="wp-block-heading has-text-align-center" style="font-size:2rem;margin-top:var(--wp--preset--spacing--20);margin-bottom:var(--wp--preset--spacing--30)"><?php echo esc_html_x( 'One good read, every Thursday', 'Newsletter pattern heading', 'northline' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"1rem","lineHeight":"1.7"},"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}},"textColor":"contrast-2"} -->
	<p class="has-text-align-center has-contrast-2-color has-text-color" style="font-size:1rem;line-height:1.7;margin-bottom:var(--wp--preset--spacing--40)"><?php echo esc_html_x( 'No filler and no daily digest — just the week\'s piece, plus two or three links worth your time. Unsubscribe whenever.', 'Newsletter pattern copy', 'northline' ); ?></p>
	<!-- /wp:paragraph -->

	<?php
	$northline_form_id = northline_newsletter_form_id();

	if ( $northline_form_id ) :
		?>
		<!-- wp:shortcode -->
		[contact-form-7 id="<?php echo (int) $northline_form_id; ?>" title="Newsletter"]
		<!-- /wp:shortcode -->
	<?php else : ?>
		<!-- wp:paragraph {"align":"center","style":{"typography":{"fontFamily":"var:preset|font-family|manrope","fontSize":"0.8125rem"}},"textColor":"contrast-2"} -->
		<p class="has-text-align-center has-contrast-2-color has-text-color" style="font-family:var(--wp--preset--font-family--manrope);font-size:0.8125rem"><?php echo esc_html_x( 'Drop your mailing-list embed code here (Mailchimp, ConvertKit, Buttondown…).', 'Newsletter pattern placeholder note', 'northline' ); ?></p>
		<!-- /wp:paragraph -->
	<?php endif; ?>
</div>
<!-- /wp:group -->
