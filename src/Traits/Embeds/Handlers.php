<?php
/**
 * Embed handlers that answer a URL in post content before oEmbed.
 *
 * @package   SugarCommerce\Embeds
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Embeds\Traits\Embeds;

use SugarCommerce\Embeds\Models\Platform;
use SugarCommerce\Embeds\Models\Theme;

/**
 * Trait Handlers
 *
 * WordPress's wp_embed_register_handler(), with the player embed() builds.
 *
 * @since 1.0.0
 */
trait Handlers {

	/**
	 * Let a URL pasted into post content become one of these players.
	 *
	 * WordPress turns a URL on a line of its own into an embed through
	 * oEmbed, which asks the platform for its HTML over the network. That is
	 * the right answer for most platforms and core already does it. Two cases
	 * it is not: Bandcamp, which core does not know, and a site that wants
	 * YouTube without a request per URL and without youtube.com's cookies.
	 * A handler registered here answers before oEmbed is asked, with the
	 * player embed() builds; a URL it cannot frame falls through to core.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $id        The plugin's prefix. Handler ids are
	 *                                        `{id}-{platform}`, so two plugins
	 *                                        bundling this do not collide.
	 * @param array<int, Platform> $platforms Which platforms. Link-only ones
	 *                                        are skipped.
	 * @param Theme                $theme     The page the players land on.
	 * @param int                  $priority  Before core's own handlers at
	 *                                        the default.
	 *
	 * @return list<string> The handler ids registered.
	 */
	public static function register_handlers( string $id, array $platforms = array( Platform::Bandcamp ), Theme $theme = Theme::Light, int $priority = 9 ): array {
		$registered = array();

		foreach ( $platforms as $platform ) {
			if ( ! $platform instanceof Platform || ! $platform->embeddable() ) {
				continue;
			}

			$hosts = implode( '|', array_map( static fn ( string $host ): string => preg_quote( $host, '#' ), $platform->hosts() ) );
			$name  = $id . '-' . $platform->value;

			wp_embed_register_handler(
				$name,
				'#^https://(?:www\.)?(?:' . $hosts . ')/\S+$#i',
				static function ( $matches, $attr, $url ) use ( $platform, $theme ) {
					$embed = self::embed( (string) $url, array( $platform ), $theme );

					return null === $embed ? false : $embed->html();
				},
				$priority
			);

			$registered[] = $name;
		}

		return $registered;
	}
}
