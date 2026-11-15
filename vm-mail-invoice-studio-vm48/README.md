# VM Mail & Invoice Studio — VirtueMart 4 edition

Targets Joomla 5.x, PHP 8.1+, VirtueMart 4.2.6+ in the 4.2.x, 4.4.x, 4.6.x or 4.8.x series, and VirtueMart’s separately installed TCPDF component. Joomla 4/6 and VirtueMart 5 are intentionally rejected by this edition.

Install dist/pkg_vmmailinvoicestudio_vm48_v1.0.1.zip through Joomla Extensions → Install. Configure Studio, enable the required designs, apply to the site template selected in VirtueMart, enable VirtueMart useLayoutOverrides, and use HTML emails. Existing overrides are protected.

This edition shares component/plugin IDs and settings with the VM5 edition, but uses the distinct parent package identifier `pkg_vmmailinvoicestudio_vm48`. Do not install both editions together.

The VM4 edition has its own update-feed URL (vmmailinvoicestudio-vm48.xml) and download filenames. Publish the generated feed and register these filenames with Update Key Manager before enabling production updates. The feed is a local draft, not a published service.

Upstream reference inspected: official com_virtuemart.4.8.0.11339 package, https://dev.virtuemart.net/attachments/download/1409/com_virtuemart.4.8.0.11339_package_or_extract.zip . VM4.8 uses global VmConfig and VmVendorPDF classes. Its invoice email subtemplate sequence matches the existing integration. No upstream implementation files are bundled in Studio.

Validate on a staging copy before production: install, save, HTML/PDF preview, shopper/vendor emails, a newly generated invoice with taxes and multipage items, disabled-design fallback, delivery notes, removal and uninstall. Local checks do not replace a complete Joomla/VirtueMart installation test.

Copyright (C) 2026 Darko Fatur / ToPowerYou. GNU GPL version 2 or later.

## Updated features (1.0.0)

Includes all current VM5 Studio features adapted for Joomla 5 and the supported VM4 versions: save/cache fixes; Classic and Modern; selectable colors; Unicode č/Č PDF fonts; Modern billing details on the left and invoice details on the right; combined names/addresses; Modern hides shipping/order status/history while Classic keeps them; matching previews; separately installed design packs with safe core fallback.

Optional Joomla 5 example pack: `dist/design-packs/plg_vmstudiodesign_studioexample_vm48_v1.0.0.zip`. Install the rebuilt core first, then install/enable Studio Design – Example Pack (Access: Public), and select Editorial. See `docs/DESIGN-PACKS.md` for authoring and selling packs.

New validation details: `docs/VALIDATION.md`. Existing PDFs are unchanged; test a newly generated invoice.

## Compatibility range

The installer accepts VM 4.2.6+ in the 4.2, 4.4, 4.6 and 4.8 branches on Joomla 5 / PHP 8.1+. Joomla 5's backward compatibility plugin must be enabled where required by VirtueMart. Earlier versions remain blocked: VM 4.2.6 was the official Joomla 5 compatibility release. No official VM 4.1 package was found in the release listing, so 4.1 is not claimed compatible. Development branches and prerelease version strings are not accepted.

Source comparisons cover 16 local VM packages; native invoice/PDF fixture tests pass on 4.2.6, 4.4.4, 4.6.4, 4.8.0 and 4.8.8. These establish integration compatibility, not a full live-store certification. See docs/COMPATIBILITY.md for evidence and limits. The VM4 parent package identity is now `pkg_vmmailinvoicestudio_vm48`. Sites installed under the old `pkg_vmmailinvoicestudio` identity need a separately tested migration; the renamed feed does not automatically match those installations.

## Invoice & Email Collection

Install core 1.0.1 before collection pack 1.2.0. Both updates are required for consistent native invoice/email styling and Studio previews. See collection verification for evidence, review files and remaining live-store checks.

## Building version 1.0.1

Run `python3 tools/build.py` from this edition directory. Run `python3 tools/check.py` with PHP CLI available. Generated installers and feeds are written to `dist/` and are not committed. Supporting validation documents referenced above are maintained outside this source repository.

The package manifest uses a readable install name. The update feed uses `<client>site</client>` and targets only this edition’s Joomla major version. Key Manager currently regenerates display names from the configured package prefix and hardcodes Joomla 5/6 compatibility; publishing through it requires checking the resulting feed.


## Core 1.0.2: add-on notices

Add-ons & News reads banner content and embedded JSON from one public Joomla article, with installed-version comparison and manual shop downloads. See `../announcements/README.md` and `../article.html` for publishing instructions. Template packs no longer register Joomla update sites.
