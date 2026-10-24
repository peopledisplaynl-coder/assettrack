"""Creates docs/handleiding/reference.docx: the Word style template used by pandoc
(AssetTrack colours, readable fonts, coloured table header)."""
import subprocess, sys
from docx import Document
from docx.shared import Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH

out = sys.argv[1]
subprocess.run(['pandoc', '-o', out, '--print-default-data-file', 'reference.docx'], check=True)
with open(out, 'wb') as f:
    f.write(subprocess.run(['pandoc', '--print-default-data-file', 'reference.docx'], check=True, capture_output=True).stdout)
doc = Document(out)
NAVY, BLUE, GREY = RGBColor(0x1a, 0x23, 0x32), RGBColor(0x25, 0x63, 0xeb), RGBColor(0x64, 0x74, 0x8b)
def style(name, size=None, color=None, bold=None, font='Calibri'):
    try:
        s = doc.styles[name]
    except KeyError:
        return
    s.font.name = font
    rpr = s.element.get_or_add_rPr()
    rfonts = rpr.find('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}rFonts')
    if rfonts is not None:
        for attr in ('ascii', 'hAnsi', 'cs', 'eastAsia'):
            rfonts.set('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}' + attr, font)
        for attr in ('asciiTheme', 'hAnsiTheme', 'cstheme', 'eastAsiaTheme'):
            rfonts.attrib.pop('{http://schemas.openxmlformats.org/wordprocessingml/2006/main}' + attr, None)
    if size: s.font.size = Pt(size)
    if color is not None: s.font.color.rgb = color
    if bold is not None: s.font.bold = bold
for n in ('Normal', 'Body Text', 'First Paragraph', 'Compact'):
    style(n, 10.5, RGBColor(0x1e, 0x29, 0x3b))
style('Title', 30, NAVY, True)
style('Subtitle', 15, BLUE, True)
style('Author', 11, GREY); style('Date', 11, GREY)
style('Heading 1', 18, NAVY, True); style('Heading 2', 13.5, BLUE, True); style('Heading 3', 11.5, NAVY, True)
style('Image Caption', 8.5, GREY); style('Block Text', 10, NAVY)
for n in ('Title', 'Subtitle', 'Author', 'Date', 'Image Caption', 'Captioned Figure'):
    try: doc.styles[n].paragraph_format.alignment = WD_ALIGN_PARAGRAPH.CENTER
    except KeyError: pass
doc.save(out)
print(out)
