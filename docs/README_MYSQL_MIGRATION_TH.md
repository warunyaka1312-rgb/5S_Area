# ย้ายฐานข้อมูล 5S SMART จาก SQLite → MySQL / MariaDB

## ขั้นตอน (ทำครั้งเดียว)

1. **เปิด MySQL** ใน XAMPP Control Panel
2. **ตั้งค่าการเชื่อมต่อ** ในไฟล์ `includes/db_config.php`
   (host / port / ชื่อฐานข้อมูล / user / password — ค่าเริ่มต้นคือ `127.0.0.1:3306`, ฐานข้อมูล `five_s_smart`, user `root`)
3. **รันคำสั่งย้ายข้อมูล** ใน Command Prompt:
   ```
   C:\xampp\php\php.exe C:\xampp\htdocs\5S_area_auto\db\migrate_sqlite_to_mysql.php
   ```
   - สร้างฐานข้อมูลและตารางให้เอง (จาก `db/schema_mysql.sql`)
   - คัดลอกทุกตาราง id เดิมไม่เปลี่ยน แล้วแสดงตารางเทียบจำนวนแถว SQLite / MySQL
   - ต้องขึ้นข้อความ `MIGRATION COMPLETE`
   - รันซ้ำได้: ตารางใน MySQL จะถูกเขียนทับด้วยข้อมูลจากไฟล์ SQLite
4. เปิดเว็บตามปกติ แล้วตรวจที่ **ผู้ดูแลระบบ → ตรวจความพร้อมระบบ** (ต้องขึ้น "เชื่อมต่อฐานข้อมูล MySQL" และ CHECK TABLE = ok)

> ไฟล์ `db/database.sqlite` ไม่ถูกแก้ไข เก็บไว้เป็นต้นฉบับ
> โค้ดเดิมก่อนแก้ทั้งหมดอยู่ที่ `backups/pre_mysql_20261114/` (ถ้าต้องการย้อนกลับ ให้คัดลอก `index.php`, `includes/` และ `Page/` จากโฟลเดอร์นั้นกลับมา)

## สิ่งที่เปลี่ยน

| เรื่อง | เดิม (SQLite) | ใหม่ (MySQL) |
|---|---|---|
| การเชื่อมต่อ | `db/database.sqlite` | `includes/db_config.php` |
| สร้างตาราง | `CREATE TABLE` กระจายหลายหน้า | `db/schema_mysql.sql` ไฟล์เดียว (รันอัตโนมัติ) |
| Backup อัตโนมัติ / ด้วยตนเอง | ไฟล์ `.sqlite` | ไฟล์ `.sql` (ไม่ต้องใช้ mysqldump) |
| กู้คืน | ไฟล์ `.sqlite` | ไฟล์ `.sql` หรือ `.sqlite` รุ่นเดิมก็ได้ |
| หน้าตรวจความพร้อมระบบ | PRAGMA integrity_check | เชื่อมต่อ MySQL + CHECK TABLE |

## ทำให้ผลลัพธ์เหมือน SQLite ทุกอย่าง

- **Collation `utf8mb4_bin`** — เปรียบเทียบ/เรียงข้อความแบบเดียวกับ SQLite (ตัวพิมพ์ใหญ่-เล็กต่างกัน, ภาษาไทยเรียงตามรหัสอักษร)
  ยกเว้น `departments.name` และ `users.username` ที่เดิมเป็น `COLLATE NOCASE` → ใช้ `utf8mb4_general_ci` (ไม่สนตัวพิมพ์)
- เวลา `CURRENT_TIMESTAMP` เก็บเป็น UTC เหมือนเดิม (session `time_zone='+00:00'`)
- `rowCount()` ของ UPDATE นับแถวที่ตรงเงื่อนไข เหมือน SQLite
- `GROUP_CONCAT(DISTINCT …)` ของประวัติผู้ตรวจ ทำใน PHP เพื่อคงลำดับเดิม (MySQL จะเรียงค่าให้เอง)
- ผลรวม `SUM()` ในหน้า TAG แปลงเป็นตัวเลขจำนวนเต็ม/ทศนิยมเหมือนเดิม (ไม่เป็นข้อความ)

## ผลการทดสอบก่อนส่งมอบ (MariaDB 10.4 ชุดทดสอบแยก)

- ข้อมูลทั้ง 22 ตาราง 58,989 ค่า ตรงกับ SQLite ทุกค่า
- เปิด 298 หน้า/ตัวเลือก (ทุกเดือน ทุกแผนก ทุกทีม) เทียบกับโค้ดเดิมบน SQLite → HTML เหมือนกันทุกหน้า
  ยกเว้นหน้า Backup และ ตรวจความพร้อมระบบ ที่ตั้งใจเปลี่ยนเป็น MySQL
- บันทึกข้อมูล 31 แบบ (คะแนน Production/Office, TAG, แผนตรวจ, ผู้ใช้, แผนก, พื้นที่, ประวัติผู้ตรวจ, ตั้งค่า) บนทั้งสองระบบ → ฐานข้อมูลผลลัพธ์เหมือนกันทุกแถว
- Backup `.sql` → ทำข้อมูลเสีย → Restore → ข้อมูลกลับมาเหมือนเดิมทุกตาราง; Restore จาก `.sqlite` รุ่นเดิมได้

## ข้อสังเกตที่มีอยู่แล้วก่อนย้าย (ไม่ได้แก้)

- `legacy_scores` 1,524 แถวอ้างถึง `area_id` 58–110 ซึ่งถูกลบไปแล้วตั้งแต่ใน SQLite (ไม่มีหน้าไหนใช้ตารางนี้) — คัดลอกไปตามเดิม
- `Page/public/org_3month_board_template.php` เปิดตรง ๆ แล้ว error `FIVE_S_BASE` ทั้งบน SQLite และ MySQL (ไฟล์นี้เป็น template ที่ถูก include จากหน้าอื่น)
