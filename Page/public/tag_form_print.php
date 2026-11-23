<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
$p=db();
/* Additive fields used by the original PLANT PATROL 5S & AM TAG form. */
$existing=db_table_columns('tags',$p);
foreach(['tag_type','defect_types','suggestion','issued_by','receiver'] as $col){
  if(!in_array($col,$existing,true)) $p->exec("ALTER TABLE tags ADD COLUMN ".$col." VARCHAR(1000) NOT NULL DEFAULT ''");
}
if(!function_exists('tag_print_photo_paths')){
 function tag_print_photo_paths($raw): array {
  if(!$raw) return []; $v=json_decode((string)$raw,true);
  if(is_array($v)) $items=array_values(array_filter($v,'is_string')); else $items=[(string)$raw];
  return array_values(array_filter($items,fn($x)=>str_starts_with(str_replace('\\','/',(string)$x),'uploads/tag_photos/')));
 }
}
$id=(int)($_GET['id']??0);$tag=null;
if($id>0){$q=$p->prepare('SELECT * FROM tags WHERE id=?');$q->execute([$id]);$tag=$q->fetch()?:null;}
$months=[1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
$defectLabels=['1'=>'ก่อให้เกิดการชำรุด','2'=>'Basic Condition ไม่ดี','3'=>'ยากลำบากในการทำงาน','4'=>'ก่อให้เกิดความสกปรก','5'=>'ก่อให้เกิดของเสีย','6'=>'มากเกินความจำเป็น / ระเกะระกะ','7'=>'ก่อให้เกิดอันตราย','8'=>'ก่อให้เกิดการเปลืองพลังงาน','9'=>'ความปลอดภัย','10'=>'สิ่งแวดล้อม'];
function tag_print_date($value): string { $v=trim((string)$value); if($v===''||$v==='0000-00-00') return ''; $t=strtotime($v); return $t?date('d/m/Y',$t):$v; }
function tag_print_month($value,$months): string { $t=strtotime((string)$value); if(!$t) return 'ไม่ระบุ'; $m=(int)date('n',$t); return ($months[$m]??''). ' '.((int)date('Y',$t)+543); }
$defects=[]; if($tag){$defects=json_decode((string)($tag['defect_types']??'[]'),true);if(!is_array($defects))$defects=[];$defects=array_map('strval',$defects);}
$before=$tag?tag_print_photo_paths($tag['before_photo']??''):[];$after=$tag?tag_print_photo_paths($tag['after_photo']??''):[];
$title='พิมพ์แบบฟอร์ม Tag';
require FIVE_S_ROOT.'/includes/header.php';
?>
<style>
.tag-printed-page{max-width:1180px;margin:0 auto}.tag-sheet{max-width:1050px;margin:0 auto;background:#fff;padding:12px;box-shadow:0 3px 22px rgba(15,23,42,.12);color:#111;font-family:Tahoma,"Leelawadee UI",Arial,sans-serif}
.tag-form{border:1.4px solid #111;display:flex;flex-direction:column;min-height:135mm;font-size:12px;line-height:1.35;overflow:hidden}.tag-head{text-align:center;border-bottom:1px solid #111;padding:6px 8px;position:relative}.tag-head h2{font-size:21px;line-height:1.15;font-weight:900;margin:0}.tag-head .month{font-size:12px;font-weight:700;margin-top:3px}.kind-ribbon{position:absolute;right:8px;top:7px;padding:4px 8px;border:2px solid;border-radius:6px;font-size:11px;font-weight:900}.kind-red{color:#b91c1c;border-color:#dc2626;background:#fef2f2}.kind-white{color:#334155;border-color:#94a3b8;background:#fff}.kind-none{color:#6b7280;border-color:#9ca3af;background:#f3f4f6}
.tag-fields{padding:5px 8px;border-bottom:1px solid #111}.field-line{display:flex;gap:8px;min-height:7mm;align-items:flex-end}.field{display:flex;gap:4px;align-items:flex-end;min-width:0}.field strong{white-space:nowrap}.field-value{border-bottom:1px dotted #333;min-height:5mm;flex:1;padding:0 2px;font-weight:700;overflow-wrap:anywhere}
.defect-title{font-weight:900;font-size:12.5px;padding:4px 8px;background:#f3f4f6;border-bottom:1px solid #111}.defect-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:2px 8px;padding:5px 8px;border-bottom:1px solid #111}.defect{display:flex;align-items:flex-start;gap:5px;font-size:11px;min-height:5mm}.check{display:inline-flex;align-items:center;justify-content:center;width:4mm;height:4mm;border:1px solid #111;flex:0 0 4mm;font-size:12px;font-weight:bold;line-height:1}.tag-kind{padding:5px 8px;border-bottom:1px solid #111;font-size:12px;font-weight:800;display:flex;gap:12px;align-items:center}.kind-option{display:inline-flex;gap:4px;align-items:center}.suggestion{padding:6px 8px;border-bottom:1px solid #111;min-height:21mm;white-space:pre-wrap;overflow-wrap:anywhere}.suggestion strong{display:block;margin-bottom:4px}.suggestion-text{min-height:10mm}.photo-heading{font-size:11px;font-weight:900;margin:0 0 3px}.photo-section{border:1px solid #b6c7da;padding:5px 7px;margin-top:5px;border-radius:4px;background:#fff}.photo-group-title{font-size:11px;font-weight:900;margin:0 0 4px}.photo-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:5px}.photo-item{border:1px solid #d1d5db;padding:2px;text-align:center;min-width:0}.photo-item img{width:100%;height:22mm;object-fit:contain;display:block}.photo-item span{display:block;font-size:8px;overflow-wrap:anywhere}.photo-empty{font-size:10px;color:#64748b;padding:6px}
.tag-bottom{display:grid;grid-template-columns:1fr 1fr 1fr;min-height:19mm}.bottom-cell{padding:5px 7px;border-right:1px solid #111;line-height:1.45}.bottom-cell:last-child{border-right:0}.bottom-line{display:flex;gap:4px;align-items:flex-end;margin-top:5px}.bottom-line span:first-child{white-space:nowrap}.bottom-line i{border-bottom:1px dotted #333;flex:1;min-height:4mm}.bottom-line .filled{font-weight:700;border-bottom:1px dotted #333;flex:1;min-height:4mm;overflow-wrap:anywhere}
@media print{@page{size:A4 portrait;margin:5mm}html,body{background:#fff!important;margin:0!important;padding:0!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}.topbar,.sidebar,header,footer,.no-print{display:none!important}main.main,main,.wrap,.tag-printed-page{margin:0!important;padding:0!important;width:100%!important;max-width:none!important}.tag-sheet{box-shadow:none;margin:0;padding:0;max-width:none}.tag-form{min-height:0}.photo-section{break-inside:avoid;page-break-inside:avoid}.photo-item img{height:20mm}.tag-form,.photo-section{break-inside:avoid;page-break-inside:avoid}}
@media screen and (max-width:750px){.tag-sheet{padding:5px}.tag-form{min-width:650px}}
</style>
<div class="tag-printed-page">
 <div class="no-print flex flex-wrap items-center justify-between gap-3 mb-4">
  <div><h1 class="text-2xl font-black">🖨 แบบฟอร์ม Tag จากข้อมูลในระบบ</h1><p class="text-sm text-slate-600">ดึงรายละเอียด Tag และรูป Before/After ของรายการเดียวกันโดยอัตโนมัติ</p></div>
  <div class="flex gap-2"><a href="Page/auditor/tag_manage.php" class="rounded-xl border px-4 py-3 font-bold">← กลับรายการ Tag</a><?php if($tag):?><button onclick="window.print()" class="rounded-xl bg-orange-600 text-white px-5 py-3 font-black">🖨 พิมพ์ / Save PDF</button><?php endif;?></div>
 </div>
 <?php if(!$tag): ?>
  <div class="card p-5"><h2 class="text-xl font-black mb-3">เลือกรายการ Tag เพื่อพิมพ์</h2><p class="text-slate-600 mb-4">เลือกจากรายการด้านล่าง ระบบจะดึงข้อมูลและรูปภาพที่บันทึกไว้ให้อัตโนมัติ</p><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="bg-slate-100"><th class="p-2 text-left">Tag No.</th><th class="p-2 text-left">แผนก</th><th class="p-2 text-left">หน่วย</th><th class="p-2 text-left">รายละเอียด</th><th class="p-2 text-left">ประเภท Tag</th><th></th></tr></thead><tbody>
  <?php foreach($p->query("SELECT * FROM tags ORDER BY tag_date DESC,id DESC")->fetchAll() as $row):?><tr class="border-b"><td class="p-2 font-bold"><?=h($row['tag_no'])?></td><td class="p-2"><?=h($row['department'])?></td><td class="p-2"><?=h($row['unit_no'])?></td><td class="p-2"><?=h($row['note'])?></td><td class="p-2"><?=($row['tag_type']??'')==='RED'?'Tag แดง':(($row['tag_type']??'')==='WHITE'?'Tag ขาว':'ยังไม่ระบุ')?></td><td class="p-2"><a class="inline-block rounded-lg bg-orange-600 text-white px-3 py-2 font-bold" target="_blank" href="Page/public/tag_form_print.php?id=<?=(int)$row['id']?>">เปิดแบบฟอร์ม</a></td></tr><?php endforeach;?>
  </tbody></table></div></div>
 <?php else: ?>
 <div class="tag-sheet">
  <section class="tag-form">
   <div class="tag-head" style="position:relative"><img src="assets/alumet-logo.png" alt="ALUMET" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);height:20px;width:auto"><h2>PLANT PATROL 5S &amp; AM TAG</h2><div class="month">ประจำเดือน <?=h(tag_print_month($tag['tag_date']??'', $months))?></div><div class="kind-ribbon <?=($tag['tag_type']??'')==='RED'?'kind-red':((($tag['tag_type']??'')==='WHITE')?'kind-white':'kind-none')?>"><?=($tag['tag_type']??'')==='RED'?'TAG แดง':((($tag['tag_type']??'')==='WHITE')?'TAG ขาว':'ยังไม่ระบุประเภท')?></div></div>
   <div class="tag-fields">
    <div class="field-line"><div class="field" style="width:100%"><strong>จุดบกพร่องที่ตรวจพบ:</strong><span class="field-value"><?=h($tag['note']??'')?></span></div></div>
    <div class="field-line">
     <div class="field" style="flex:1"><strong>หมายเลข Tag:</strong><span class="field-value"><?=h($tag['tag_no']??'')?></span></div>
     <div class="field" style="flex:1"><strong>แผนก:</strong><span class="field-value"><?=h($tag['department']??'')?></span></div>
     <div class="field" style="flex:1"><strong>พื้นที่กลุ่มย่อย:</strong><span class="field-value"><?=h($tag['unit_no']??'')?></span></div>
    </div>
   </div>
   <div class="defect-title">ประเภทจุดบกพร่อง</div>
   <div class="defect-grid">
    <?php foreach($defectLabels as $key=>$label): ?><div class="defect"><span class="check"><?=in_array((string)$key,$defects,true)?'✓':''?></span><span><?=h($key.'. '.$label)?></span></div><?php endforeach;?>
   </div>
   <div class="tag-kind"><span>จุดบกพร่องที่ค้นพบกำหนดให้เป็น Tag ประเภท:</span><span class="kind-option"><i class="check"><?=($tag['tag_type']??'')==='WHITE'?'✓':''?></i> Tag ขาว</span><span class="kind-option"><i class="check"><?=($tag['tag_type']??'')==='RED'?'✓':''?></i> Tag แดง</span></div>
   <div class="suggestion"><strong>ข้อเสนอแนะในการดำเนินการ:</strong><div class="suggestion-text"><?=h($tag['suggestion']??'')?></div><?php if(trim((string)($tag['corrective_action']??''))!==''):?><div style="margin-top:3px"><b>การแก้ไข:</b> <?=h($tag['corrective_action'])?></div><?php endif;?></div>
   <div class="tag-bottom">
    <div class="bottom-cell"><strong>วันที่ออก Tag:</strong><div class="bottom-line"><span class="filled"><?=h(tag_print_date($tag['tag_date']??''))?></span></div><div class="bottom-line"><span>วันที่คาดว่าจะแล้วเสร็จ:</span><span class="filled"><?=h(tag_print_date($tag['due_date']??''))?></span></div></div>
    <div class="bottom-cell"><strong>ติด Tag โดย:</strong><div class="bottom-line" style="margin-top:8mm"><span class="filled"><?=h((($tag['issued_by']??'')!=='')?$tag['issued_by']:($tag['auditor']??''))?></span></div></div>
    <div class="bottom-cell"><strong>ผู้รับ Tag:</strong><div class="bottom-line" style="margin-top:8mm"><span class="filled"><?=h($tag['receiver']??'')?></span></div></div>
   </div>
  </section>
  <div class="photo-section">
   <div class="grid grid-cols-2 gap-4">
    <div><h3 class="photo-group-title" style="color:#b91c1c">📷 รูปก่อนแก้ไข (Before) — <?=count($before)?> รูป</h3><?php if($before):?><div class="photo-grid"><?php foreach($before as $photo):?><div class="photo-item"><img src="<?=h($photo)?>" alt="Before"><span>ก่อนแก้ไข</span></div><?php endforeach;?></div><?php else:?><div class="photo-empty">ไม่มีรูปก่อนแก้ไขแนบในรายการนี้</div><?php endif;?></div>
    <div><h3 class="photo-group-title" style="color:#15803d">📷 รูปหลังแก้ไข (After) — <?=count($after)?> รูป</h3><?php if($after):?><div class="photo-grid"><?php foreach($after as $photo):?><div class="photo-item"><img src="<?=h($photo)?>" alt="After"><span>หลังแก้ไข</span></div><?php endforeach;?></div><?php else:?><div class="photo-empty">ไม่มีรูปหลังแก้ไขแนบในรายการนี้</div><?php endif;?></div>
   </div>
  </div>
  <?php if(trim((string)($tag['verification_note']??''))!==''):?><div class="photo-section"><b>ผลการตรวจยืนยัน / Verification:</b> <?=h($tag['verification_note'])?><?php if(!empty($tag['corrective_by'])):?> &nbsp; <b>ผู้ดำเนินการแก้ไข:</b> <?=h($tag['corrective_by'])?><?php endif;?><?php if(!empty($tag['corrective_date'])):?> &nbsp; <b>วันที่แก้ไข:</b> <?=h(tag_print_date($tag['corrective_date']))?><?php endif;?></div><?php endif;?>
  <div class="text-xs text-slate-500 mt-2">เอกสารนี้ดึงข้อมูลจากรายการ Tag หมายเลข <?=h($tag['tag_no'])?> โดยอัตโนมัติ • 5S SMART | ALUMET</div>
 </div>
 <?php endif;?>
</div>
<?php require FIVE_S_ROOT.'/includes/footer.php'; ?>
