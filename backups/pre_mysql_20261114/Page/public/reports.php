<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
$title='รายงาน / Ranking';
$p=db();$year=(int)($_GET['year']??date('Y'));$dept=$_GET['department']??'Production';
if(!in_array($dept,['Production','Office'],true))$dept='Production';
$scoreTable=$dept==='Office'?'office_scores':'scores';$critTable=$dept==='Office'?'office_criteria':'criteria';$target=target_pct($dept);
/* V60: with no month chosen, open the latest month that has saved scores. */
if(!isset($_GET['month'])&&!isset($_GET['year'])){[$__ly,$__lm]=five_s_latest_data_month($dept);$year=$__ly;}
$month=(int)($_GET['month']??($__lm??date('n'))); if($month<1||$month>12)$month=(int)date('n');
$yearDb=$year>=2400?$year-543:$year; $thaiYear=$yearDb+543;
$thaiMonths=['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$monthStart=sprintf('%04d-%02d-01',$yearDb,$month); $monthEnd=date('Y-m-t',strtotime($monthStart));
$auditorCounts=[];
$aq=$p->prepare("SELECT auditor,auditor2,auditor3,audit_date FROM audits WHERE department=? AND audit_date BETWEEN ? AND ? ORDER BY audit_date,auditor");
$aq->execute([$dept,$monthStart,$monthEnd]);
foreach($aq as $ar){
  foreach(['auditor','auditor2','auditor3'] as $ak){
    $nm=trim((string)($ar[$ak]??'')); if($nm==='') continue;
    if(!isset($auditorCounts[$nm])) $auditorCounts[$nm]=['name'=>$nm,'count'=>0];
    $auditorCounts[$nm]['count']++;
  }
}
$auditorChart=array_values($auditorCounts); usort($auditorChart,fn($a,$b)=>$b['count']<=>$a['count']);
$p->exec("CREATE TABLE IF NOT EXISTS monthly_scan_documents(id INTEGER PRIMARY KEY AUTOINCREMENT,department TEXT NOT NULL DEFAULT 'Production',scan_year INTEGER NOT NULL,scan_month INTEGER NOT NULL,title TEXT NOT NULL DEFAULT '',filename TEXT NOT NULL DEFAULT '',stored_path TEXT NOT NULL DEFAULT '',created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
$scanDir=FIVE_S_ROOT.'/uploads/monthly_scans'; if(!is_dir($scanDir)) @mkdir($scanDir,0775,true);
$uploadError='';
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='upload_monthly_scan'){
  $upDept=$_POST['department']??$dept; if(!in_array($upDept,['Production','Office'],true))$upDept='Production';
  $upYear=(int)($_POST['year']??$year); $upMonth=(int)($_POST['month']??$month); $upYearDb=$upYear>=2400?$upYear-543:$upYear;
  $title=trim((string)($_POST['title']??'')); $f=$_FILES['scan_pdf']??null;
  if(!$f || ($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) $uploadError='กรุณาเลือกไฟล์ PDF';
  elseif(strtolower(pathinfo((string)$f['name'],PATHINFO_EXTENSION))!=='pdf') $uploadError='รับเฉพาะไฟล์ PDF เท่านั้น';
  elseif((int)$f['size']>20*1024*1024) $uploadError='ไฟล์ต้องไม่เกิน 20 MB';
  else{
    $safe=(string)preg_replace('/[^A-Za-z0-9._-]+/','_',pathinfo((string)$f['name'],PATHINFO_FILENAME)); $safe=trim($safe,'._-')?:'scan';
    $fn=$upDept.'_'.$upYearDb.'_'.$upMonth.'_'.date('YmdHis').'_'.bin2hex(random_bytes(3)).'_'.$safe.'.pdf'; $dest=$scanDir.'/'.$fn;
    if(move_uploaded_file($f['tmp_name'],$dest)){
      $rel='uploads/monthly_scans/'.$fn;
      $st=$p->prepare('INSERT INTO monthly_scan_documents(department,scan_year,scan_month,title,filename,stored_path) VALUES(?,?,?,?,?,?)');
      $st->execute([$upDept,$upYearDb,$upMonth,$title!==''?$title:(($thaiMonths[$upMonth-1]??'เดือน').' '.$upYear),$f['name'],$rel]);
      header('Location: '.FIVE_S_BASE.'Page/public/reports.php?department='.urlencode($upDept).'&year='.urlencode($upYear).'&month='.$upMonth.'&uploaded=1'); exit;
    }
    $uploadError='บันทึกไฟล์ PDF ไม่สำเร็จ';
  }
}
$scansQ=$p->prepare('SELECT * FROM monthly_scan_documents WHERE department=? AND scan_year=? AND scan_month=? ORDER BY id DESC');
$scansQ->execute([$dept,$yearDb,$month]); $scanDocs=$scansQ->fetchAll();

$areas=$p->prepare("SELECT * FROM areas WHERE active=1 AND department=? ORDER BY CAST(area_code AS INTEGER),area_name");$areas->execute([$dept]);$areas=$areas->fetchAll();
$rows=[];
foreach($areas as $a){
 $q=$p->prepare("SELECT s.score1,s.score2,s.score3,c.max_score
   FROM audits au
   JOIN {$scoreTable} s ON s.audit_id=au.id
   JOIN {$critTable} c ON c.id=s.criterion_id
   WHERE au.area_id=? AND au.audit_date BETWEEN ? AND ?");
 $q->execute([$a['id'],$monthStart,$monthEnd]);$s=0;$m=0;$audDates=[];
 foreach($q as $r){
   $v=score_avg($r['score1'],$r['score2'],$r['score3']);
   if($v!==null){$s+=(float)$v;$m+=(float)$r['max_score'];}
 }
 $aq=$p->prepare("SELECT COUNT(*) FROM audits WHERE area_id=? AND audit_date BETWEEN ? AND ?");
 $aq->execute([$a['id'],$monthStart,$monthEnd]);$aud=(int)$aq->fetchColumn();
 if($m>0||$aud>0)$rows[]=['name'=>$a['area_name'],'audits'=>$aud,'score'=>$s,'max'=>$m,'pct'=>$m?round($s/$m*100,1):0];
}
usort($rows,fn($a,$b)=>$b['pct']<=>$a['pct']);
/* คะแนนกราฟด้านขวา: ใช้ข้อมูลของเดือนที่เลือกให้ตรงกับ Dashboard
   ให้แสดงคะแนนตามข้อจริงแทน เพื่อไม่ให้กราฟว่าง/เป็นศูนย์ทั้งหมด */
$cat=[];
$q=$p->prepare("SELECT cr.item_no,s.score1,s.score2,s.score3,cr.max_score
  FROM audits au
  JOIN {$scoreTable} s ON s.audit_id=au.id
  JOIN {$critTable} cr ON cr.id=s.criterion_id
  WHERE au.department=? AND au.audit_date BETWEEN ? AND ?
  ORDER BY cr.item_no");
$q->execute([$dept,$monthStart,$monthEnd]);
$byItem=[];
foreach($q as $r){
  $n=(int)$r['item_no'];
  if(!isset($byItem[$n]))$byItem[$n]=['s'=>0.0,'m'=>0.0];
  $v=score_avg($r['score1'],$r['score2'],$r['score3']);
  if($v!==null){$byItem[$n]['s']+=(float)$v;$byItem[$n]['m']+=(float)$r['max_score'];}
}
foreach($byItem as $n=>$v){
  $cat[]=['cat'=>'ข้อ '.$n,'pct'=>$v['m']?round($v['s']/$v['m']*100,1):0];
}
require FIVE_S_ROOT.'/includes/header.php';
?>
<div class="hero"><div class="hero-copy"><h1>กราฟ / <span>Ranking</span></h1><p><?=h($dept)?> • ข้อมูลคะแนนจริงของเดือนที่เลือก • ใช้ฐานข้อมูลเดียวกับ Dashboard</p></div><div class="hero-right"><strong><?=number_format($target,1)?>%</strong><small>เป้าหมายปัจจุบัน</small></div></div>
<div class="card p-5 mb-5 no-print"><form class="flex flex-wrap gap-3 items-end"><div><label class="text-xs font-bold">ประเภท</label><select name="department" class="border rounded-xl p-3"><option value="Production" <?=$dept==='Production'?'selected':''?>>Production</option><option value="Office" <?=$dept==='Office'?'selected':''?>>Office</option></select></div><div><label class="text-xs font-bold">เดือน</label><select name="month" class="border rounded-xl p-3"><?php foreach($thaiMonths as $mi=>$mn): ?><option value="<?=($mi+1)?>" <?=($month===($mi+1)?'selected':'')?>><?=h($mn)?></option><?php endforeach; ?></select></div><div><label class="text-xs font-bold">ปี</label><input name="year" type="number" value="<?=$year?>" class="border rounded-xl p-3 w-32"></div><button class="bg-slate-800 text-white rounded-xl px-5 py-3 font-bold">ดูรายงาน</button><a href="Page/admin/settings.php" class="border rounded-xl px-5 py-3 font-bold">⚙ แก้เป้าหมาย</a><button type="button" onclick="window.print()" class="border rounded-xl px-5 py-3 font-bold">🖨 พิมพ์รายงาน A4 / PDF</button><a target="_blank" href="Page/public/graph_print.php?department=<?=urlencode($dept)?>&view=month&month=<?=urlencode($thaiMonths[$month-1])?>&year=<?=$year?>&chart=split&size=A4" class="border border-orange-300 text-orange-700 rounded-xl px-5 py-3 font-bold">📊 กราฟ A4</a><a target="_blank" href="Page/public/graph_print.php?department=<?=urlencode($dept)?>&view=month&month=<?=urlencode($thaiMonths[$month-1])?>&year=<?=$year?>&chart=split&size=A3" class="border border-orange-300 text-orange-700 rounded-xl px-5 py-3 font-bold">📊 กราฟ A3</a></form></div>
<div class="grid xl:grid-cols-2 gap-5 mb-5"><div class="card p-5"><h2 class="font-bold mb-1">กรรมการที่มาตรวจจริง — <?=h($thaiMonths[$month-1])?> <?=$thaiYear?></h2><p class="text-sm text-slate-500 mb-3">นับจากรายการตรวจที่บันทึกจริง และรวมผู้ตรวจ 1–3 คน</p><div style="height:330px;position:relative"><canvas id="auditorChart"></canvas></div></div><div class="card p-5 no-print"><h2 class="font-bold mb-1">สแกนใบตรวจเก็บเป็น PDF</h2><p class="text-sm text-slate-500 mb-3">เก็บเอกสารกระดาษของเดือนนี้ไว้เปิดดูย้อนหลัง</p><?php if($uploadError!==''): ?><div class="bg-red-50 text-red-700 border border-red-200 rounded-lg p-2 mb-3"><?=h($uploadError)?></div><?php endif; ?><form method="post" enctype="multipart/form-data" class="space-y-3"><input type="hidden" name="action" value="upload_monthly_scan"><input type="hidden" name="department" value="<?=h($dept)?>"><input type="hidden" name="year" value="<?=$year?>"><input type="hidden" name="month" value="<?=$month?>"><input name="title" class="w-full border rounded-xl p-3" placeholder="ชื่อเอกสาร เช่น ใบตรวจ 5 ส. เดือนกันยายน"><input type="file" name="scan_pdf" accept="application/pdf,.pdf" required class="w-full border rounded-xl p-3"><button class="bg-green-600 text-white rounded-xl px-5 py-3 font-bold">📄 บันทึก PDF สแกน</button></form><div class="mt-4 space-y-2"><?php foreach($scanDocs as $sd): ?><div class="flex items-center justify-between gap-2 border rounded-lg p-2"><div><div class="font-bold"><?=h($sd['title'])?></div><div class="text-xs text-slate-500"><?=h($sd['filename'])?></div></div><a class="bg-slate-800 text-white rounded-lg px-3 py-2 text-sm font-bold" target="_blank" href="<?=h($sd['stored_path'])?>">เปิด PDF</a></div><?php endforeach; if(!$scanDocs): ?><div class="text-sm text-slate-400">ยังไม่มีไฟล์สแกนของเดือนนี้</div><?php endif; ?></div></div></div><div class="grid xl:grid-cols-2 gap-5"><div class="card p-5"><h2 class="font-bold mb-3">คะแนนรายพื้นที่ <?=$year?></h2><canvas id="rank" height="180"></canvas></div><div class="card p-5"><h2 class="font-bold mb-3">คะแนนรายข้อ <?=$year?></h2><div style="height:420px;position:relative"><canvas id="cat"></canvas></div></div></div>
<div class="card p-5 mt-5"><h2 class="font-bold text-lg mb-3">ตารางสรุปคะแนน — เป้าหมาย <?=number_format($target,1)?>%</h2><table class="w-full border-collapse"><thead><tr class="bg-slate-100"><th class="p-3 border">อันดับ</th><th class="p-3 border text-left">พื้นที่</th><th class="p-3 border">ครั้งตรวจ</th><th class="p-3 border">คะแนน</th><th class="p-3 border">เต็มที่ใช้จริง</th><th class="p-3 border">%</th><th class="p-3 border">สถานะ</th></tr></thead><tbody><?php foreach($rows as $i=>$r):?><tr><td class="p-3 border text-center"><?=$i+1?></td><td class="p-3 border"><?=h($r['name'])?></td><td class="p-3 border text-center"><?=$r['audits']?></td><td class="p-3 border text-center"><?=number_format($r['score'],2)?></td><td class="p-3 border text-center"><?=number_format($r['max'],2)?></td><td class="p-3 border text-center font-bold"><?=$r['pct']?>%</td><td class="p-3 border text-center"><?=h(status_text((float)$r['pct'],$dept))?></td></tr><?php endforeach;?></tbody></table></div>
<script>
const auditorData=<?=json_encode($auditorChart,JSON_UNESCAPED_UNICODE)?>;
new Chart(document.getElementById('auditorChart'),{type:'bar',data:{labels:auditorData.map(x=>x.name),datasets:[{label:'ครั้งที่มาตรวจ',data:auditorData.map(x=>x.count),borderRadius:7}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,scales:{x:{beginAtZero:true,ticks:{precision:0},title:{display:true,text:'จำนวนครั้ง'}}},plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>' '+c.parsed.x+' ครั้ง'}}}}});
const rows=<?=json_encode($rows,JSON_UNESCAPED_UNICODE)?>,cat=<?=json_encode($cat,JSON_UNESCAPED_UNICODE)?>,TARGET=<?=json_encode($target)?>;
new Chart(document.getElementById('rank'),{type:'bar',data:{labels:rows.map(x=>x.name),datasets:[{label:'คะแนน %',data:rows.map(x=>x.pct),borderRadius:6},{label:'เป้าหมาย '+TARGET+'%',type:'line',data:rows.map(()=>TARGET),borderColor:'#ef4444',borderDash:[6,4],pointRadius:0}]},options:{indexAxis:'y',scales:{x:{min:0,max:100}},plugins:{legend:{position:'top'}}}});
new Chart(document.getElementById('cat'),{
 type:'bar',
 data:{labels:cat.map(x=>x.cat),datasets:[{
   label:'คะแนน %',
   data:cat.map(x=>x.pct),
   borderRadius:5
 }]},
 options:{
   indexAxis:'y',
   responsive:true,
   maintainAspectRatio:false,
   scales:{x:{min:0,max:100,title:{display:true,text:'คะแนน (%)'}}},
   plugins:{legend:{display:false}}
 }
});
</script>
<?php require FIVE_S_ROOT.'/includes/footer.php';?>
