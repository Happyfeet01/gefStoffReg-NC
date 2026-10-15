"""Create synthetic extraction fixtures (requires reportlab and Pillow).

Usage: python3 tests/create-sds-fixtures.py /tmp/gsk-test-fixtures
"""
import sys
from pathlib import Path
from reportlab.pdfgen import canvas
from reportlab.lib.utils import ImageReader
from PIL import Image, ImageDraw, ImageFont

target = Path(sys.argv[1])
target.mkdir(parents=True, exist_ok=True)
lines = ['SECTION 1: Identification', 'Synthetic test fixture - not a real safety data sheet.',
         'SECTION 2: Hazards identification', '2.1 Classification', 'Skin Corr. 1A H314',
         '2.2 Label elements', 'Danger', 'GHS05', 'H314 Causes severe skin burns and eye damage.',
         'SECTION 3: Composition', 'H301 Ingredient-only hazard - do not import.']
pdf = canvas.Canvas(str(target / 'text.pdf'))
for i, line in enumerate(lines):
    pdf.drawString(40, 800-i*24, line)
pdf.save()
image = Image.new('RGB', (1654, 2339), 'white')
draw = ImageDraw.Draw(image)
font = ImageFont.truetype('DejaVuSans.ttf', 30)
for i, line in enumerate(lines):
    draw.text((70, 100+i*65), line, fill='black', font=font)
pdf = canvas.Canvas(str(target / 'scan.pdf'))
pdf.drawImage(ImageReader(image), 0, 0, width=595, height=842)
pdf.save()
