<?php
/**
 * @package   SugarCommerce\Embeds
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Embeds\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SugarCommerce\Embeds\{Embeds, Platform, Theme};

/**
 * Class EmbedsTest
 *
 * @since 1.0.0
 */
final class EmbedsTest extends TestCase {

	/**
	 * Every way a person can arrive holding a YouTube link.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function youtube_urls(): array {
		return array(
			'watch'      => array( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ),
			'share'      => array( 'https://youtu.be/dQw4w9WgXcQ' ),
			'short'      => array( 'https://www.youtube.com/shorts/dQw4w9WgXcQ' ),
			'embed'      => array( 'https://www.youtube.com/embed/dQw4w9WgXcQ' ),
			'nocookie'   => array( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ' ),
			'mobile'     => array( 'https://m.youtube.com/watch?v=dQw4w9WgXcQ' ),
			'with noise' => array( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s&list=PL1' ),
			'no www'     => array( 'https://youtube.com/watch?v=dQw4w9WgXcQ' ),
		);
	}

	#[DataProvider( 'youtube_urls' )]
	public function test_youtube_always_lands_on_the_same_player( string $url ): void {
		$embed = Embeds::embed( $url );

		$this->assertNotNull( $embed );
		$this->assertSame( Platform::YouTube, $embed->platform );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $embed->src );
		$this->assertSame( '16 / 9', $embed->ratio );
		$this->assertTrue( $embed->responsive() );
	}

	public function test_a_youtube_id_of_the_wrong_length_is_refused(): void {
		$this->assertNull( Embeds::embed( 'https://www.youtube.com/watch?v=tooshort' ) );
		$this->assertNull( Embeds::embed( 'https://www.youtube.com/watch?v=waaaaaaaaaaaytoolong' ) );
	}

	public function test_a_youtube_channel_is_recognised_but_cannot_be_framed(): void {
		$url = 'https://www.youtube.com/@someartist';

		$this->assertSame( Platform::YouTube, Embeds::platform( $url ) );
		$this->assertNull( Embeds::embed( $url ) );
	}

	public function test_vimeo_keeps_the_hash_of_an_unlisted_video(): void {
		$path  = Embeds::embed( 'https://vimeo.com/123456789/abc123def456' );
		$query = Embeds::embed( 'https://vimeo.com/123456789?h=abc123def456' );

		$this->assertNotNull( $path );
		$this->assertNotNull( $query );
		$this->assertSame( 'https://player.vimeo.com/video/123456789?h=abc123def456', $path->src );
		$this->assertSame( $path->src, $query->src );
	}

	public function test_vimeo_accepts_a_player_url(): void {
		$embed = Embeds::embed( 'https://player.vimeo.com/video/123456789' );

		$this->assertNotNull( $embed );
		$this->assertSame( 'https://player.vimeo.com/video/123456789', $embed->src );
		$this->assertSame( 'https://vimeo.com/123456789', $embed->url );
	}

	public function test_soundcloud_passes_the_track_url_to_the_player(): void {
		$embed = Embeds::embed( 'https://soundcloud.com/some-artist/midnight-drive' );

		$this->assertNotNull( $embed );
		$this->assertStringStartsWith( 'https://w.soundcloud.com/player/?url=', $embed->src );
		$this->assertStringContainsString( rawurlencode( 'https://soundcloud.com/some-artist/midnight-drive' ), $embed->src );
		$this->assertSame( 166, $embed->height );
		$this->assertFalse( $embed->responsive() );
	}

	public function test_a_soundcloud_set_gets_room_for_its_tracklist(): void {
		$embed = Embeds::embed( 'https://soundcloud.com/some-artist/sets/the-album' );

		$this->assertNotNull( $embed );
		$this->assertSame( 450, $embed->height );
	}

	public function test_a_soundcloud_profile_is_not_a_track(): void {
		$this->assertNull( Embeds::embed( 'https://soundcloud.com/some-artist' ) );
		$this->assertNull( Embeds::embed( 'https://soundcloud.com/a/b/c/d' ) );
	}

	public function test_spotify_survives_a_localised_share_link(): void {
		$plain = Embeds::embed( 'https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT' );
		$intl  = Embeds::embed( 'https://open.spotify.com/intl-de/track/4cOdK2wGLETKBW3PvgPWqT' );

		$this->assertNotNull( $plain );
		$this->assertNotNull( $intl );
		$this->assertSame( $plain->src, $intl->src );
		$this->assertSame( 'https://open.spotify.com/embed/track/4cOdK2wGLETKBW3PvgPWqT', $plain->src );
		$this->assertSame( 152, $plain->height );
	}

	public function test_a_spotify_album_is_taller_than_a_track(): void {
		$embed = Embeds::embed( 'https://open.spotify.com/album/4cOdK2wGLETKBW3PvgPWqT' );

		$this->assertNotNull( $embed );
		$this->assertSame( 352, $embed->height );
	}

	public function test_bandcamp_takes_the_players_own_url_and_rebuilds_it(): void {
		$embed = Embeds::embed(
			'https://bandcamp.com/EmbeddedPlayer/track=1234567890/size=small/bgcol=333333/linkcol=ff0000/'
		);

		$this->assertNotNull( $embed );
		$this->assertStringStartsWith( 'https://bandcamp.com/EmbeddedPlayer/track=1234567890/', $embed->src );
		// Their styling is dropped in favour of ours.
		$this->assertStringNotContainsString( 'bgcol=333333', $embed->src );
		$this->assertSame( 120, $embed->height );
	}

	public function test_a_dark_page_gets_a_dark_bandcamp_player(): void {
		$url   = 'https://bandcamp.com/EmbeddedPlayer/album=1234567890/';
		$light = Embeds::embed( $url );
		$dark  = Embeds::embed( $url, theme: Theme::Dark );

		$this->assertNotNull( $light );
		$this->assertNotNull( $dark );
		$this->assertStringContainsString( 'bgcol=ffffff', $light->src );
		$this->assertStringContainsString( 'bgcol=111111', $dark->src );
		$this->assertSame( 470, $dark->height );
	}

	/**
	 * The option exists for the one player whose colours are ours to set.
	 * Anywhere else it must be a no-op rather than a setting that looks like
	 * it did something.
	 */
	public function test_the_theme_changes_nothing_it_cannot_change(): void {
		foreach ( array( 'https://youtu.be/dQw4w9WgXcQ', 'https://soundcloud.com/a/b' ) as $url ) {
			$this->assertSame(
				Embeds::embed( $url )?->src,
				Embeds::embed( $url, theme: Theme::Dark )?->src,
				$url
			);
		}
	}

	public function test_a_bandcamp_artist_page_is_recognised_but_has_no_player(): void {
		$url = 'https://someartist.bandcamp.com/album/midnight-drive';

		$this->assertSame( Platform::Bandcamp, Embeds::platform( $url ) );
		$this->assertNull( Embeds::embed( $url ) );
	}

	/**
	 * Hosts written to be mistaken for one on the list.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function impostors(): array {
		return array(
			'suffix'       => array( 'https://notyoutube.com/watch?v=dQw4w9WgXcQ' ),
			'prefix'       => array( 'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ' ),
			'bandcamp'     => array( 'https://bandcamp.com.evil.test/EmbeddedPlayer/track=1/' ),
			'userinfo'     => array( 'https://www.youtube.com@evil.test/watch?v=dQw4w9WgXcQ' ),
			'credentials'  => array( 'https://user:pass@www.youtube.com/watch?v=dQw4w9WgXcQ' ),
			'plain http'   => array( 'http://www.youtube.com/watch?v=dQw4w9WgXcQ' ),
			'javascript'   => array( 'javascript:alert(1)' ),
			'odd port'     => array( 'https://www.youtube.com:8443/watch?v=dQw4w9WgXcQ' ),
			'interior tab' => array( "https://www.youtube.com/wat\tch?v=dQw4w9WgXcQ" ),
			'header split' => array( "https://www.youtube.com/watch?v=dQw4w9WgXcQ\r\nX: 1" ),
			'empty'        => array( '' ),
			'no host'      => array( 'https:///watch?v=dQw4w9WgXcQ' ),
		);
	}

	#[DataProvider( 'impostors' )]
	public function test_a_host_that_only_looks_right_is_refused( string $url ): void {
		$this->assertNull( Embeds::embed( $url ) );
		$this->assertNull( Embeds::platform( $url ) );
	}

	public function test_a_pasted_url_may_carry_surrounding_whitespace(): void {
		// Copying a link out of a browser or an email very often takes a
		// newline with it. Refusing that would be refusing the normal case.
		$embed = Embeds::embed( "  https://youtu.be/dQw4w9WgXcQ\n" );

		$this->assertNotNull( $embed );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $embed->src );
	}

	public function test_a_trailing_dot_does_not_dodge_the_list(): void {
		$embed = Embeds::embed( 'https://www.youtube.com./watch?v=dQw4w9WgXcQ' );

		$this->assertNotNull( $embed );
		$this->assertSame( Platform::YouTube, $embed->platform );
	}

	/**
	 * Query values that arrive percent-encoded with a trailing line break.
	 *
	 * The URL itself is clean — the control character only exists once the
	 * query is decoded — and `$` in a pattern matches before a final "\n".
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function trailing_newlines(): array {
		return array(
			'youtube v' => array( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ%0A' ),
			'vimeo h'   => array( 'https://vimeo.com/123456789?h=abc123def456%0A' ),
		);
	}

	#[DataProvider( 'trailing_newlines' )]
	public function test_a_decoded_trailing_newline_never_reaches_the_src( string $url ): void {
		$this->assertNull( Embeds::embed( $url ) );
	}

	public function test_a_long_query_string_does_not_raise_a_warning(): void {
		// Over max_input_vars, parse_str() raises E_WARNING, which an
		// application that turns warnings into exceptions renders as a 500
		// on every page showing the stored URL.
		$url = 'https://www.youtube.com/watch?' . str_repeat( 'a=&', 1100 ) . 'v=dQw4w9WgXcQ';

		$embed = Embeds::embed( $url );

		$this->assertNotNull( $embed );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $embed->src );
	}

	public function test_an_overlong_url_is_refused(): void {
		$url = 'https://youtu.be/dQw4w9WgXcQ?' . str_repeat( 'a', 5000 );

		$this->assertNull( Embeds::embed( $url ) );
		$this->assertNull( Embeds::platform( $url ) );
	}

	public function test_an_array_shaped_query_value_is_ignored(): void {
		$this->assertNull( Embeds::embed( 'https://www.youtube.com/watch?v[]=dQw4w9WgXcQ' ) );
		$this->assertNotNull( Embeds::embed( 'https://www.youtube.com/watch?v[]=x&v=dQw4w9WgXcQ' ) );
	}

	public function test_only_restricts_which_platforms_are_accepted(): void {
		$url = 'https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT';

		$this->assertNotNull( Embeds::embed( $url, array( Platform::Spotify ) ) );
		$this->assertNull( Embeds::embed( $url, array( Platform::YouTube, Platform::Vimeo ) ) );
	}

	public function test_a_social_profile_is_recognised_without_being_embeddable(): void {
		$this->assertSame( Platform::X, Embeds::platform( 'https://x.com/someone' ) );
		$this->assertSame( Platform::X, Embeds::platform( 'https://twitter.com/someone' ) );
		$this->assertSame( Platform::TikTok, Embeds::platform( 'https://www.tiktok.com/@someone' ) );
		$this->assertSame( Platform::Kick, Embeds::platform( 'https://kick.com/someone' ) );
		$this->assertNull( Embeds::embed( 'https://x.com/someone/status/1' ) );
	}

	public function test_every_embeddable_platform_contributes_exactly_one_origin(): void {
		$origins = Platform::origins();

		$this->assertCount(
			count( array_filter( Platform::cases(), static fn( Platform $one ): bool => $one->embeddable() ) ),
			$origins
		);

		foreach ( $origins as $origin ) {
			$this->assertStringStartsWith( 'https://', $origin );
			$this->assertSame( $origin, rtrim( $origin, '/' ) );
		}
	}

	public function test_an_iframe_title_names_the_platform(): void {
		$embed = Embeds::embed( 'https://youtu.be/dQw4w9WgXcQ' );

		$this->assertNotNull( $embed );
		$this->assertSame( 'YouTube player', $embed->title() );
		$this->assertSame( 'YouTube preview of Midnight Drive', $embed->title( 'Midnight Drive' ) );
	}

	/* ─── Icons ──────────────────────────────────────────────────────── */

	/**
	 * Every platform, so a case added without a mark fails here.
	 *
	 * @return array<string, array{0: Platform}>
	 */
	public static function platforms(): array {
		$cases = array();

		foreach ( Platform::cases() as $platform ) {
			$cases[ $platform->value ] = array( $platform );
		}

		return $cases;
	}

	#[DataProvider( 'platforms' )]
	public function test_every_platform_has_a_mark( Platform $platform ): void {
		$path = $platform->icon_path();

		$this->assertNotSame( '', $path );
		// Path data and nothing else: no markup, no quotes, no URL.
		$this->assertMatchesRegularExpression( '/^[MmZzLlHhVvCcSsQqTtAa0-9.,\s\-]+$/', $path );
	}

	#[DataProvider( 'platforms' )]
	public function test_every_icon_is_one_filled_path( Platform $platform ): void {
		$svg = $platform->icon( 'h-5 w-5' );

		$this->assertStringStartsWith( '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="', $svg );
		$this->assertStringEndsWith( '"/></svg>', $svg );
		$this->assertSame( 1, substr_count( $svg, '<path' ) );
		$this->assertStringContainsString( $platform->icon_path(), $svg );
	}

	public function test_marks_are_distinct(): void {
		$paths = array_map( static fn( Platform $platform ): string => $platform->icon_path(), Platform::cases() );

		$this->assertSame( $paths, array_values( array_unique( $paths ) ) );
	}

	public function test_an_icon_with_no_class_has_no_class_attribute(): void {
		$this->assertStringStartsWith( '<svg viewBox=', Platform::GitHub->icon() );
	}

	public function test_an_icon_escapes_its_class(): void {
		$svg = Platform::X->icon( '" onload="alert(1)' );

		$this->assertStringNotContainsString( '" onload', $svg );
		$this->assertStringContainsString( 'class="&quot; onload=&quot;alert(1)"', $svg );
	}
}
