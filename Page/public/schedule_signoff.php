<?php
/* V68: ใบลงชื่อ Auditor — คอลัมน์วันที่สร้างอัตโนมัติจากวันตรวจในแผนของแต่ละทีม */
require_once dirname(__DIR__, 2).'/includes/config.php';
require_once FIVE_S_ROOT.'/includes/print_brand.php';
require_once FIVE_S_ROOT.'/includes/automation.php';
$p = db();
[$__dm, $__dy] = five_s_default_plan_period();
$month = (int)($_GET['month'] ?? $__dm);
$year = (int)($_GET['year'] ?? $__dy);
if ($month < 1 || $month > 12) $month = (int)date('n');
if ($year < 2500 || $year > 2700) $year = (int)date('Y') + 543;
$teamSel = strtoupper(trim((string)($_GET['team'] ?? 'ALL')));
if (!in_array($teamSel, ['A','B','ALL'], true)) $teamSel = 'ALL';
$months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
$mShort = [1=>'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$wd = ['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'];
function signoff_h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$q = $p->prepare("SELECT id, auditor_name, department, inspection_date, inspection_time, sort_no FROM audit_plans WHERE month=? AND year=? AND team=? AND active=1 ORDER BY sort_no, id");
$teams = [];
foreach (['A','B'] as $tk) {
    $q->execute([$month, $year, $tk]);
    $people = []; $dates = []; $times = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $k = (int)$r['sort_no'];
        $people[$k] ??= ['name'=>'', 'deps'=>[], 'dates'=>[]];
        $n = trim((string)$r['auditor_name']); if ($people[$k]['name'] === '' && $n !== '') $people[$k]['name'] = $n;
        $d = five_s_plan_date_iso((string)$r['inspection_date']);
        $dep = trim((string)$r['department']);
        if ($dep !== '') $people[$k]['deps'][] = $dep;
        if ($d !== '') { $dates[$d] = true; $people[$k]['dates'][$d][] = $dep; }
        $t = trim((string)$r['inspection_time']); if ($t !== '') $times[$t] = true;
    }
    // แถวที่มีแผนกแต่ยังไม่มีชื่อ ก็ยังต้องมีช่องลงชื่อ
    $people = array_values(array_filter($people, fn($x) => $x['name'] !== '' || $x['deps']));
    $dates = array_keys($dates); sort($dates);
    $teams[$tk] = ['people'=>$people, 'dates'=>$dates, 'times'=>array_keys($times), 'named'=>count(array_filter($people, fn($x)=>$x['name']!==''))];
}
$show = $teamSel === 'ALL' ? ['A','B'] : [$teamSel];
$dateHead = function(string $d) use($wd,$mShort){ $t = strtotime($d); return ['wd'=>$wd[(int)date('w',$t)], 'd'=>(int)date('j',$t).' '.$mShort[(int)date('n',$t)].' '.(((int)date('Y',$t)+543)%100)]; };
?>
<!doctype html>
<html lang="th"><head><meta charset="utf-8"><base href="<?=htmlspecialchars(FIVE_S_BASE,ENT_QUOTES)?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title>แบบลงชื่อ Auditor 5ส. - <?=signoff_h($months[$month].' '.$year)?></title><?=five_s_favicon_tags()?>
<link rel="stylesheet" href="assets/vendor/fonts.css">
<style>
:root{--navy:#0E1A2F;--org:#F26A21;--line:#D5DCE6;--mut:#64748B}
*{box-sizing:border-box}
body{margin:0;background:#EEF2F7;color:#0F172A;font-family:"IBM Plex Sans Thai","Inter",Tahoma,sans-serif}
.wrap{max-width:1500px;margin:16px auto;padding:0 14px}
.toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;background:#fff;border:1px solid #E2E8F0;border-radius:14px;padding:12px 14px;margin-bottom:14px}
.toolbar label{display:block;font-size:12.5px;font-weight:600;color:#475569;margin-bottom:4px}
.toolbar select{height:42px;border:1px solid #CBD5E1;border-radius:10px;padding:0 10px;font:600 15px inherit;font-family:inherit;min-width:120px}
.seg{display:inline-flex;background:#F1F5F9;border-radius:11px;padding:4px;gap:4px}
.seg a{padding:8px 14px;border-radius:8px;font-weight:700;color:#475569;text-decoration:none;font-size:14.5px}.seg a.on{background:var(--navy);color:#fff}
.btn{display:inline-flex;align-items:center;gap:6px;height:42px;padding:0 16px;border-radius:10px;border:1px solid #CBD5E1;background:#fff;font-weight:700;font-size:14.5px;cursor:pointer;color:var(--navy);text-decoration:none;font-family:inherit}
.btn.org{background:var(--org);border-color:var(--org);color:#fff}
.sp{flex:1}
.hint{font-size:13px;color:var(--mut);margin:0 2px 12px}
.page{background:#fff;border:1px solid #E2E8F0;border-radius:16px;padding:22px 24px;margin-bottom:18px;box-shadow:0 8px 24px rgba(15,23,42,.06)}
.hd{display:flex;align-items:center;gap:18px;padding-bottom:12px;border-bottom:3px solid var(--org)}
.hd .ttl{flex:1;text-align:center}
.hd h1{margin:0;font-size:24px;font-weight:700;color:var(--navy)}
.hd h1 span{color:var(--org)}
.hd .sub{font:600 11px Inter,sans-serif;letter-spacing:3px;color:var(--mut);margin-top:2px}
.badge{background:var(--navy);color:#fff;border-radius:12px;padding:6px 16px;text-align:center}
.badge small{display:block;font:700 10px Inter,sans-serif;letter-spacing:2px;color:#CBD5E1}.badge b{font:800 28px/1 Inter,sans-serif;color:#F7A21B}
.info{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}
.info div{background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:6px 12px;font-size:13px;color:#334155}
.info b{color:var(--navy);font-size:14.5px}
.info .dts span{display:inline-block;background:#FFF1E7;color:#C2410C;border-radius:6px;padding:1px 8px;margin-left:4px;font-weight:700}
table{width:100%;border-collapse:collapse;table-layout:fixed}
th,td{border:1px solid var(--line)}
thead th{background:var(--navy);color:#fff;font-weight:700;font-size:14px;padding:7px 4px;text-align:center}
thead th.dt{background:#F26A21}
thead th.dt small{display:block;font-weight:600;font-size:11.5px;color:#FFEDD5}
thead th.dt b{font:800 17px Inter,sans-serif}
td{height:46px;padding:4px 6px;vertical-align:middle}
td.no{text-align:center;font:700 14px Inter,sans-serif;color:var(--mut)}
td.nm b{display:block;font-size:15px;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
td.nm small{display:block;font-size:11.5px;color:var(--mut);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
td.sg{position:relative}
td.sg.plan{background:#fff}
td.sg.plan .tag{position:absolute;left:5px;top:3px;font-size:10.5px;font-weight:700;color:#C2410C;white-space:nowrap;overflow:hidden;max-width:calc(100% - 10px);text-overflow:ellipsis}
td.sg.off{background:#fff}
td.sg .ln{position:absolute;left:8px;right:8px;bottom:9px;border-bottom:1.5px dotted #94A3B8}
td.sg.plan .ln{border-bottom:1.5px solid #F59E0B}
td.nt{background:#fff}
tr.blank td.nm{color:#CBD5E1}
.legend{display:flex;gap:16px;flex-wrap:wrap;font-size:12.5px;color:#475569;margin-top:8px}
.legend i{display:inline-block;width:18px;height:12px;border:1px solid var(--line);vertical-align:-1px;margin-right:5px}
.foot{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:14px}
.foot section{border:1px solid #E2E8F0;border-radius:10px;padding:10px 14px;font-size:13px;color:#334155}
.foot .l{margin-top:26px;border-bottom:1px solid #94A3B8}
.foot .s{display:flex;justify-content:space-between;font-size:12px;color:var(--mut);margin-top:4px}
.empty{background:#FEF3C7;border:1px solid #FCD34D;color:#92400E;border-radius:10px;padding:8px 12px;font-size:13px;font-weight:600;margin-bottom:10px}
@media print{
 @page{size:A4 landscape;margin:7mm}
 body{background:#fff}.wrap{max-width:none;margin:0;padding:0}
 .toolbar,.hint,.fs-pt{display:none!important}
 .page{border:0;border-radius:0;box-shadow:none;padding:0;margin:0;break-after:page;page-break-after:always}
 .page:last-child{break-after:auto;page-break-after:auto}
 .page{zoom:var(--z,1)}
 td{height:44px!important;padding:3px 6px!important}
 td.nm b{line-height:1.25}td.nm small{line-height:1.2}
 tr{break-inside:avoid}
 *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
}
</style></head><body>
<?=five_s_print_toolbar('Page/admin/schedule_manage.php?month='.$month.'&year='.$year,'กลับหน้าแผนตรวจ',false)?>
<div class="wrap">
<form class="toolbar" method="get">
 <div><label>เดือน</label><select name="month" onchange="this.form.submit()"><?php foreach($months as $m=>$name):?><option value="<?=$m?>" <?=$month===$m?'selected':''?>><?=signoff_h($name)?></option><?php endforeach;?></select></div>
 <div><label>ปี พ.ศ.</label><select name="year" onchange="this.form.submit()"><?php for($y=2568;$y<=2575;$y++):?><option value="<?=$y?>" <?=$year===$y?'selected':''?>><?=$y?></option><?php endfor;?></select></div>
 <input type="hidden" name="team" value="<?=signoff_h($teamSel)?>">
 <div><label>ทีม</label><div class="seg"><?php foreach(['A'=>'ทีม A','B'=>'ทีม B','ALL'=>'ทั้งสองทีม'] as $k=>$lb):?><a class="<?=$teamSel===$k?'on':''?>" href="<?=h(five_s_self())?>?month=<?=$month?>&year=<?=$year?>&team=<?=$k?>"><?=$lb?></a><?php endforeach;?></div></div>
 <span class="sp"></span>
 <button class="btn org" type="button" onclick="window.print()">🖨 พิมพ์แบบลงชื่อ (A4 แนวนอน)</button>
</form>
<div class="hint">ช่องวันที่สร้างอัตโนมัติจากวันตรวจในแผนของแต่ละทีม (เช่น แผนกำหนด 22, 23, 24 → มี 3 ช่อง) • ให้ผู้ตรวจเซ็นชื่อในช่องวันที่ที่มาตรวจจริง • แก้วันที่ที่หน้าแผนตรวจแล้วใบนี้เปลี่ยนตามเอง</div>
<?php foreach($show as $tk): $T=$teams[$tk]; $dates=$T['dates']?:['']; $nd=count($dates);
  $rows=$T['people']; $pad=max(count($rows)+2, 12); 
  $noW=4; $ntW=$nd>=5?9:12; $nmW=$nd>=6?19:($nd>=4?22:26); $dW=(100-$noW-$ntW-$nmW)/$nd;
  $zoom = round(min(1, 700/(400+$pad*44)),3); ?>
<div class="page" style="--z:<?=$zoom?>">
 <div class="hd"><div><?=five_s_print_logo(34)?></div>
  <div class="ttl"><h1>ใบลงชื่อ Auditor ตรวจ 5 ส. ประจำเดือน <span><?=signoff_h($months[$month].' '.$year)?></span></h1><div class="sub">5S AUDITOR SIGN-IN SHEET</div></div>
  <div class="badge"><small>TEAM</small><b><?=$tk?></b></div></div>
 <div class="info">
  <div>ผู้ตรวจในแผน <b><?=$T['named']?></b> คน</div>
  <div class="dts">วันตรวจตามแผน <b><?=count($T['dates'])?></b> วัน<?php foreach($T['dates'] as $d): $h=$dateHead($d);?><span><?=$h['wd'].' '.$h['d']?></span><?php endforeach;?></div>
  <?php if($T['times']):?><div>เวลา <b><?=signoff_h(implode(', ',$T['times']))?></b></div><?php endif;?>
 </div>
 <?php if(!$T['dates']):?><div class="empty">ยังไม่กำหนดวันตรวจในแผนของทีม <?=$tk?> — ใบนี้จึงมีช่องวันที่ว่าง 1 ช่อง เมื่อกำหนดวันในหน้าแผนตรวจแล้ว ช่องวันที่จะสร้างให้อัตโนมัติ</div><?php endif;?>
 <table><colgroup><col style="width:<?=$noW?>%"><col style="width:<?=$nmW?>%"><?php foreach($dates as $d):?><col style="width:<?=$dW?>%"><?php endforeach;?><col style="width:<?=$ntW?>%"></colgroup>
 <thead><tr><th>ลำดับ</th><th>ชื่อผู้ตรวจ</th><?php foreach($dates as $d): if($d===''):?><th class="dt"><small>วันที่ตรวจ</small><b>___/___/___</b></th><?php else: $h=$dateHead($d);?><th class="dt"><small><?=$h['wd']?></small><b><?=$h['d']?></b></th><?php endif; endforeach;?><th>หมายเหตุ</th></tr></thead>
 <tbody>
 <?php for($i=0;$i<$pad;$i++): $r=$rows[$i]??null; $has=$r && $r['dates']; ?>
  <tr class="<?=$r?'':'blank'?>"><td class="no"><?=$i+1?></td>
  <td class="nm"><?php if($r):?><b><?=signoff_h($r['name'])?></b><?php endif;?></td>
  <?php foreach($dates as $d): $plan=$r && $d!=='' && isset($r['dates'][$d]); $cls=$plan?'plan':(($has && $d!=='')?'off':''); ?>
   <td class="sg <?=$cls?>"></td>
  <?php endforeach;?>
  <td class="nt"></td></tr>
 <?php endfor;?>
 </tbody></table>
  <div class="foot"><section><b>ผู้รับรองการตรวจ / หัวหน้าทีม <?=$tk?></b><div class="l"></div><div class="s"><span>ลงชื่อ</span><span>วันที่ ____/____/______</span></div></section>
 <section><b>ผู้จัดทำ / เลขานุการ 5 ส.</b><div class="l"></div><div class="s"><span>ลงชื่อ</span><span>วันที่ ____/____/______</span></div></section></div>
</div>
<?php endforeach;?>
</div></body></html>
