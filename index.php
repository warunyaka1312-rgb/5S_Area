<?php
require_once __DIR__.'/includes/config.php';
$title='Dashboard 5 ส.';
$p=db();
$department=$_GET['department']??'Production';
if(!in_array($department,['Production','Office'],true))$department='Production';
$view=$_GET['view']??'month';
$year=(int)($_GET['year']??date('Y'));
$month=(int)($_GET['month']??0);
/* ถ้าไม่ได้ระบุเดือน ให้เปิดเดือนล่าสุดที่มีข้อมูลจริงของประเภทที่เลือก
   เพื่อไม่ให้ Dashboard ดูเหมือนข้อมูลหายเมื่อขึ้นเดือนใหม่ */
if(!isset($_GET['month'])){
    $latestQ=$p->prepare("SELECT MAX(audit_date) FROM audits WHERE department=?");
    $latestQ->execute([$department]);
    $latestDate=(string)$latestQ->fetchColumn();
    if($latestDate!==''){
        $year=(int)substr($latestDate,0,4);
        $month=(int)substr($latestDate,5,2);
    }else{
        $month=(int)date('n');
    }
}
if($month<1 || $month>12)$month=(int)date('n');
$months=[1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
$monthName=$months[$month]??$months[(int)date('n')];
$target=target_pct($department);$prodTarget=target_pct('Production');$officeTarget=target_pct('Office');
$critTable=$department==='Office'?'office_criteria':'criteria';
$scoreTable=$department==='Office'?'office_scores':'scores';

/* Same unit list and same score as the overview page, printed graphs and 3-month board. */
function dashboard_stats(PDO $p,string $dept,string $start,string $end):array{
    $m=five_s_month_unit_scores($p,$dept,$start,$end);
    $rows=[];
    foreach($m['rows'] as $r)$rows[]=['group_name'=>$r['label'],'avg'=>$r['pct'],'n'=>1];
    usort($rows,fn($a,$b)=>$b['avg']<=>$a['avg']);
    return ['avg'=>$m['avg']??0,'groups'=>count($rows),'audits'=>count($rows),'rows'=>$rows];
}
$start=sprintf('%04d-%02d-01',$year,$month);$end=date('Y-m-t',strtotime($start));
$prod=dashboard_stats($p,'Production',$start,$end);
$off=dashboard_stats($p,'Office',$start,$end);
$inspection=[
 'Production'=>build_completion($p,'Production',$start,$end,(int)$p->query("SELECT COUNT(*) FROM criteria WHERE active=1")->fetchColumn()),
 'Office'=>build_completion($p,'Office',$start,$end,(int)$p->query("SELECT COUNT(*) FROM office_criteria WHERE active=1")->fetchColumn()),
];
$deptData=$department==='Office'?$off['rows']:$prod['rows'];
$score=$department==='Office'?$off['avg']:$prod['avg'];
$passGroups=0;$lowGroups=0;foreach($deptData as $r){if($r['avg']>=target_pct($department))$passGroups++;else$lowGroups++;}
$trend=[];for($m=1;$m<=12;$m++){ $st=sprintf('%04d-%02d-01',$year,$m);$en=date('Y-m-t',strtotime($st));$z=dashboard_stats($p,$department,$st,$en);$trend[]=$z['avg']?:null;}
// TAG dashboard chart must use the live Tag records, not the imported historical summary table.
// This keeps the chart synchronized with the Receive/Close Tag page after every save, edit, close, or reopen.
$tagOpen=0; $tagClosed=0;
try {
    $tagCounts=$p->query("SELECT SUM(CASE WHEN UPPER(TRIM(status))='CLOSED' OR TRIM(closed_date)<>'' THEN 1 ELSE 0 END) AS closed_count, SUM(CASE WHEN UPPER(TRIM(status))='CLOSED' OR TRIM(closed_date)<>'' THEN 0 ELSE 1 END) AS open_count FROM tags")->fetch(PDO::FETCH_ASSOC) ?: [];
    $tagClosed=(int)($tagCounts['closed_count']??0);
    $tagOpen=(int)($tagCounts['open_count']??0);
} catch (Throwable $e) {
    // If Tag tables are not initialized yet, display zero rather than using unrelated historical summary totals.
    $tagClosed=0; $tagOpen=0;
}
require FIVE_S_ROOT.'/includes/header.php';
?>
<style>
/* ===== V61 Dashboard — international look ===== */
.dash{--p:#F26A21;--p-bg:#FFF1E7;--o:#2563EB;--o-bg:#EAF1FF;--ok:#16A34A;--ok-bg:#E9F8EF;--bad:#DC2626;--bad-bg:#FDEDED;--navy:#0E1A2F;--ink:#0F172A;--muted:#64748B;--line:#E4E8EF}
.dash-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:#fff;border:1px solid var(--line);border-radius:16px;padding:12px 14px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 18px rgba(15,23,42,.05);margin-bottom:16px}
.seg{display:inline-flex;background:#F1F4F8;border-radius:11px;padding:4px}
.seg a{padding:8px 16px;border-radius:8px;font-weight:600;font-size:14px;color:#475569;text-decoration:none;white-space:nowrap}
.seg a.on{background:var(--navy);color:#fff;box-shadow:0 2px 6px rgba(14,26,47,.25)}
.dash-filter{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.fld{display:flex;flex-direction:column;gap:2px}
.fld label{font-size:11px;font-weight:600;color:var(--muted);letter-spacing:.3px;padding-left:2px}
.fld select,.fld input{height:40px;border:1px solid #D5DBE5;border-radius:10px;padding:0 12px;background:#fff;font-weight:600;font-size:14px;color:var(--ink);min-width:120px}
.fld input{width:96px;min-width:0}
.btn-x{display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 16px;border-radius:10px;font-weight:600;font-size:14px;text-decoration:none;cursor:pointer;border:1px solid transparent;white-space:nowrap}
.btn-x .ico{width:16px;height:16px}
.btn-x.primary{background:var(--p);color:#fff;align-self:flex-end}.btn-x.primary:hover{background:#DB5512}
.btn-x.ghost{background:#fff;border-color:#D5DBE5;color:#1E293B}.btn-x.ghost:hover{border-color:var(--p);color:var(--p)}
.dash-actions{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap}
/* department scorecards */
.dept-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.dept{position:relative;border-radius:18px;padding:20px 22px;color:#fff;overflow:hidden;display:grid;grid-template-columns:auto 1fr;gap:22px;align-items:center;text-decoration:none;box-shadow:0 10px 24px rgba(15,23,42,.10);transition:transform .15s}
.dept:hover{transform:translateY(-2px)}
.dept.prod{background:linear-gradient(120deg,#E8540F 0%,#F26A21 45%,#F7A21B 100%)}
.dept.off{background:linear-gradient(120deg,#1E3A8A 0%,#2563EB 55%,#3B82F6 100%)}
.dept.sel{outline:3px solid #0E1A2F;outline-offset:3px}
.dept::after{content:"";position:absolute;right:-50px;top:-50px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.12)}
.ring{--v:0;width:120px;height:120px;border-radius:50%;background:conic-gradient(#fff calc(var(--v)*1%),rgba(255,255,255,.28) 0);box-shadow:0 6px 18px rgba(0,0,0,.15);display:flex;align-items:center;justify-content:center;position:relative;z-index:1}
.ring>div{width:98px;height:98px;border-radius:50%;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center}
.ring b{font:800 25px/1 "Inter",sans-serif;letter-spacing:-.5px}.dept.prod .ring b{color:#C2410C}.dept.off .ring b{color:#1D4ED8}.ring small{font-size:11.5px;color:#64748B;font-weight:600;margin-top:4px}
.dept-info{position:relative;z-index:1}
.dept-info .k{font-size:13px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;opacity:.85}
.dept-info h3{margin:2px 0 10px;font-size:24px;font-weight:700}
.dept-stats{display:flex;gap:8px;flex-wrap:wrap}
.dept-stats span{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.25);border-radius:10px;padding:6px 12px;font-size:13.5px;font-weight:500}
.dept-stats span b{font:800 17px "Inter",sans-serif;margin-right:3px}
.dept-status{display:inline-flex;align-items:center;gap:6px;background:#fff;border-radius:999px;padding:4px 12px;font-size:13px;font-weight:700;margin-left:8px;vertical-align:middle}
.dept.prod .dept-status{color:#C2410C}.dept.off .dept-status{color:#1D4ED8}
/* KPI tiles */
.v14-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px;margin-bottom:16px}
.v14-kpi{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px;display:flex;gap:14px;align-items:center;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 18px rgba(15,23,42,.04)}
.v14-kpi .ico-b{width:48px;height:48px;border-radius:13px;display:flex;align-items:center;justify-content:center;flex:0 0 auto;background:var(--bgc);color:var(--fc)}
.v14-kpi .ico-b .ico{width:24px;height:24px}
.v14-kpi .lbl{font-size:13px;color:var(--muted);font-weight:600;line-height:1.3}
.v14-kpi .val{font:800 30px/1.1 "Inter","IBM Plex Sans Thai",sans-serif;color:var(--ink);letter-spacing:-.5px;margin-top:2px}
.v14-kpi .val.th{font-family:"IBM Plex Sans Thai",sans-serif;font-size:26px;font-weight:700}
.v14-kpi .sub{font-size:12px;color:var(--muted)}
/* cards & charts */
.v14-grid2{display:grid;grid-template-columns:1fr;gap:16px}
.v14-card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:20px 22px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 18px rgba(15,23,42,.05)}
.card-h{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px}
.card-h h2{margin:0;font-size:19px;font-weight:700;color:var(--navy);display:flex;align-items:center;gap:10px}
.card-h p{margin:3px 0 0;font-size:13px;color:var(--muted)}
.badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;letter-spacing:.4px}
.badge.p{background:var(--p-bg);color:#C2410C}.badge.o{background:var(--o-bg);color:#1D4ED8}.badge.n{background:#EEF1F6;color:#334155}
.mini-btns{display:flex;gap:6px}
.mini-btns button{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 12px;border:1px solid #D5DBE5;border-radius:9px;background:#fff;font-size:13px;font-weight:600;color:#334155;cursor:pointer}
.mini-btns button:hover{border-color:var(--p);color:var(--p)}.mini-btns .ico{width:15px;height:15px}
.chart-legend{display:flex;gap:16px;flex-wrap:wrap;font-size:13px;color:#475569;margin:0 0 6px}
.chart-legend span{display:inline-flex;align-items:center;gap:6px}.chart-legend i{width:12px;height:12px;border-radius:3px;display:inline-block}
.v14-chart{position:relative;height:320px}
.v14-grid2 .v14-chart{height:560px}
.v14-grid3{display:grid;grid-template-columns:1.35fr .8fr 1fr;gap:16px;margin-top:16px}
.tag-sum{display:flex;justify-content:center;gap:22px;margin-top:8px;font-size:13.5px;color:#475569}
.tag-sum b{font:800 18px "Inter",sans-serif;margin-right:4px}
.rank{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px}
.rank li{display:grid;grid-template-columns:34px 1fr auto;gap:12px;align-items:center}
.rank .no{width:32px;height:32px;border-radius:10px;display:flex;align-items:center;justify-content:center;font:800 14px "Inter",sans-serif;background:#EEF1F6;color:#334155}
.rank li:nth-child(1) .no{background:#FEF3C7;color:#B45309}.rank li:nth-child(2) .no{background:#E5E7EB;color:#374151}.rank li:nth-child(3) .no{background:#FDE7D6;color:#9A3412}
.rank .nm{font-size:14px;font-weight:600;color:var(--ink);line-height:1.3}
.rank .bar{height:6px;border-radius:9px;background:#EEF1F5;margin-top:5px;overflow:hidden}.rank .bar i{display:block;height:100%;border-radius:9px;background:linear-gradient(90deg,#22C55E,#16A34A)}
.rank .sc{font:800 16px "Inter",sans-serif;color:var(--ok)}
@media(max-width:1280px){.v14-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.v14-grid3{grid-template-columns:1fr 1fr}.v14-grid3>:first-child{grid-column:1/-1}}
@media(max-width:860px){.dept-grid{grid-template-columns:1fr}.dash-actions{margin-left:0}.v14-grid3{grid-template-columns:1fr}}
@media(max-width:560px){.v14-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.dept{grid-template-columns:1fr}.ring{margin:auto}}
</style>
<?php
$__deptCards=[];
foreach(['Production'=>$prod,'Office'=>$off] as $__d=>$__s){
  $__t=target_pct($__d); $__pass=0; foreach($__s['rows'] as $__r){ if($__r['avg']>=$__t) $__pass++; }
  $__deptCards[$__d]=['avg'=>$__s['avg'],'target'=>$__t,'units'=>count($__s['rows']),'pass'=>$__pass,'below'=>count($__s['rows'])-$__pass,'audits'=>$__s['audits']];
}
$__q=fn($d)=>'?view='.urlencode($view).'&department='.$d.'&year='.$year.'&month='.$month;
?>
<div class="v14 dash">
<div class="dash-bar no-print">
 <div class="seg"><a class="<?=$view==='month'?'on':''?>" href="<?=h(five_s_self())?>?view=month&department=<?=$department?>&year=<?=$year?>&month=<?=$month?>">รายเดือน</a><a class="<?=$view==='year'?'on':''?>" href="<?=h(five_s_self())?>?view=year&department=<?=$department?>&year=<?=$year?>&month=<?=$month?>">รายปี</a><a class="<?=$view==='all'?'on':''?>" href="<?=h(five_s_self())?>?view=all&department=<?=$department?>&year=<?=$year?>&month=<?=$month?>">รวมทั้งหมด</a></div>
 <form class="dash-filter" method="get"><input type="hidden" name="view" value="<?=h($view)?>">
  <div class="fld"><label>ประเภท</label><select name="department"><option value="Production" <?=$department==='Production'?'selected':''?>>Production</option><option value="Office" <?=$department==='Office'?'selected':''?>>Office</option></select></div>
  <div class="fld"><label>เดือน</label><select name="month"><?php foreach($months as $m=>$n):?><option value="<?=$m?>" <?=$month===$m?'selected':''?>><?=$n?></option><?php endforeach;?></select></div>
  <div class="fld"><label>ปี (ค.ศ.)</label><input name="year" type="number" value="<?=$year?>"></div>
  <button class="btn-x primary"><?=five_s_icon('search')?> แสดงข้อมูล</button>
 </form>
 <div class="dash-actions">
  <a class="btn-x ghost" href="Page/public/graph_print.php?department=Production&view=month&month=<?=urlencode($monthName)?>&year=<?=$year?>&chart=split&size=A4" target="_blank"><?=five_s_icon('printer')?> Production</a>
  <a class="btn-x ghost" href="Page/public/graph_print.php?department=Office&view=month&month=<?=urlencode($monthName)?>&year=<?=$year?>&chart=split&size=A4" target="_blank"><?=five_s_icon('printer')?> Office</a>
  <a class="btn-x ghost" href="Page/admin/backup.php"><?=five_s_icon('database')?> Backup</a>
  <a class="btn-x ghost" href="Page/admin/settings.php"><?=five_s_icon('target')?> เป้าหมาย</a>
 </div>
</div>

<div class="dept-grid">
<?php foreach($__deptCards as $__d=>$__c): $__ok=$__c['avg']>=$__c['target']; ?>
 <a class="dept <?=$__d==='Production'?'prod':'off'?> <?=$department===$__d?'sel':''?>" href="<?=h($__q($__d))?>">
  <div class="ring" style="--v:<?=max(0,min(100,(float)$__c['avg']))?>"><div><b><?=number_format($__c['avg'],1)?>%</b><small>คะแนนเฉลี่ย</small></div></div>
  <div class="dept-info">
   <div class="k"><?=$__d==='Production'?'Production • ฝ่ายผลิต':'Office • สำนักงาน'?></div>
   <h3><?=h($monthName)?> <?=$year+543?> <span class="dept-status"><?=$__c['units']?($__ok?'✓ ผ่านเป้าหมาย':'! ต่ำกว่าเป้าหมาย'):'ยังไม่มีข้อมูล'?></span></h3>
   <div class="dept-stats"><span><b><?=number_format($__c['target'],0)?>%</b>เป้าหมาย</span><span><b><?=$__c['units']?></b>หน่วยที่ตรวจ</span><span><b><?=$__c['pass']?></b>ผ่าน</span><span><b><?=$__c['below']?></b>ต่ำกว่าเป้า</span></div>
  </div>
 </a>
<?php endforeach; ?>
</div>

<div class="v14-card" id="inspectionStatus" style="margin-bottom:16px">
 <div class="card-h"><div><h2>สถานะการตรวจ — <?=h($monthName)?> <?=$year+543?></h2><p>นับจากผลตรวจที่บันทึกจริง • ตรวจครบทุกหัวข้อ (N/A นับว่าตรวจแล้ว)</p></div><a class="btn-x ghost no-print" href="Page/public/completion_summary.php?month=<?=$month?>&year=<?=$year+543?>">ดูรายละเอียด</a></div>
 <?php foreach($inspection as $__d=>$__c):?><div class="dept-stats" style="margin-top:8px;align-items:center"><b style="min-width:96px"><?=h($__d)?></b><span style="background:#E9F8EF;border-color:#BBF7D0;color:#166534" data-k="done"><b><?=$__c['audited_units']?></b>ตรวจแล้ว</span><span style="background:#FEF3C7;border-color:#FDE68A;color:#92400E" data-k="partial"><b><?=$__c['partial_units']?></b>กำลังตรวจ</span><span style="background:#FDEDED;border-color:#FECACA;color:#991B1B" data-k="none"><b><?=$__c['missing_units']?></b>ยังไม่ได้ตรวจ</span><span style="background:#F1F5F9;border-color:#E2E8F0;color:#334155" data-k="total"><b><?=$__c['expected_units']?></b>หน่วยทั้งหมด</span></div><?php endforeach;?>
</div>

<div class="v14-kpis">
 <div class="v14-kpi" style="--bgc:#EEF2FF;--fc:#4F46E5"><div class="ico-b"><?=five_s_icon('list')?></div><div><div class="lbl">หัวข้อตรวจ</div><div class="val"><?=($department==='Production'?35:21)?></div><div class="sub">ข้อ • <?=h($department)?></div></div></div>
 <div class="v14-kpi" style="--bgc:#E0F2FE;--fc:#0284C7"><div class="ico-b"><?=five_s_icon('map')?></div><div><div class="lbl">หน่วยที่มีข้อมูล</div><div class="val"><?=count($deptData)?></div><div class="sub">หน่วย</div></div></div>
 <div class="v14-kpi" style="--bgc:#FFF1E7;--fc:#EA580C"><div class="ico-b"><?=five_s_icon('trend')?></div><div><div class="lbl">คะแนนเฉลี่ยรวม</div><div class="val"><?=number_format($score,2)?>%</div><div class="sub">เป้าหมาย <?=number_format($target,1)?>%</div></div></div>
 <div class="v14-kpi" style="--bgc:#E9F8EF;--fc:#16A34A"><div class="ico-b"><?=five_s_icon('check')?></div><div><div class="lbl">ผ่านเป้าหมาย</div><div class="val" style="color:#16A34A"><?=$passGroups?></div><div class="sub">หน่วย</div></div></div>
 <div class="v14-kpi" style="--bgc:#FDEDED;--fc:#DC2626"><div class="ico-b"><?=five_s_icon('bolt')?></div><div><div class="lbl">ต่ำกว่าเป้าหมาย</div><div class="val" style="color:#DC2626"><?=$lowGroups?></div><div class="sub">หน่วย</div></div></div>
 <div class="v14-kpi" style="--bgc:#F1F5F9;--fc:#334155"><div class="ico-b"><?=five_s_icon('calendar')?></div><div><div class="lbl">ข้อมูลล่าสุด</div><div class="val th"><?=h($monthName)?></div><div class="sub"><?=$year+543?></div></div></div>
</div>

<div class="v14-grid2">
 <div class="v14-card">
  <div class="card-h"><div><h2><span class="badge p">PRODUCTION</span>คะแนนรายหน่วย — <?=$monthName?> <?=$year+543?></h2><p>เรียงจากคะแนนสูงไปต่ำ • เส้นประสีแดง = เป้าหมาย <?=number_format($prodTarget,0)?>%</p></div><div class="mini-btns no-print"><button onclick="copyChart('prod','Production')"><?=five_s_icon('file')?> คัดลอกรูปส่ง LINE</button></div></div>
  <div class="chart-legend"><span><i style="background:#16A34A"></i>ผ่านเป้าหมาย</span><span><i style="background:#DC2626"></i>ต่ำกว่าเป้าหมาย</span><span><i style="background:transparent;border-top:3px dashed #DC2626;height:0;width:18px;border-radius:0"></i>เป้าหมาย</span></div>
  <div class="v14-chart"><canvas id="prod"></canvas></div>
 </div>
 <div class="v14-card">
  <div class="card-h"><div><h2><span class="badge o">OFFICE</span>คะแนนรายหน่วย — <?=$monthName?> <?=$year+543?></h2><p>เรียงจากคะแนนสูงไปต่ำ • เส้นประสีแดง = เป้าหมาย <?=number_format($officeTarget,0)?>%</p></div><div class="mini-btns no-print"><button onclick="copyChart('office','Office')"><?=five_s_icon('file')?> คัดลอกรูปส่ง LINE</button></div></div>
  <div class="chart-legend"><span><i style="background:#16A34A"></i>ผ่านเป้าหมาย</span><span><i style="background:#DC2626"></i>ต่ำกว่าเป้าหมาย</span><span><i style="background:transparent;border-top:3px dashed #DC2626;height:0;width:18px;border-radius:0"></i>เป้าหมาย</span></div>
  <div class="v14-chart"><canvas id="office"></canvas></div>
 </div>
</div>
<div class="v14-grid3">
 <div class="v14-card"><div class="card-h"><div><h2><span class="badge n"><?=h(strtoupper($department))?></span>แนวโน้มคะแนนรายเดือน <?=$year+543?></h2><p>คะแนนเฉลี่ยแต่ละเดือนเทียบเป้าหมาย</p></div></div><div class="v14-chart"><canvas id="trend"></canvas></div></div>
 <div class="v14-card"><div class="card-h"><div><h2>สถานะ TAG 5 ส.</h2><p>อัปเดตจากหน้ารับ/ปิด TAG</p></div></div><div class="v14-chart" style="height:250px"><canvas id="tag"></canvas></div><div class="tag-sum"><span><b style="color:#16A34A"><?=$tagClosed?></b>ปิดจบ</span><span><b style="color:#DC2626"><?=$tagOpen?></b>ยังไม่ปิด</span></div></div>
 <div class="v14-card"><div class="card-h"><div><h2>5 อันดับคะแนนสูงสุด</h2><p><?=h($department)?> • <?=h($monthName)?> <?=$year+543?></p></div></div>
  <?php if($deptData): ?><ol class="rank"><?php foreach(array_slice($deptData,0,5) as $i=>$r):?><li><span class="no"><?=$i+1?></span><div><div class="nm"><?=h(str_replace(' / ',' • ',$r['group_name']))?></div><div class="bar"><i style="width:<?=max(0,min(100,$r['avg']))?>%"></i></div></div><span class="sc"><?=number_format($r['avg'],2)?>%</span></li><?php endforeach;?></ol><?php else:?><p style="color:#64748B">ยังไม่มีข้อมูลในเดือนนี้</p><?php endif;?>
 </div>
</div>
</div>
<script>
Chart.defaults.font.family='"IBM Plex Sans Thai","Inter",Tahoma,sans-serif';Chart.defaults.color='#475569';
const PROD_TARGET=<?=json_encode($prodTarget)?>, OFFICE_TARGET=<?=json_encode($officeTarget)?>; const chartRefs={};
const prod=<?=json_encode($prod['rows'],JSON_UNESCAPED_UNICODE)?>,off=<?=json_encode($off['rows'],JSON_UNESCAPED_UNICODE)?>,trend=<?=json_encode($trend)?>;
function makeBar(id,rows,target){
  const canvas=document.getElementById(id);
  const wrap=canvas.parentElement;
  // Make each dashboard graph large enough to show every unit clearly.
  const dynamicHeight=Math.max(360, Math.min(1500, rows.length*32+90));
  wrap.style.height=dynamicHeight+'px';
  const labels=rows.map(x=>x.group_name);
  const values=rows.map(x=>Number(x.avg));
  const ok=x=>Number(x.avg)>=Number(target);
  const grad=(c,a,b)=>{const ctx=c.getContext('2d'),g=ctx.createLinearGradient(0,0,c.parentElement.clientWidth||800,0);g.addColorStop(0,a);g.addColorStop(1,b);return g;};
  const gOk=grad(canvas,'#4ADE80','#15803D'), gBad=grad(canvas,'#F87171','#B91C1C');
  const colors=rows.map(x=>ok(x)?gOk:gBad);
  const borders=rows.map(x=>ok(x)?'#16A34A':'#DC2626');
  chartRefs[id]=new Chart(canvas,{
    type:'bar',
    data:{
      labels:labels,
      datasets:[
        {label:'คะแนนเฉลี่ย %',data:values,backgroundColor:colors,borderColor:borders,borderWidth:0,borderRadius:{topRight:8,bottomRight:8,topLeft:0,bottomLeft:0},borderSkipped:false,barThickness:20,maxBarThickness:22},
        {label:'เป้าหมาย '+target+'%',type:'line',data:rows.map(()=>target),borderColor:'#DC2626',borderDash:[6,5],borderWidth:2.5,pointRadius:0}
      ]
    },
    options:{
      indexAxis:'y',
      responsive:true,
      maintainAspectRatio:false,
      layout:{padding:{left:4,right:24,top:8,bottom:8}},
      scales:{
        x:{min:0,max:100,border:{display:false},grid:{color:'rgba(148,163,184,.18)'},ticks:{font:{size:12,weight:'500'},color:'#64748B',callback:v=>v+'%'}},
        y:{border:{display:false},grid:{display:false},ticks:{font:{size:13.5,weight:'600'},color:'#1E293B',padding:10,autoSkip:false,callback:function(value){
          return String(this.getLabelForValue(value)).replace(' / ',' • ');
        }},afterFit:axis=>{axis.width=Math.min(260,Math.max(axis.width,170));}}
      },
      plugins:{
        legend:{display:false},
        tooltip:{callbacks:{
          title:function(items){return items.length?items[0].label:'';},
          label:function(ctx){
            if(ctx.datasetIndex===1) return ' เป้าหมาย '+Number(target).toFixed(1)+'%';
            return ' คะแนนเฉลี่ย '+Number(ctx.parsed.x).toFixed(2)+'%';
          }
        }}
      }
    },
    plugins:[{
      id:'labels_'+id,
      afterDatasetsDraw(ch){
        const ctx=ch.ctx,meta=ch.getDatasetMeta(0),d=ch.data.datasets[0];
        ctx.save();ctx.font='700 13px Inter, sans-serif';ctx.textAlign='left';ctx.textBaseline='middle';
        meta.data.forEach((b,i)=>{const v=Number(d.data[i]),t=v.toFixed(2)+'%',w=b.x-b.base;if(w>90){ctx.textAlign='right';ctx.fillStyle='#fff';ctx.shadowColor='rgba(0,0,0,.25)';ctx.shadowBlur=2;ctx.fillText(t,b.x-10,b.y);ctx.shadowBlur=0;}else{ctx.textAlign='left';ctx.fillStyle=v>=Number(target)?'#15803D':'#B91C1C';ctx.fillText(t,b.x+8,b.y);}});
        ctx.restore();
      }
    }]
  });
}
makeBar('prod',prod,PROD_TARGET);makeBar('office',off,OFFICE_TARGET);
function canvasWithHeading(source,title,subtitle=''){
  if(!source || !source.width || !source.height) throw new Error('ไม่พบภาพกราฟ');
  const out=document.createElement('canvas');
  const w=source.width, pad=Math.max(18,Math.round(w*0.024));
  const titleSize=Math.max(19,Math.min(34,Math.round(w/34)));
  const subSize=Math.max(12,Math.min(21,Math.round(w/58)));
  const measure=document.createElement('canvas').getContext('2d');
  const maxW=w-pad*2;
  const wrap=(text,font)=>{
    measure.font=font; const lines=[]; let line='';
    String(text||'').split(/\s+/).forEach(word=>{
      const candidate=line?line+' '+word:word;
      if(line && measure.measureText(candidate).width>maxW){lines.push(line);line=word;}else{line=candidate;}
    });
    if(line)lines.push(line); return lines;
  };
  const titleLines=wrap(title,'900 '+titleSize+'px "IBM Plex Sans Thai", Tahoma, Arial, sans-serif');
  const subLines=subtitle?wrap(subtitle,'600 '+subSize+'px "IBM Plex Sans Thai", Tahoma, Arial, sans-serif'):[];
  const titleLineH=Math.round(titleSize*1.25), subLineH=Math.round(subSize*1.35);
  const headH=Math.max(82,18+titleLines.length*titleLineH+(subLines.length?8+subLines.length*subLineH:0)+20);
  out.width=w;out.height=source.height+headH;
  const ctx=out.getContext('2d');
  ctx.fillStyle='#ffffff';ctx.fillRect(0,0,out.width,out.height);
  ctx.fillStyle='#F26A21';ctx.fillRect(0,0,out.width,9);
  ctx.fillStyle='#13213a';ctx.textBaseline='top';ctx.font='900 '+titleSize+'px "IBM Plex Sans Thai", Tahoma, Arial, sans-serif';
  let y=17;titleLines.forEach(line=>{ctx.fillText(line,pad,y,maxW);y+=titleLineH;});
  if(subLines.length){y+=4;ctx.fillStyle='#64748b';ctx.font='600 '+subSize+'px "IBM Plex Sans Thai", Tahoma, Arial, sans-serif';subLines.forEach(line=>{ctx.fillText(line,pad,y,maxW);y+=subLineH;});}
  ctx.strokeStyle='#dbe3ee';ctx.lineWidth=2;ctx.beginPath();ctx.moveTo(pad,headH-4);ctx.lineTo(w-pad,headH-4);ctx.stroke();
  ctx.drawImage(source,0,headH);
  return out;
}

function chartImageWithHeading(id,name){
  const chart=chartRefs[id];if(!chart||!chart.canvas)throw new Error('ไม่พบกราฟ '+name);
  const card=chart.canvas.closest('.v14-card');const heading=card?card.querySelector('h2'):null;
  const title=heading?heading.textContent.trim():('กราฟ '+name+' — <?=h($monthName)?> <?=$year?>');
  return canvasWithHeading(chart.canvas,title,'5S SMART | ALUMET • คะแนนเฉลี่ยและเป้าหมาย '+(name==='Office'?OFFICE_TARGET:PROD_TARGET)+'%');
}
function downloadChart(id,name){try{const image=chartImageWithHeading(id,name),a=document.createElement('a');a.href=image.toDataURL('image/png',1);a.download='5S_'+name+'_<?=h($monthName)?>_<?=$year?>_พร้อมหัวข้อ.png';document.body.appendChild(a);a.click();a.remove();}catch(e){alert('ไม่สามารถบันทึกกราฟพร้อมหัวข้อได้: '+e.message);}}
function copyChart(id,name){FiveSCopy.copyFrom(()=>chartImageWithHeading(id,name),'5S_'+name+'_<?=h($monthName)?>_<?=$year?>.png');}

const trendLabels=<?=json_encode(array_values($months),JSON_UNESCAPED_UNICODE)?>;
const trendTarget=(<?=json_encode($department)?>==='Office'?OFFICE_TARGET:PROD_TARGET);
const trendHasData=trend.filter(v=>v!==null).length;
new Chart(document.getElementById('trend'),{type:'line',data:{labels:trendLabels,datasets:[{label:'คะแนนเฉลี่ย %',data:trend,borderColor:'#F26A21',borderWidth:3,backgroundColor:(c)=>{const a=c.chart.chartArea;if(!a)return 'rgba(242,106,33,.12)';const g=c.chart.ctx.createLinearGradient(0,a.top,0,a.bottom);g.addColorStop(0,'rgba(242,106,33,.28)');g.addColorStop(1,'rgba(242,106,33,0)');return g;},fill:true,tension:.35,spanGaps:false,pointRadius:trend.map(v=>v===null?0:5),pointHoverRadius:7,pointBackgroundColor:'#fff',pointBorderColor:'#F26A21',pointBorderWidth:2.5},{label:'เป้าหมาย '+trendTarget+'%',data:Array(12).fill(trendTarget),borderColor:'#DC2626',borderDash:[6,5],borderWidth:2,pointRadius:0}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},scales:{y:{min:50,max:100,border:{display:false},grid:{color:'rgba(148,163,184,.18)'},ticks:{callback:v=>v+'%'}},x:{grid:{display:false},ticks:{maxRotation:0,autoSkip:true,callback:function(v){return ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][v]}}}},plugins:{legend:{labels:{usePointStyle:true,boxWidth:8,font:{size:12,weight:'600'}}},title:{display:true,text:'มีข้อมูล '+trendHasData+' จาก 12 เดือน',font:{size:12,weight:'500'},color:'#64748b',padding:{bottom:4}},tooltip:{callbacks:{label:function(ctx){return ctx.datasetIndex===1?' เป้าหมาย '+trendTarget.toFixed(1)+'%':(ctx.parsed.y===null?' ไม่มีข้อมูล':' คะแนนเฉลี่ย '+Number(ctx.parsed.y).toFixed(2)+'%');}}}}},plugins:[{id:'trendValueLabels',afterDatasetsDraw:function(chart){const ctx=chart.ctx,meta=chart.getDatasetMeta(0),data=chart.data.datasets[0].data;ctx.save();ctx.font='700 11px Inter, sans-serif';ctx.fillStyle='#C2410C';ctx.textAlign='center';meta.data.forEach((pt,i)=>{const v=data[i];if(v!==null){ctx.fillText(Number(v).toFixed(1)+'%',pt.x,pt.y-10);}});ctx.restore();}}]});
new Chart(document.getElementById('tag'),{type:'doughnut',data:{labels:['ปิดจบ','ยังไม่ปิด'],datasets:[{data:[<?=json_encode($tagClosed)?>,<?=json_encode($tagOpen)?>],backgroundColor:['#16A34A','#DC2626'],borderWidth:4,borderColor:'#fff',hoverOffset:6}]},options:{responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{display:false},tooltip:{callbacks:{label:(ctx)=>' '+ctx.label+': '+Number(ctx.raw).toLocaleString('th-TH')+' รายการ'}}}},plugins:[{id:'tagCenter',afterDraw(chart){const {ctx,chartArea:a}=chart;const d=chart.data.datasets[0].data;const tot=d[0]+d[1];const pct=tot?Math.round(d[0]/tot*100):0;ctx.save();ctx.textAlign='center';ctx.textBaseline='middle';const cx=(a.left+a.right)/2,cy=(a.top+a.bottom)/2;ctx.fillStyle='#0F172A';ctx.font='800 30px Inter, sans-serif';ctx.fillText(pct+'%',cx,cy-8);ctx.fillStyle='#64748B';ctx.font='500 12px "IBM Plex Sans Thai", sans-serif';ctx.fillText('ปิดแล้ว '+d[0]+'/'+tot,cx,cy+18);ctx.restore();}}]});
</script>
<?php require FIVE_S_ROOT.'/includes/footer.php';?>
