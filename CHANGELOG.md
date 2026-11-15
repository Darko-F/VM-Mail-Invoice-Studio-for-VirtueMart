# Changelog

## 1.0.2 — 2026-11-15

- Add an article-based news banner and hidden-JSON add-on catalogue for VM4 and VM5, with installed-version comparison and manual shop links.
- Preserve safe inline article formatting, responsive card/grid classes and readable light cards in dark admin themes; place news below component content.
- Cache the public article for one hour and provide a permission-checked, CSRF-protected Refresh news action.
- Remove Joomla update-site declarations and generated update XML from optional design packs; clean old associations on install/update while preserving unrelated extensions.
- Name Pack 1 `VMStudioDesign - Mail Invoice TP 1` in both editions, retain its plugin/design IDs and use release version 1.0.
- Restyle Minimal Signature with left branding, compact right-aligned invoice headings, fine grey rows and right-aligned totals, preserving native invoice data.
- Supply a paste-ready article, corrected edition-specific product links, JSON examples and publishing instructions.

### Validation

Both editions passed 91 article/parser/status checks, 680 collection rendering assertions with TCPDF 6.3.2, PHP syntax checks, XML/language checks and archive integrity checks. A temporary PHP 8.1 CLI runtime was used; the VM5 target remains PHP 8.3+/Joomla 6. Full live Joomla installation/update and JED approval are not established by these local checks.


## 1.0.1 — 2026-11-14

- Include current invoice/email rendering and design-catalog changes and their fixture tests.
- Add optional Invoice & Email Collection 1.2.0 sources; the collection requires core 1.0.1.
- Use readable package install names: VM 5 Mail Invoice Studio and VM 4 Mail Invoice Studio.
- Give the VM4 package its own `pkg_vmmailinvoicestudio_vm48` identity, manifest filename and system-language keys.
- Generate update feeds with `client=site`, matching package identities, edition-specific Joomla targets and ZIP SHA-256 checksums.
- Add public build, installation and update documentation.

### Upgrade limitations

The VM4 identity change does not migrate old package records automatically. Test migration separately for existing installations. Package version remains 1.0.1; previously installed builds reporting 1.0.1 are not eligible for a same-version automatic update. Key Manager’s existing publisher can replace feed names and compatibility metadata.

### Validation

Both release archives are checked locally for XML identity, version, language declarations, ZIP integrity and matching feed checksums. Full PHP checks, JED Checker and live installation/update tests remain required; PHP CLI was unavailable during this preparation.
