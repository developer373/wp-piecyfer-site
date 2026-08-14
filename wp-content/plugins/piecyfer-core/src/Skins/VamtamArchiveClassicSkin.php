<?php
/**
 * The `vamtam_classic` skin for the `archive-posts` widget.
 *
 * Port of `VamtamElementor\Widgets\PostsBase\Skin_Vamtam_Archive_Posts_Classic`
 * (vamtam-elementor-integration-tecnologia/includes/widgets/posts-base.php:315-317).
 *
 * Same id and same render overrides as VamtamClassicSkin, but a different
 * parent — and therefore a different container class. `ArchiveClassicSkin`
 * hard-codes `elementor-posts--skin-classic`, so:
 *
 *   /blogs/       posts.vamtam_classic         → elementor-posts--skin-vamtam_classic
 *   /?s=software  archive-posts.vamtam_classic → elementor-posts--skin-classic
 *
 * Same skin id, two container classes. Verified in
 * _project/snapshots/ref-a/html/{blogs,s-software}.html and against a live curl.
 * It also has a visible consequence: Pro's stylesheet carries exactly one
 * classic-scoped rule, `.elementor-posts--skin-classic .elementor-post
 * { overflow: hidden }`, which therefore applies to the archives and NOT to the
 * blog listing.
 *
 * @package PieCyfer\Core
 */

declare( strict_types = 1 );

namespace PieCyfer\Core\Skins;

defined( 'ABSPATH' ) || exit;

class VamtamArchiveClassicSkin extends ArchiveClassicSkin {

	use VamtamClassicOverridesTrait;
}
