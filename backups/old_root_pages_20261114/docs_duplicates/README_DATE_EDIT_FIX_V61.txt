V61 - แก้การเปลี่ยนวันที่ใน history.php
- เปลี่ยนวันที่แล้วบันทึกอัตโนมัติ
- ใช้ event delegation เพื่อรองรับ DataTables redraw
- หาก AJAX endpoint มีปัญหา จะ fallback ส่ง POST ไป history.php โดยตรง
- ไม่เพิ่มปุ่ม Save
- ไม่แก้คะแนนหรือข้อมูลตรวจอื่น
- ห้ามนำ db/database.sqlite จากแพ็กเกจไปทับฐานข้อมูลจริงของผู้ใช้
