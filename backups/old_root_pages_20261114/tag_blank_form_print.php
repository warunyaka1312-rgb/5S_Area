<?php
require_once 'config.php';
$title='พิมพ์แบบฟอร์ม Tag เปล่า';
require 'includes/header.php';
?>
<style>
.tag-print-page{max-width:1150px;margin:0 auto}
.tag-preview{background:#e2e8f0;border-radius:14px;padding:18px;overflow:auto}
.tag-sheet{width:100%;max-width:800px;margin:0 auto;background:#fff;color:#111827;padding:12px;box-shadow:0 8px 28px rgba(15,23,42,.12)}
.tag-form{height:138mm;border:1.5px solid #111;display:flex;flex-direction:column;font-family:"Leelawadee UI",Tahoma,Arial,sans-serif;font-size:12.5px;font-weight:500;color:#000;overflow:hidden;background:#fff}
.tag-head{text-align:center;border-bottom:1px solid #111;padding:7px 8px 5px;min-height:15mm;display:flex;flex-direction:column;justify-content:center}
.tag-head h2{font-size:19px;font-weight:900;margin:0;line-height:1.15;color:#111}.tag-head .month{font-size:11px;font-weight:700;margin-top:3px}
.punch{position:absolute;top:3px;left:50%;transform:translateX(-50%);border:1.5px solid #111;border-radius:50%;width:5mm;height:5mm;background:white}
.tag-fields{padding:5px 8px 4px;border-bottom:1px solid #111;line-height:1.65;font-size:12px}
.field-line{display:flex;gap:8px;align-items:flex-end;min-height:7mm}.field{display:flex;align-items:flex-end;gap:4px;min-width:0}.field strong{white-space:nowrap}.write-line{border-bottom:1px dotted #333;flex:1;min-width:15px;height:4mm}
.defect-title{font-weight:900;font-size:12.5px;padding:4px 8px 2px;border-bottom:1px solid #111;background:#f3f4f6;color:#000}
.defect-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:3px 9px;padding:5px 9px 5px;flex:0 0 31mm;border-bottom:1px solid #111;align-content:center}
.defect{display:flex;align-items:center;gap:5px;line-height:1.25;min-height:6mm;font-size:12px;font-weight:600}.check{display:inline-block;width:4mm;height:4mm;border:1px solid #111;flex:0 0 4mm}
.tag-kind{padding:5px 8px;border-bottom:1px solid #111;display:flex;align-items:center;gap:12px;min-height:9mm;font-size:12px;font-weight:800}.kind-option{display:inline-flex;align-items:center;gap:4px;font-weight:700}
.suggestion{padding:5px 8px;border-bottom:1px solid #111;flex:1;min-height:25mm}.suggestion strong{display:block;margin-bottom:5px}.suggestion .blank-area{height:calc(100% - 16px)}
.tag-bottom{display:grid;grid-template-columns:1fr 1fr 1fr;min-height:20mm}.bottom-cell{padding:5px 7px;border-right:1px solid #111;line-height:1.45}.bottom-cell:last-child{border-right:0}.bottom-line{display:flex;align-items:flex-end;gap:3px;margin-top:5px}.bottom-line span:first-child{white-space:nowrap}.bottom-line i{border-bottom:1px dotted #333;flex:1;height:4mm}
.cut-line{border-top:1px dashed #64748b;margin:4mm 0 3mm;position:relative;text-align:center;color:#64748b;font-size:9px;height:0}.cut-line span{position:relative;top:-7px;background:#e2e8f0;padding:0 10px}
@media print{
 @page{size:A4 portrait;margin:4mm}
 body{background:#fff!important;margin:0!important;padding:0!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
 header,.sidebar,#mobileNav,footer,.no-print,.tag-instructions{display:none!important}
 main,.main-content,.content,.page-content{margin:0!important;padding:0!important;width:100%!important;max-width:none!important}
 .tag-print-page{max-width:none;margin:0;padding:0}
 .tag-preview{background:#fff;padding:0;border-radius:0;overflow:visible}
 .tag-sheet{width:100%;max-width:none;padding:0;margin:0;box-shadow:none}
 .tag-form{height:139mm;break-inside:avoid;page-break-inside:avoid}
 .cut-line{margin:3mm 0 2mm;border-color:#555}
}
@media screen and (max-width:700px){.tag-form{min-width:700px}.tag-sheet{min-width:720px}.tag-preview{overflow-x:auto}}
</style>
<div class="tag-print-page">
 <div class="flex items-center justify-between gap-3 mb-4 no-print">
  <div><h1 class="text-2xl font-black text-slate-900">🖨 พิมพ์แบบฟอร์ม Tag เปล่า</h1><p class="text-slate-500 mt-1">กระดาษ A4 แนวตั้ง จำนวน 2 ใบต่อหน้า เขียนข้อมูลด้วยมือได้ทันที</p></div>
  <button type="button" onclick="window.print()" class="rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold px-5 py-3 shadow">🖨 พิมพ์แบบฟอร์ม / Save PDF</button>
 </div>
 <div class="tag-instructions card p-4 mb-4 no-print text-sm text-slate-600">ฟอร์มนี้เป็นแบบเปล่า ไม่ดึงข้อมูลจากฐานข้อมูลและไม่บันทึกทับข้อมูล Tag ที่มีอยู่ พิมพ์ออกมาแล้วกรอกได้เอง</div>
 <div class="tag-preview"><div class="tag-sheet">
 <?php for($copy=0;$copy<2;$copy++): ?>
  <section class="tag-form">
   <div class="tag-head" style="position:relative"><img src="assets/alumet-logo.png" alt="ALUMET" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);height:20px;width:auto"><h2>PLANT PATROL 5S &amp; AM TAG</h2><div class="month">ประจำเดือน <span style="display:inline-block;min-width:38mm;border-bottom:1px dotted #333">&nbsp;</span></div></div>
   <div class="tag-fields">
    <div class="field-line"><div class="field" style="width:100%"><strong>จุดบกพร่องที่ตรวจพบ:</strong><span class="write-line"></span></div></div>
    <div class="field-line">
     <div class="field" style="flex:1"><strong>หมายเลข Tag</strong><span class="write-line"></span></div>
     <div class="field" style="flex:1"><strong>แผนก</strong><span class="write-line"></span></div>
     <div class="field" style="flex:1"><strong>พื้นที่กลุ่มย่อย</strong><span class="write-line"></span></div>
    </div>
   </div>
   <div class="defect-title">ประเภทจุดบกพร่อง</div>
   <div class="defect-grid">
    <div class="defect"><span class="check"></span><span>1. ก่อให้เกิดการชำรุด</span></div>
    <div class="defect"><span class="check"></span><span>5. ก่อให้เกิดของเสีย</span></div>
    <div class="defect"><span class="check"></span><span>9. ความปลอดภัย</span></div>
    <div class="defect"><span class="check"></span><span>2. Basic Condition ไม่ดี</span></div>
    <div class="defect"><span class="check"></span><span>6. มากเกินความจำเป็น / ระเกะระกะ</span></div>
    <div class="defect"><span class="check"></span><span>10. สิ่งแวดล้อม</span></div>
    <div class="defect"><span class="check"></span><span>3. ยากลำบากในการทำงาน</span></div>
    <div class="defect"><span class="check"></span><span>7. ก่อให้เกิดอันตราย</span></div>
    <div class="defect"><span class="check"></span><span></span></div>
    <div class="defect"><span class="check"></span><span>4. ก่อให้เกิดความสกปรก</span></div>
    <div class="defect"><span class="check"></span><span>8. ก่อให้เกิดการเปลืองพลังงาน</span></div>
    <div class="defect"><span></span><span></span></div>
   </div>
   <div class="tag-kind"><span>จุดบกพร่องที่ค้นพบกำหนดให้เป็น Tag ประเภท:</span><span class="kind-option"><i class="check"></i> Tag ขาว</span><span class="kind-option"><i class="check"></i> Tag แดง</span></div>
   <div class="suggestion"><strong>ข้อเสนอแนะในการดำเนินการ:</strong><div class="blank-area"></div></div>
   <div class="tag-bottom">
    <div class="bottom-cell"><strong>วันที่ออก Tag:</strong><div class="bottom-line"><i></i></div><div class="bottom-line"><span>วันที่คาดว่าจะแล้วเสร็จ:</span><i></i></div></div>
    <div class="bottom-cell"><strong>ติด Tag โดย:</strong><div class="bottom-line" style="margin-top:10mm"><i></i></div></div>
    <div class="bottom-cell"><strong>ผู้รับ Tag:</strong><div class="bottom-line" style="margin-top:10mm"><i></i></div></div>
   </div>
  </section>
  <?php if($copy===0): ?><div class="cut-line"><span>✂ ตัดตามเส้นประ</span></div><?php endif; ?>
 <?php endfor; ?>
 </div></div>
</div>
<?php require 'includes/footer.php'; ?>
