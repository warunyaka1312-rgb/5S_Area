# V33 Schedule Structure Update

ปรับหน้า `schedule_manage.php` และ `schedule_print.php` ตามโครงสร้างตารางที่ตกลงกัน:

- รายชื่อผู้ตรวจเป็น "ทีมตรวจ" ไม่แยกสมาชิกในทีมออกเป็นรายบุคคล
- รองรับ 13 ทีมตรวจ/13 ช่องรายชื่อ
- คอลัมน์ลำดับและชื่อ-สกุลเป็นพื้นสีขาวทั้งหมด
- 1 ทีมสามารถมีหลายแผนกได้ โดยกด `+` เพื่อเพิ่มแผนกให้ทีมเดิม
- หน่วยพื้นที่ 5 ส. แยกตามแต่ละแผนก
- จำนวนหน่วยคำนวณจากรายการพื้นที่อัตโนมัติ
- ชื่อทีม, วันที่ และเวลาสามารถ Merge ข้ามแถวของแผนกภายใต้ทีมเดียวกัน
- คงโทนสีของช่องวันที่/เวลาเฉพาะกลุ่มวันที่แบบภาพต้นแบบ
- แสดงชุดที่ใช้งานเพียงชุดเดียวในเดือนนั้น ไม่แสดง Team A และ Team B ซ้อนกัน
- ระบบกำหนดรอบเริ่มต้นเดือนกันยายน 2569 = Team B และสลับ A/B ทุกเดือน
- ปุ่ม `สลับชุด A ↔ B (ยกชุด)` ใช้สลับชุดสำหรับเดือนปัจจุบัน โดยไม่ลบข้อมูลของอีกชุด
- หน้า A4/A3 ใช้โครงสร้างเดียวกับหน้าจัดการ และไม่มีคอลัมน์จัดการตอนพิมพ์

## หมายเหตุ
ข้อมูลในฐานข้อมูลเดิมไม่ได้ถูกลบหรือเขียนทับจากการปรับโครงสร้างครั้งนี้

## Printable 5S inspection forms
- `inspection_form.php` provides Department/Section and Area selection for Production and Office separately.
- Area choices are loaded directly from `areas` and update after selecting the department.
- `inspection_form_print.php` prints a blank A4 landscape form using all active criteria from `criteria` (Production, 35 items / 50 points) or `office_criteria` (Office, 21 items / 50 points).
- The selected Department/Section and Area/Unit are automatically placed in the form header.
