#!/usr/bin/env python3
"""Local package, security declaration and language checks. Not a JED certification."""
from pathlib import Path
import xml.etree.ElementTree as ET
import zipfile, io, re, subprocess, sys, os
root=Path(__file__).resolve().parents[1]
php=os.environ.get('PHP_BIN','php')
count=0
folders = ['component', 'plugin'] + [p.relative_to(root).as_posix() for p in sorted((root/'design-packs').iterdir()) if p.is_dir()]
for p in [p for folder in folders + ['package'] for p in (root/folder).rglob('*.xml')]:
    ET.parse(p);count+=1
for folder in folders:
    for p in (root/folder).rglob('*.php'):
        s=p.read_text()
        assert "defined('_JEXEC') or die" in s, p
        assert 'GNU General Public License version 2 or later' in s,p
        subprocess.run([php,'-n','-l',str(p)],check=True,stdout=subprocess.DEVNULL);count+=1
    manifest=next((root/folder).glob('*.xml'))
    xml=ET.parse(manifest).getroot()
    assert xml.find('license') is not None
    if folder in ('component', 'plugin'):
        assert xml.find('updateservers/server').text.startswith('https://shop.topoweryou.com/')
    else:
        assert xml.attrib['group'] == 'vmstudiodesign'
    for node in xml.findall('.//files')+[xml.find('media')]:
        if node is None: continue
        base=root/folder/node.attrib.get('folder','')
        for f in node:
            assert (base/f.text).exists(),(manifest,f.text)
    for script in xml.findall('scriptfile'):assert (root/folder/script.text).is_file()
    language='\n'.join(p.read_text() for p in (root/folder).rglob('*.ini'))
    keys=set(re.findall(r'^(\w+)=',language,re.M))
    for p in (root/folder).rglob('*'):
        if p.suffix not in ('.php','.xml'):continue
        for key in re.findall(r"['\"]((?:COM_VMMAILINVOICESTUDIO|PLG_INSTALLER_VMSTUDIOUPDATE|PLG_VMSTUDIODESIGN_STUDIOEXAMPLE)[A-Z_]+)['\"]",p.read_text()):
            if key.endswith('_'):continue
            assert key in keys,(p,key)
for p in (root/'dist').glob('*.zip'):
    with zipfile.ZipFile(p) as z:
        assert z.testzip() is None
        assert not any('vmstudiodesign' in name for name in z.namelist()), 'Core must not bundle optional packs'
        xml=ET.fromstring(z.read('pkg_vmmailinvoicestudio.xml'))
        lang = xml.find("languages/language[@tag='en-GB']")
        assert lang is not None, 'Package must declare an English system language file'
        lang_path = xml.find('languages').get('folder', '').strip('/') + '/' + lang.text
        assert lang_path.endswith('/pkg_vmmailinvoicestudio.sys.ini')
        translations = dict(re.findall(r'^([A-Z0-9_]+)="(.*)"$', z.read(lang_path).decode(), re.M))
        assert xml.findtext('name') in translations, 'Package install name must translate'
        with zipfile.ZipFile(io.BytesIO(z.read('com_vmmailinvoicestudio.zip'))) as component:
            component_lang = component.read('admin/language/en-GB/com_vmmailinvoicestudio.sys.ini').decode()
            component_name = re.search(r'^COM_VMMAILINVOICESTUDIO="(.*)"$', component_lang, re.M).group(1)
            assert translations[xml.findtext('name')] == component_name, 'Package/component names must match'
        for f in xml.findall('files/file'):
            with zipfile.ZipFile(io.BytesIO(z.read(f.text))) as child:assert child.testzip() is None
for p in (root/'dist'/'design-packs').glob('*.zip'):
    with zipfile.ZipFile(p) as z:
        assert z.testzip() is None
        manifests = [name for name in z.namelist() if '/' not in name and name.endswith('.xml')]
        assert len(manifests) == 1
        xml = ET.fromstring(z.read(manifests[0]))
        assert xml.attrib['type'] == 'plugin' and xml.attrib['group'] == 'vmstudiodesign'
        assert 'services/provider.php' in z.namelist() and 'LICENSE.txt' in z.namelist()
print(f'PASS: {count} XML/PHP files; manifests, language keys and nested archive integrity')
