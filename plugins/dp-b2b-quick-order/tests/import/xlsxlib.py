"""Tiny .xlsx writer used ONLY to generate the static import templates and parser test fixtures.

Not shipped at runtime and not a dependency of the plugin: the committed templates are generated once with
tests/import/build-templates.py. Standard library only (zipfile).
"""
import zipfile
from xml.sax.saxutils import escape

NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'


def col_letters(i):
    s = ''
    i += 1
    while i:
        i, r = divmod(i - 1, 26)
        s = chr(65 + r) + s
    return s


class Cell:
    """kind: 's' shared string | 'inline' | 'n' number (raw text) | 'str' formula string | 'b' | 'e' | 'f' formula"""

    def __init__(self, value, kind='s', formula=None, style=None):
        self.value, self.kind, self.formula, self.style = value, kind, formula, style


def build_xlsx(path, sheets, text_column_a=True, extra_parts=None, content_types_extra='',
               workbook_sheet_attrs=None, declared_override=None, workbook_extra=''):
    """sheets: list of (name, rows) where rows is a list of lists of Cell|str|None, or dict with
    'rows' and optional 'hidden_rows' (set of 1-based row numbers) / 'dimension' / 'row_refs' (list of explicit row numbers)."""
    shared, shared_idx = [], {}

    def sst(text):
        if text not in shared_idx:
            shared_idx[text] = len(shared)
            shared.append(text)
        return shared_idx[text]

    sheet_xml = []
    for si, (name, spec) in enumerate(sheets):
        if isinstance(spec, dict):
            rows, hidden_rows, dim, row_refs = spec['rows'], spec.get('hidden_rows', set()), spec.get('dimension'), spec.get('row_refs')
        else:
            rows, hidden_rows, dim, row_refs = spec, set(), None, None
        out = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
               f'<worksheet xmlns="{NS}" xmlns:r="{NS_R}">']
        if dim:
            out.append(f'<dimension ref="{dim}"/>')
        if text_column_a:
            out.append('<cols><col min="1" max="1" width="30" style="1" customWidth="1"/><col min="2" max="2" width="14" customWidth="1"/></cols>')
        out.append('<sheetData>')
        for ri, row in enumerate(rows):
            rn = row_refs[ri] if row_refs else ri + 1
            hid = ' hidden="1"' if rn in hidden_rows else ''
            out.append(f'<row r="{rn}"{hid}>')
            for ci, cell in enumerate(row):
                if cell is None:
                    continue
                if not isinstance(cell, Cell):
                    cell = Cell(cell, 's')
                ref = f'{col_letters(ci)}{rn}'
                st = f' s="{cell.style}"' if cell.style is not None else ''
                f = f'<f>{escape(cell.formula)}</f>' if cell.formula else ''
                if cell.kind == 's':
                    out.append(f'<c r="{ref}"{st} t="s"><v>{sst(cell.value)}</v></c>')
                elif cell.kind == 'inline':
                    out.append(f'<c r="{ref}"{st} t="inlineStr"><is><t xml:space="preserve">{escape(cell.value)}</t></is></c>')
                elif cell.kind == 'n':
                    out.append(f'<c r="{ref}"{st}>{f}<v>{escape(str(cell.value))}</v></c>')
                elif cell.kind == 'nov':  # formula without cached value
                    out.append(f'<c r="{ref}"{st}>{f}</c>')
                elif cell.kind == 'str':
                    out.append(f'<c r="{ref}"{st} t="str">{f}<v>{escape(cell.value)}</v></c>')
                elif cell.kind == 'b':
                    out.append(f'<c r="{ref}"{st} t="b"><v>{cell.value}</v></c>')
                elif cell.kind == 'e':
                    out.append(f'<c r="{ref}"{st} t="e"><v>{escape(cell.value)}</v></c>')
            out.append('</row>')
        out.append('</sheetData></worksheet>')
        sheet_xml.append('\n'.join(out))

    sheet_attrs = workbook_sheet_attrs or [''] * len(sheets)
    wb_sheets = ''.join(
        f'<sheet name="{escape(n)}" sheetId="{i+1}"{sheet_attrs[i]} r:id="rId{i+1}"/>' for i, (n, _) in enumerate(sheets))
    workbook = (f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                f'<workbook xmlns="{NS}" xmlns:r="{NS_R}"><sheets>{wb_sheets}</sheets>{workbook_extra}</workbook>')
    n = len(sheets)
    rels = ''.join(
        f'<Relationship Id="rId{i+1}" Type="{NS_R}/worksheet" Target="worksheets/sheet{i+1}.xml"/>' for i in range(n))
    rels += f'<Relationship Id="rId{n+1}" Type="{NS_R}/styles" Target="styles.xml"/>'
    rels += f'<Relationship Id="rId{n+2}" Type="{NS_R}/sharedStrings" Target="sharedStrings.xml"/>'
    wb_rels = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
               f'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">{rels}</Relationships>')
    overrides = ''.join(
        f'<Override PartName="/xl/worksheets/sheet{i+1}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        for i in range(n))
    content_types = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                     '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                     '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                     '<Default Extension="xml" ContentType="application/xml"/>'
                     '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                     f'{overrides}'
                     '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                     '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
                     f'{content_types_extra}</Types>')
    root_rels = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                 '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                 f'<Relationship Id="rId1" Type="{NS_R}/officeDocument" Target="xl/workbook.xml"/></Relationships>')
    # xf 0 = default; xf 1 = Text (numFmtId 49, "@"); xf 2 = bold header, Text
    styles = (f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="{NS}">'
              '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
              '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
              '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
              '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
              '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
              '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
              '<xf numFmtId="49" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/></cellXfs>'
              '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>')
    sst_xml = (f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="{NS}" count="{len(shared)}" uniqueCount="{len(shared)}">'
               + ''.join(f'<si><t xml:space="preserve">{escape(t)}</t></si>' for t in shared) + '</sst>')

    with zipfile.ZipFile(path, 'w', zipfile.ZIP_DEFLATED) as z:
        z.writestr('[Content_Types].xml', content_types)
        z.writestr('_rels/.rels', root_rels)
        z.writestr('xl/workbook.xml', workbook)
        z.writestr('xl/_rels/workbook.xml.rels', wb_rels)
        z.writestr('xl/styles.xml', styles)
        z.writestr('xl/sharedStrings.xml', sst_xml)
        for i, x in enumerate(sheet_xml):
            z.writestr(f'xl/worksheets/sheet{i+1}.xml', x)
        for name, data in (extra_parts or {}).items():
            z.writestr(name, data)
