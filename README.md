# VM Mail & Invoice Studio for VirtueMart

Version **1.0.1** provides email and invoice design tools for two separate Joomla/VirtueMart combinations.

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

Python 3 builds the archives; validation additionally requires PHP CLI. ZIPs, checksums, and update feeds are generated in each edition’s `dist/` directory and excluded from Git. Optional design packs have their own build tool and version; the Invoice & Email Collection is version 1.2.0 and requires core 1.0.1.

## Updating

Publish each edition’s matching ZIP and XML together. Download keys authorize protected updates, not runtime use. An installed 1.0.1 does not receive another 1.0.1 as an automatic update.

The VM4 parent package identity changed in this release. Existing sites using the shared old package identity need a separately tested migration before automatic updates can follow the renamed feed. Do not uninstall a working installation merely to change its package identity.

Key Manager currently regenerates feed display names from package prefixes and targets Joomla 5/6 regardless of edition. Verify published compatibility against the generated edition-specific XML.

See [release notes](CHANGELOG.md). Local archive checks do not establish live Joomla/VirtueMart acceptance or JED approval.

Copyright (C) 2026 Darko Fatur / ToPowerYou. GNU GPL version 2 or later; see [LICENSE](LICENSE).
