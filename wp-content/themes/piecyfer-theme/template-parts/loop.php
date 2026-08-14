<?php
/**
 * The theme's own post loop.
 *
 * Used only by the fallback branches of index.php, archive.php, search.php and
 * author.php, none of which executes while an Elementor Theme Builder archive
 * document exists. It replaces the previous theme's `loop.php` plus the 17
 * files under `templates/post/`, which between them were unreachable for the
 * same reason.
 *
 * Deliberately plain: it exists so that a site with no archive document still
 * renders readable pages, not to be styled. The four served stylesheets contain
 * no rules for it.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

if ( ! have_posts() ) {
	return;
}
?>
<div class="loop-wrapper regular">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<div <?php post_class( 'page-content post-header clearfix list-item' ); ?>>
			<div>
				<h2 class="entry-title">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h2>

				<?php if ( has_post_thumbnail() ) : ?>
					<a class="entry-thumbnail" href="<?php the_permalink(); ?>">
						<?php the_post_thumbnail( 'large' ); ?>
					</a>
				<?php endif ?>

				<div class="entry-summary">
					<?php the_excerpt(); ?>
				</div>
			</div>
		</div>
		<?php
	endwhile;
	?>
</div>

<?php
the_posts_pagination(
	array(
		'prev_text' => '<span class="screen-reader-text">' . esc_html__( 'Previous', 'piecyfer-theme' ) . '</span>',
		'next_text' => '<span class="screen-reader-text">' . esc_html__( 'Next', 'piecyfer-theme' ) . '</span>',
	)
);
