<?php
/**
 * 404 Error Page Template.
 *
 * Renders Elementor 404 template (Post 8716) via Theme Builder location 'single'
 * or falls back to a modern, brand-aligned 404 error experience.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

piecyfer_print_pending_styles();

if ( ! piecyfer_do_location( 'single' ) ) : ?>
	<main id="main" class="site-main">
		<div class="piecyfer-404-container">
			<div class="piecyfer-404-content">
				<div class="piecyfer-404-badge">
					<span class="error-code">404</span>
				</div>
				<h1 class="piecyfer-404-title"><?php esc_html_e( 'Oops! Page Not Found', 'piecyfer-theme' ); ?></h1>
				<p class="piecyfer-404-subtitle">
					<?php esc_html_e( 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.', 'piecyfer-theme' ); ?>
				</p>

				<div class="piecyfer-404-search">
					<?php get_search_form(); ?>
				</div>

				<div class="piecyfer-404-actions">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="piecyfer-btn-primary">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
						<?php esc_html_e( 'Back to Homepage', 'piecyfer-theme' ); ?>
					</a>
					<a href="<?php echo esc_url( home_url( '/hire-an-expert/' ) ); ?>" class="piecyfer-btn-secondary">
						<?php esc_html_e( 'Hire an Expert', 'piecyfer-theme' ); ?>
					</a>
					<a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>" class="piecyfer-btn-secondary">
						<?php esc_html_e( 'Contact Us', 'piecyfer-theme' ); ?>
					</a>
				</div>
			</div>
		</div>
	</main>
<?php endif;

get_footer();

