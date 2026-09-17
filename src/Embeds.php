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

	/**
	 * Split a URL into the parts worth trusting, or null.
	 *
	 * Refuses anything but https on the default port, and anything carrying
	 * credentials. `https://youtube.com@evil.test/` parses with a host of
	 * `evil.test`, so the userinfo is not merely unused — its presence is a
	 * sign the URL was written to be misread, and it is rejected outright.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Any URL.
	 *
	 * @return array{host: string, path: string, query: array<string, string>}|null
	 */
	private static function parts( string $url ): ?array {
		$url = trim( $url );

		// parse_url is lenient about control characters; a URL containing one
		// was not typed by a person.
		if ( '' === $url || 1 === preg_match( '/[\x00-\x20\x7F]/', $url ) ) {
			return null;
		}

		$parts = parse_url( $url );

		if ( false === $parts || ! isset( $parts['host'] ) ) {
			return null;
		}

		if ( 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) ) {
			return null;
		}

		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return null;
		}

		if ( isset( $parts['port'] ) && 443 !== $parts['port'] ) {
			return null;
		}

		$query = array();

		parse_str( (string) ( $parts['query'] ?? '' ), $query );

		return array(
			'host'  => self::normalise( (string) $parts['host'] ),
			'path'  => (string) ( $parts['path'] ?? '' ),
			'query' => array_map( static fn( mixed $one ): string => is_string( $one ) ? $one : '', $query ),
		);
	}

	/**
	 * A host reduced to the form the platform lists are written in.
	 *
	 * @since 1.0.0
	 */
	private static function normalise( string $host ): string {
		$host = rtrim( strtolower( $host ), '.' );

		return str_starts_with( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}

	/**
	 * The platform owning a normalised host.
	 *
	 * Exact comparison, with one deliberate exception: Bandcamp gives every
	 * artist their own subdomain, so `artist.bandcamp.com` is theirs. The
	 * label is checked against a DNS pattern and must be the only one, which
	 * is what keeps `bandcamp.com.evil.test` out.
	 *
	 * @since 1.0.0
	 */
	private static function match_host( string $host ): ?Platform {
		foreach ( Platform::cases() as $platform ) {
			if ( in_array( $host, $platform->hosts(), true ) ) {
				return $platform;
			}
		}

		if ( str_ends_with( $host, '.bandcamp.com' ) ) {
			$label = substr( $host, 0, -strlen( '.bandcamp.com' ) );

			return 1 === preg_match( '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label ) ? Platform::Bandcamp : null;
		}

		return null;
	}

	/**
	 * Path segments, undecoded.
	 *
	 * Deliberately not run through rawurldecode: decoding turns `%2F` into a
	 * separator after the split has already happened, which is how one
	 * segment becomes two.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	private static function segments( string $path ): array {
		return array_values( array_filter( explode( '/', $path ), static fn( string $one ): bool => '' !== $one ) );
	}

	/**
	 * A YouTube watch, share, embed, short or live URL.
	 *
	 * Built against youtube-nocookie.com, which serves the same player
	 * without writing tracking cookies until somebody presses play. On a page
	 * that shows a preview nobody asked for, that is the difference between
	 * showing a player and setting a third party's cookie on every visitor.
	 *
	 * @since 1.0.0
	 */
	private static function youtube( string $host, string $path, array $query ): ?Embed {
		$segments = self::segments( $path );
		$first    = $segments[0] ?? '';

		$id = match ( true ) {
			'youtu.be' === $host                                             => $first,
			'watch' === $first                                               => $query['v'] ?? '',
			in_array( $first, array( 'embed', 'shorts', 'live', 'v' ), true ) => $segments[1] ?? '',
			default                                                          => '',
		};

		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ) {
			return null;
		}

		return new Embed(
			Platform::YouTube,
			'https://www.youtube-nocookie.com/embed/' . $id,
			'https://www.youtube.com/watch?v=' . $id,
			ratio: '16 / 9',
		);
	}

	/**
	 * A Vimeo video, including the unlisted kind.
	 *
	 * An unlisted video carries a hash, either as a second path segment or
	 * as `?h=`. Without it the player answers "private", so it is kept.
	 *
	 * @since 1.0.0
	 */
	private static function vimeo( string $path, array $query ): ?Embed {
		$segments = self::segments( $path );

		if ( 'video' === ( $segments[0] ?? '' ) ) {
			array_shift( $segments );
		}

		$id   = $segments[0] ?? '';
		$hash = $segments[1] ?? ( $query['h'] ?? '' );

		if ( 1 !== preg_match( '/^[0-9]{6,12}$/', $id ) ) {
			return null;
		}

		if ( '' !== $hash && 1 !== preg_match( '/^[A-Za-z0-9]{6,32}$/', $hash ) ) {
			return null;
		}

		$suffix = '' === $hash ? '' : '?h=' . $hash;

		return new Embed(
			Platform::Vimeo,
			'https://player.vimeo.com/video/' . $id . $suffix,
			'https://vimeo.com/' . $id . ( '' === $hash ? '' : '/' . $hash ),
			ratio: '16 / 9',
		);
	}

	/**
	 * A SoundCloud track or set.
	 *
	 * The player takes the page URL rather than an id, so this is the one
	 * platform where a URL is passed on — after being rebuilt segment by
	 * segment from parts that each matched a pattern, and encoded into a
	 * query parameter rather than a path.
	 *
	 * @since 1.0.0
	 */
	private static function soundcloud( string $path ): ?Embed {
		$segments = self::segments( $path );
		$count    = count( $segments );

		if ( 2 !== $count && 3 !== $count ) {
			return null;
		}

		if ( 3 === $count && 'sets' !== $segments[1] ) {
			return null;
		}

		foreach ( $segments as $segment ) {
			if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{1,80}$/', $segment ) ) {
				return null;
			}
		}

		$url = 'https://soundcloud.com/' . implode( '/', $segments );

		return new Embed(
			Platform::SoundCloud,
			// No colour parameter: the accent is the only thing the widget
			// takes and its default is SoundCloud's own, which is the one
			// colour guaranteed to look deliberate on their white player.
			'https://w.soundcloud.com/player/?url=' . rawurlencode( $url )
				. '&visual=false&hide_related=true&show_comments=false&show_teaser=false',
			$url,
			// SoundCloud's compact player is 166px and does not reflow. A set
			// needs room for the track list.
			height: 3 === $count ? 450 : 166,
		);
	}

	/**
	 * A Spotify track, album, playlist, show, episode or artist.
	 *
	 * @since 1.0.0
	 */
	private static function spotify( string $path ): ?Embed {
		$segments = self::segments( $path );

		// open.spotify.com/intl-de/track/… — a localised share link.
		if ( 1 === preg_match( '/^intl-[a-z]{2}$/', $segments[0] ?? '' ) ) {
			array_shift( $segments );
		}

		if ( 'embed' === ( $segments[0] ?? '' ) ) {
			array_shift( $segments );
		}

		$kind = $segments[0] ?? '';
		$id   = $segments[1] ?? '';

		if ( ! in_array( $kind, array( 'track', 'album', 'playlist', 'episode', 'show', 'artist' ), true ) ) {
			return null;
		}

		if ( 1 !== preg_match( '/^[A-Za-z0-9]{22}$/', $id ) ) {
			return null;
		}

		return new Embed(
			Platform::Spotify,
			'https://open.spotify.com/embed/' . $kind . '/' . $id,
			'https://open.spotify.com/' . $kind . '/' . $id,
			height: in_array( $kind, array( 'track', 'episode' ), true ) ? 152 : 352,
		);
	}

	/**
	 * A Bandcamp player, and only a player.
	 *
	 * An artist page cannot be turned into an embed: the player is addressed
	 * by a numeric album or track id that appears nowhere in the page URL,
	 * only in the page's HTML. So this accepts the URL Bandcamp's own "Share
	 * / Embed" panel produces, and rebuilds it from the two values that
	 * matter — everything else in that URL is styling we choose ourselves.
	 *
	 * @since 1.0.0
	 */
	private static function bandcamp( string $host, string $path, Theme $theme = Theme::Light ): ?Embed {
		if ( 'bandcamp.com' !== $host ) {
			return null;
		}

		$segments = self::segments( $path );

		if ( 'EmbeddedPlayer' !== array_shift( $segments ) ) {
			return null;
		}

		$options = array();

		foreach ( $segments as $segment ) {
			if ( 1 !== preg_match( '/^([a-z]+)=([A-Za-z0-9]+)$/', $segment, $found ) ) {
				return null;
			}

			$options[ $found[1] ] = $found[2];
		}

		$kind = match ( true ) {
			isset( $options['album'] ) => 'album',
			isset( $options['track'] ) => 'track',
			default                    => '',
		};

		if ( '' === $kind || 1 !== preg_match( '/^[0-9]{1,20}$/', $options[ $kind ] ) ) {
			return null;
		}

		return new Embed(
			Platform::Bandcamp,
			'https://bandcamp.com/EmbeddedPlayer/' . $kind . '=' . $options[ $kind ]
				. '/size=large/bgcol=' . $theme->background() . '/linkcol=' . $theme->accent()
				. '/tracklist=false/transparent=true/',
			// The page this player belongs to is not derivable from it.
			'',
			height: 'album' === $kind ? 470 : 120,
		);
	}
}
