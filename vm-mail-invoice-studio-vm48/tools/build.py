#!/usr/bin/env python3
"""Build deterministic Joomla installation archives. GPL-2.0-or-later."""
from pathlib import Path
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED
import hashlib, io, xml.etree.ElementTree as ET
ROOT = Path(__file__).resolve().parents[1]
DIST = ROOT / 'dist'
DIST.mkdir(exist_ok=True)
VERSION = ET.parse(ROOT/'package/pkg_vmmailinvoicestudio_vm48.xml').getroot().findtext('version')

def archive(files):
    output = io.BytesIO()
    with ZipFile(output, 'w', ZIP_DEFLATED) as z:
        for name, data in sorted(files.items()):
            info = ZipInfo(name, (2026,11,11,0,0,0))
            info.compress_type = ZIP_DEFLATED
            info.external_attr = 0o644 << 16
            z.writestr(info, data)
    return output.getvalue()

def tree(folder):
    return {p.relative_to(folder).as_posix():p.read_bytes() for p in folder.rglob('*') if p.is_file()}

component = archive(tree(ROOT/'component'))
plugin = archive(tree(ROOT/'plugin'))
files = tree(ROOT/'package')
files.update({'com_vmmailinvoicestudio.zip':component,'plg_installer_vmstudioupdate.zip':plugin})
name = f'pkg_vmmailinvoicestudio_vm48_v{VERSION}.zip'
package = archive(files)
(DIST/name).write_bytes(package)
sha = hashlib.sha256(package).hexdigest()
(DIST/(name+'.sha256')).write_text(f'{sha}  {name}\n')
updates=ET.Element('updates')
u=ET.SubElement(updates,'update')
for key,value in [('name','VM 4 Mail Invoice Studio'),('description','Order email and PDF invoice design for VirtueMart 4.2.6–4.8'),('element','pkg_vmmailinvoicestudio_vm48'),('type','package'),('version',VERSION),('client','site')]: ET.SubElement(u,key).text=value
ET.SubElement(u,'infourl',{'title':'ToPowerYou'}).text='https://topoweryou.com'
d=ET.SubElement(u,'downloads')
ET.SubElement(d,'downloadurl',{'type':'full','format':'zip'}).text=f'https://shop.topoweryou.com/index.php?option=com_vmupdatekeymanager&task=download.get&file={name}&channel=update'
ET.SubElement(u,'dlid',{'prefix':'key=','suffix':''})
ET.SubElement(u,'tags');ET.SubElement(u.find('tags'),'tag').text='stable'
ET.SubElement(u,'targetplatform',{'name':'joomla','version':'5\.[0-9]+'})
ET.SubElement(u,'php_minimum').text='8.1.0'
ET.SubElement(u,'sha256').text=sha
ET.indent(updates)
(DIST/'vmmailinvoicestudio-vm48.xml').write_bytes(ET.tostring(updates,encoding='utf-8',xml_declaration=True))
print(f'Built {name} ({len(package):,} bytes)\nSHA-256 {sha}')
