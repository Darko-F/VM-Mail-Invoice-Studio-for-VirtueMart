# VM Mail & Invoice Studio for VirtueMart

Version **1.0.2** provides email and invoice design tools for two separate Joomla/VirtueMart combinations.

| Edition | Requirements | Package identity |
| --- | --- | --- |
| [VM5 edition](vm-mail-invoice-studio/README.md) | Joomla 6, PHP 8.3+, VirtueMart 5 | `pkg_vmmailinvoicestudio` |
| [VM4 edition](vm-mail-invoice-studio-vm48/README.md) | Joomla 5, PHP 8.1+, VirtueMart 4.2.6+ in the 4.2, 4.4, 4.6 and 4.8 branches | `pkg_vmmailinvoicestudio_vm48` |

Install VirtueMart’s TCPDF component separately. The editions share child extension IDs; do not install both together.

## Build

```bash
python3 vm-mail-invoice-studio/tools/build.py
python3 vm-mail-invoice-studio-vm48/tools/build.py
python3 vm-mail-invoice-studio/tools/check.py
python3 vm-mail-invoice-studio-vm48/tools/check.py
```

Python 3 builds the archives; validation additionally requires PHP CLI. ZIPs, checksums, and update feeds are generated in each edition’s `dist/` directory and excluded from Git. Optional design packs have their own build tool and version; VMStudioDesign - Mail Invoice TP 1 is version 1.0 and requires core 1.0.1.

## Add-on news and Pack 1

Studio displays the public Joomla article as a banner at the bottom of its administrator page, reads the article's hidden JSON for edition-specific add-on notices, and compares installed plugin versions. Supported card/grid classes and safe inline styles preserve article formatting. Click **Refresh news** to clear the one-hour article cache.

See [article publishing instructions](announcements/README.md), the [paste-ready article](article.html), and [JSON data](announcements/packs.json). The sample points to public shop product pages; edit it before publishing your own article.

Paid design packs are installed and updated manually. Their manifests no longer register Joomla update servers; core and pack installers remove old associations for the known Studio design plugins. Core updates retain the existing Joomla update mechanism. Pack 1 uses the display name **VMStudioDesign - Mail Invoice TP 1**, release **1.0**, and unchanged plugin/design IDs. Minimal Signature follows the supplied white/grey invoice reference.

## Updating

Publish each edition’s matching ZIP and XML together. Download keys authorize protected updates, not runtime use. An installed 1.0.1 does not receive another 1.0.1 as an automatic update.

The VM4 parent package identity changed in release 1.0.1. Existing sites using the shared old package identity need a separately tested migration before automatic updates can follow the renamed feed. Do not uninstall a working installation merely to change its package identity.

Key Manager currently regenerates feed display names from package prefixes and targets Joomla 5/6 regardless of edition. Verify published compatibility against the generated edition-specific XML.

See [release notes](CHANGELOG.md). Local archive checks do not establish live Joomla/VirtueMart acceptance or JED approval.

Copyright (C) 2026 Darko Fatur / ToPowerYou. GNU GPL version 2 or later; see [LICENSE](LICENSE).
