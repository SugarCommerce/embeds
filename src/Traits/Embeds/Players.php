<?php
/**
 * A player for each platform, rebuilt from the id in a URL.
 *
 * @package   SugarCommerce\Embeds
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Embeds\Traits\Embeds;

use SugarCommerce\Embeds\Models\Embed;
use SugarCommerce\Embeds\Models\Platform;
use SugarCommerce\Embeds\Models\Theme;

/**
 * Trait Players
 *
 * Each pulls an id out of a URL, checks it against a strict pattern and
 * builds a fresh source from it: nothing pasted reaches an `src`.
 *
 * @since 1.0.0
 */
trait Players {

	/**
	 * A YouTube watch, share, embed, short or live URL.
	 *
	 * Built against youtube-nocookie.com, which serves the same player
	 * without writing tracking cookies until somebody presses play. On a page
	 * that shows a preview nobody asked for, that is the difference between
	 * showing a player and setting a third party's cookie on every visitor.
	 *
	 * @since 1.0.0
	 *
	 * @param string                $host  The normalised host.
	 * @param string                $path  The URL's path.
	 * @param array<string, string> $query The decoded query.
	 *
	 * @return Embed|null Null when no valid video id can be found.
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

		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{11}$/D', $id ) ) {
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
	 *
	 * @param string                $path  The URL's path.
	 * @param array<string, string> $query The decoded query.
	 *
	 * @return Embed|null Null when the id or hash does not fit its pattern.
	 */
	private static function vimeo( string $path, array $query ): ?Embed {
		$segments = self::segments( $path );

		if ( 'video' === ( $segments[0] ?? '' ) ) {
			array_shift( $segments );
		}

		$id   = $segments[0] ?? '';
		$hash = $segments[1] ?? ( $query['h'] ?? '' );

		if ( 1 !== preg_match( '/^[0-9]{6,12}$/D', $id ) ) {
			return null;
		}

		if ( '' !== $hash && 1 !== preg_match( '/^[A-Za-z0-9]{6,32}$/D', $hash ) ) {
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
	 *
	 * @param string $path The URL's path.
	 *
	 * @return Embed|null Null for anything but a track or a set.
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
			if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{1,80}$/D', $segment ) ) {
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
	 *
	 * @param string $path The URL's path.
	 *
	 * @return Embed|null Null for any other kind, or an id of the wrong shape.
	 */
	private static function spotify( string $path ): ?Embed {
		$segments = self::segments( $path );

		// open.spotify.com/intl-de/track/… — a localised share link.
		if ( 1 === preg_match( '/^intl-[a-z]{2}$/D', $segments[0] ?? '' ) ) {
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

		if ( 1 !== preg_match( '/^[A-Za-z0-9]{22}$/D', $id ) ) {
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
	 *
	 * @param string $host  The normalised host.
	 * @param string $path  The URL's path.
	 * @param Theme  $theme The page the player will land on.
	 *
	 * @return Embed|null Null for anything but an album or track player URL.
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
			if ( 1 !== preg_match( '/^([a-z]+)=([A-Za-z0-9]+)$/D', $segment, $found ) ) {
				return null;
			}

			$options[ $found[1] ] = $found[2];
		}

		$kind = match ( true ) {
			isset( $options['album'] ) => 'album',
			isset( $options['track'] ) => 'track',
			default                    => '',
		};

		if ( '' === $kind || 1 !== preg_match( '/^[0-9]{1,20}$/D', $options[ $kind ] ) ) {
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
