<?php
/**
 * The platforms this library knows by name.
 *
 * @package   SugarCommerce\Embeds
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Embeds;

/**
 * Enum Platform
 *
 * A closed list, and that is the feature. Anything not named here is not a
 * platform as far as this library is concerned, which is what lets a caller
 * put the result in an iframe or behind an icon without wondering where it
 * points.
 *
 * Some of these can be embedded and some can only be linked to. Both are
 * useful — an icon in a page footer needs the same host matching as a player
 * does — so link-only platforms are cases here rather than a second list
 * somewhere else.
 *
 * @since 1.0.0
 */
enum Platform: string {

	case YouTube    = 'youtube';
	case Vimeo      = 'vimeo';
	case SoundCloud = 'soundcloud';
	case Bandcamp   = 'bandcamp';
	case Spotify    = 'spotify';
	case X          = 'x';
	case Instagram  = 'instagram';
	case TikTok     = 'tiktok';
	case Kick       = 'kick';
	case Twitch     = 'twitch';
	case Facebook   = 'facebook';
	case Threads    = 'threads';
	case Bluesky    = 'bluesky';
	case GitHub     = 'github';
	case Discord    = 'discord';

	/**
	 * The hosts that belong to this platform.
	 *
	 * Exact hosts, compared with `===` after a `www.` prefix is removed.
	 * Never a suffix test: `str_ends_with( $host, 'youtube.com' )` is true
	 * of `notyoutube.com`, and that one line is how an allow-list becomes a
	 * redirect to anywhere.
	 *
	 * Subdomains that genuinely vary — a Bandcamp artist page — are handled
	 * in Embeds::host(), not by loosening anything here.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function hosts(): array {
		return match ( $this ) {
			self::YouTube    => array( 'youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com' ),
			self::Vimeo      => array( 'vimeo.com', 'player.vimeo.com' ),
			self::SoundCloud => array( 'soundcloud.com', 'm.soundcloud.com' ),
			self::Bandcamp   => array( 'bandcamp.com' ),
			self::Spotify    => array( 'open.spotify.com', 'play.spotify.com' ),
			self::X          => array( 'x.com', 'twitter.com' ),
			self::Instagram  => array( 'instagram.com' ),
			self::TikTok     => array( 'tiktok.com' ),
			self::Kick       => array( 'kick.com' ),
			self::Twitch     => array( 'twitch.tv' ),
			self::Facebook   => array( 'facebook.com', 'fb.com' ),
			self::Threads    => array( 'threads.net', 'threads.com' ),
			self::Bluesky    => array( 'bsky.app' ),
			self::GitHub     => array( 'github.com' ),
			self::Discord    => array( 'discord.gg', 'discord.com' ),
		};
	}

	/**
	 * What to call it on screen.
	 *
	 * @since 1.0.0
	 */
	public function label(): string {
		return match ( $this ) {
			self::YouTube    => 'YouTube',
			self::Vimeo      => 'Vimeo',
			self::SoundCloud => 'SoundCloud',
			self::Bandcamp   => 'Bandcamp',
			self::Spotify    => 'Spotify',
			self::X          => 'X',
			self::Instagram  => 'Instagram',
			self::TikTok     => 'TikTok',
			self::Kick       => 'Kick',
			self::Twitch     => 'Twitch',
			self::Facebook   => 'Facebook',
			self::Threads    => 'Threads',
			self::Bluesky    => 'Bluesky',
			self::GitHub     => 'GitHub',
			self::Discord    => 'Discord',
		};
	}

	/**
	 * Whether this platform has a player worth framing.
	 *
	 * Twitch is deliberately absent. Its embed requires a `parent` parameter
	 * naming every domain the iframe will appear on, so an embed built here
	 * cannot be correct without knowing where the caller will render it —
	 * and one that is wrong shows a black box rather than an error.
	 *
	 * @since 1.0.0
	 */
	public function embeddable(): bool {
		return match ( $this ) {
			self::YouTube, self::Vimeo, self::SoundCloud, self::Bandcamp, self::Spotify => true,
			default => false,
		};
	}

	/**
	 * The origin an embed for this platform is served from.
	 *
	 * This is what belongs in a `frame-src` content security policy
	 * directive. It is not always a host the caller pasted: a SoundCloud
	 * track lives on soundcloud.com and its player on w.soundcloud.com.
	 *
	 * @since 1.0.0
	 *
	 * @return string The origin, or '' when the platform has no embed.
	 */
	public function origin(): string {
		return match ( $this ) {
			self::YouTube    => 'https://www.youtube-nocookie.com',
			self::Vimeo      => 'https://player.vimeo.com',
			self::SoundCloud => 'https://w.soundcloud.com',
			self::Bandcamp   => 'https://bandcamp.com',
			self::Spotify    => 'https://open.spotify.com',
			default          => '',
		};
	}

	/**
	 * Every embed origin, for a content security policy.
	 *
	 * Generated from the cases rather than written out again, so adding a
	 * platform cannot leave a policy behind.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, self> $only Limit to these platforms. Empty means all.
	 *
	 * @return array<int, string>
	 */
	public static function origins( array $only = array() ): array {
		$cases = $only ?: self::cases();

		return array_values( array_unique( array_filter( array_map(
			static fn( self $platform ): string => $platform->origin(),
			$cases
		) ) ) );
	}
}
