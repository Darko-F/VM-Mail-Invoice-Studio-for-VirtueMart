# Changelog

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
