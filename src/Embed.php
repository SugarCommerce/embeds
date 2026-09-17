<?php
/**
 * One thing that can be framed.
 *
 * @package   SugarCommerce\Embeds
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Embeds;

/**
 * Class Embed
 *
 * Everything a caller needs to write an iframe, and nothing it has to work
 * out for itself. In particular the shape: a video wants an aspect ratio and
 * will letterbox at any width, while an audio player is a fixed number of
 * pixels tall and looks broken at any other height.
 *
 * @since 1.0.0
 */
final readonly class Embed {

	/**
	 * @param Platform $platform Which platform this came from.
	 * @param string   $src      The iframe source, built by this library.
	 * @param string   $url      The canonical page URL, for a link out.
	 * @param string   $ratio    CSS aspect ratio, e.g. '16 / 9', or '' when
	 *                           the player has a fixed height instead.
	 * @param int      $height   Height in pixels, or 0 when $ratio applies.
	 */
	public function __construct(
		public Platform $platform,
		public string $src,
		public string $url,
		public string $ratio = '',
		public int $height = 0,
	) {}

	/**
	 * Whether this embed is sized by ratio rather than by height.
	 *
	 * @since 1.0.0
	 */
	public function responsive(): bool {
		return '' !== $this->ratio;
	}

	/**
	 * A description for the iframe's title attribute.
	 *
	 * An iframe with no title is announced as "frame" and nothing else,
	 * which for a screen reader on a page of several is no help at all.
	 *
	 * @since 1.0.0
	 *
	 * @param string $subject What the embed is of, e.g. a product name.
	 */
	public function title( string $subject = '' ): string {
		return '' === $subject
			? $this->platform->label() . ' player'
			: $this->platform->label() . ' preview of ' . $subject;
	}
}
