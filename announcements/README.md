# One Joomla article for Studio news and add-on versions (1.0.2)

The component now reads **one article** for both its banner and JSON catalogue. No version bump was made: both editions remain 1.0.2.

## Publish

1. Open your article at `https://shop.topoweryou.com/?option=com_content&view=article&id=2` in Joomla administrator.
2. Switch the article editor to HTML/source mode (or use No Editor). Replace the article body with the complete fragment in `../article.html`. Do not paste it into the visual editor as escaped source code.
3. Edit the visible text within `<article id="vmstudio-banner">`. Safe inline styles for text/background colors, fonts, alignment, spacing, borders and basic flex/grid layouts are preserved, along with HTTPS links and images. The root article style is preserved too. Use inline styles; stylesheets, theme classes, background images and executable CSS are not imported. The component renders a sanitized banner at the bottom of the page, without the shop menu or login modules. A light canvas and explicit text color keep unstyled articles readable in dark admin themes; inline article colors take precedence.
4. Keep the `<pre id="vmstudio-data" hidden="hidden">` block after the visible article. It holds plain JSON, not executable JavaScript. Keep its ID and valid JSON syntax. Joomla must preserve the PRE element and its ID; if the editor strips the hidden attribute, the data remains readable by Studio but may be visible on the public article. Check the saved article HTML.
5. Replace the placeholder shop homepage URLs with your exact product or download URLs. This template contains one pack entry for each edition (`vm5` and `vm4`). The core only reads entries matching its edition.
6. Save the article as published and public. Install the rebuilt 1.0.2 core ZIP on the customer site. The default article-only URL is already configured; no separate feed needs uploading.

## JSON fields

The root has `"schema": 1` and a `"packs"` array. Each entry requires `edition`, the plugin `element`, `name`, `description`, `version`, `min_core` and an HTTPS `url`. Versions use two or three numeric components. Only one entry per plugin/edition is used.

For a maintenance release, change `version` and the description. For a different paid product, add a new entry with its own plugin element. Pack versions and the core version are independent. Banner text is edited separately from the machine-readable version fields; keep the visible text consistent with JSON.

## Behaviour

The core fetches server-side with a three-second timeout and caches the article for one hour. Article HTML is sanitized: scripts, forms, frames, unsafe CSS and event handlers are excluded. Relative image and link URLs resolve against the article URL. A plain public Joomla article without JSON still displays its banner. Missing/invalid JSON suppresses version cards only. A missing/unavailable article does not block Studio.

The previous default XML feed setting automatically resolves to the article URL. Custom URLs remain unchanged and must point to an article using the same format. The old standalone `announcements-vm5.xml` and `announcements-vm4.xml` examples are legacy and are no longer read by this implementation. Notices can be disabled in Options. Cached content may take up to an hour to refresh after editing the article.

Disabled installed plugins are included in installed-version comparison. The status is available to purchase, update available, current, or requires newer core. Paid packs have no Joomla update site; purchases and ZIP installations stay manual. Core updates retain their existing Joomla update mechanism.

## Validation

`tests/announcements.php` checks banner extraction, JSON edition filtering, malformed/unsafe data, markup sanitization, relative links and images, UTF-8, installed-version statuses, fetch failure and migration of the previous default URL. These checks passed on a temporary PHP 8.1 CLI runtime for both editions, including captured HTML from the live article. A live Joomla admin installation test is still required; the article on the shop has not been modified automatically.

To see article edits immediately, click **Refresh news** at the bottom of Studio. This clears only the Studio article cache and reloads the page. It requires Studio configuration permission and a valid Joomla form token. The button remains available if the article is temporarily unavailable. You can also clear the `com_vmmailinvoicestudio_announcements_article_v2` group using Joomla Clear Cache.

## Bootstrap card example

The banner supports the card/grid classes in the supplied four-design section: `row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4`, `col`, `card h-100`, `card-body`, `h3`, `h5`, `mb-0`, `mb-4`, `figure`, `d-block`, `figure-img`, `img-fluid`, `w-100`, `rounded`, and `figure-caption`. It shows one column on small screens, two from 768px, and four from 992px. Styles are scoped to the banner; no extra Bootstrap CDN or administrator stylesheet is needed. Other Bootstrap classes are not automatically supported. Paste the section inside the existing `article id="vmstudio-banner"` wrapper, keeping the JSON block after it. Click Refresh news after saving the article.

`bg-light` is also supported on cards and other banner elements. It uses a fixed light-grey background (#f8f9fa) and dark text (#212529), including in a dark administrator theme.
