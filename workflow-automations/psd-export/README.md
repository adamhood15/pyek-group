# PSD export tooling

Reusable tooling for the "Email Image Export" steps in the root CLAUDE.md. Do not write ad-hoc
PSD/Pillow scripts; use `./psd` from the repo root.

- `./psd setup` creates `.venv/` here from `requirements.txt` (one time per clone; git-ignored).
- `./psd layers file.psd` lists artboards, groups and layers.
- `./psd render file.psd --artboard "Name" --out scratch/full.png [--hide "Body Color,Copy"]`
- `./psd slice scratch/full.png --cuts 0,420 --out-dir out --prefix hero`
- `./psd trim file.png`, `./psd compress file.png`, `./psd check out/*.png`

Output goes in the scratchpad or a user-named folder, never committed. If the tool lacks a feature,
extend `export.py` rather than writing a one-off script.
