<?php
/**
 * Recognising a URL and building an embed for it.
 *
 * @package   SugarCommerce\Embeds
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Embeds;

use SugarCommerce\Embeds\Traits\Embeds\Handlers;
use SugarCommerce\Embeds\Traits\Embeds\Parsing;
use SugarCommerce\Embeds\Traits\Embeds\Players;

/**
 * Class Embeds
 *
 * The one rule this library exists to keep: **the string that arrives is
 * never the string that is rendered**. Every method here pulls an id out of a
 * URL, checks it against a strict pattern, and builds a fresh source from
 * that id. Nothing a caller pastes reaches an `src` attribute, so there is no
 * query parameter to smuggle, no path to traverse and no second host hiding
 * behind an `@`.
 *
 * The cost is that an unrecognised URL returns null rather than being passed
 * through, which is the correct answer for a field somebody else fills in.
 *
 * @since 1.0.0
 */
final class Embeds {

	use Parsing;
	use Players;
	use Handlers;

	/**
	 * Which platform a URL belongs to.
	 *
	 * Recognition only. A YouTube channel, a Bandcamp artist page and an X
	 * profile all answer here, and none of them can be framed — that is
	 * embed()'s question, and it is a different one.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Any URL.
	 *
	 * @return Platform|null Null when the URL is unusable or unrecognised.
	 */
	public static function platform( string $url ): ?Platform {
		$parts = self::parts( $url );

		return null === $parts ? null : self::match_host( $parts['host'] );
	}

	/**
	 * Build an embed for a URL, or refuse.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $url   Any URL.
	 * @param array<int, Platform> $only  Restrict to these platforms. Empty
	 *                                    means every embeddable one.
	 * @param Theme                $theme The page this will land on. Affects
	 *                                     only the players whose colours are
	 *                                     ours to set — see Theme.
	 *
	 * @return Embed|null Null when the URL is not something this library can
	 *                    frame, which includes a recognised platform whose
	 *                    page carries no player.
	 */
	public static function embed( string $url, array $only = array(), Theme $theme = Theme::Light ): ?Embed {
		$parts = self::parts( $url );

		if ( null === $parts ) {
			return null;
		}

		$platform = self::match_host( $parts['host'] );

		if ( null === $platform || ! $platform->embeddable() ) {
			return null;
		}

		if ( $only && ! in_array( $platform, $only, true ) ) {
			return null;
		}

		return match ( $platform ) {
			Platform::YouTube    => self::youtube( $parts['host'], $parts['path'], $parts['query'] ),
			Platform::Vimeo      => self::vimeo( $parts['path'], $parts['query'] ),
			Platform::SoundCloud => self::soundcloud( $parts['path'] ),
			Platform::Spotify    => self::spotify( $parts['path'] ),
			Platform::Bandcamp   => self::bandcamp( $parts['host'], $parts['path'], $theme ),
			default              => null,
		};
	}
}
