<?php
require_once 'config.php';require_once __DIR__.'/includes/print_brand.php';

$department = $_GET['department'] ?? 'Production';
$view = $_GET['view'] ?? 'month';
/* V60: default to the latest month that has saved scores for this department. */
[$__ly,$__lm] = five_s_latest_data_month(in_array($_GET['department'] ?? 'Production', ['Production','Office'], true) ? ($_GET['department'] ?? 'Production') : 'Production');
$month = $_GET['month'] ?? five_s_thai_months()[$__lm];
$yearInput = (int)($_GET['year'] ?? $__ly);
$chart = $_GET['chart'] ?? 'split';
$size = strtoupper($_GET['size'] ?? 'A4');

if (!in_array($department, ['Production','Office'], true)) $department = 'Production';
if (!in_array($size, ['A4','A3'], true)) $size = 'A4';
if (!in_array($chart, ['split','unit','group'], true)) $chart = 'split';

$yearDb = $yearInput >= 2400 ? $yearInput - 543 : $yearInput;
$thaiYear = $yearDb + 543;
$target = target_pct($department);

$months = [
    'มกราคม'=>1,'กุมภาพันธ์'=>2,'มีนาคม'=>3,'เมษายน'=>4,
    'พฤษภาคม'=>5,'มิถุนายน'=>6,'กรกฎาคม'=>7,'สิงหาคม'=>8,
    'กันยายน'=>9,'ตุลาคม'=>10,'พฤศจิกายน'=>11,'ธันวาคม'=>12
];
$monthNo = $months[$month] ?? (int)date('n');

if ($view === 'year') {
    $start = sprintf('%04d-01-01', $yearDb);
    $end = sprintf('%04d-12-31', $yearDb);
    $periodLabel = 'ปี ' . $thaiYear;
} else {
    $start = sprintf('%04d-%02d-01', $yearDb, $monthNo);
    $end = date('Y-m-t', strtotime($start));
    $periodLabel = ($month ?: array_search($monthNo, $months, true)) . ' ' . $thaiYear;
}

/* =====================================================================
   V64 board-ready graph print (A4 / A3 landscape, one page)
   - Duplicate Master records of the same group+unit are merged
   - Scores saved on an older inactive record of the same unit still count
   ===================================================================== */
$p = db();
$summaryFn = $department === 'Office' ? 'office_audit_summary' : 'production_audit_summary';
$units = []; // key => meta
foreach ($p->query("SELECT id,group_name,unit_no,area_name,active FROM areas WHERE department=".$p->quote($department)." ORDER BY active DESC, group_name COLLATE NOCASE, CAST(unit_no AS INTEGER), unit_no") as $a) {
    $group = trim((string)display_area_group((string)$a['group_name'])); if ($group === '') $group = trim((string)$a['group_name']); if ($group === '') $group = 'ไม่ระบุกลุ่ม';
    $unit = trim((string)$a['unit_no']);
    $key = $group.'|'.$unit;
    if (!isset($units[$key])) {
        if ((int)$a['active'] !== 1) continue;            // inactive-only records are not part of the Master
        $label = $unit !== '' ? $group.' • หน่วย '.$unit : (trim((string)$a['area_name']) ?: $group);
        $units[$key] = ['group'=>$group,'unit'=>$unit,'label'=>$label,'ids'=>[]];
    }
    $units[$key]['ids'][] = (int)$a['id'];
}
$auditQ = $p->prepare("SELECT id,area_id,audit_date FROM audits WHERE department=? AND audit_date BETWEEN ? AND ? ORDER BY audit_date DESC,id DESC");
$auditQ->execute([$department, $start, $end]);
$auditsByArea = [];
foreach ($auditQ as $ar) $auditsByArea[(int)$ar['area_id']][] = $ar;
$unitRows = []; $missingUnits = [];
foreach ($units as $u) {
    $best = null;
    foreach ($u['ids'] as $aid) foreach (($auditsByArea[$aid] ?? []) as $ar) {
        $sum = $summaryFn($p, (int)$ar['id']);
        if (($sum['max'] ?? 0) > 0 && ($best === null || $ar['audit_date'] > $best['date'])) { $best = ['pct'=>round((float)$sum['pct'],2),'date'=>$ar['audit_date']]; }
        if ($best) break;
    }
    if (!$best) { $missingUnits[] = $u['label']; continue; }
    $unitRows[] = $u + ['pct'=>$best['pct']];
}
$totalActiveAreas = count($units);
if ($chart === 'group') {
    $g = [];
    foreach ($unitRows as $r) { $g[$r['group']]['s'] = ($g[$r['group']]['s'] ?? 0) + $r['pct']; $g[$r['group']]['n'] = ($g[$r['group']]['n'] ?? 0) + 1; }
    $rows = [];
    foreach ($g as $name=>$x) $rows[] = ['label'=>$name.' ('.$x['n'].' หน่วย)','pct'=>round($x['s']/$x['n'],2)];
} else { $rows = $unitRows; }
$belowRows = array_values(array_filter($rows, fn($r)=>$r['pct'] < $target));
$passRows  = array_values(array_filter($rows, fn($r)=>$r['pct'] >= $target));
usort($belowRows, fn($a,$b)=>$a['pct']<=>$b['pct'] ?: strnatcasecmp($a['label'],$b['label']));
usort($passRows,  fn($a,$b)=>$b['pct']<=>$a['pct'] ?: strnatcasecmp($a['label'],$b['label']));
$avg = count($unitRows) ? round(array_sum(array_column($unitRows,'pct'))/count($unitRows),2) : 0;
$passed = count(array_filter($unitRows, fn($r)=>$r['pct'] >= $target));
$below = count($unitRows) - $passed;
$missingCount = count($missingUnits);
$coverage = $totalActiveAreas ? round(count($unitRows)/$totalActiveAreas*100,1) : 0;
$isOffice = $department === 'Office';
$deptTh = $isOffice ? 'Office • สำนักงาน' : 'Production • ฝ่ายผลิต';
$title = 'คะแนน 5 ส. — ' . $department . ' / ' . $periodLabel;
/* Pass list is split into columns so every unit fits on ONE landscape page. */
$passCols = count($passRows) > 26 ? 3 : (count($passRows) > 13 ? 2 : 1);
$belowCols = count($belowRows) > 22 ? 2 : 1;
$rowsPerCol = max(1, (int)ceil(count($passRows)/$passCols));
$passChunks = $passRows ? array_chunk($passRows, $rowsPerCol) : [];
$axisMin = 50; // bars start at 50% so differences are visible; labelled on every bar
$barW = fn($v)=>max(2, min(100, ((float)$v-$axisMin)/(100-$axisMin)*100));
$tgtPos = $barW($target);
$q = 'department='.urlencode($department).'&view='.urlencode($view).'&month='.urlencode((string)$month).'&year='.$yearInput.'&chart='.urlencode($chart);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($title)?></title><?=five_s_favicon_tags()?>
<link rel="stylesheet" href="assets/vendor/fonts.css">
<script src="assets/vendor/html2canvas.min.js"></script>
<script src="assets/copy-line.js?v=64"></script>
<style>
*{box-sizing:border-box}
:root{--brand:#F26A21;--amber:#F7A21B;--navy:#0E1A2F;--ink:#0F172A;--muted:#5B6B82;--line:#E2E8F0;--ok:#16A34A;--ok2:#15803D;--bad:#DC2626;--bad2:#B91C1C;--font:"IBM Plex Sans Thai","Inter",Tahoma,sans-serif;--num:"Inter","IBM Plex Sans Thai",Arial,sans-serif}
html,body{margin:0;background:#E9EDF3;color:var(--ink);font-family:var(--font);-webkit-font-smoothing:antialiased}
.bar-tools{display:flex;gap:8px;justify-content:center;flex-wrap:wrap;padding:12px 16px}
.bar-tools a,.bar-tools button{display:inline-flex;align-items:center;gap:6px;height:40px;padding:0 16px;border-radius:10px;border:1px solid #D5DBE5;background:#fff;color:#1E293B;font:600 14px var(--font);text-decoration:none;cursor:pointer}
.bar-tools a.on{background:var(--navy);color:#fff;border-color:var(--navy)}
.bar-tools .primary{background:var(--brand);border-color:var(--brand);color:#fff}
.wrap{padding:0 16px 30px;overflow-x:auto}
.poster{width:1600px;height:1131px;margin:0 auto;background:#fff;box-shadow:0 10px 40px #0f172a1f;padding:28px 36px 18px;display:flex;flex-direction:column;gap:14px;position:relative;overflow:hidden}
.poster::before{content:"";position:absolute;left:0;right:0;top:0;height:9px;background:linear-gradient(90deg,var(--amber),var(--brand) 40%,var(--navy))}
.hd{display:grid;grid-template-columns:auto 1fr auto;gap:28px;align-items:center;padding-bottom:12px;border-bottom:2px solid var(--line)}
.hd .lg{display:flex;align-items:center;gap:16px}.hd .lg img{height:48px}.hd .lg .s{width:2px;height:42px;background:var(--line)}
.hd .lg b{font:800 24px/1 var(--num);color:var(--navy)}.hd .lg b span{color:var(--brand)}.hd .lg small{display:block;font:600 11.5px var(--font);color:var(--muted);letter-spacing:.8px;margin-top:5px}
.hd .tt{text-align:center}.hd .tt h1{margin:0;font-size:40px;font-weight:700;color:var(--navy);line-height:1.15}.hd .tt h1 span{color:<?=$isOffice?'#2563EB':'var(--brand)'?>}
.hd .tt p{margin:4px 0 0;font:700 13px var(--num);letter-spacing:3px;color:var(--muted);text-transform:uppercase}
.hd .pd{text-align:right;border-left:2px solid var(--line);padding-left:24px}.hd .pd small{display:block;font-size:13px;color:var(--muted);font-weight:600}.hd .pd b{font:800 28px/1.2 var(--num);color:var(--navy);white-space:nowrap}
.kp{display:grid;grid-template-columns:330px repeat(5,1fr);gap:12px}
.kp .main{background:<?=$isOffice?'linear-gradient(120deg,#1E3A8A,#2563EB)':'linear-gradient(120deg,#E8540F,#F26A21 55%,#F7A21B)'?>;color:#fff;border-radius:16px;padding:14px 22px;display:flex;flex-direction:column;justify-content:center}
.kp .main small{font-size:15px;font-weight:600;opacity:.9}.kp .main b{font:800 62px/1 var(--num);letter-spacing:-2px;margin-top:4px}.kp .main b sup{font-size:28px;letter-spacing:0;top:-24px;position:relative;margin-left:3px}
.kp .main em{font-style:normal;display:inline-block;margin-top:6px;background:rgba(255,255,255,.2);border-radius:999px;padding:3px 12px;font-size:14px;font-weight:700;width:max-content}
.kp .k{border:1px solid var(--line);border-radius:16px;padding:12px 16px;position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:center}
.kp .k::before{content:"";position:absolute;left:0;top:0;bottom:0;width:6px;background:var(--c)}
.kp .k small{font-size:15px;font-weight:700;color:#334155}.kp .k b{font:800 46px/1.1 var(--num);color:var(--c)}.kp .k b i{font-style:normal;font-size:22px;color:var(--muted)}.kp .k span{font-size:13px;color:var(--muted)}
.body{flex:1;display:grid;grid-template-columns:<?=$belowRows?'minmax(0,'.($belowCols==2?'1.5':($passCols>=3?'1.35':'1')).'fr) minmax(0,'.max(1,$passCols).'fr)':'minmax(0,1fr)'?>;gap:16px;min-height:0}
<?php if(!$belowRows||!$passRows):?>.body{grid-template-columns:1fr}<?php endif;?>
.pn{border:1px solid var(--line);border-radius:16px;padding:12px 16px;display:flex;flex-direction:column;min-height:0;min-width:0}
.pn.bad{background:linear-gradient(180deg,#FFF6F6,#fff 90px);border-color:#F6CACA}.pn.ok{background:linear-gradient(180deg,#F2FBF5,#fff 90px);border-color:#BFE6CC}
.pn h2{margin:0 0 4px;font-size:21px;font-weight:700;display:flex;align-items:center;gap:10px}
.pn h2 .n{font:800 15px var(--num);color:#fff;border-radius:999px;padding:2px 12px}
.pn.bad h2{color:var(--bad2)}.pn.bad h2 .n{background:var(--bad)}.pn.ok h2{color:var(--ok2)}.pn.ok h2 .n{background:var(--ok)}
.pn .sub{font-size:12.5px;color:var(--muted);margin-bottom:8px}
.cols{flex:1;display:grid;gap:14px;min-height:0}.cols>div{min-width:0}
.list{display:flex;flex-direction:column;gap:5px;min-height:0}
.r{display:grid;grid-template-columns:var(--lw,178px) 1fr 64px;gap:8px;align-items:center;height:var(--rh,26px)}
.r .l{font-size:var(--fs,14px);font-weight:600;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-align:right}
.r .t{position:relative;height:calc(var(--rh,26px) - 8px);background:#EEF1F5;border-radius:6px;overflow:visible}
.r .t i{position:absolute;left:0;top:0;bottom:0;border-radius:6px}
.pn.bad .r .t i{background:linear-gradient(90deg,#F87171,#B91C1C)}.pn.ok .r .t i{background:linear-gradient(90deg,#4ADE80,#15803D)}
.r .t .tg{position:absolute;top:-3px;bottom:-3px;width:0;border-left:2px dashed #DC2626}
.r .v{font:800 var(--fs,14px) var(--num);text-align:left}
.pn.bad .r .v{color:var(--bad2)}.pn.ok .r .v{color:var(--ok2)}
.axis{display:grid;grid-template-columns:var(--lw,178px) 1fr 64px;gap:8px;margin-top:4px}
.axis div{display:flex;justify-content:space-between;font:600 11px var(--num);color:var(--muted)}
.miss{margin-top:10px;background:#FFF8EE;border:1px solid #F8D9A6;border-radius:12px;padding:8px 12px}
.miss b{display:block;font-size:14px;color:#92400E;margin-bottom:5px}
.miss span{display:inline-block;background:#fff;border:1px solid #F3D19B;border-radius:999px;padding:2px 10px;margin:2px 3px 0 0;font-size:12.5px;font-weight:600;color:#7C2D12}
.empty{flex:1;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:var(--ok2)}
.ft{display:flex;justify-content:space-between;align-items:center;gap:16px;padding-top:8px;border-top:2px solid var(--line);font-size:12.5px;color:var(--muted)}
.ft .lg{display:flex;align-items:center;gap:10px}.ft img{height:20px}
.ft .key{display:flex;gap:16px;align-items:center}.ft .key i{display:inline-block;width:14px;height:10px;border-radius:3px;margin-right:5px;vertical-align:middle}
.ft .key .dash{width:16px;height:0;border-top:2px dashed #DC2626;border-radius:0}
@page{size:<?=$size?> landscape;margin:0}
@media print{
 html,body{background:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
 .no-print,.fs-pt{display:none!important}.wrap{padding:0;overflow:visible}
 .poster{box-shadow:none;margin:0;zoom:<?=$size==='A3'?'.985':'.698'?>}
}
</style>
</head>
<body>
<?=five_s_print_toolbar($isOffice?'office_reports.php':'reports.php?department=Production','กลับไปกราฟและ Ranking',false)?>
<div class="bar-tools no-print">
  <a class="<?=$department==='Production'?'on':''?>" href="?<?=str_replace('department='.urlencode($department),'department=Production',$q)?>&size=<?=$size?>">Production</a>
  <a class="<?=$department==='Office'?'on':''?>" href="?<?=str_replace('department='.urlencode($department),'department=Office',$q)?>&size=<?=$size?>">Office</a>
  <span style="width:12px"></span>
  <a class="<?=$size==='A4'?'on':''?>" href="?<?=$q?>&size=A4">A4</a>
  <a class="<?=$size==='A3'?'on':''?>" href="?<?=$q?>&size=A3">A3</a>
  <span style="width:12px"></span>
  <a class="<?=$chart!=='group'?'on':''?>" href="?<?=str_replace('chart='.urlencode($chart),'chart=split',$q)?>&size=<?=$size?>">รายหน่วย</a>
  <a class="<?=$chart==='group'?'on':''?>" href="?<?=str_replace('chart='.urlencode($chart),'chart=group',$q)?>&size=<?=$size?>">รายกลุ่ม</a>
  <span style="width:12px"></span>
  <button onclick="savePNG()">📋 คัดลอกรูปส่ง LINE</button>
  <button class="primary" onclick="window.print()">🖨 พิมพ์ <?=$size?> แนวนอน</button>
</div>
<div class="wrap">
<article class="poster" id="poster">
  <header class="hd">
    <div class="lg"><img src="assets/alumet-logo.png" alt="ALUMET"><span class="s"></span><div><b>5S <span>SMART</span></b><small>5S AUDIT SCORE REPORT</small></div></div>
    <div class="tt"><h1>คะแนน 5 ส. <span><?=h($department)?></span></h1><p><?=h($deptTh)?> • <?=$chart==='group'?'รายกลุ่ม':'รายหน่วย'?></p></div>
    <div class="pd"><small><?=$view==='year'?'ประจำปี':'ประจำเดือน'?></small><b><?=h($periodLabel)?></b><small>พิมพ์ <?=date('d/m/').(date('Y')+543)?></small></div>
  </header>
  <section class="kp">
    <div class="main"><small>คะแนนเฉลี่ย <?=h($department)?></small><b><?=number_format($avg,2)?><sup>%</sup></b><em><?=$avg>=$target?'✓ ผ่านเป้าหมาย':'! ต่ำกว่าเป้าหมาย'?> <?=number_format($target,0)?>%</em></div>
    <div class="k" style="--c:#2563EB"><small>ตรวจแล้ว</small><b><?=count($unitRows)?><i> / <?=$totalActiveAreas?></i></b><span>ครอบคลุม <?=number_format($coverage,1)?>%</span></div>
    <div class="k" style="--c:#16A34A"><small>ผ่านเป้าหมาย</small><b><?=$passed?></b><span>หน่วย</span></div>
    <div class="k" style="--c:#DC2626"><small>ต่ำกว่าเป้าหมาย</small><b><?=$below?></b><span>หน่วย • ต้องปรับปรุง</span></div>
    <div class="k" style="--c:#C2410C"><small>ยังไม่มีคะแนน</small><b><?=$missingCount?></b><span>หน่วย</span></div>
    <div class="k" style="--c:#0E1A2F"><small>เป้าหมาย</small><b><?=number_format($target,0)?><i>%</i></b><span>เส้นประสีแดงในกราฟ</span></div>
  </section>
  <?php
    $maxRowsShown = max(1, $belowRows ? (int)ceil(count($belowRows)/$belowCols) : 0, $rowsPerCol);
    $rh = max(20, min(40, (int)floor(640 / $maxRowsShown)));
    $fs = $rh >= 34 ? 17 : ($rh >= 28 ? 15.5 : ($rh >= 24 ? 14 : 12.5));
    $renderList = function(array $list) use ($barW,$tgtPos) { ?>
      <div class="list"><?php foreach($list as $r):?><div class="r"><div class="l" title="<?=h($r['label'])?>"><?=h($r['label'])?></div><div class="t"><i style="width:<?=$barW($r['pct'])?>%"></i><span class="tg" style="left:<?=$tgtPos?>%"></span></div><div class="v"><?=number_format($r['pct'],2)?></div></div><?php endforeach;?></div>
  <?php };
    $axis = '<div class="axis"><span></span><div><span>50%</span><span>75%</span><span>100%</span></div><span></span></div>';
  ?>
  <section class="body" style="--rh:<?=$rh?>px;--fs:<?=$fs?>px;--lw:<?=$chart==='group'?'220px':($fs>=15.5?'200px':'176px')?>">
    <?php if($belowRows):?>
    <div class="pn bad" style="--lw:<?=$chart==='group'?'240px':'250px'?>">
      <h2>ต่ำกว่าเป้าหมาย <span class="n"><?=count($belowRows)?></span></h2>
      <div class="sub">เรียงคะแนนน้อย → มาก • หน่วยที่ต้องเร่งปรับปรุงอยู่ด้านบน</div>
      <div class="cols" style="grid-template-columns:repeat(<?=$belowCols?>,minmax(0,1fr))"><?php foreach(array_chunk($belowRows,(int)ceil(count($belowRows)/$belowCols)) as $ch){ echo '<div>'; $renderList($ch); echo $axis.'</div>'; } ?></div>
      <?php if($missingUnits):?><div class="miss"><b>ยังไม่มีคะแนน <?=$missingCount?> หน่วย (ไม่นำมาคำนวณ)</b><?php foreach(array_slice($missingUnits,0,8) as $m):?><span><?=h($m)?></span><?php endforeach;?><?=$missingCount>8?'<span>+ อีก '.($missingCount-8).'</span>':''?></div><?php endif;?>
    </div>
    <?php endif;?>
    <?php if($passRows):?>
    <div class="pn ok" style="--lw:<?=$chart==='group'?'220px':($passCols>=3?'182px':'205px')?>">
      <h2>ผ่านเป้าหมาย <span class="n"><?=count($passRows)?></span></h2>
      <div class="sub">เรียงคะแนนมาก → น้อย</div>
      <div class="cols" style="grid-template-columns:repeat(<?=$passCols?>,minmax(0,1fr))"><?php foreach($passChunks as $ch){ echo '<div>'; $renderList($ch); echo $axis.'</div>'; } ?></div>
      <?php if(!$belowRows && $missingUnits):?><div class="miss"><b>ยังไม่มีคะแนน <?=$missingCount?> หน่วย</b><?php foreach(array_slice($missingUnits,0,10) as $m):?><span><?=h($m)?></span><?php endforeach;?></div><?php endif;?>
    </div>
    <?php endif;?>
    <?php if(!$belowRows && !$passRows):?><div class="pn"><div class="empty">ยังไม่มีข้อมูลคะแนนในช่วงเวลาที่เลือก</div></div><?php endif;?>
  </section>
  <footer class="ft">
    <div class="lg"><img src="assets/alumet-logo.png" alt="ALUMET"><b style="color:#334155">5S SMART Management System</b></div>
    <div class="key"><span><i style="background:#16A34A"></i>ผ่านเป้าหมาย</span><span><i style="background:#DC2626"></i>ต่ำกว่าเป้าหมาย</span><span><i class="dash"></i>เป้าหมาย <?=number_format($target,0)?>%</span><span>แกนคะแนนเริ่มที่ 50%</span></div>
    <div style="font:800 12px var(--num);letter-spacing:2px;color:var(--brand)">CLEAN TODAY • BETTER TOMORROW</div>
  </footer>
</article>
</div>
<script>
function savePNG(){
  FiveSCopy.copyElement('#poster','5S_<?=$department?>_<?=preg_replace('/\s+/','_',$periodLabel)?>_<?=$size?>.png',{windowWidth:1700});
}
</script>
</body>
</html>
