แก้ไขหน้า tag_manage.php ให้แนบรูปก่อนแก้ไขและหลังแก้ไขได้

วิธีติดตั้ง:
1) สำรองโฟลเดอร์โปรแกรมและ db/database.sqlite ก่อน
2) คัดลอกไฟล์ใน ZIP ทับไฟล์โปรแกรมเดิม โดยคงโฟลเดอร์ 5S_V33_final_area_auto
3) ห้ามนำฐานข้อมูลใน ZIP มาทับฐานข้อมูลจริง (ZIP นี้ไม่รวม db/database.sqlite)
4) เปิดหน้า tag_manage.php แล้วกด Ctrl+F5
5) เลือก JPG/PNG/WEBP ไม่เกิน 5 MB ต่อภาพ และกดบันทึก Tag
6) ระบบเพิ่มคอลัมน์ before_photo / after_photo ให้อัตโนมัติ โดยไม่ลบข้อมูลเดิม
7) รูปถูกเก็บใน uploads/tag_photos และแสดงภาพย่อในตารางรายการ Tag

หากอัปโหลดไม่ผ่าน ให้ตรวจ PHP upload_max_filesize / post_max_size และสิทธิ์เขียนโฟลเดอร์ uploads/tag_photos ของ XAMPP.
