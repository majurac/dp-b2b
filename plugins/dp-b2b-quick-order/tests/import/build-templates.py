"""One-off generator for the STATIC import templates committed under assets/templates/.

Run manually after changing the template contract (never at runtime, never in the build):

    py tests/import/build-templates.py

XLSX: header SKU | Količina, identifier column A formatted as Text (so leading zeros such as `000046`
survive), no formulas / macros / external links, a short instruction beside the header (column D, which the
parser ignores). CSV: UTF-8 BOM, semicolon delimiter, `SKU;Količina`.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from xlsxlib import Cell, build_xlsx  # noqa: E402

root = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'assets', 'templates')
os.makedirs(root, exist_ok=True)

instructions = [
    'Upute',
    'U stupac SKU upišite kataloški broj ili SKU artikla, a u stupac Količina željenu količinu (cijeli broj veći od 0).',
    'Stupac SKU je formatiran kao tekst — ne mijenjajte format kako se vodeće nule ne bi izgubile.',
    'Ne mijenjajte nazive stupaca u prvom retku. Najviše 500 redaka.',
]

rows = [[Cell('SKU', 's', style=2), Cell('Količina', 's', style=2), None, Cell(instructions[0], 's', style=2)]]
for line in instructions[1:]:
    rows.append([None, None, None, Cell(line, 's')])

build_xlsx(os.path.join(root, 'dp-quick-order-import-template.xlsx'), [('Narudžba', rows)])

with open(os.path.join(root, 'dp-quick-order-import-template.csv'), 'w', encoding='utf-8-sig', newline='') as fh:
    fh.write('SKU;Količina\r\n')

print('templates written to', os.path.abspath(root))
