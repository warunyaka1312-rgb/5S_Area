<?php
require_once 'config.php';
$title='สรุปการปิด Tag 5 ส.';
$p=db();$year=(int)($_GET['year']??(int)app_setting('tag_dashboard_year',(string)date('Y')));$month=(int)($_GET['month']??0);$dept=trim($_GET['department']??'');
$months=[1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
$queryYear=$year>2400?$year-543:$year;
$where="year=?";$params=[$queryYear];if($month>0){$where.=" AND month=?";$params[]=$month;}if($dept!==''){$where.=" AND department=?";$params[]=$dept;}
$tagWhere="substr(tag_date,1,4)=?";$tagParams=[(string)$queryYear];if($month>0){$tagWhere.=" AND CAST(substr(tag_date,6,2) AS INTEGER)=?";$tagParams[]=$month;}if($dept!==''){$tagWhere.=" AND department=?";$tagParams[]=$dept;}
$check=$p->prepare("SELECT COUNT(*) FROM tags WHERE $tagWhere");$check->execute($tagParams);$useLiveTags=((int)$check->fetchColumn())>0;
if($useLiveTags){
 $q=$p->prepare("SELECT department,COUNT(*) total,SUM(CASE WHEN status='CLOSED' THEN 1 ELSE 0 END) closed,SUM(CASE WHEN status='CLOSED' THEN 0 ELSE 1 END) open_count,CASE WHEN COUNT(*)>0 THEN SUM(CASE WHEN status='CLOSED' THEN 1 ELSE 0 END)*100.0/COUNT(*) ELSE 0 END pct FROM tags WHERE $tagWhere GROUP BY department ORDER BY pct ASC");$q->execute($tagParams);$rows=$q->fetchAll();
 $t=$p->prepare("SELECT COUNT(*) total,SUM(CASE WHEN status='CLOSED' THEN 1 ELSE 0 END) closed,SUM(CASE WHEN status='CLOSED' THEN 0 ELSE 1 END) open_count FROM tags WHERE $tagWhere");$t->execute($tagParams);$s=$t->fetch();
}else{
 $q=$p->prepare("SELECT department,SUM(total) total,SUM(closed) closed,SUM(open_count) open_count,CASE WHEN SUM(total)>0 THEN SUM(closed)*100.0/SUM(total) ELSE 0 END pct FROM tag_monthly_summary WHERE $where GROUP BY department ORDER BY pct ASC");$q->execute($params);$rows=$q->fetchAll();
 $t=$p->prepare("SELECT SUM(total) total,SUM(closed) closed,SUM(open_count) open_count FROM tag_monthly_summary WHERE $where");$t->execute($params);$s=$t->fetch();
}
$total=(int)($s['total']??0);$closed=(int)($s['closed']??0);$open=(int)($s['open_count']??0);$pct=$total?round($closed/$total*100,1):0;
$period=$month?($months[$month]." ".$year):"สรุปรวมทั้งปี ".$year;
$lineText="📌 สรุปการปิด Tag 5 ส. ALUMET\n".$period."\nTag ทั้งหมด: ".$total." รายการ\nปิดจบ: ".$closed." รายการ\nยังไม่ปิด: ".$open." รายการ\n% ปิดจบ: ".$pct."%";
require 'includes/header.php';?>
<style>
.board{max-width:1200px;margin:auto}.board-head{border-bottom:5px solid #ff5a00;padding-bottom:15px}.board-title{font-size:34px;font-weight:900}.board-sub{color:#64748b}.big-num{font-size:42px;font-weight:900}.status-open{color:#b91c1c}.status-close{color:#15803d}
@media print{@page{size:A4 landscape;margin:10mm}.print-a3{@page{size:A3 landscape}}.no-print{display:none!important}.main{margin:0;padding:0}.sidebar,.topbar,.footer{display:none!important}.board{max-width:none}.board-title{font-size:30px}.big-num{font-size:34px}}
</style>
<div class="board">
<div class="no-print flex justify-end gap-2 mb-4"><button onclick="window.print()" class="bg-orange-500 text-white rounded-xl px-5 py-3 font-black">🖨 พิมพ์ / Save PDF</button><button onclick="copyLine()" class="bg-green-600 text-white rounded-xl px-5 py-3 font-black">💬 คัดลอกข้อความส่ง LINE</button></div>
<div class="board-head"><div class="flex items-center gap-3"><img src="assets/alumet-logo.png" alt="ALUMET" style="height:34px;width:auto"><span class="text-orange-600 font-black text-xl">5S SMART</span></div><div class="board-title">สรุปการปิด Tag 5 ส.</div><div class="board-sub"><?=$period?><?= $dept?' • '.h($dept):' • ทุกแผนก'?></div></div>
<div class="grid grid-cols-4 gap-4 my-5"><div class="card p-5 text-center"><div class="text-slate-500">Tag ทั้งหมด</div><div class="big-num"><?=$total?></div></div><div class="card p-5 text-center"><div class="text-slate-500">ปิดจบ</div><div class="big-num status-close"><?=$closed?></div></div><div class="card p-5 text-center"><div class="text-slate-500">ยังไม่ปิด</div><div class="big-num status-open"><?=$open?></div></div><div class="card p-5 text-center"><div class="text-slate-500">% ปิดจบ</div><div class="big-num text-orange-600"><?=$pct?>%</div></div></div>
<div class="card p-5"><h2 class="text-xl font-black mb-3">📋 สรุปตามแผนก</h2><table class="w-full text-sm"><thead><tr class="bg-slate-900 text-white"><th class="p-3 text-left">แผนก</th><th class="p-3">Tag ทั้งหมด</th><th class="p-3">ปิดจบ</th><th class="p-3">ไม่ปิดจบ</th><th class="p-3">% ปิดจบ</th><th class="p-3">สถานะ</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr class="border-b"><td class="p-3 font-black"><?=h($r['department'])?></td><td class="p-3 text-center"><?=$r['total']?></td><td class="p-3 text-center text-green-700 font-bold"><?=$r['closed']?></td><td class="p-3 text-center text-red-700 font-bold"><?=$r['open_count']?></td><td class="p-3 text-center font-black"><?=number_format($r['pct'],1)?>%</td><td class="p-3 text-center"><?=($r['pct']>=85?'ผ่าน':'ต้องติดตาม')?></td></tr><?php endforeach;?>
</tbody></table></div>
<div class="grid grid-cols-2 gap-4 mt-5"><div class="card p-4"><b>เกณฑ์ติดตาม:</b> ถ้า % ปิดจบต่ำกว่า 85% ให้ติดตามหน่วยงานที่ยังมี Tag ค้าง</div><div class="card p-4 text-right">จัดทำโดย 5S SMART | ALUMET</div></div>
</div>
<script>
function copyLine(){const text=<?=json_encode($lineText,JSON_UNESCAPED_UNICODE)?>;navigator.clipboard?.writeText(text).then(()=>alert('คัดลอกข้อความสรุปแล้ว สามารถวางใน LINE กลุ่มได้เลย'));}
</script>
<?php require 'includes/footer.php';?>
