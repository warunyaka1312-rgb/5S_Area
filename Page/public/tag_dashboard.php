<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
$title='Tag 5 ส. — Dashboard';
$p=db();
$savedYear=(int)app_setting('tag_dashboard_year',(string)(date('Y')+543)); $year=(int)($_GET['year']??$savedYear); if($year<2000||$year>2700)$year=$savedYear;
// UI may use Thai Buddhist Era (พ.ศ.); SQLite dates are stored as Gregorian YYYY-MM-DD.
$queryYear = $year > 2400 ? $year - 543 : $year;
$month=(int)($_GET['month']??0);
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_year'){
  $newYear=(int)($_POST['year']??0);
  if($newYear>=2000 && $newYear<=2100){
    save_app_setting('tag_dashboard_year',(string)$newYear);
    header('Location: '.FIVE_S_BASE.'Page/public/tag_dashboard.php?year='.$newYear.'&month='.(int)($_POST['month']??0).'&department='.urlencode(trim($_POST['department']??'')));
    exit;
  }
}
$dept=trim($_GET['department']??'');
$months=[1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];

// Dashboard source selection: use live Tag records when available for the selected period;
// otherwise use the imported monthly summary for legacy historical reports.
$sumWhere="year=?"; $sumParams=[$queryYear];
if($month>0){$sumWhere.=" AND month=?";$sumParams[]=$month;}
if($dept!==''){$sumWhere.=" AND department=?";$sumParams[]=$dept;}
$tagWhere="substr(tag_date,1,4)=?"; $tagParams=[(string)$queryYear];
if($month>0){$tagWhere.=" AND CAST(substr(tag_date,6,2) AS SIGNED)=?";$tagParams[]=$month;}
if($dept!==''){$tagWhere.=" AND department=?";$tagParams[]=$dept;}
$check=$p->prepare("SELECT COUNT(*) FROM tags WHERE $tagWhere");$check->execute($tagParams);
$useLiveTags=((int)$check->fetchColumn())>0;
if($useLiveTags){
  $q=$p->prepare("SELECT COUNT(*) total, CAST(SUM(CASE WHEN status='CLOSED' THEN 1 ELSE 0 END) AS SIGNED) closed, CAST(SUM(CASE WHEN status='CLOSED' THEN 0 ELSE 1 END) AS SIGNED) open_count, CASE WHEN COUNT(*)>0 THEN CAST(SUM(CASE WHEN status='CLOSED' THEN 1 ELSE 0 END) AS SIGNED)*100e0/COUNT(*) ELSE 0 END pct FROM tags WHERE $tagWhere");
  $q->execute($tagParams);$sum=$q->fetch();
  $total=(int)($sum['total']??0);$closed=(int)($sum['closed']??0);$open=(int)($sum['open_count']??0);$pct=$total?round($closed/$total*100,1):0;
  $groups=$p->prepare("SELECT department, COUNT(*) total, CAST(SUM(CASE WHEN status='CLOSED' THEN 1 ELSE 0 END) AS SIGNED) closed, CAST(SUM(CASE WHEN status='CLOSED' THEN 0 ELSE 1 END) AS SIGNED) open_count, CASE WHEN COUNT(*)>0 THEN CAST(SUM(CASE WHEN status='CLOSED' THEN 1 ELSE 0 END) AS SIGNED)*100e0/COUNT(*) ELSE 0 END pct FROM tags WHERE $tagWhere GROUP BY department ORDER BY pct ASC,department");
  $groups->execute($tagParams);$rows=$groups->fetchAll();
} else {
  $q=$p->prepare("SELECT CAST(SUM(total) AS SIGNED) total,CAST(SUM(closed) AS SIGNED) closed,CAST(SUM(open_count) AS SIGNED) open_count, CASE WHEN CAST(SUM(total) AS SIGNED)>0 THEN CAST(SUM(closed) AS SIGNED)*100e0/CAST(SUM(total) AS SIGNED) ELSE 0 END pct FROM tag_monthly_summary WHERE $sumWhere");$q->execute($sumParams);$sum=$q->fetch();
  $total=(int)($sum['total']??0);$closed=(int)($sum['closed']??0);$open=(int)($sum['open_count']??0);$pct=$total?round($closed/$total*100,1):0;
  $groups=$p->prepare("SELECT department,CAST(SUM(total) AS SIGNED) total,CAST(SUM(closed) AS SIGNED) closed,CAST(SUM(open_count) AS SIGNED) open_count, CASE WHEN CAST(SUM(total) AS SIGNED)>0 THEN CAST(SUM(closed) AS SIGNED)*100e0/CAST(SUM(total) AS SIGNED) ELSE 0 END pct FROM tag_monthly_summary WHERE $sumWhere GROUP BY department ORDER BY pct ASC,department");$groups->execute($sumParams);$rows=$groups->fetchAll();
}
// The trend always shows all months in the selected year (not just the month filter).
$trendTagWhere="substr(tag_date,1,4)=?"; $trendTagParams=[(string)$queryYear];
if($dept!==''){$trendTagWhere.=" AND department=?";$trendTagParams[]=$dept;}
$trendCheck=$p->prepare("SELECT COUNT(*) FROM tags WHERE $trendTagWhere");$trendCheck->execute($trendTagParams);
if((int)$trendCheck->fetchColumn()>0){
  $tq=$p->prepare("SELECT CAST(substr(tag_date,6,2) AS SIGNED) month,COUNT(*) total,CAST(SUM(CASE WHEN status='CLOSED' THEN 1 ELSE 0 END) AS SIGNED) closed,CASE WHEN COUNT(*)>0 THEN CAST(SUM(CASE WHEN status='CLOSED' THEN 1 ELSE 0 END) AS SIGNED)*100e0/COUNT(*) ELSE 0 END pct FROM tags WHERE $trendTagWhere GROUP BY substr(tag_date,6,2) ORDER BY month");
  $tq->execute($trendTagParams);$trend=$tq->fetchAll();
} else {
  $trendParams=[$queryYear];$trendSql="SELECT month,CAST(SUM(total) AS SIGNED) total,CAST(SUM(closed) AS SIGNED) closed,CASE WHEN CAST(SUM(total) AS SIGNED)>0 THEN CAST(SUM(closed) AS SIGNED)*100e0/CAST(SUM(total) AS SIGNED) ELSE 0 END pct FROM tag_monthly_summary WHERE year=?";
  if($dept!==''){$trendSql.=" AND department=?";$trendParams[]=$dept;}
  $trendSql.=" GROUP BY month ORDER BY month";$tq=$p->prepare($trendSql);$tq->execute($trendParams);$trend=$tq->fetchAll();
}
$tagq=$p->query("SELECT * FROM tags ORDER BY CASE WHEN status='OPEN' THEN 0 ELSE 1 END,tag_date DESC,id DESC LIMIT 20");$recent=$tagq->fetchAll();

require FIVE_S_ROOT.'/includes/header.php';
?>
<div class="hero">
 <div class="hero-copy"><h1>ระบบติดตาม <span>5 ส. TAG</span></h1><p>รับ Tag รายเดือน • ติดตามการปิดจบ • สรุปผล • พิมพ์ติดบอร์ด</p></div>
 <div class="hero-right"><strong>TAG CONTROL</strong><small>OPEN • CLOSED • OVERDUE</small></div>
</div>
<div class="flex flex-wrap items-end justify-between gap-4 mb-5">
 <div><div class="text-3xl font-black">🏷 Dashboard การปิด Tag 5 ส.</div><div class="text-slate-500">ตรวจสถานะ Tag รายเดือนและรวมรายปี</div></div>
 <div class="flex gap-2 no-print"><a href="Page/auditor/tag_manage.php" class="px-5 py-3 rounded-xl bg-orange-500 text-white font-black">＋ รับ Tag / ลงข้อมูล</a><a href="Page/public/tag_print.php?year=<?=$year?>&month=<?=$month?>&department=<?=urlencode($dept)?>" target="_blank" class="px-5 py-3 rounded-xl bg-slate-900 text-white font-black">🖨 พิมพ์ A4/A3</a></div>
</div>
<form method="get" class="card p-4 mb-3 no-print grid md:grid-cols-5 gap-3 items-end">
 <div><label class="text-xs font-bold">ปี</label><input id="tagYear" name="year" type="number" min="2000" max="2100" value="<?=$year?>" class="w-full border rounded-xl p-3"></div>
 <div><label class="text-xs font-bold">เดือน</label><select name="month" class="w-full border rounded-xl p-3"><option value="0">รวมทั้งปี</option><?php foreach($months as $m=>$n):?><option value="<?=$m?>" <?=$month==$m?'selected':''?>><?=$n?></option><?php endforeach;?></select></div>
 <div><label class="text-xs font-bold">แผนก</label><input name="department" value="<?=h($dept)?>" placeholder="เว้นว่าง = ทุกแผนก" class="w-full border rounded-xl p-3"></div>
 <button class="bg-slate-800 text-white rounded-xl p-3 font-black">🔎 แสดงผล</button>
</form>
<form method="post" class="no-print flex items-center gap-2 mb-5">
 <input type="hidden" name="action" value="save_year"><input type="hidden" name="year" id="saveYear" value="<?=$year?>">
 <input type="hidden" name="month" value="<?=$month?>"><input type="hidden" name="department" value="<?=h($dept)?>">
 <button class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl px-5 py-3 font-black">💾 บันทึกปี <?=$year?> เป็นปีเริ่มต้น</button>
 <span class="text-sm text-slate-500">หลังบันทึก เปิดหน้าใหม่ก็ยังใช้ปีนี้</span>
</form>
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
 <div class="card p-5"><div class="text-xs text-slate-500">Tag ทั้งหมด</div><div class="text-4xl font-black"><?=$total?></div><div class="text-xs text-slate-500">รายการ</div></div>
 <div class="card p-5"><div class="text-xs text-slate-500">ปิดจบแล้ว</div><div class="text-4xl font-black text-emerald-600"><?=$closed?></div><div class="text-xs text-slate-500">รายการ</div></div>
 <div class="card p-5"><div class="text-xs text-slate-500">ยังไม่ปิดจบ</div><div class="text-4xl font-black text-red-600"><?=$open?></div><div class="text-xs text-slate-500">รายการ</div></div>
 <div class="card p-5"><div class="text-xs text-slate-500">% ปิดจบ</div><div class="text-4xl font-black text-orange-600"><?=$pct?>%</div><div class="text-xs text-slate-500"><?=$month?($months[$month]??''): 'รวมทั้งปี'?> <?=$year?></div></div>
</div>
<div class="grid xl:grid-cols-2 gap-5 mb-5">
 <div class="card chart-card p-5"><div class="section-title mb-3">📊 สถานะการปิด Tag ตามแผนก</div><div class="chart-wrap"><canvas id="tagDeptChart"></canvas></div></div>
 <div class="card chart-card p-5"><div class="section-title mb-3">📈 แนวโน้ม % ปิดจบรายเดือน</div><?php if(!$trend):?><p class="text-slate-500 text-sm mb-2">ยังไม่มีข้อมูลแนวโน้มในปีที่เลือก</p><?php endif;?><div class="chart-wrap"><canvas id="tagTrendChart"></canvas></div></div>
</div>
<div class="card p-5 mb-5"><div class="section-title mb-4">📋 สรุปตามแผนก</div>
<div class="overflow-x-auto"><table id="tagSummary" class="display w-full"><thead><tr><th>แผนก</th><th>Tag ทั้งหมด</th><th>ปิดจบ</th><th>ไม่ปิดจบ</th><th>% ปิดจบ</th><th>สถานะ</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td class="font-bold"><?=h($r['department'])?></td><td><?=$r['total']?></td><td><?=$r['closed']?></td><td><?=$r['open_count']?></td><td class="font-black"><?=number_format($r['pct'],1)?>%</td><td><?=($r['pct']>=85?'<span class="good px-2 py-1 rounded-full">ผ่าน</span>':'<span class="bad px-2 py-1 rounded-full">ต้องติดตาม</span>')?></td></tr><?php endforeach;?>
</tbody></table></div></div>
<div class="card p-5"><div class="flex justify-between items-center mb-3"><div class="section-title">🏷 Tag ที่บันทึกเข้าระบบล่าสุด</div><a class="text-orange-600 font-bold no-print" href="Page/auditor/tag_manage.php">จัดการ Tag →</a></div>
<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b"><th class="p-2 text-left">Tag</th><th>แผนก</th><th>หน่วย</th><th>วันที่รับ</th><th>กำหนด</th><th>สถานะ</th></tr></thead><tbody>
<?php foreach($recent as $r):?><tr class="border-b"><td class="p-2 font-bold"><?=h($r['tag_no'])?></td><td><?=h($r['department'])?></td><td><?=h($r['unit_no'])?></td><td><?=h($r['tag_date'])?></td><td><?=h($r['due_date'])?></td><td><?=status_text($r['status']==='CLOSED'?100:0)?></td></tr><?php endforeach;?>
</tbody></table></div></div>
<script>
const deptData=<?=json_encode($rows,JSON_UNESCAPED_UNICODE)?>,trendData=<?=json_encode($trend,JSON_UNESCAPED_UNICODE)?>;
new Chart(document.getElementById('tagDeptChart'),{type:'bar',data:{labels:deptData.map(x=>x.department),datasets:[
 {label:'ปิดจบ',data:deptData.map(x=>Number(x.closed)),backgroundColor:'#16a34a',borderRadius:6},
 {label:'ไม่ปิดจบ',data:deptData.map(x=>Number(x.open_count)),backgroundColor:'#ef4444',borderRadius:6}
]},options:{responsive:true,maintainAspectRatio:false,scales:{x:{stacked:true},y:{stacked:true,beginAtZero:true}},plugins:{legend:{position:'top'}}}});
new Chart(document.getElementById('tagTrendChart'),{type:'line',data:{labels:trendData.map(x=><?=$month?>?<?=json_encode($months)?>[x.month]:<?=json_encode($months)?>[x.month]),datasets:[{label:'% ปิดจบ',data:trendData.map(x=>Number(x.pct).toFixed(1)),borderColor:'#ff5a00',backgroundColor:'rgba(255,90,0,.12)',fill:true,tension:.3,pointRadius:4}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{min:0,max:100,ticks:{callback:v=>v+'%'}}},plugins:{legend:{display:false}}}});
$(function(){$('#tagSummary').DataTable({pageLength:25,scrollX:true,language:{search:'ค้นหา:',lengthMenu:'แสดง _MENU_',paginate:{next:'ถัดไป',previous:'ก่อนหน้า'}}})});
</script>
<?php require FIVE_S_ROOT.'/includes/footer.php';?>
