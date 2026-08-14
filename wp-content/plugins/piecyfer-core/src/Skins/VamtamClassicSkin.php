<?php
/**
 * The `vamtam_classic` skin for the `posts` widget.
 *
 * Port of `VamtamElementor\Widgets\PostsBase\Skin_Vamtam_Posts_Classic`
 * (vamtam-elementor-integration-tecnologia/includes/widgets/posts-base.php:311-313).
 *
 * All 12 saved `posts` instances select this skin, so its id is now part of the
 * site's data schema: 91 saved keys per instance are prefixed `vamtam_classic_`,
 * and the `__globals__` map that carries every typography and half the colours
 * is keyed by those exact ids. Renaming the skin would orphan all of them with
 * no fallback value behind them — the typography sub-keys are all empty strings.
 *
 * Container class: `elementor-posts--skin-vamtam_classic`, inherited from
 * `SkinBase::get_container_class()`. The archive twin deliberately renders
 * `--skin-classic` instead; see VamtamArchiveClassicSkin.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Skins;

defined( 'ABSPATH' ) || exit;

class VamtamClassicSkin extends ClassicSkin {

	use VamtamClassicOverridesTrait;
}
