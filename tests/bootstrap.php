<?php
/**
 * PHPUnit bootstrap.
 *
 * Just enough of WordPress for the players: translation, core's escapers
 * and the embed handler registry.
 *
 * @package SugarCommerce\Embeds
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

/**
 * Registered embed handlers, by id.
 *
 * @var array<string, array{regex: string, callback: callable, priority: int}>
 */
$GLOBALS['em_handlers'] = array();

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation stub.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 *
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Core's attribute escaper.
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Core's URL escaper, as far as these players need it: http(s) only,
	 * and an ampersand written as core writes it.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	function esc_url( string $url ): string {
		if ( 1 !== preg_match( '#^https?://#i', $url ) ) {
			return '';
		}

		return str_replace( array( '&', "'", '"' ), array( '&#038;', '&#039;', '&quot;' ), $url );
	}
}

if ( ! function_exists( 'wp_embed_register_handler' ) ) {
	/**
	 * Record an embed handler.
	 *
	 * @param string   $id       Handler id.
	 * @param string   $regex    The URLs it answers.
	 * @param callable $callback What it answers with.
	 * @param int      $priority Its turn.
	 *
	 * @return void
	 */
	function wp_embed_register_handler( string $id, string $regex, callable $callback, int $priority = 10 ): void {
		$GLOBALS['em_handlers'][ $id ] = array(
			'regex'    => $regex,
			'callback' => $callback,
			'priority' => $priority,
		);
	}
}

/**
 * What a URL on its own line becomes, as WP_Embed would ask: the first
 * matching handler's answer, or false for oEmbed to try.
 *
 * @param string $url The pasted URL.
 *
 * @return string|false
 */
function em_autoembed( string $url ): string|false {
	foreach ( $GLOBALS['em_handlers'] as $handler ) {
		if ( 1 === preg_match( $handler['regex'], $url, $matches ) ) {
			$html = ( $handler['callback'] )( $matches, array(), $url, array() );

			if ( false !== $html ) {
				return $html;
			}
		}
	}

	return false;
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
