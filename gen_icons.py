import zlib
import struct
import os

rowsA = [
    "    XX    ",
    "   X  X   ",
    "   X  X   ",
    "  X    X  ",
    "  XXXXXX  ",
    "  X    X  ",
    " X      X ",
    " X      X ",
    " X      X ",
]
rowsT = [
    " XXXXXXX ",
    "    X    ",
    "    X    ",
    "    X    ",
    "    X    ",
    "    X    ",
    "    X    ",
    "    X    ",
    "    X    ",
]
rows = [rowsA[i] + '  ' + rowsT[i] for i in range(len(rowsA))]

bg = (26, 35, 50)
fg = (255, 255, 255)

def write_png(path, width, height, pixels):
    def chunk(t, d):
        return struct.pack('>I', len(d)) + t + d + struct.pack('>I', zlib.crc32(t + d) & 0xffffffff)
    raw = b''
    for y in range(height):
        raw += b'\x00'
        for x in range(width):
            raw += bytes(pixels[y][x])
    png = b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', width, height, 8, 2, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(raw)) + chunk(b'IEND', b'')
    with open(path, 'wb') as f:
        f.write(png)

base = r'd:\PeopleDisplay_ASSET_install\assettrack'
for size, rel in [(192, r'assets\img\icon-192.png'), (512, r'assets\img\icon-512.png')]:
    path = os.path.join(base, rel)
    scale = size // (len(rows) + 4)
    width = len(rows[0]) * scale + scale * 4
    height = len(rows) * scale + scale * 4
    pixels = [[bg for _ in range(width)] for __ in range(height)]
    offset_x = (width - len(rows[0]) * scale) // 2
    offset_y = (height - len(rows) * scale) // 2
    for ry, row in enumerate(rows):
        for rx, ch in enumerate(row):
            if ch == 'X':
                for dy in range(scale):
                    for dx in range(scale):
                        pixels[offset_y + ry * scale + dy][offset_x + rx * scale + dx] = fg
    os.makedirs(os.path.dirname(path), exist_ok=True)
    write_png(path, width, height, pixels)
    print('Generated ' + path)
