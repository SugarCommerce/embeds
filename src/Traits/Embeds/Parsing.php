<?php
/**
 * A URL split into the parts worth trusting, and the platform that owns its host.
 *
 * @package   SugarCommerce\Embeds
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Embeds\Traits\Embeds;

use SugarCommerce\Embeds\Platform;

/**
 * Trait Parsing
 *
 * https on the default port with no credentials, a bounded length and no
 * control characters; plain query pairs; exact host matching.
 *
 * @since 1.0.0
 */
trait Parsing {

	/**
	 * The longest URL worth looking at, in bytes.
	 *
	 * Every URL shape recognised here fits in a few hundred bytes, even with
	 * a share link's tracking parameters attached. Anything longer was not
	 * pasted from a browser's address bar, and refusing it up front bounds
	 * the work every later step does.
	 *
	 * @since 1.0.0
	 */
	private const int MAX_LENGTH = 4096;

	/**
	 * Split a URL into the parts worth trusting, or null.
	 *
	 * Refuses anything but https on the default port, and anything carrying
	 * credentials. `https://youtube.com@evil.test/` parses with a host of
	 * `evil.test`, so the userinfo is not merely unused — its presence is a
	 * sign the URL was written to be misread, and it is rejected outright.
	 * So is anything longer than MAX_LENGTH.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Any URL.
	 *
	 * @return array{host: string, path: string, query: array<string, string>}|null
	 */
	private static function parts( string $url ): ?array {
		$url = trim( $url );

		if ( '' === $url || strlen( $url ) > self::MAX_LENGTH ) {
			return null;
		}

		// parse_url is lenient about control characters; a URL containing one
		// was not typed by a person.
		if ( 1 === preg_match( '/[\x00-\x20\x7F]/', $url ) ) {
			return null;
		}

		$parts = parse_url( $url );

		if ( false === $parts || ! isset( $parts['host'] ) ) {
			return null;
		}

		if ( 'https' !== strtolower( $parts['scheme'] ?? '' ) ) {
			return null;
		}

		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return null;
		}

		if ( isset( $parts['port'] ) && 443 !== $parts['port'] ) {
			return null;
		}

		return array(
			'host'  => self::normalise( $parts['host'] ),
			'path'  => $parts['path'] ?? '',
			'query' => self::query( $parts['query'] ?? '' ),
		);
	}

	/**
	 * The plain `name=value` pairs of a query string, decoded.
	 *
	 * Not parse_str(): past `max_input_vars` pairs it raises a warning, which
	 * an application that promotes warnings to exceptions turns into an error
	 * page wherever the stored URL is shown, and it builds nested arrays this
	 * library never reads. Only names that are a plain word are kept — `v[]`
	 * is not `v` — and a later pair overwrites an earlier one, as it would
	 * in PHP.
	 *
	 * Decoded values can hold anything, a line break included, so every
	 * caller still checks a value against its own pattern before use.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query The raw query, without its `?`.
	 *
	 * @return array<string, string> Decoded values keyed by name.
	 */
	private static function query( string $query ): array {
		$found = array();

		foreach ( explode( '&', $query ) as $pair ) {
			$split = explode( '=', $pair, 2 );
			$name  = urldecode( $split[0] );

			if ( 1 !== preg_match( '/^[A-Za-z0-9_]{1,32}$/D', $name ) ) {
				continue;
			}

			$found[ $name ] = urldecode( $split[1] ?? '' );
		}

		return $found;
	}

	/**
	 * A host reduced to the form the platform lists are written in.
	 *
	 * Lower case, trailing dots removed, and a leading `www.` dropped, since
	 * the lists name each host without one.
	 *
	 * @since 1.0.0
	 *
	 * @param string $host The host as parse_url() returned it.
	 *
	 * @return string The normalised host.
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
	 *
	 * @param string $host A host already passed through normalise().
	 *
	 * @return Platform|null Null when no platform owns the host.
	 */
	private static function match_host( string $host ): ?Platform {
		foreach ( Platform::cases() as $platform ) {
			if ( in_array( $host, $platform->hosts(), true ) ) {
				return $platform;
			}
		}

		if ( str_ends_with( $host, '.bandcamp.com' ) ) {
			$label = substr( $host, 0, -strlen( '.bandcamp.com' ) );

			return 1 === preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $label ) ? Platform::Bandcamp : null;
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
	 * @param string $path The URL's path, still encoded.
	 *
	 * @return list<string> The non-empty segments, in order.
	 */
	private static function segments( string $path ): array {
		return array_values( array_filter( explode( '/', $path ), static fn( string $one ): bool => '' !== $one ) );
	}
}
