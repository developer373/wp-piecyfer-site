<?php
/**
 * Author archive template.
 *
 * READ THIS BEFORE EDITING - THIS FILE DOES NOT RUN TODAY.
 *
 * The task that produced this theme flagged `author.php` as the one route where
 * a theme swap could change real output with nothing watching, on the grounds
 * that it renders the theme's own markup with no Theme Builder template. That
 * is not what the site does. Measured, not inferred:
 *
 *   - Elementor Pro's core `archive` location carries `'overwrite' => true`
 *     (elementor-pro/modules/theme-builder/classes/locations-manager.php:531-537).
 *   - `Locations_Manager::template_include()` maps every `is_archive()` request
 *     - author archives included - to the `archive` location (`:178`), finds
 *     `need_override_location` true (`:222`), and replaces the template with
 *     Elementor's own `header-footer.php` (`:240-256`).
 *   - That file calls `get_header()`, prints the location, and calls
 *     `get_footer()`. The theme's `author.php` is never included.
 *   - Confirmed against the live site: `/author/webdeveloper373/` returns 200
 *     and its markup contains `data-elementor-type="archive"` with
 *     `data-elementor-id="8559"`, no `.page-wrapper` and no `.author-info-box`.
 *     Same in `_project/snapshots/ref2-a/html/author-webdeveloper373.html`.
 *
 * So the author archive renders Theme Builder document 8559, exactly like every
 * category and tag archive, and this file contributes nothing to the theme swap.
 *
 * WHICH IS WHY IT LEADS WITH THE LOCATION CALL. The previous theme's author.php
 * had none - it relied entirely on Pro's takeover. Reproducing that omission
 * would mean that the moment piecyfer-core's Theme Builder replaces Pro without
 * also reproducing the `overwrite` half of `template_include`, this file starts
 * executing and the author archive silently turns into the theme's own loop.
 * With the call here, the archive document renders either way and the fallback
 * below is genuinely unreachable rather than accidentally unreachable.
 *
 * The fallback keeps the previous theme's `.page-wrapper > article` shell and
 * author box so that a site with no archive document still gets a usable page.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

$piecyfer_author = $GLOBALS['authordata'] ?? null;

rewind_posts();
get_header();
?>

<?php if ( ! piecyfer_do_location( 'archive' ) ) : ?>

	<div class="page-wrapper">

		<article class="full">
			<div class="page-content clearfix">
				<?php
				$piecyfer_bio = $piecyfer_author ? get_the_author_meta( 'description', $piecyfer_author->ID ) : '';

				if ( ! empty( $piecyfer_bio ) ) :
					?>
					<div class="author-info-box clearfix">
						<div class="author-avatar">
							<?php echo get_avatar( get_the_author_meta( 'user_email', $piecyfer_author->ID ), 60 ); ?>
						</div>
						<div class="author-description">
							<h4>
								<?php
								/* translators: %s: author display name. */
								echo esc_html( sprintf( __( 'About %s', 'piecyfer-theme' ), $piecyfer_author->display_name ) );
								?>
							</h4>
							<?php echo wp_kses( $piecyfer_bio, 'user_description' ); ?>
						</div>
					</div>
					<?php
				endif;

				rewind_posts();

				if ( have_posts() ) {
					get_template_part( 'template-parts/loop' );
				} else {
					echo '<h2 class="no-posts-by-author">';
					/* translators: %s: author display name. */
					echo esc_html( sprintf( __( '%s has not published any posts yet', 'piecyfer-theme' ), $piecyfer_author ? $piecyfer_author->display_name : '' ) );
					echo '</h2>';
				}
				?>
			</div>
		</article>

	</div>

<?php endif ?>

<?php get_footer(); ?>
