# 5S SMART | ALUMET — Board & LINE report

เพิ่มหน้าโปสเตอร์สรุปผลแบบมืออาชีพ 3 แบบ:
- ภาพรวมองค์กร (Organization Overview)
- Production Performance
- Office Performance

แต่ละรายงานแสดงแนวโน้ม ก.ค.–ก.ย. 2569, คะแนนเฉลี่ย, จำนวนหน่วยมีคะแนน/หน่วยทั้งหมด, ผ่านเป้าหมาย, ต่ำกว่าเป้าหมาย, หน่วยที่ยังไม่มีคะแนน และรายการที่ควรติดตาม

## วิธีติดตั้งอย่างปลอดภัย
1. แตก ZIP นี้ไปที่ `C:\xampp\htdocs\` โดยเลือก Merge/Replace ไฟล์เมื่อ Windows ถาม
2. ไฟล์นี้เป็น ZIP อัปเดตเฉพาะส่วนที่เกี่ยวข้อง และ **ไม่มี `db/database.sqlite`** จึงไม่ทับฐานข้อมูลที่ใช้งานอยู่
3. เปิด `http://localhost/5S_V33_final_area_auto/org_3month_summary.php`
4. เลือกปุ่ม `รายงานติดบอร์ด / ส่ง LINE` หรือใช้ลิงก์โดยตรง:
   - `http://localhost/5S_V33_final_area_auto/org_3month_summary.php?board=organization`
   - `http://localhost/5S_V33_final_area_auto/org_3month_summary.php?board=Production`
   - `http://localhost/5S_V33_final_area_auto/org_3month_summary.php?board=Office`

## ส่ง LINE / ติดบอร์ด
- `คัดลอกรูปส่ง LINE`: เปิด LINE บนคอมพิวเตอร์แล้วกด Ctrl+V ในช่องแชต
- หากเบราว์เซอร์ไม่อนุญาตคัดลอกรูป ระบบจะดาวน์โหลด PNG ให้ นำไฟล์นั้นแนบใน LINE ได้
- `ดาวน์โหลด PNG`: บันทึกภาพความละเอียดสูง
- `พิมพ์ A3` หรือ `พิมพ์ A4`: ระบบสร้างภาพรายงานเต็มหน้าในหน้าพิมพ์ใหม่ เพื่อให้ออกกระดาษขนาดที่เลือกเพียง 1 หน้า ไม่แยกต่อหน้า หาก Chrome บล็อกหน้าต่างใหม่ให้อนุญาต Pop-ups สำหรับ localhost แล้วกดอีกครั้ง

ข้อมูลย้อนหลัง ก.ค.–ส.ค. ใช้คะแนนสรุปจาก Excel ที่นำเข้า ส่วน ก.ย. ใช้คะแนนจริงจากใบตรวจในระบบ หน่วยที่ไม่มีคะแนนจะแสดงแยกและไม่นำไปคิดเป็นศูนย์
