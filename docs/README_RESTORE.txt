5S SMART V29 - RESTORE PAGES

ปัญหาที่พบ:
index.php ที่กำลังเปิดอยู่กลายเป็นหน้ารับไฟล์นำเข้า ทำให้ Dashboard/กราฟ/เมนูเดิมไม่แสดง

ไฟล์ในชุดนี้:
- index.php = Dashboard เดิม พร้อมกราฟ
- import.php = หน้านำเข้าผลตรวจ V29 REAL
- schedule_manage.php = แผนการตรวจ 5 ส.
- period_report.php = รายเดือน / รายปี + กราฟ
- score.php = ลงคะแนน Production
- office_score.php = ลงคะแนน Office
- office_criteria.php = หัวข้อคะแนน Office
- legacy.php = ข้อมูลจาก Excel เดิม
- tag_dashboard.php = Dashboard Tag 5 ส.
- print_office.php = พิมพ์ผล Office

วิธีติดตั้งที่ปลอดภัย:
1. สำรองโฟลเดอร์ 5S_Score_System_V29 ทั้งโฟลเดอร์ก่อน
2. สำรอง db/database.sqlite ห้ามลบ/ห้ามสร้างฐานข้อมูลใหม่
3. แตกชุดนี้เข้าโฟลเดอร์ 5S_Score_System_V29 และยืนยันเขียนทับเฉพาะไฟล์ PHP ด้านบน
4. เปิด http://localhost/5S_Score_System_V29/index.php
5. หน้านำเข้ากระดาษให้เปิดผ่าน /import.php ไม่ใช่ index.php

หมายเหตุ:
- ชุดนี้ไม่รวม database.sqlite เพื่อป้องกันข้อมูลเดิมถูกเขียนทับ
- กราฟยังคงอยู่ที่ Dashboard และ period_report.php
