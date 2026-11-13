# VM Mail & Invoice Studio

Joomla 6 administrator component and licensed-update installer plugin for VirtueMart 5.

This extension styles VirtueMart's existing HTML order emails and invoice documents. It does not calculate taxes, issue invoice numbers, send additional emails, or change order statuses. Those remain VirtueMart responsibilities.

## Installation

1. Install VirtueMart 5 and its TCPDF package on Joomla 6 / PHP 8.3 or later.
2. Upload `dist/pkg_vmmailinvoicestudio_v1.0.1.zip` using Joomla's extension installer.
3. Open Components → VM Mail & Invoice Studio. Choose your email and invoice designs, accent colour, optional local logo, introduction and closing text. Save.
4. Preview the sample email and invoice. PDF preview uses VirtueMart's PDF engine and current vendor paper settings; it contains fictional data.
5. Enable VirtueMart layout overrides (`useLayoutOverrides`). Select the **site template used by VirtueMart** and apply Studio's overrides. Existing third-party overrides are not overwritten. Repeat for other templates if needed.
6. Enable HTML order emails in VirtueMart. Test an actual order and a newly generated invoice on staging.

Studio starts with both document designs disabled. Enable them in the designer when ready. Existing archived PDFs are not regenerated. Confirmed-order invoicing is controlled by VirtueMart's invoice status configuration (normally C); Studio never labels an unpaid order as paid.

## Existing VirtueMart features reused

Vendor logo; letter header/footer HTML; vendor placeholders; font sizes; paper format and orientation; margins; recurring PDF header/footer; terms and conditions; shopper/vendor email messages; addresses; item tables; discounts; tax breakdowns; payment/shipment details; status history; invoice/credit-note number and dates. Native subtemplates remain in use, including their plugin output and translations.

Studio includes Classic and Modern permanently, separate email/invoice selection, local branding, optional plain-text introductions and closings, HTML previews and a sample PDF. Additional designs can be installed as separate Joomla plugins in the `vmstudiodesign` group. Packs cannot replace the bundled designs. A missing, disabled or incompatible pack falls back to a core design while preserving the saved selection.

See Design packs for installation, the optional example ZIP, the API and selling independently updated packs. The core installation does not include the example plugin. Customer-entered executable code and a drag-and-drop editor are outside this release.

## Reverting and uninstalling

Disable either design to render VirtueMart's original layout. Use Remove Studio overrides for a selected template to remove only byte-identical Studio-owned wrappers. Modified wrappers are preserved and reported. Component uninstall removes unchanged wrappers from installed site templates. Preserved wrappers fall back to native VirtueMart if Studio is no longer installed. No original VirtueMart file is modified.

## Updates

See `docs/UPDATE-MANAGER.md`. The update endpoint must be published on your shop before distribution. A download key affects update downloads only; there is no activation call and no runtime licence lock.

## Validation

See `docs/VALIDATION.md` for automated checks and the staging acceptance checklist. JED Checker scans and live Joomla/VirtueMart acceptance must be recorded before claiming production compatibility or JED approval.

Copyright (C) 2026 Darko Fatur / ToPowerYou. GNU General Public License version 2 or later. No affiliation with the Joomla or VirtueMart projects is implied.

## Invoice & Email Collection

Install core 1.0.1 before collection pack 1.2.0. Both updates are required for consistent native invoice/email styling and Studio previews. See collection verification for evidence, review files and remaining live-store checks.

## Building version 1.0.1

Run `python3 tools/build.py` from this edition directory. Run `python3 tools/check.py` with PHP CLI available. Generated installers and feeds are written to `dist/` and are not committed. Supporting validation documents referenced above are maintained outside this source repository.

The package manifest uses a readable install name. The update feed uses `<client>site</client>` and targets only this edition’s Joomla major version. Key Manager currently regenerates display names from the configured package prefix and hardcodes Joomla 5/6 compatibility; publishing through it requires checking the resulting feed.
