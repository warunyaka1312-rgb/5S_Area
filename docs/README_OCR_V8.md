# V29 OCR V8 — Real Grid + Sequential Physical Columns

This version fixes the two main problems found during testing:

1. The scanned Production form has **6 actual score cells**: 3 on the left and 3 on the right, with a blank separator in the middle.
2. The user interface may leave blank unit slots (for example 19,20,21,blank,22,23,24). The OCR now maps the entered units **sequentially** to the 6 physical score cells on paper instead of using the UI slot number as the physical score-column number.

The row boundaries and score-cell coordinates are calibrated from the actual scanned PDF `20260923171829765.pdf`.

Do not delete the existing SQLite database when installing over the V29 folder.
