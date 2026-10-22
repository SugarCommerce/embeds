<?php
/**
 * Which way round the page a player is landing on.
 *
 * @package   SugarCommerce\Embeds
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Embeds;

/**
 * Enum Theme
 *
 * This changes only the platforms whose chrome is ours to set, which today
 * means Bandcamp: its player takes background and link colours as part of the
 * URL, so a default of white is a white slab on a dark page.
 *
 * It does not change the others, and cannot. YouTube and Vimeo are black
 * whatever anyone asks. SoundCloud's compact player is white and offers no
 * background parameter at all. Spotify decides for itself. A theme option
 * that pretended otherwise would be a setting that silently does nothing.
 *
 * @since 1.0.0
 */
enum Theme {

	/**
	 * A light page — white player, blue links. The default.
	 *
	 * @since 1.0.0
	 */
	case Light;

	/**
	 * A dark page — near-black player, white links.
	 *
	 * @since 1.0.0
	 */
	case Dark;

	/**
	 * Background colour for a player that accepts one, as six hex digits.
	 *
	 * @since 1.0.0
	 *
	 * @return string Six lower-case hex digits, no `#`.
	 */
	public function background(): string {
		return match ( $this ) {
			self::Light => 'ffffff',
			self::Dark  => '111111',
		};
	}

	/**
	 * Link and accent colour to go with it.
	 *
	 * @since 1.0.0
	 *
	 * @return string Six lower-case hex digits, no `#`.
	 */
	public function accent(): string {
		return match ( $this ) {
			self::Light => '0687f5',
			self::Dark  => 'ffffff',
		};
	}
}
