"""Generate .xlsx parser test fixtures (valid + hostile) into the directory given as argv[1].

    py tests/import/gen-fixtures.py <outdir>

Fixtures are throw-away test inputs (never committed, never shipped). Standard library only.
"""
import os
import struct
import sys
import zipfile

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from xlsxlib import Cell, build_xlsx  # noqa: E402

out = sys.argv[1]
os.makedirs(out, exist_ok=True)


def P(name):
    return os.path.join(out, name)


H = [Cell('SKU', 's', style=2), Cell('Količina', 's', style=2)]


def data(rows):
    return [H] + [[Cell(i, 's'), Cell(q, 'n')] for i, q in rows]


# --- valid -------------------------------------------------------------------------------------------------
build_xlsx(P('valid.xlsx'), [('Narudžba', data([('JS/C/B/+0', 3), ('P-55609', 12), ('Kod, s zarezom & razmakom', 1)]))])
# Text cell keeps "000046"; a numeric cell has already lost the zeros ("46") and must stay "46".
build_xlsx(P('leading-zero.xlsx'), [('S', [H, [Cell('000046', 's'), Cell(2, 'n')], [Cell(46, 'n'), Cell(5, 'n')]])])
build_xlsx(P('rows-500.xlsx'), [('S', data([(f'ID{i:04d}', 1) for i in range(500)]))])
build_xlsx(P('rows-501.xlsx'), [('S', data([(f'ID{i:04d}', 1) for i in range(501)]))])
build_xlsx(P('header-alias.xlsx'), [('S', [[Cell('Šifra artikla', 's'), Cell('KOLICINA', 's')], [Cell('A1', 's'), Cell(1, 'n')]])])
build_xlsx(P('header-ean.xlsx'), [('S', [[Cell('EAN', 's'), Cell('Količina', 's')], [Cell('1', 's'), Cell(1, 'n')]])])
build_xlsx(P('no-header.xlsx'), [('S', [[Cell('ABC', 's'), Cell(2, 'n')]])])
build_xlsx(P('empty.xlsx'), [('S', [H])])
build_xlsx(P('inline-strings.xlsx'), [('S', [[Cell('SKU', 'inline'), Cell('Količina', 'inline')], [Cell('INL-1', 'inline'), Cell('7', 'inline')]])])
build_xlsx(P('blank-rows-and-extra-cols.xlsx'), [('S', {
    'rows': [H + [None, Cell('napomena', 's')], [Cell('A1', 's'), Cell(1, 'n'), None, Cell('x', 's')], [None, None], [Cell('B2', 's'), Cell(2, 'n')]],
    'row_refs': [1, 2, 3, 9]})])

# --- sheets --------------------------------------------------------------------------------------------------
build_xlsx(P('hidden-first.xlsx'), [('Skriveni', data([('HIDDEN-1', 1)])), ('Vidljivi', data([('VISIBLE-1', 2)]))],
           workbook_sheet_attrs=[' state="hidden"', ''])
build_xlsx(P('very-hidden-only.xlsx'), [('Skriveni', data([('HIDDEN-1', 1)]))], workbook_sheet_attrs=[' state="veryHidden"'])
build_xlsx(P('multi-visible.xlsx'), [('Prvi', data([('FIRST-1', 1)])), ('Drugi', data([('SECOND-1', 2)]))])
build_xlsx(P('hidden-rows.xlsx'), [('S', {'rows': data([('A1', 1), ('B2', 2)]), 'hidden_rows': {3}})])

# --- formulas -------------------------------------------------------------------------------------------------
build_xlsx(P('formula-cached.xlsx'), [('S', [H, [Cell('F1', 's'), Cell(6, 'n', formula='2*3')]])])
build_xlsx(P('formula-novalue.xlsx'), [('S', [H, [Cell('F1', 's'), Cell(None, 'nov', formula='2*3')]])])
build_xlsx(P('formula-string-id.xlsx'), [('S', [H, [Cell('CONCAT1', 'str', formula='"CONC"&"AT1"'), Cell(2, 'n')]])])
build_xlsx(P('boolean-quantity.xlsx'), [('S', [H, [Cell('B1', 's'), Cell(1, 'b')]])])

# --- hostile --------------------------------------------------------------------------------------------------
build_xlsx(P('vba.xlsx'), [('S', data([('A1', 1)]))], extra_parts={'xl/vbaProject.bin': b'\xd0\xcf\x11\xe0 fake'})
build_xlsx(P('macro-content-type.xlsx'), [('S', data([('A1', 1)]))],
           content_types_extra='<Override PartName="/xl/vbaProject.bin" ContentType="application/vnd.ms-office.vbaProject"/>')
build_xlsx(P('macro-wb-type.xlsx'), [('S', data([('A1', 1)]))],
           content_types_extra='<!-- macroEnabled -->')
build_xlsx(P('external-links.xlsx'), [('S', data([('A1', 1)]))],
           extra_parts={'xl/externalLinks/externalLink1.xml': '<externalLink xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>',
                        'xl/externalLinks/_rels/externalLink1.xml.rels': '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="x" Target="file:///etc/passwd" TargetMode="External"/></Relationships>'})
build_xlsx(P('sparse-dimension.xlsx'), [('S', {'rows': data([('A1', 1), ('Z9', 2), ('LAST', 3)]),
                                               'dimension': 'A1:XFD1048576', 'row_refs': [1, 1000000, 1048575, 1048576]})])
build_xlsx(P('many-entries.xlsx'), [('S', data([('A1', 1)]))], extra_parts={f'xl/media/x{i}.txt': b'x' for i in range(150)})

# DOCTYPE / entity expansion attempt inside the worksheet
build_xlsx(P('doctype.xlsx'), [('S', data([('A1', 1)]))])
with zipfile.ZipFile(P('doctype.xlsx')) as z:
    parts = {n: z.read(n) for n in z.namelist()}
parts['xl/worksheets/sheet1.xml'] = (b'<?xml version="1.0"?><!DOCTYPE lolz [<!ENTITY a "aaaaaaaaaa"><!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;">]>'
                                     + parts['xl/worksheets/sheet1.xml'].split(b'?>', 1)[1])
with zipfile.ZipFile(P('doctype.xlsx'), 'w', zipfile.ZIP_DEFLATED) as z:
    for n, b in parts.items():
        z.writestr(n, b)

# malformed XML in the worksheet
with zipfile.ZipFile(P('valid.xlsx')) as z:
    parts = {n: z.read(n) for n in z.namelist()}
bad = dict(parts)
bad['xl/worksheets/sheet1.xml'] = parts['xl/worksheets/sheet1.xml'][:-40]
with zipfile.ZipFile(P('malformed-xml.xlsx'), 'w', zipfile.ZIP_DEFLATED) as z:
    for n, b in bad.items():
        z.writestr(n, b)

# malformed ZIP: valid signature, truncated body
raw = open(P('valid.xlsx'), 'rb').read()
open(P('malformed-zip.xlsx'), 'wb').write(raw[: len(raw) // 2])
open(P('not-a-zip.xlsx'), 'wb').write(b'plain text pretending to be xlsx\n')

# > 2 MB compressed (incompressible parts)
build_xlsx(P('over-2mb.xlsx'), [('S', data([('A1', 1)]))], extra_parts={'xl/media/big.bin': os.urandom(2 * 1024 * 1024 + 4096)})


def with_part(name_out, part, payload):
    with zipfile.ZipFile(P('valid.xlsx')) as z:
        p = {n: z.read(n) for n in z.namelist()}
    p[part] = payload
    with zipfile.ZipFile(P(name_out), 'w', zipfile.ZIP_DEFLATED) as z:
        for n, b in p.items():
            z.writestr(n, b)


# honest, highly compressible bombs
huge_sheet = b'<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' \
             + (b'<row r="1"><c r="A1" t="inlineStr"><is><t>x</t></is></c></row>' * 200000) + b'</sheetData></worksheet>'
with_part('sheet-over-cap.xlsx', 'xl/worksheets/sheet1.xml', huge_sheet)  # ~11 MB declared > 8 MB cap
build_xlsx(P('total-over-cap.xlsx'), [('S', data([('A1', 1)]))], extra_parts={'xl/media/zeros.bin': bytes(35 * 1024 * 1024)})  # 35 MB declared

# LYING declared size: real sheet is huge, central + local header claim 2 KB. fflate must NOT expand past the
# declared buffer; the truncated XML is then rejected.
with_part('lying-declared-size.xlsx', 'xl/worksheets/sheet1.xml', huge_sheet)
b = bytearray(open(P('lying-declared-size.xlsx'), 'rb').read())
target = b'xl/worksheets/sheet1.xml'
i = 0
patched = 0
while True:
    i = b.find(b'PK\x01\x02', i)
    if i < 0:
        break
    nlen = struct.unpack_from('<H', b, i + 28)[0]
    if bytes(b[i + 46:i + 46 + nlen]) == target:
        struct.pack_into('<I', b, i + 24, 2048)  # uncompressed size in the central directory
        patched += 1
    i += 4
j = 0
while True:
    j = b.find(b'PK\x03\x04', j)
    if j < 0:
        break
    nlen = struct.unpack_from('<H', b, j + 26)[0]
    if bytes(b[j + 30:j + 30 + nlen]) == target:
        struct.pack_into('<I', b, j + 22, 2048)
        patched += 1
    j += 4
assert patched == 2, patched
open(P('lying-declared-size.xlsx'), 'wb').write(bytes(b))

print('fixtures written to', out)
