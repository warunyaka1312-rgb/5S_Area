5S SMART | ALUMET - Image capture fix
Date: 2026-10-15

Changes:
- Stabilized PNG capture positioning so html2canvas captures the full poster instead of placing the clone far outside the viewport.
- Increased capture resolution to 3x.
- Centered the poster heading and supporting labels in the exported image.
- Kept the existing DOM styles so department/date color backgrounds remain represented in the exported image.
- Updated the dedicated schedule print PNG capture to use the target's actual dimensions.

Files changed:
- schedule_manage.php
- schedule_print.php

Safety:
- The live database db/database.sqlite is intentionally excluded from this ZIP.
- Extract/merge this folder into the existing 5S_V33_final_area_auto folder. Do not replace your live database.
- PHP syntax check passed for both modified files.
- Browser/XAMPP runtime capture still needs verification on the user's machine.
