"""Generate abstract editorial cover images for the Northline demo blog.

Original geometric compositions only — no third-party imagery, so the client
can ship these or swap them for their own photography.
"""
import math
import os
import random

from PIL import Image, ImageDraw, ImageFilter

W, H = 1600, 1067
OUT = "/tmp/claude-1007/-home-freelancer/a5c67109-9743-4fa2-8ad3-cbba4ea941a5/scratchpad/covers"
os.makedirs(OUT, exist_ok=True)

# Palette drawn from the theme tokens, plus a few tints for depth.
PAPER = (253, 252, 250)
SAND = (242, 238, 231)
INK = (22, 24, 28)
TERRA = (177, 74, 38)
NAVY = (31, 58, 95)
CLAY = (206, 132, 96)
SLATE = (94, 99, 108)
MOSS = (94, 110, 84)


def grain(img, amount=7):
    """Very light paper grain so flat fills do not look like clip art."""
    px = img.load()
    rnd = random.Random(9)
    for y in range(0, H, 2):
        for x in range(0, W, 2):
            n = rnd.randint(-amount, amount)
            r, g, b = px[x, y]
            px[x, y] = (
                max(0, min(255, r + n)),
                max(0, min(255, g + n)),
                max(0, min(255, b + n)),
            )
    return img


def base(bg):
    return Image.new("RGB", (W, H), bg)


def soft(img, radius=0.6):
    return img.filter(ImageFilter.GaussianBlur(radius))


# --- 1. Concentric arcs -------------------------------------------------
def cover_arcs():
    img = base(SAND)
    d = ImageDraw.Draw(img)
    cx, cy = W * 0.62, H * 1.05
    for i, col in enumerate([NAVY, TERRA, CLAY, INK, SLATE, NAVY, TERRA]):
        r = 190 + i * 118
        d.arc([cx - r, cy - r, cx + r, cy + r], 180, 360, fill=col, width=26)
    d.rectangle([0, 0, W * 0.16, H], fill=INK)
    return img


# --- 2. Stacked bars ----------------------------------------------------
def cover_bars():
    img = base(PAPER)
    d = ImageDraw.Draw(img)
    cols = [INK, TERRA, NAVY, CLAY, SLATE, MOSS]
    x = W * 0.10
    rnd = random.Random(4)
    for i in range(11):
        w = rnd.choice([38, 54, 78, 96])
        h = rnd.uniform(0.28, 0.78) * H
        y = (H - h) / 2 + rnd.uniform(-70, 70)
        d.rounded_rectangle([x, y, x + w, y + h], radius=w / 2, fill=cols[i % len(cols)])
        x += w + rnd.choice([30, 44, 58])
        if x > W * 0.95:
            break
    return img


# --- 3. Grid of dots with a drift --------------------------------------
def cover_grid():
    img = base(INK)
    d = ImageDraw.Draw(img)
    for row in range(9):
        for col in range(14):
            t = col / 13
            x = 110 + col * 100
            y = 120 + row * 100 + math.sin(t * math.pi * 1.4 + row * 0.35) * 26
            r = 6 + 20 * (t ** 1.8)
            c = TERRA if (row + col) % 7 == 0 else (SAND if col % 3 else CLAY)
            d.ellipse([x - r, y - r, x + r, y + r], fill=c)
    return img


# --- 4. Overlapping translucent panels ----------------------------------
def cover_panels():
    """Translucent panels, kept light so overlaps stay coloured rather than muddy."""
    img = base(PAPER).convert("RGBA")
    plates = [
        ((*TERRA, 120), 0.05, 0.10, 0.46, 0.62),
        ((*NAVY, 105), 0.30, 0.24, 0.44, 0.60),
        ((*MOSS, 95), 0.52, 0.06, 0.42, 0.66),
        ((*CLAY, 100), 0.18, 0.44, 0.60, 0.44),
    ]
    for col, x, y, w, h in plates:
        layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
        ImageDraw.Draw(layer).rounded_rectangle(
            [x * W, y * H, (x + w) * W, (y + h) * H], radius=16, fill=col
        )
        img = Image.alpha_composite(img, layer)

    out = img.convert("RGB")
    d = ImageDraw.Draw(out)
    d.line([(0, H * 0.86), (W, H * 0.86)], fill=INK, width=4)
    return out


# --- 5. Topographic contour lines --------------------------------------
def cover_contour():
    img = base(SAND)
    d = ImageDraw.Draw(img)
    lines = 40
    for i in range(lines):
        pts = []
        for x in range(0, W + 20, 20):
            t = x / W
            y = (
                H * 1.28
                + math.sin(t * 5.2 + i * 0.26) * (48 + i * 3.4)
                + math.sin(t * 2.1 - i * 0.17) * 34
                - i * (H * 1.42 / lines)
            )
            pts.append((x, y))
        col = TERRA if i % 6 == 0 else (INK if i % 3 == 0 else SLATE)
        d.line(pts, fill=col, width=6 if i % 6 == 0 else 3, joint="curve")
    return img


# --- 6. Half circles on a rule -----------------------------------------
def cover_halves():
    """Half discs balanced on a rule, sized to actually fill the frame."""
    img = base(PAPER)
    d = ImageDraw.Draw(img)
    d.rectangle([0, H * 0.52, W, H], fill=SAND)
    y = H * 0.52
    d.line([0, y, W, y], fill=INK, width=6)

    # Radii sum to just under the frame width so the row spans it without
    # the discs colliding.
    discs = [
        (180, TERRA, True),
        (120, NAVY, False),
        (230, CLAY, True),
        (100, MOSS, False),
        (160, INK, False),
    ]
    total = sum(2 * r for r, _, _ in discs)
    gap = (W - total) / (len(discs) + 1)
    x = gap
    for r, col, up in discs:
        box = [x, y - r, x + 2 * r, y + r]
        d.pieslice(box, 180 if up else 0, 360 if up else 180, fill=col)
        x += 2 * r + gap
    return img


# --- 7. Diagonal weave --------------------------------------------------
def cover_weave():
    img = base(NAVY)
    d = ImageDraw.Draw(img)
    step = 96
    for i in range(-12, 30):
        x = i * step
        col = SAND if i % 4 else TERRA
        d.line([(x, 0), (x + H, H)], fill=col, width=14 if i % 4 else 30)
    for i in range(-4, 16):
        y = i * step * 1.9
        d.line([(0, y), (W, y - W * 0.35)], fill=INK, width=5)
    return img


# --- 8. Radiating spokes ------------------------------------------------
def cover_spokes():
    img = base(SAND)
    d = ImageDraw.Draw(img)
    cx, cy = W * 0.3, H * 0.45
    for i in range(46):
        a = i * (2 * math.pi / 46)
        ln = 340 + 260 * abs(math.sin(i * 0.7))
        x2 = cx + math.cos(a) * ln
        y2 = cy + math.sin(a) * ln
        col = [TERRA, INK, NAVY, CLAY][i % 4]
        d.line([cx, cy, x2, y2], fill=col, width=5 if i % 3 else 11)
    d.ellipse([cx - 120, cy - 120, cx + 120, cy + 120], fill=PAPER)
    d.ellipse([cx - 54, cy - 54, cx + 54, cy + 54], fill=TERRA)
    return img


COVERS = {
    "cover-arcs": cover_arcs,
    "cover-bars": cover_bars,
    "cover-grid": cover_grid,
    "cover-panels": cover_panels,
    "cover-contour": cover_contour,
    "cover-halves": cover_halves,
    "cover-weave": cover_weave,
    "cover-spokes": cover_spokes,
}

if __name__ == "__main__":
    for name, fn in COVERS.items():
        img = grain(soft(fn()))
        path = os.path.join(OUT, f"{name}.jpg")
        img.save(path, "JPEG", quality=88, optimize=True)
        print(path, img.size)
