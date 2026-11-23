# 5S Score System V30 — Exact Production Template

This version is based on the supplied scanned Production form and the existing 35-item Production criteria shown in the application.

## Fixed paper geometry
- 35 criterion rows.
- 6 physical score cells: 3 left + 3 right.
- Blank separator between the two groups is not read.
- UI unit slots can contain blanks; entered units are mapped sequentially to the 6 physical score cells.
- Row 8 is taller; its internal text line is not treated as a row boundary.

## Safety rule
Handwritten OCR is not guaranteed to be correct. V30 therefore uses a conservative acceptance threshold and leaves uncertain cells blank. Every OCR cell has a preview link when an image is available so the user can verify the actual handwriting before saving.

## Template calibration
Open `calibrate.php` to overlay the fixed row/cell boxes on a scan. This is for verification only and does not alter stored data.

Do not delete the existing `db/database.sqlite` when installing over V29.
