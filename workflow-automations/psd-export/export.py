#!/usr/bin/env python3
"""PSD -> email image export helper. Run through ./psd from the repo root.

Commands:
  layers  <psd> [--depth N]                     print artboards/groups/layers with bboxes
  render  <psd> --artboard NAME --out FILE.png [--hide "a,b"] [--hide-prefix "x|y"]
                 [--show-only "a,b"] [--only-top "Group"] [--crop-y 0,1100]
                 [--mask-below "Body Color,Rectangle 3"]
                                                composite one artboard (or whole PSD) to PNG
  slice   <png> --cuts 0,420,900 --out-dir DIR --prefix hero|image [--start 1]
                                                cut a render at Y positions (full-res px),
                                                scale to max 600 px wide, save PNGs
  trim    <png>                                 crop fully transparent rows/cols off the bottom
  compress <png> [--max-kb 250]                 pngquant --nofs ladder 256>128>112>96, then oxipng
  check   <png...>                              size, dimensions, colors, last-row alpha check
"""
import argparse, os, shutil, subprocess, sys
import numpy as np
from PIL import Image

Image.MAX_IMAGE_PIXELS = None


def _open_psd(path):
    from psd_tools import PSDImage
    return PSDImage.open(path)


def _walk(layer, depth, max_depth, indent=0):
    for l in layer:
        kind = l.kind
        tag = "ARTBOARD" if kind == "artboard" else kind
        print(f'{"  " * indent}[{tag}] "{l.name}" bbox={l.bbox} visible={l.visible}')
        if l.is_group() and indent < max_depth:
            _walk(l, depth, max_depth, indent + 1)


def cmd_layers(a):
    psd = _open_psd(a.psd)
    print(f"size={psd.size}")
    _walk(psd, 0, a.depth)


def _find(root, name):
    for l in root.descendants():
        if l.name == name and l.is_group():
            return l
    return None


def cmd_render(a):
    psd = _open_psd(a.psd)
    hide = {n.strip() for n in (a.hide or "").split(",") if n.strip()}
    only = {n.strip() for n in (a.show_only or "").split(",") if n.strip()}
    prefixes = [n.strip() for n in (a.hide_prefix or "").split("|") if n.strip()]
    for l in psd.descendants():
        if l.name in hide or any(l.name.startswith(p) for p in prefixes):
            l.visible = False
    target = _find(psd, a.artboard) if a.artboard else psd
    if a.artboard and target is None:
        sys.exit(f'Artboard/group "{a.artboard}" not found. Run: ./psd layers {a.psd}')
    if a.only_top:
        keep = {n.strip() for n in a.only_top.split(",") if n.strip()}
        for l in target:
            if l.name not in keep:
                l.visible = False
    if only:
        for l in target.descendants():
            if not l.is_group():
                l.visible = l.name in only
    img = target.composite(force=True) if hasattr(target, "composite") else psd.composite(force=True)
    img = img.convert("RGBA")
    if a.mask_below:
        # Make everything the body color covers (below/outside the divider) transparent,
        # keeping the divider itself. Pass "BodyLayer,DividerLayer" (exact layer names).
        body_name, div_name = (n.strip() for n in a.mask_below.split(","))
        vp = (0, 0, img.width, img.height)

        def alpha_of(name):
            # Names can repeat in a PSD; use the first matching layer that actually has pixels.
            cands = [l for l in psd.descendants() if l.name == name and not l.is_group()]
            if not cands:
                sys.exit(f'Layer "{name}" not found')
            best = None
            for lay in cands:
                lay.visible = True
                im = lay.composite(viewport=vp, force=True)
                arr_a = np.array(im.convert("RGBA").resize(img.size))[:, :, 3].astype(float) / 255
                if arr_a.max() > 0:
                    return arr_a
                best = arr_a
            return best
        body, div = alpha_of(body_name), alpha_of(div_name)
        cover = body * (1 - div)
        arr = np.array(img).astype(float)
        arr[:, :, 3] *= 1 - cover
        img = Image.fromarray(arr.round().astype("uint8"), "RGBA")
    if a.crop_y:
        y0, y1 = (int(v) for v in a.crop_y.split(","))
        img = img.crop((0, y0, img.width, y1))
    os.makedirs(os.path.dirname(os.path.abspath(a.out)), exist_ok=True)
    img.save(a.out)
    print(f"{a.out}: {img.size[0]}x{img.size[1]}")


def cmd_slice(a):
    img = Image.open(a.png).convert("RGBA")
    cuts = [int(c) for c in a.cuts.split(",")]
    if cuts[0] != 0:
        cuts.insert(0, 0)
    if cuts[-1] != img.height:
        cuts.append(img.height)
    os.makedirs(a.out_dir, exist_ok=True)
    for i, (top, bot) in enumerate(zip(cuts, cuts[1:]), start=a.start):
        part = img.crop((0, top, img.width, bot))
        if part.width > 600:  # resize BEFORE compressing
            part = part.resize((600, round(part.height * 600 / part.width)), Image.LANCZOS)
        out = os.path.join(a.out_dir, f"{i}-{a.prefix}.png")
        part.save(out)
        print(f"{out}: {part.size[0]}x{part.size[1]}")


def _trim_bottom(path):
    img = Image.open(path).convert("RGBA")
    alpha = np.array(img)[:, :, 3]
    rows = np.where(alpha.max(axis=1) > 0)[0]
    if len(rows) == 0:
        sys.exit(f"{path}: fully transparent")
    last = rows[-1] + 1
    if last != img.height:
        img.crop((0, 0, img.width, last)).save(path)
        print(f"{path}: trimmed {img.height - last}px of empty bottom rows")


def cmd_trim(a):
    _trim_bottom(a.png)


def _png_colors(path):
    im = Image.open(path)
    return len(im.getpalette()) // 3 if im.mode == "P" else len(set(im.convert("RGBA").getdata()))


def cmd_compress(a):
    if not shutil.which("pngquant") or not shutil.which("oxipng"):
        sys.exit("pngquant and oxipng are required (brew).")
    src = a.png
    limit = a.max_kb * 1024
    used = None
    for colors in (256, 128, 112, 96):
        tmp = src + ".q.png"
        subprocess.run(["pngquant", str(colors), "--nofs", "--force", "--output", tmp, src], check=True)
        subprocess.run(["oxipng", "-o", "4", "--strip", "safe", "-q", tmp], check=True)
        if os.path.getsize(tmp) <= limit:
            os.replace(tmp, src)
            used = colors
            break
        os.remove(tmp)
    if used is None:
        sys.exit(f"{src}: still over {a.max_kb} KB at 96 colors. Split into more slices instead.")
    print(f"{src}: {os.path.getsize(src)/1024:.0f} KB at {used} colors")


def cmd_check(a):
    for p in a.pngs:
        im = Image.open(p).convert("RGBA")
        alpha = np.array(im)[:, :, 3]
        rows = np.where(alpha.max(axis=1) > 0)[0]
        last_ok = len(rows) > 0 and rows[-1] == im.height - 1
        print(f"{p}: {im.width}x{im.height} {os.path.getsize(p)/1024:.0f} KB "
              f"{_png_colors(p)} colors bottom-row-has-pixels={last_ok} "
              f"{'OK' if im.width <= 600 and os.path.getsize(p) < 250*1024 else 'OVER LIMIT'}")


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    s = p.add_subparsers(dest="cmd", required=True)
    x = s.add_parser("layers"); x.add_argument("psd"); x.add_argument("--depth", type=int, default=2); x.set_defaults(f=cmd_layers)
    x = s.add_parser("render"); x.add_argument("psd"); x.add_argument("--artboard"); x.add_argument("--out", required=True)
    x.add_argument("--hide"); x.add_argument("--show-only"); x.add_argument("--hide-prefix", help='layer-name prefixes to hide, separated by "|"')
    x.add_argument("--only-top", help="keep only these direct children of the artboard visible"); x.add_argument("--crop-y", help="y0,y1 crop (full-res px)")
    x.add_argument("--mask-below", help='"BodyLayer,DividerLayer": make body-color-covered area transparent'); x.set_defaults(f=cmd_render)
    x = s.add_parser("slice"); x.add_argument("png"); x.add_argument("--cuts", required=True)
    x.add_argument("--out-dir", required=True); x.add_argument("--prefix", required=True, choices=["hero", "image"])
    x.add_argument("--start", type=int, default=1); x.set_defaults(f=cmd_slice)
    x = s.add_parser("trim"); x.add_argument("png"); x.set_defaults(f=cmd_trim)
    x = s.add_parser("compress"); x.add_argument("png"); x.add_argument("--max-kb", type=int, default=250); x.set_defaults(f=cmd_compress)
    x = s.add_parser("check"); x.add_argument("pngs", nargs="+"); x.set_defaults(f=cmd_check)
    a = p.parse_args()
    a.f(a)


if __name__ == "__main__":
    main()
