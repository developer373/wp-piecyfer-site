<?php
/**
 * Archive template - and, through WordPress's template hierarchy, the file that
 * `author.php`, `search.php` and `index.php` all mirror.
 *
 * Like every archive route on this site, this file is not what renders today:
 * Elementor Pro's core `archive` location carries `'overwrite' => true`, so
 * `Locations_Manager::template_include()` swaps in Elementor's own
 * `header-footer.php` before WordPress ever reaches the template hierarchy. The
 * category, tag, author and search captures all show the Theme Builder archive
 * documents (8559, 6126, 8711) rendering directly inside `#main` with no theme
 * markup around them at all.
 *
 * The location call is here anyway, for the reason set out at length in
 * author.php: it makes the theme produce the same page whether or not the
 * Theme Builder implementation that replaces Pro reproduces that takeover.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<?php if ( ! piecyfer_do_location( 'archive' ) ) : ?>

	<div class="page-wrapper">

		<article class="full">
			<div class="page-content clearfix">
				<?php
				rewind_posts();

				get_template_part( 'template-parts/loop' );
				?>
			</div>
		</article>

	</div>

<?php endif ?>

<?php get_footer(); ?>
