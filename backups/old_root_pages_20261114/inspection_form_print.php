<?php
require_once 'config.php';require_once __DIR__.'/includes/print_brand.php';
$p=db();
$type=$_GET['type']??'Production';
if(!in_array($type,['Production','Office'],true))$type='Production';
$group=trim($_GET['group']??'');
$ids=array_values(array_filter(array_map('intval',explode(',',(string)($_GET['area_ids']??'')))));
$blankUnits=(isset($_GET['blank_units']) && (string)$_GET['blank_units']==='4');
if(!$group && !$blankUnits) exit('ไม่พบแผนกที่เลือก');
$allAreas=[];
if($group!==''){
  $q=$p->prepare("SELECT id,unit_no,area_name FROM areas WHERE active=1 AND department=? AND group_name=? ORDER BY CAST(unit_no AS INTEGER),unit_no");
  $q->execute([$type,$group]);$allAreas=$q->fetchAll();
}
$allowed=[];foreach($allAreas as $a)$allowed[(int)$a['id']]=$a;
$areas=[];foreach($ids as $id)if(isset($allowed[$id]))$areas[]=$allowed[$id];
if($blankUnits){$areas=[];for($i=1;$i<=4;$i++)$areas[]=['id'=>0,'unit_no'=>'','area_name'=>''];}
elseif(!$areas)$areas=$allAreas;
if($type==='Production'){$criteria=$p->query("SELECT item_no,max_score,description FROM criteria WHERE active=1 ORDER BY item_no")->fetchAll();}
else{$criteria=$p->query("SELECT item_no,max_score,description FROM office_criteria WHERE active=1 ORDER BY item_no")->fetchAll();}
$total=array_sum(array_map(fn($r)=>(float)$r['max_score'],$criteria));
$month=date('m');$year=date('Y')+543;$thaiMonths=['01'=>'มกราคม','02'=>'กุมภาพันธ์','03'=>'มีนาคม','04'=>'เมษายน','05'=>'พฤษภาคม','06'=>'มิถุนายน','07'=>'กรกฎาคม','08'=>'สิงหาคม','09'=>'กันยายน','10'=>'ตุลาคม','11'=>'พฤศจิกายน','12'=>'ธันวาคม'];$monthName=$thaiMonths[$month];
$cols=max(1,count($areas));
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ใบตรวจ 5 ส. <?=h($type)?> <?=h($group)?></title><?=five_s_favicon_tags()?>
<style>
@page{size:A4 portrait;margin:3.5mm}
*{box-sizing:border-box}
html,body{width:100%;margin:0;padding:0}
body{font-family:Tahoma,"Noto Sans Thai",Arial,sans-serif;color:#111;background:#fff;font-size:10.5px;line-height:1.08;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.toolbar{display:flex;gap:8px;margin-bottom:6px}
.btn{border:0;border-radius:7px;padding:7px 11px;font-weight:900;cursor:pointer}
.orange{background:#ff5a00;color:#fff}.navy{background:#172238;color:#fff}
.head{border:1.3px solid #111;overflow:hidden}
.title{font-size:20px;font-weight:900;text-align:center;padding:6px 5px;background:#c2410c;color:#fff;letter-spacing:.1px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.sub{text-align:center;padding:2px 5px;font-weight:900;border-bottom:1px solid #222;font-size:10px}
.meta{display:grid;grid-template-columns:1.2fr 1fr 1.35fr 1fr}
.meta>div{padding:3px 5px;border-right:1px solid #222;min-height:31px}
.meta>div:last-child{border-right:0}
.label{display:block;font-weight:900;color:#334155;font-size:8.5px}
.value{font-size:10.5px;font-weight:900;margin-top:1px;white-space:nowrap}
.table{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:3px}
.table th,.table td{border:1px solid #222;padding:1.8px 2.5px;vertical-align:middle;word-wrap:break-word}
.table th{background:#dbe4ef;font-weight:900;text-align:center;font-size:9.5px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.no{width:6%}.desc{width:51%}.max{width:9%}.area{width:calc(34% / var(--cols))}
.score{height:17px;text-align:center;font-weight:900;font-size:9.5px}
.areahead{font-size:8.5px;line-height:1.02}
.areahead small{display:block;font-size:7px;font-weight:600;color:#475569}
.summary{display:grid;grid-template-columns:1fr 1fr 1fr;margin-top:3px}
.summary>div{border:1px solid #222;padding:2.5px 4px;font-weight:900;font-size:9.5px;min-height:23px}
.sign{display:grid;grid-template-columns:1fr 1fr 1.4fr;margin-top:3px}
.sign>div{border:1px solid #222;min-height:34px;padding:2px;text-align:center;font-size:9px}
.hint{font-size:6.8px;color:#475569;margin-top:2px}
@media print{.no-print{display:none!important}body{background:#fff}}
</style></head><body style="--cols:<?=$cols?>">
<?=five_s_print_toolbar('inspection_form.php','กลับไปหน้าเลือกใบตรวจ')?>
<div class="head"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px"><?=five_s_print_logo(28)?><span style="font-size:10px;color:#64748b">5S SMART • ALUMET</span></div><div class="title">แบบตรวจประเมินมาตรฐานกิจกรรม 5 ส. (<?=h(strtoupper($type))?>)</div><div class="sub">ประจำเดือน <?=h($monthName.' '.$year)?> &nbsp; • &nbsp; แผนก / Section: <b><?=h($group!==''?$group:'________________')?></b></div><div class="meta"><div><span class="label">ผู้ตรวจ (Auditor)</span><div class="value">________________________________</div></div><div><span class="label">วันที่ตรวจ</span><div class="value">____ / ____ / ______</div></div><div><span class="label">พื้นที่ / หน่วย 5 ส.</span><div class="value"><?php if($blankUnits): ?>หน่วย ______ &nbsp; หน่วย ______ &nbsp; หน่วย ______ &nbsp; หน่วย ______<?php else: foreach($areas as $i=>$a): ?><?=($i?', ':'').h($a['unit_no'])?><?php endforeach; ?> <span class="hint">(<?=count($areas)?> หน่วย)</span><?php endif; ?></div></div><div><span class="label">คะแนนเต็มต่อหัวข้อ</span><div class="value"><?=number_format($total,0)?> คะแนน</div></div></div></div>
<table class="table"><thead><tr><th class="no">ข้อ</th><th class="desc">หัวข้อประเมินมาตรฐาน</th><th class="max">เต็ม</th><?php foreach($areas as $i=>$a): ?><th class="area areahead"><?php if($blankUnits): ?>หน่วย<small>________________</small><?php else: ?>พื้นที่ / หน่วย <?=h($a['unit_no'])?><?php endif; ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach($criteria as $r): ?><tr><td style="text-align:center;font-weight:900"><?=h($r['item_no'])?></td><td><?=h($r['description'])?></td><td style="text-align:center;font-weight:900"><?=rtrim(rtrim(number_format((float)$r['max_score'],2,'.',''),'0'),'.')?></td><?php foreach($areas as $a): ?><td class="score">&nbsp;</td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table>
<div class="summary"><div>คะแนนเต็มรวม: <b><?=number_format($total,0)?></b></div><div>คะแนนที่ได้: __________________</div><div>คะแนนรวม (%): __________________</div></div>
<div class="sign"><div>ผู้ตรวจ (Auditor)<br><br>ลงชื่อ ______________________________</div><div>วันที่ตรวจ<br><br>____ / ____ / ______</div><div>หมายเหตุ / ข้อเสนอแนะ<br><br>________________________________</div></div>
<div class="hint"><?php if($blankUnits): ?>หมายเหตุ: แบบฟอร์มเปล่า ไม่ระบุเลขหน่วยล่วงหน้า ให้เขียนเลขพื้นที่/หน่วยจริงลงในช่องทั้ง 4 ช่องก่อนตรวจ<?php else: ?>หมายเหตุ: เลขพื้นที่ / หน่วยด้านบนถูกดึงอัตโนมัติจากข้อมูล <?=h($type)?> ของแผนก <?=h($group)?> โดยตรง หากแผนกมีหลายพื้นที่ ระบบจะแสดงเป็นคอลัมน์แยกตามเลขพื้นที่<?php endif; ?></div>
</body></html>
