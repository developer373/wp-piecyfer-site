<?php
/**
 * Replacement for Elementor Pro's `theme-site-logo` widget.
 *
 * 2 instances, both in "Header - H. IT Services" — so this renders on every
 * page of the site.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Widgets;

use Elementor\Group_Control_Image_Size;
use Elementor\Plugin as ElementorPlugin;
use Elementor\Widget_Image;

defined( 'ABSPATH' ) || exit;

/**
 * Like the title widgets, this is not a bespoke widget: Pro's is the *free*
 * Image widget with the image control defaulted to the `site-logo` dynamic tag
 * and the link defaulted to the site URL. Extending Widget_Image keeps every
 * one of its style control ids — and therefore every generated CSS rule —
 * identical for free.
 */
final class SiteLogoWidget extends Widget_Image {

	public function get_name(): string {
		// The `theme-` prefix avoids colliding with the dynamic tag of the same
		// name. It is part of the saved data, so it stays.
		return 'theme-site-logo';
	}

	public function get_title(): string {
		return esc_html__( 'Site Logo', 'piecyfer-core' );
	}

	public function get_icon(): string {
		return 'eicon-site-logo';
	}

	public function get_categories(): array {
		return array( 'pro-elements' );
	}

	public function get_keywords(): array {
		return array( 'site', 'logo', 'branding' );
	}

	protected function register_controls(): void {
		parent::register_controls();

		$tags = ElementorPlugin::$instance->dynamic_tags;

		$this->update_control(
			'section_image',
			array( 'label' => esc_html__( 'Site Logo', 'piecyfer-core' ) )
		);

		/*
		 * Pro swaps in its own Control_Media_Preview here, which renders the
		 * current logo plus a "Change Site Logo" button in the panel. That is
		 * editor chrome; the stored value and the rendered markup are the same
		 * either way, so the free MEDIA control is kept and the dynamic default
		 * is what actually matters.
		 */
		$this->update_control(
			'image',
			array(
				'label'   => esc_html__( 'Site Logo', 'piecyfer-core' ),
				'dynamic' => array(
					'default' => $tags->tag_data_to_tag_text( null, 'site-logo' ),
				),
			),
			array( 'recursive' => true )
		);

		$this->update_control(
			'image_size',
			array(
				'separator' => 'before',
				'default'   => 'full',
			)
		);

		$this->update_control(
			'link_to',
			array(
				'options' => array(
					'none'     => esc_html__( 'None', 'piecyfer-core' ),
					'site_url' => esc_html__( 'Site URL', 'piecyfer-core' ),
					'custom'   => esc_html__( 'Custom URL', 'piecyfer-core' ),
					'file'     => esc_html__( 'Media File', 'piecyfer-core' ),
				),
				'default' => 'site_url',
			),
			array( 'recursive' => true )
		);

		$this->update_control(
			'caption_source',
			array(
				'options' => array(
					'none'       => esc_html__( 'None', 'piecyfer-core' ),
					'attachment' => esc_html__( 'Attachment Caption', 'piecyfer-core' ),
				),
			)
		);

		// Pro removes the free widget's custom-caption field, since a logo's
		// caption can only sensibly come from the attachment.
		$this->remove_control( 'caption' );
	}

	/**
	 * Matches Pro: the wrapper keeps `elementor-widget-image` alongside
	 * `elementor-widget-theme-site-logo`, which is what the theme stylesheet
	 * targets.
	 */
	protected function get_html_wrapper_class(): string {
		return parent::get_html_wrapper_class() . ' elementor-widget-' . parent::get_name();
	}

	protected function render(): void {
		try {
			$this->render_logo();
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[piecyfer-core] theme-site-logo render failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	/**
	 * Mirrors Pro's render, which is itself a copy of the free Image widget's
	 * with one change: the link target resolves through get_link_url() so that
	 * "Site URL" is an option.
	 */
	private function render_logo(): void {
		$settings = $this->get_settings_for_display();

		if ( empty( $settings['image']['url'] ) ) {
			return;
		}

		$has_caption = ! empty( $settings['caption_source'] ) && 'none' !== $settings['caption_source'];
		$link        = $this->get_link_url( $settings );

		if ( $link ) {
			$this->add_link_attributes( 'link', $link );

			if ( ElementorPlugin::$instance->editor->is_edit_mode() ) {
				$this->add_render_attribute( 'link', 'class', 'elementor-clickable' );
			}

			if ( 'file' === $settings['link_to'] ) {
				$this->add_lightbox_data_attributes( 'link', $settings['image']['id'], $settings['open_lightbox'] );
			}
		}
		?>
		<?php if ( $has_caption ) : ?>
		<figure class="wp-caption">
	<?php endif; ?>
		<?php if ( $link ) : ?>
		<a <?php $this->print_render_attribute_string( 'link' ); ?>>
	<?php endif; ?>
		<?php Group_Control_Image_Size::print_attachment_image_html( $settings ); ?>
		<?php if ( $link ) : ?>
		</a>
	<?php endif; ?>
		<?php if ( $has_caption ) : ?>
			<figcaption class="widget-image-caption wp-caption-text"><?php
				echo wp_kses_post( (string) wp_get_attachment_caption( $settings['image']['id'] ) );
			?></figcaption>
		<?php endif; ?>
		<?php if ( $has_caption ) : ?>
		</figure>
	<?php endif; ?>
		<?php
	}

	/**
	 * Must stay `protected` and untyped in its parameter: Widget_Image declares
	 * `protected function get_link_url( $settings )`, and PHP rejects both a
	 * narrower visibility and an added parameter type on an override.
	 *
	 * @param array<string,mixed> $settings
	 * @return array{url:string}|false
	 */
	protected function get_link_url( $settings ) {
		switch ( $settings['link_to'] ?? '' ) {
			case 'none':
				return false;

			case 'custom':
				return empty( $settings['link']['url'] ) ? false : $settings['link'];

			case 'site_url':
				// Pro resolves this through its `site-url` dynamic tag, which is
				// `home_url()` with no argument. Calling it directly avoids
				// registering a tag purely as an indirection, but it has to be
				// the *same* call: `home_url( '/' )` appends a trailing slash and
				// changed the logo's href on every page.
				return array( 'url' => home_url() );

			default:
				return array( 'url' => $settings['image']['url'] );
		}
	}
}
