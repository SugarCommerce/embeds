<?php
/**
 * The iframe, and the handlers that put it in post content.
 *
 * @package SugarCommerce\Embeds
 */

declare( strict_types=1 );

namespace SugarCommerce\Embeds\Tests;

use PHPUnit\Framework\TestCase;
use SugarCommerce\Embeds\Embed;
use SugarCommerce\Embeds\Embeds;
use SugarCommerce\Embeds\Platform;
use SugarCommerce\Embeds\Theme;

/**
 * What reaches the page: every attribute escaped, the sandbox fixed, and a
 * pasted URL in a post answered without a network request — or left to core.
 */
final class WordPressTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['em_handlers'] = array();
	}

	public function test_a_video_iframe_is_sized_by_ratio_and_sandboxed(): void {
		$html = Embeds::embed( 'https://youtu.be/dQw4w9WgXcQ' )->html( 'Midnight Drive', array( 'class' => 'product-preview' ) );

		$this->assertSame(
			'<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" title="YouTube preview of Midnight Drive"'
			. ' class="product-preview" style="aspect-ratio: 16 / 9; width: 100%; height: auto; border: 0;" loading="lazy"'
			. ' referrerpolicy="strict-origin-when-cross-origin"'
			. ' sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-presentation"'
			. ' allow="encrypted-media; fullscreen; picture-in-picture"></iframe>',
			$html
		);
	}

	public function test_an_audio_iframe_is_its_own_height(): void {
		$html = Embeds::embed( 'https://soundcloud.com/artist/track' )->html( '', array( 'lazy' => false ) );

		$this->assertStringContainsString( ' height="166"', $html );
		$this->assertStringContainsString( 'title="SoundCloud player"', $html );
		$this->assertStringContainsString( '?url=https%3A%2F%2Fsoundcloud.com%2Fartist%2Ftrack&#038;visual=false', $html );
		$this->assertStringNotContainsString( 'loading=', $html );
		$this->assertStringNotContainsString( 'class=', $html, 'No class, no empty attribute.' );
	}

	public function test_everything_written_into_an_attribute_is_escaped(): void {
		$html = Embeds::embed( 'https://youtu.be/dQw4w9WgXcQ' )->html( '"><script>alert(1)</script>', array( 'class' => '" onload="x' ) );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '" onload="', $html );
		$this->assertStringContainsString( 'title="YouTube preview of &quot;&gt;&lt;script&gt;', $html );
		$this->assertSame( 1, substr_count( $html, '<iframe' ) );
	}

	public function test_a_hand_built_embed_cannot_smuggle_a_scheme(): void {
		$html = ( new Embed( Platform::YouTube, 'javascript:alert(1)', '', '16 / 9' ) )->html();

		$this->assertStringNotContainsString( 'javascript', $html );
		$this->assertStringNotContainsString( 'src=', $html );
	}

	public function test_bandcamp_is_handled_by_default_and_falls_through_when_unframeable(): void {
		$this->assertSame( array( 'shop-bandcamp' ), Embeds::register_handlers( 'shop' ) );
		$this->assertSame( 9, $GLOBALS['em_handlers']['shop-bandcamp']['priority'] );

		$html = em_autoembed( 'https://bandcamp.com/EmbeddedPlayer/album=123456/size=large/bgcol=000000/' );

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'album=123456/size=large/bgcol=ffffff/', $html, 'Rebuilt, in our colours.' );
		$this->assertFalse( em_autoembed( 'https://bandcamp.com/EmbeddedPlayer/album=abc/' ), 'Left to core.' );
		$this->assertFalse( em_autoembed( 'https://notbandcamp.com/EmbeddedPlayer/album=123456/' ) );
	}

	public function test_youtube_can_be_answered_without_oembed(): void {
		$ids = Embeds::register_handlers( 'shop', array( Platform::YouTube, Platform::X, Platform::Twitch ), Theme::Dark, 5 );

		$this->assertSame( array( 'shop-youtube' ), $ids, 'Link-only platforms get no handler.' );
		$this->assertStringContainsString( 'youtube-nocookie.com/embed/dQw4w9WgXcQ', (string) em_autoembed( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42' ) );
		$this->assertFalse( em_autoembed( 'https://www.youtube.com/@channel' ) );
		$this->assertFalse( em_autoembed( 'https://www.youtube.com.evil.test/watch?v=dQw4w9WgXcQ' ) );
	}

	public function test_icons_are_escaped_with_cores_escaper(): void {
		$this->assertStringContainsString( 'class="a &quot;b&quot;"', Platform::GitHub->icon( 'a "b"' ) );
	}
}
