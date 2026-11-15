#!/usr/bin/env python3
"""Build one separate, deterministic Joomla design-pack ZIP. GPL-2.0-or-later."""

import argparse
import hashlib
import io
from pathlib import Path, PurePosixPath
import re
import xml.etree.ElementTree as ET
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

ROOT = Path(__file__).resolve().parents[1]


def manifest_path(text):
    """Only portable relative archive paths are accepted in manifests."""
    value = (text or '').strip()
    path = PurePosixPath(value)
    if (not value or '\\' in value or path.is_absolute()
            or any(part in ('', '.', '..') for part in value.split('/'))
            or ':' in value):
        raise ValueError(f'Invalid manifest path: {value!r}')
    return path


def check_source(path):
    if path.is_symlink():
        raise ValueError(f'Symbolic links cannot be packaged: {path}')
    if any(part.startswith('.') for part in path.parts) or path.name.endswith('~'):
        raise ValueError(f'Hidden or backup files cannot be packaged: {path}')
    if path.suffix.lower() in ('.bak', '.orig', '.rej', '.tmp', '.swp', '.swo'):
        raise ValueError(f'Backup files cannot be packaged: {path}')


def collect(source):
    manifests = sorted(source.glob('*.xml'))
    if len(manifests) != 1:
        raise ValueError('The plugin source must contain exactly one root XML manifest.')
    manifest = manifests[0]
    xml = ET.parse(manifest).getroot()
    if xml.tag != 'extension' or xml.get('type') != 'plugin' or xml.get('group') != 'vmstudiodesign':
        raise ValueError('Expected a vmstudiodesign plugin manifest.')
    version = (xml.findtext('version') or '').strip()
    if not re.fullmatch(r'\d+\.\d+(?:\.\d+)?', version):
        raise ValueError('The version must use two or three numeric components.')
    elements = [entry.get('plugin') for entry in xml.findall('./files/*') if entry.get('plugin')]
    if len(elements) != 1 or not re.fullmatch(r'[a-z][a-z0-9_]*', elements[0]):
        raise ValueError('The manifest must declare exactly one valid plugin element.')
    element = elements[0]
    if manifest.name != element + '.xml':
        raise ValueError('The manifest filename must match the plugin element.')

    files = {}

    def add(path):
        relative = path.relative_to(source)
        check_source(relative)
        # Check every ancestor too: a declared folder must not follow a symlink.
        for parent in (path, *path.parents):
            if parent == source:
                break
            if parent.is_symlink():
                raise ValueError(f'Symbolic links cannot be packaged: {relative}')
        if not path.is_file():
            raise ValueError(f'Missing manifest file: {relative}')
        files[relative.as_posix()] = path.read_bytes()

    add(manifest)
    entries = list(xml.findall('./files/*'))
    script = xml.find('scriptfile')
    if script is not None:
        entries.append(script)
    for entry in entries:
        if entry.tag not in ('folder', 'filename', 'scriptfile'):
            raise ValueError(f'Unsupported manifest file entry: {entry.tag}')
        relative = manifest_path(entry.text)
        path = source.joinpath(*relative.parts)
        check_source(Path(relative))
        if path.is_symlink():
            raise ValueError(f'Symbolic links cannot be packaged: {relative}')
        if entry.tag == 'folder':
            if not path.is_dir():
                raise ValueError(f'Missing manifest folder: {relative}')
            for child in sorted(path.rglob('*')):
                check_source(child.relative_to(source))
                if child.is_symlink():
                    raise ValueError(f'Symbolic links cannot be packaged: {child}')
                if child.is_file():
                    add(child)
        else:
            add(path)
    if 'LICENSE.txt' not in files:
        raise ValueError('The plugin must include LICENSE.txt in its manifest.')
    if 'services/provider.php' not in files:
        raise ValueError('The plugin must include services/provider.php.')
    return element, version, files


def archive(files):
    output = io.BytesIO()
    with ZipFile(output, 'w', ZIP_DEFLATED) as zipped:
        for name, data in sorted(files.items()):
            info = ZipInfo(name, (2026, 11, 12, 0, 0, 0))
            info.compress_type = ZIP_DEFLATED
            info.external_attr = 0o644 << 16
            zipped.writestr(info, data)
    return output.getvalue()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('source', nargs='?', type=Path,
                        default=ROOT / 'design-packs' / 'studioexample')
    args = parser.parse_args()
    if args.source.is_symlink():
        parser.error('The plugin source cannot be a symbolic link.')
    try:
        element, version, files = collect(args.source.resolve())
        data = archive(files)
    except (ValueError, OSError, ET.ParseError) as error:
        parser.error(str(error))
    # Derive the distribution filename from the manifest release version.
    name = (f'plg_vmmailinvoicestudio_vm48_template_pack_v{version}.zip'
            if element == 'studioinvoicecollection'
            else f'plg_vmstudiodesign_{element}_vm48_v{version}.zip')
    destination = ROOT / 'dist' / 'design-packs'
    destination.mkdir(parents=True, exist_ok=True)
    (destination / name).write_bytes(data)
    digest = hashlib.sha256(data).hexdigest()
    (destination / (name + '.sha256')).write_text(f'{digest}  {name}\n')
    print(f'Built {name} ({len(data):,} bytes)\nSHA-256 {digest}')


if __name__ == '__main__':
    main()
