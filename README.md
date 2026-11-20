# Embeds

Turn a URL somebody pasted into a field — a product's preview, an artist's
links — into a player you can safely frame, or a platform you can show an
icon for.

## What it does

The naive version is a regex and an `<iframe src>`, and it is wrong in ways
nobody notices until somebody is looking: `str_ends_with( $host, 'youtube.com' )`
is true of `notyoutube.com`, `https://www.youtube.com@evil.test/` has a host of
`evil.test`, and a pasted URL carries its own query string into the page.

This keeps a closed list of platforms, matches hosts exactly, pulls the id
out, checks it against a strict pattern and builds a fresh `src` from it.
Nothing that was pasted reaches the page. It makes no network request, so a
field can be checked as it is saved.

## Features

* Turn a YouTube, Vimeo, SoundCloud, Spotify or Bandcamp link into a player, or refuse it
* Write the iframe with every attribute escaped, a fixed sandbox, and the right shape — 16:9 for a video, 166 pixels for a track
* Play YouTube from `youtube-nocookie.com`, so nobody gets a cookie for a video they never pressed play on
* Recognise X, Instagram, TikTok, Bluesky, GitHub and the rest for a row of social links
* Draw each platform's mark as inline SVG in the link's own colour
* Answer a Bandcamp — or YouTube — URL pasted into a post before WordPress asks oEmbed
* List the `frame-src` origins for a content security policy

## Installation

```bash
composer require sugarcommerce/embeds
```

## Quick start

A product's preview, from a URL the shop owner pasted:

```php
use SugarCommerce\Embeds\Embeds;

$embed = Embeds::embed( get_post_meta( $product_id, 'preview_url', true ) );

if ( null !== $embed ) {
	echo $embed->html( get_the_title( $product_id ), array( 'class' => 'product-preview' ) );
	// <iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"
	//         title="YouTube preview of Midnight Drive" ... sandbox="..."></iframe>
}
```

Anything unrecognised is null, which is the whole point of a field somebody
else fills in — check it when the field is saved, too:

```php
Embeds::embed( 'https://notyoutube.com/watch?v=dQw4w9WgXcQ' );  // null
Embeds::embed( 'https://soundcloud.com/some-artist' );          // null: a profile, not a track
Embeds::embed( $url, array( Platform::SoundCloud, Platform::Bandcamp ) ); // audio only
```

## The iframe

`html()` writes `src` through `esc_url()` and the title through `esc_attr()`.
The sandbox allows scripts, the player's own origin, popups for its "watch
on" link, and presentation for fullscreen — never top navigation, forms or
downloads. `referrerpolicy` is `strict-origin-when-cross-origin`, because
YouTube refuses to play without a referrer and the page's path is nobody's
business. A video is full width at its aspect ratio; an audio player is full
width at its own height. Pass `'lazy' => false` for a player above the fold.

To write your own element, the parts are there: `$embed->src`, `$embed->url`
(the page to link to), `$embed->ratio` (`'16 / 9'`, or `''`), `$embed->height`
and `$embed->title( $subject )`, translated.

Bandcamp's player takes its colours in the URL; pass `Theme::Dark` for a dark
page. The others are the colour they are, and no option pretends otherwise.

## In post content

WordPress already turns a URL on its own line into an embed through oEmbed,
and for most platforms that is right. Two cases it is not: Bandcamp, which
core does not know, and a site that wants YouTube without a request per URL
and without youtube.com's cookies.

```php
add_action( 'init', function () {
	Embeds::register_handlers( 'myplugin' );                                         // Bandcamp
	Embeds::register_handlers( 'myplugin', array( Platform::YouTube, Platform::Vimeo ) ); // no oEmbed for these
} );
```

Each handler answers before oEmbed is asked. A URL it cannot frame — a
channel, a profile — falls through to core.

## Icons and links

```php
$platform = Embeds::platform( 'https://x.com/someone' );  // Platform::X, or null

printf(
	'<a href="%s" aria-label="%s">%s</a>',
	esc_url( $url ),
	esc_attr( $platform->label() ),
	$platform->icon( 'social-icon' )
);
```

Marks are from [Simple Icons](https://simpleicons.org) (CC0), copied in rather
than fetched, filled with `currentColor` and hidden from screen readers — the
link carries the name. `Platform::origins()` gives the `frame-src` list.

## What it does not do

* **No oEmbed.** Core's `WP_oEmbed` already fetches a title and thumbnail,
  and embeds the platforms this only links to; it is not duplicated here.
* **No Twitch player.** Its embed needs a `parent` naming every domain it
  appears on, and a wrong one is a black box rather than an error.
* **No Bandcamp artist page.** The player's album id is in the page's HTML,
  not its URL; take the URL from Bandcamp's own Share/Embed panel.

## Layout

* `src/Embeds.php`: the one class you call; `src/Traits/Embeds/` holds
  reading a link, building a player and the WordPress handlers.
* `src/Models/`: data shapes. `Embed.php` is everything an iframe needs,
  `Platform.php` the closed list of services and `Theme.php` light or dark.

## Security

* A look-alike host is not the platform: hosts are compared exactly, so `notyoutube.com` and
  `youtube.com.evil.test` are refused, and a Bandcamp subdomain must be one DNS label
* A URL written to be misread is refused outright: credentials (`https://youtube.com@evil.test/`), any scheme but
  https, a port other than 443, control characters or spaces, or more than 4 KB
* Nothing pasted reaches the page: each id is checked against a strict pattern and the `src` is built fresh from
  it, so no query string, path or second host is carried through
* `javascript:`, `data:` and plain http never become a player
* The iframe escapes every attribute, is sandboxed without top navigation, forms or downloads, and sends the site's
  origin as the referrer, never the page's path
* No request leaves the site: there is no oEmbed fetch, so nothing here can be pointed at an internal address
* No player sets a YouTube cookie before somebody presses play

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
