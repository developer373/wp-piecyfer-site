<?php
/**
 * Footer template.
 *
 * Closes the wrappers header.php opened and renders the Theme Builder footer
 * location inside `.footer-wrapper > footer#main-footer.main-footer`.
 *
 * Note `piecyfer_location_exists()` rather than a match test: this asks only
 * whether the footer location is registered, so the two wrapper elements are
 * emitted even on a request no footer document matches. That is what the
 * previous theme did (it called `elementor_location_exits()` with the default
 * `$check_match = false`) and the CSS is written against those wrappers.
 *
 * The `style=""` attribute on `.footer-wrapper` is inherited: the previous
 * theme wrote a `display:none` into it for one-page templates, which this site
 * does not use, so it was always empty - and it is in the captured markup.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

?>

			</div><!-- #main -->

		</div><!-- #main-content -->

		<?php if ( piecyfer_location_exists( 'footer' ) ) : ?>
			<div class="footer-wrapper" style="">
				<footer id="main-footer" class="main-footer">
					<?php piecyfer_do_location( 'footer' ); ?>
				</footer>
			</div>
		<?php endif ?>

</div><!-- / #page -->

<?php wp_footer(); ?>
</body>
</html>
