# 5S SMART | ALUMET — V80 (โครงสร้างใหม่แยกตามสิทธิ์)

| โฟลเดอร์ | ใช้ทำอะไร | ใครเข้าได้ |
|---|---|---|
| `index.php` | จุดเข้า — Dashboard ภาพรวม | ทุกคน |
| `public/` | รายงาน กราฟ พิมพ์เอกสาร ป้ายบอร์ด สรุป 3 เดือน แผนตรวจ (พิมพ์) แบบลงชื่อ TAG Dashboard + หน้า login / logout / บัญชีของฉัน | ทุกคน (ดูได้โดยไม่ต้องล็อกอิน, การบันทึกต้องล็อกอิน) |
| `auditor/` | บันทึกคะแนน Production / Office, รับ-ปิด TAG, นำเข้าผลตรวจ, ประวัติการตรวจ | Auditor และ Admin |
| `admin/` | พื้นที่/หน่วย, แผนก/กลุ่ม, หัวข้อตรวจ, จัดตารางแผนตรวจ, ตั้งค่า, สำรองข้อมูล, ตรวจระบบ, ผู้ใช้งาน | Admin |
| `.env` | **ค่าตั้งค่าทั้งหมด** (ฐานข้อมูล MySQL, ชื่อแอพ, timezone, session, รหัสผ่าน admin เริ่มต้น, งานอัตโนมัติ) — ตัวอย่างที่ `.env.example` | ห้ามเปิดจากเว็บ (.htaccess) |
| `includes/` | config + env.php (อ่าน .env) + db_config.php + db_tools.php, auth (login/สิทธิ์), paths, header/footer/เมนู, งานอัตโนมัติ | ห้ามเปิดจากเว็บ (.htaccess) |
| `assets/` | โลโก้ ธีม CSS สคริปต์ และไลบรารีสำรองแบบออฟไลน์ | ทุกคน |
| `db/` | `schema_mysql.sql` (โครงสร้างตาราง MySQL), `migrate_sqlite_to_mysql.php` (ย้ายข้อมูล), `database.sqlite` (ฐานข้อมูลเดิม — เก็บไว้เป็นต้นฉบับ) | ห้ามเปิดจากเว็บ (.htaccess) |
| `uploads/` `backups/` `data/` | รูป TAG / สแกน, ไฟล์สำรองอัตโนมัติ, ข้อมูลประวัติ | |
| `docs/` | คู่มือและประวัติการแก้ไขเดิม | ห้ามเปิดจากเว็บ |

**Tech stack:** PHP 8 (vanilla) • MySQL / MariaDB (PDO MySQL) • Tailwind CSS v3 / Alpine.js v3 / Chart.js v4 / DataTables 1.13 ผ่าน CDN (มีไฟล์สำรองใน assets/vendor เมื่อไม่มีอินเทอร์เน็ต)

**เข้าสู่ระบบครั้งแรก:** `admin` / `admin1234` → ระบบบังคับให้เปลี่ยนรหัสผ่าน แล้วเพิ่มผู้ใช้อื่นที่ “ผู้ใช้งานและสิทธิ์”

**ติดตั้ง XAMPP:** วางโฟลเดอร์ไว้ที่ `C:\xampp\htdocs\5S_V33_final_area_auto` แล้วเปิด `http://localhost/5S_V33_final_area_auto/`

**อัปขึ้น Shared Hosting (FTP):** อัปโหลดทั้งโฟลเดอร์ (รวม db/ และไฟล์ .env) ไปไว้ที่ public_html หรือโฟลเดอร์ย่อยใดก็ได้ — ระบบคำนวณ URL เองอัตโนมัติ
โฮสต์ต้องเปิด PHP 8 + pdo_mysql และมีฐานข้อมูล MySQL/MariaDB (ตั้งค่า `DB_*` ในไฟล์ `.env`) และให้สิทธิ์เขียนโฟลเดอร์ `uploads/ backups/`

**ฐานข้อมูล MySQL:** ดูขั้นตอนย้ายจาก SQLite ที่ `docs/README_MYSQL_MIGRATION_TH.md`
