#!/usr/bin/env python3
"""Exercise the real installer with Joomla/database doubles. GPL-2.0-or-later."""
from pathlib import Path
import os
import subprocess

php = os.environ.get('PHP_BIN', 'php')
test = Path(__file__).resolve().parents[1] / 'tests/preflight.php'
cases = []
for version in ['4.2.6', '4.2.6.10972', '4.2.18', '4.4.0', '4.4.4.11101', '4.6.0', '4.6.4.11226', '4.8.0', '4.8.8.11614']:
    cases.append(('5.0.0', version, 'present', 'accept'))
for version in ['', 'garbage', '3.8.0', '4.0.22', '4.1.0', '4.2.0', '4.2.5', '4.3.0', '4.5.0', '4.7.0', '4.9.0', '5.0.0', '4.8.0-RC1', '4.8.bad', '4.8.0.extra']:
    cases.append(('5.0.0', version, 'present', 'reject'))
for joomla in ['4.4.0', '6.0.0']:
    cases.append((joomla, '4.8.0', 'present', 'reject'))
cases += [('5.4.0', '4.2.6', 'present', 'accept'), ('5.4.0', '4.8.0', 'absent', 'reject')]
for case in cases:
    subprocess.run([php, str(test), *case], check=True)
print(f'PASS: {len(cases)} installer scenarios')
