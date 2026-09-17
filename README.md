# Embeds

Turn a URL somebody pasted into an embed you can safely put in an iframe. A closed list of platforms, exact host matching, and a `src` rebuilt from validated parts rather than the string that arrived. Zero dependencies.

## Why

The naive version of this is two lines — match a regex, interpolate into an `<iframe src>` — and it is wrong in ways that do not show up until somebody is looking for them:

- **`str_ends_with( $host, 'youtube.com' )` is true of `notyoutube.com`.** One suffix test turns an allow-list into an open redirect with a player skin.
- **`https://www.youtube.com@evil.test/` has a host of `evil.test`.** It parses exactly as written and reads exactly wrong.
- **A pasted URL becomes an attribute.** Anything passed through to `src` carries its own query string, and a player's parameters are not always harmless.
- **The same video arrives six ways.** `watch?v=`, `youtu.be/`, `/shorts/`, `/embed/`, `/live/`, `m.youtube.com`, plus a `&t=` somebody did not mean to include.
- **Players are not all shaped alike.** A video letterboxes to 16:9 at any width; a SoundCloud player is 166 pixels tall and looks broken at 167.

## Features

- 🔒 **Nothing passes through** — an id is extracted, checked against a strict pattern, and a fresh `src` is built from it.
- 🎯 **Exact host matching** — no suffix tests, `www.` normalised, trailing dots removed, userinfo and odd ports refused.
- 🍪 **YouTube via `youtube-nocookie.com`** — the same player without a cookie on every visitor who never pressed play.
- 📐 **Sizing included** — an aspect ratio for video, a pixel height for audio, so the caller does not guess.
- 🔗 **Recognition separate from embedding** — an X profile has a platform and no player; both answers are available.
- 🛡️ **`frame-src` origins generated** from the enum, so adding a platform cannot leave a policy behind.
- 🧩 **Fifteen platforms**, five of them embeddable.

## Requirements

PHP 8.5+

## Installation

```bash
composer require sugarcommerce/embeds
```

## Usage

```php
use SugarCommerce\Embeds\{Embeds, Platform};

$embed = Embeds::embed( 'https://youtu.be/dQw4w9WgXcQ' );

$embed->src;      // https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ
$embed->url;      // https://www.youtube.com/watch?v=dQw4w9WgXcQ
$embed->ratio;    // '16 / 9'
$embed->height;   // 0 — this one is sized by ratio
$embed->title( 'Midnight Drive' );  // 'YouTube preview of Midnight Drive'
```

Anything unrecognised is `null`, which is the whole point of a field somebody else fills in:

```php
Embeds::embed( 'https://notyoutube.com/watch?v=dQw4w9WgXcQ' );  // null
Embeds::embed( 'https://soundcloud.com/some-artist' );          // null — a profile, not a track
```

Restrict which platforms you will take:

```php
Embeds::embed( $url, array( Platform::SoundCloud, Platform::Bandcamp ) );
```

Recognise a URL without embedding it — for a row of social icons, where the platform decides the icon and the host decides the platform:

```php
Embeds::platform( 'https://x.com/someone' );      // Platform::X
Platform::X->label();                             // 'X'
Platform::X->embeddable();                        // false
```

Build the content security policy from the same source:

```php
'frame-src ' . implode( ' ', Platform::origins() );
```

## Platforms

| Platform | Embeddable | Notes |
|---|---|---|
| YouTube | yes | watch, share, shorts, live and embed URLs; served from `youtube-nocookie.com` |
| Vimeo | yes | including unlisted videos, whose hash is kept |
| SoundCloud | yes | tracks and sets |
| Spotify | yes | track, album, playlist, show, episode, artist |
| Bandcamp | yes | the URL from Bandcamp's own Share/Embed panel |
| X, Instagram, TikTok, Kick, Twitch, Facebook, Threads, Bluesky, GitHub, Discord | no | recognised for icons and links |

Twitch is deliberately not embeddable here. Its player requires a `parent` parameter naming every domain the iframe will appear on, so a correct embed cannot be built without knowing where the caller will render it — and an incorrect one is a black box rather than an error.

Bandcamp only accepts the URL from its own embed panel. An artist page cannot be turned into a player: the numeric album id lives in the page's HTML and nowhere in its URL.

## What it does not do

- **No network.** Nothing here fetches a URL, so nothing here can tell you whether a video exists, is private, or was deleted. It tells you the URL is well-formed and belongs to a platform on the list.
- **No oEmbed.** That needs a request per URL and an API key for some providers. If you want a title and a thumbnail, fetch them yourself and cache them.
- **No HTML.** You get a `src`, a ratio and a height. The iframe, its sandbox attributes and its escaping are yours — see `SECURITY.md`.

## Licence

GPL-2.0-or-later
