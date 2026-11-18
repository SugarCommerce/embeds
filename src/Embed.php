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
	 * Hold an embed's parts.
	 *
	 * Built by Embeds::embed(). Constructing one by hand bypasses every
	 * check that method makes, so a value built here from user input is
	 * exactly as trustworthy as that input.
	 *
	 * @since 1.0.0
	 *
	 * @param Platform $platform Which platform this came from.
	 * @param string   $src      The iframe source, built by this library.
	 * @param string   $url      The canonical page URL, for a link out, or ''
	 *                           when it cannot be derived (a Bandcamp player).
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
	 *
	 * @return bool True when $ratio applies, false when $height does.
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
	 * Plain text, not markup: `$subject` is included as given, so escape the
	 * result for the attribute it goes into.
	 *
	 * @since 1.0.0
	 *
	 * @param string $subject What the embed is of, e.g. a product name.
	 *
	 * @return string The title, unescaped, in the site's language.
	 */
	public function title( string $subject = '' ): string {
		return '' === $subject
			/* translators: %s: a platform's name, such as YouTube. */
			? sprintf( __( '%s player', 'sugarcommerce' ), $this->platform->label() )
			/* translators: 1: a platform's name, such as YouTube. 2: what is playing, such as a product's name. */
			: sprintf( __( '%1$s preview of %2$s', 'sugarcommerce' ), $this->platform->label(), $subject );
	}

	/**
	 * The iframe, written once and carefully, every attribute escaped.
	 *
	 * - `sandbox`: every player here needs scripts and its own origin, a
	 *   popup — unsandboxed, so the page it opens works — for its "watch on"
	 *   link, and presentation for fullscreen. Never top navigation, forms
	 *   or downloads.
	 * - `referrerpolicy`: YouTube refuses to play without a referrer;
	 *   `strict-origin-when-cross-origin` sends the site's origin and never
	 *   the page's path or query.
	 * - A video is full width at its aspect ratio; an audio player is full
	 *   width at the height it was drawn for.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $subject What the embed is of, for the title.
	 * @param array<string, mixed> $args    `class` for the element, `lazy`
	 *                                      (default true) to load it only
	 *                                      when it is scrolled to.
	 *
	 * @return string The `<iframe>` element.
	 */
	public function html( string $subject = '', array $args = array() ): string {
		$args       = $args + array(
			'class' => '',
			'lazy'  => true,
		);
		$attributes = array(
			'src'   => esc_url( $this->src ),
			'title' => esc_attr( $this->title( $subject ) ),
			'class' => esc_attr( trim( (string) $args['class'] ) ),
		);

		if ( $this->responsive() ) {
			$attributes['style'] = esc_attr( 'aspect-ratio: ' . $this->ratio . '; width: 100%; height: auto; border: 0;' );
		} else {
			$attributes['style']  = 'width: 100%; border: 0;';
			$attributes['height'] = (string) $this->height;
		}

		$attributes['loading']        = $args['lazy'] ? 'lazy' : '';
		$attributes['referrerpolicy'] = 'strict-origin-when-cross-origin';
		$attributes['sandbox']        = 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-presentation';
		$attributes['allow']          = 'encrypted-media; fullscreen; picture-in-picture';

		$html = '<iframe';

		foreach ( $attributes as $name => $value ) {
			if ( '' !== $value ) {
				$html .= ' ' . $name . '="' . $value . '"';
			}
		}

		return $html . '></iframe>';
	}
}
