<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
$title='ผู้ตรวจ / ประวัติการตรวจ 12 เดือน';
$p=db();
$year=(int)($_GET['year']??(date('Y')+543)); if($year<2500||$year>2700)$year=date('Y')+543;
$team=(string)($_GET['team']??'A'); if(!in_array($team,['A','B','ALL'],true))$team='A';
$months=['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

/* V37: 1 เดือน = 4 รอบตรวจ. เก็บแยกรอบ 1-4 เพื่อไม่ทำลายข้อมูลเดิม */
$p->exec("CREATE TABLE IF NOT EXISTS auditor_12month_rounds(
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 history_year INTEGER NOT NULL,
 history_month INTEGER NOT NULL,
 team TEXT NOT NULL,
 auditor_name TEXT NOT NULL,
 round_no INTEGER NOT NULL,
 checked INTEGER NOT NULL DEFAULT 0,
 inspection_date TEXT NOT NULL DEFAULT '',
 department TEXT NOT NULL DEFAULT '',
 updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(history_year,history_month,team,auditor_name,round_no)
)");

function back12(int $y,string $t,string $m='saved'):never{header('Location: auditor_12month.php?year='.$y.'&team='.urlencode($t).'&msg='.urlencode($m));exit;}
$msg='';$err='';
try{
  // Migrate old V36 monthly counts into round 1-4 once, for continuity.
  $p->exec("CREATE TABLE IF NOT EXISTS auditor_12month_migrated(flag INTEGER PRIMARY KEY CHECK(flag=1))");
  $has=$p->query("SELECT 1 FROM auditor_12month_migrated WHERE flag=1 LIMIT 1")->fetchColumn();
  if(!$has){
    $old=$p->query("SELECT history_year,history_month,team,auditor_name,inspection_count,inspection_dates,departments FROM auditor_monthly_history");
    $ins=$p->prepare("INSERT OR IGNORE INTO auditor_12month_rounds(history_year,history_month,team,auditor_name,round_no,checked,inspection_date,department) VALUES(?,?,?,?,?,?,?,?)");
    foreach($old as $r){$cnt=max(0,min(4,(int)$r['inspection_count']));$dates=array_values(array_filter(array_map('trim',explode(',',$r['inspection_dates']??''))));$deps=array_values(array_filter(array_map('trim',explode(',',$r['departments']??''))));for($i=1;$i<=4;$i++)$ins->execute([(int)$r['history_year'],(int)$r['history_month'],$r['team'],$r['auditor_name'],$i,$i<=$cnt?1:0,$dates[$i-1]??'',$deps[$i-1]??'']);}
    $p->exec("INSERT INTO auditor_12month_migrated(flag) VALUES(1)");
  }
}catch(Throwable $e){$err=$e->getMessage();}

/**
 * V38: ชื่อผู้ตรวจใช้ audit_plans (แผนตรวจ 5 ส.) เป็น Master เท่านั้น
 * ไม่สร้างชื่อใหม่จากหน้านี้ และไม่แก้ชื่อจากประวัติแยกอีกต่อไป
 * 1 เดือน = 4 รอบ โดยเรียงจากวันที่ตรวจจริงในแผนตรวจ (R1-R4)
 */
/** V66: เดือนที่กด "สลับคนตรวจ A ⇄ B" ให้นับผู้ตรวจตามทีมประจำของตัวเอง (ทีมตรงข้ามของแถวแผน) */
function a12_team_cond(int $y, string $t): array {
    $sw=[];
    for($m=1;$m<=12;$m++) if(app_setting('schedule_swapped_'.$y.'_'.$m,'0')==='1') $sw[]=$m;
    if(!$sw) return ['team=?',[$t]];
    $o=$t==='A'?'B':'A'; $in=implode(',',$sw);
    return ["((team=? AND month NOT IN ($in)) OR (team=? AND month IN ($in)))",[$t,$o]];
}
function syncAuditorsFromSchedule(PDO $p, int $y, string $t): void {
    if (!in_array($t, ['A','B'], true)) return;
    $sql = "SELECT id,month,TRIM(auditor_name) auditor_name,TRIM(inspection_date) inspection_date,TRIM(department) department
            FROM audit_plans
            WHERE active=1 AND year=? AND {TC} AND TRIM(auditor_name)<>'' AND month BETWEEN 1 AND 12
            ORDER BY month, id";
    [$tc,$tp]=a12_team_cond($y,$t); $sql=str_replace('{TC}',$tc,$sql);
    $q=$p->prepare($sql); $q->execute(array_merge([$y],$tp));
    $by=[];
    foreach($q as $r){
        $m=(int)$r['month']; $name=trim((string)$r['auditor_name']);
        if($name==='') continue;
        $key=$m.'|'.$name;
        if(!isset($by[$key])) $by[$key]=[];
        $date=trim((string)$r['inspection_date']);
        // 1 วันตรวจ = 1 รอบ; หลายแผนกในวันเดียวกันไม่ให้นับซ้ำ
        $found=false;
        foreach($by[$key] as $x) if($x['date']===$date){
            if($x['department']==='' && trim((string)$r['department'])!=='') $x['department']=trim((string)$r['department']);
            $found=true; break;
        }
        if(!$found && count($by[$key])<4) $by[$key][]=['date'=>$date,'department'=>trim((string)$r['department'])];
    }
    $ensure=$p->prepare("INSERT OR IGNORE INTO auditor_12month_rounds(history_year,history_month,team,auditor_name,round_no,checked,inspection_date,department) VALUES(?,?,?,?,?,0,'','')");
    $up=$p->prepare("UPDATE auditor_12month_rounds SET inspection_date=?,department=?,updated_at=CURRENT_TIMESTAMP WHERE history_year=? AND history_month=? AND team=? AND auditor_name=? AND round_no=?");
    foreach($by as $key=>$rounds){
        [$mm,$name]=explode('|',$key,2); $mm=(int)$mm;
        for($r=1;$r<=4;$r++){
            $ensure->execute([$y,$mm,$t,$name,$r]);
            $row=$rounds[$r-1]??['date'=>'','department'=>''];
            $up->execute([$row['date'],$row['department'],$y,$mm,$t,$name,$r]);
        }
    }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $a=(string)($_POST['action']??'');$y=(int)($_POST['year']??$year);$t=(string)($_POST['team']??$team);
  if(!in_array($t,['A','B','ALL'],true))$t='A';
  if($a==='sync'){
    if($t==='ALL') { syncAuditorsFromSchedule($p,$y,'A'); syncAuditorsFromSchedule($p,$y,'B'); }
    else syncAuditorsFromSchedule($p,$y,$t);
    back12($y,$t,'sync');
  }
  if($a==='toggle'){
    if($t==='ALL')throw new Exception('เลือกทีม A หรือ B ก่อนแก้ไข');
    $n=trim((string)($_POST['name']??''));$m=(int)($_POST['month']??0);$r=(int)($_POST['round']??0);$c=(int)($_POST['status']??($_POST['checked']??0)); if(!in_array($c,[0,1,2,3],true))$c=0;
    if($n===''||$m<1||$m>12||$r<1||$r>4)throw new Exception('ข้อมูลรอบตรวจไม่ถูกต้อง');
    [$tc,$tp]=a12_team_cond($y,$t);$chk=$p->prepare("SELECT 1 FROM audit_plans WHERE active=1 AND year=? AND $tc AND TRIM(auditor_name)=? LIMIT 1");$chk->execute(array_merge([$y],$tp,[$n]));
    if(!$chk->fetchColumn()) throw new Exception('ไม่พบชื่อผู้ตรวจในแผนตรวจ 5 ส.');
    $q=$p->prepare("INSERT INTO auditor_12month_rounds(history_year,history_month,team,auditor_name,round_no,checked,updated_at) VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(history_year,history_month,team,auditor_name,round_no) DO UPDATE SET checked=excluded.checked,updated_at=CURRENT_TIMESTAMP");$q->execute([$y,$m,$t,$n,$r,$c]);
    $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (($_POST['ajax'] ?? '') === '1');
    if($isAjax){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true,'status'=>$c],JSON_UNESCAPED_UNICODE);exit;}
    back12($y,$t);
  }
 }catch(Throwable $e){
   if((($_POST['ajax']??'')==='1')){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);exit;}
   $err=$e->getMessage();
 }
}

/* ---------- V64: อัตโนมัติ — ซิงก์รายชื่อ/วันตรวจจากแผนตรวจ และคำนวณ มา/ไม่มา จากผลตรวจจริง ---------- */
$teams = $team==='ALL' ? ['A','B'] : [$team];
foreach($teams as $tt) syncAuditorsFromSchedule($p,$year,$tt);

$normName = static fn(string $s): string => preg_replace('/\s+/u',' ',trim($s));
$isoDate = static function(string $v): string { return function_exists('five_s_plan_date_iso') ? five_s_plan_date_iso($v) : (preg_match('/^\d{4}-\d{2}-\d{2}/',$v)?substr($v,0,10):''); };
$today = date('Y-m-d');

// หลักฐานการตรวจ 1) แผนตรวจที่สถานะ ตรวจแล้ว/ตรวจบางส่วน 2) ใบตรวจในระบบที่มีชื่อผู้ตรวจในวันนั้น
$evidence = [];
$q=$p->prepare("SELECT auditor_name,inspection_date,status FROM audit_plans WHERE active=1 AND year=? AND TRIM(auditor_name)<>''");$q->execute([$year]);
foreach($q as $r){ $d=$isoDate((string)$r['inspection_date']); $s=trim((string)$r['status']);
  if($d!=='' && ($s==='ตรวจแล้ว' || $s==='ตรวจบางส่วน')) $evidence[$normName((string)$r['auditor_name']).'|'.$d]=true; }
$ce=$year-543;
$q=$p->prepare("SELECT audit_date,auditor,auditor2,auditor3 FROM audits WHERE audit_date BETWEEN ? AND ?");$q->execute([$ce.'-01-01',$ce.'-12-31']);
foreach($q as $r){ $d=substr((string)$r['audit_date'],0,10); foreach(['auditor','auditor2','auditor3'] as $k){ $n=$normName((string)$r[$k]); if($n!=='') $evidence[$n.'|'.$d]=true; } }

// รายชื่อ (Master = แผนตรวจ 5 ส.)
$rows=[];
foreach($teams as $tt){
  [$tc,$tp]=a12_team_cond($year,$tt);$q=$p->prepare("SELECT DISTINCT TRIM(auditor_name) n FROM audit_plans WHERE active=1 AND year=? AND $tc AND TRIM(auditor_name)<>''");$q->execute(array_merge([$year],$tp));
  $ns=array_values(array_unique(array_map('trim',$q->fetchAll(PDO::FETCH_COLUMN)))); sort($ns,SORT_NATURAL|SORT_FLAG_CASE);
  foreach($ns as $n) $rows[]=['team'=>$tt,'name'=>$n];
}
// รอบตรวจ R1-R4
$cells=[];
$q=$p->prepare("SELECT team,auditor_name,history_month,round_no,checked,inspection_date,department FROM auditor_12month_rounds WHERE history_year=?");$q->execute([$year]);
foreach($q as $r){ $cells[$r['team']][$r['auditor_name']][(int)$r['history_month']][(int)$r['round_no']]=['m'=>(int)$r['checked'],'d'=>$isoDate((string)$r['inspection_date']),'dep'=>trim((string)$r['department'])]; }

/* สถานะอัตโนมัติ: 1=มาตรวจ 2=ไม่พบผลตรวจ(เลยกำหนด) 4=มีแผน(ยังไม่ถึงวัน) 0=ไม่มีแผน */
$autoOf = function(string $name,string $d) use($evidence,$today,$normName): int {
  if($d==='') return 0;
  if(isset($evidence[$normName($name).'|'.$d])) return 1;
  return $d < $today ? 2 : 4;
};
$monthOk=array_fill(1,12,0);$monthNo=array_fill(1,12,0);$monthPlan=array_fill(1,12,0);
$grid=[];
foreach($rows as $i=>$row){
  $ok=0;$no=0;$plan=0;
  for($m=1;$m<=12;$m++) for($r=1;$r<=4;$r++){
    $c=$cells[$row['team']][$row['name']][$m][$r]??['m'=>0,'d'=>'','dep'=>''];
    $auto=$autoOf($row['name'],$c['d']);
    $man=$c['m'];
    $show = $man===1 ? 1 : ($man===2 ? 2 : ($man===3 ? 0 : $auto));
    $grid[$i][$m][$r]=['auto'=>$auto,'man'=>$man,'show'=>$show,'d'=>$c['d'],'dep'=>$c['dep']];
    if($c['d']!=='' || $man===1 || $man===2) { $plan++; $monthPlan[$m]++; }
    if($show===1){$ok++;$monthOk[$m]++;} elseif($show===2){$no++;$monthNo[$m]++;}
  }
  $rows[$i]+=['ok'=>$ok,'no'=>$no,'plan'=>$plan,'pct'=>($ok+$no)?round($ok/($ok+$no)*100,1):null];
}
$sumOk=array_sum($monthOk);$sumNo=array_sum($monthNo);$sumPlan=array_sum($monthPlan);
$rate=($sumOk+$sumNo)?round($sumOk/($sumOk+$sumNo)*100,1):0;
$curM = ((int)date('Y')+543)===$year ? (int)date('n') : 0;
$rank=$rows; usort($rank,fn($a,$b)=>[$b['ok'],$a['no']]<=>[$a['ok'],$b['no']]);
$teamLabel=$team==='ALL'?'ทุกทีม (A + B)':'ทีม '.$team;
require __DIR__.'/includes/header.php';
?>
<style>
.a12{--navy:#0E1A2F;--org:#F26A21;--ok:#16A34A;--no:#DC2626;--plan:#2563EB;--line:#E4E8EF;font-size:15px}
.a12-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;background:linear-gradient(115deg,#0E1A2F 0%,#16305C 70%,#1d3d73 100%);color:#fff;border-radius:20px;padding:22px 26px;margin-bottom:14px;position:relative;overflow:hidden}
.a12-head:after{content:"";position:absolute;right:-80px;top:-120px;width:340px;height:340px;border-radius:50%;background:radial-gradient(circle,rgba(242,106,33,.45),rgba(242,106,33,0) 70%)}
.a12-head h1{margin:0;font-size:30px;font-weight:700;line-height:1.3}
.a12-head p{margin:4px 0 0;color:#CBD5E1;font-size:15px}
.a12-head .auto{display:inline-flex;align-items:center;gap:6px;background:rgba(22,163,74,.18);border:1px solid rgba(74,222,128,.5);color:#BBF7D0;border-radius:999px;padding:4px 12px;font-size:13px;font-weight:600;margin-top:10px}
.a12-head .auto i{width:8px;height:8px;border-radius:50%;background:#4ADE80;box-shadow:0 0 0 4px rgba(74,222,128,.25)}
.a12-bar{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px 16px;margin-bottom:14px}
.a12-bar label{display:flex;flex-direction:column;gap:5px;font-size:13px;font-weight:600;color:#475569}
.a12-bar select{height:44px;border:1px solid #CBD5E1;border-radius:11px;padding:0 12px;font-size:16px;font-weight:700;color:var(--navy);min-width:130px;background:#fff}
.a12-seg{display:inline-flex;background:#F1F5F9;border-radius:12px;padding:4px;gap:4px}
.a12-seg a{padding:9px 16px;border-radius:9px;font-weight:700;color:#475569;text-decoration:none;font-size:15px}
.a12-seg a.on{background:var(--navy);color:#fff;box-shadow:0 2px 6px rgba(15,23,42,.2)}
.a12-btn{display:inline-flex;align-items:center;gap:8px;height:44px;padding:0 16px;border-radius:11px;border:0;font-weight:700;font-size:15px;cursor:pointer;color:#fff}
.a12-btn svg{width:18px;height:18px}
.a12-btn.navy{background:var(--navy)}.a12-btn.blue{background:#2563EB}.a12-btn.line{background:#06C755}
.a12-sp{flex:1}
.a12-kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:14px}
.a12-kpi{background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px 16px;border-top:4px solid var(--c)}
.a12-kpi .l{font-size:14px;color:#64748B;font-weight:600}
.a12-kpi .v{font:800 34px/1.15 Inter,sans-serif;color:var(--c);margin-top:4px}
.a12-kpi .v small{font-size:15px;color:#64748B;font-weight:600;margin-left:4px;font-family:"IBM Plex Sans Thai",sans-serif}
.a12-legend{display:flex;flex-wrap:wrap;gap:16px;align-items:center;font-size:14px;color:#334155;margin:0 2px 10px}
.a12-legend span{display:inline-block;line-height:26px;margin-right:6px}.a12-legend .ch{margin-right:6px}
.a12-wrap{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:auto}
.a12-t{border-collapse:separate;border-spacing:0;width:100%;min-width:1560px}
.a12-t th,.a12-t td{border-bottom:1px solid #EDF1F6;border-right:1px solid #EDF1F6;text-align:center;padding:6px 4px}
.a12-t thead th{background:var(--navy);color:#fff;font-weight:700;font-size:15px;position:sticky;top:0;z-index:3;padding:10px 4px}
.a12-t thead th.cur{background:var(--org)}
.a12-t thead th small{display:block;font-weight:500;font-size:11.5px;color:#CBD5E1;margin-top:2px}
.a12-t thead th.cur small{color:#FFEDD5}
.a12-t .no{width:44px;font:700 15px Inter,sans-serif;color:#64748B}
.a12-t .nm{position:sticky;left:0;z-index:2;background:#fff;text-align:left;min-width:240px;padding:8px 12px}
.a12-t thead .nm{background:var(--navy);z-index:4}
.a12-t .nm b{display:block;font-size:16px;color:var(--navy);font-weight:700;white-space:nowrap}
.a12-t .nm small{font-size:12.5px;color:#64748B}
.tbadge{display:inline-block;font:700 11px Inter,sans-serif;padding:2px 7px;border-radius:6px;margin-right:6px;vertical-align:2px}
.tbadge.A{background:#FFEDD5;color:#C2410C}.tbadge.B{background:#DBEAFE;color:#1D4ED8}
.a12-t td.mc{min-width:112px;background:#fff}
.a12-t td.mc.cur{background:#FFF7ED}
.rd{display:flex;justify-content:center;gap:4px}
.rb{display:flex;flex-direction:column;align-items:center;gap:2px}
.rb small{display:block;font:600 11px/13px Inter,sans-serif;color:#64748B;height:13px}
.ch{position:relative;box-sizing:border-box;width:24px;height:24px;border-radius:7px;border:1.5px solid #D5DBE5;background:#F8FAFC;display:inline-block;text-align:center;font:800 14px/21px Inter,sans-serif;font-style:normal;color:#fff;padding:0;cursor:default;vertical-align:middle}
button.ch{cursor:pointer}
.ch.s1{background:var(--ok);border-color:var(--ok)}
.ch.s2{background:var(--no);border-color:var(--no)}
.ch.s4{background:#EFF6FF;border-color:#93C5FD;color:var(--plan)}
.ch.s0.none{background:transparent;border:1.5px dashed #E2E8F0}
.ch.man:after{content:"";position:absolute;right:-3px;top:-3px;width:8px;height:8px;border-radius:50%;background:#F59E0B;border:1.5px solid #fff}
.a12-t td.tot{font:800 18px Inter,sans-serif;color:var(--ok);min-width:64px}
.a12-t td.tno{font:800 18px Inter,sans-serif;color:var(--no);min-width:58px}
.a12-t td.pc{min-width:110px;padding:6px 10px}
.pcv{font:800 16px Inter,sans-serif}
.pcb{height:7px;border-radius:9px;background:#EEF2F7;margin-top:4px;overflow:hidden}.pcb i{display:block;height:100%;border-radius:9px}
.a12-t tfoot td{background:#F8FAFC;font:700 15px Inter,sans-serif;color:var(--navy);padding:10px 4px}
.a12-t tfoot .ok{color:var(--ok)}.a12-t tfoot .bad{color:var(--no)}
.a12-charts{display:grid;grid-template-columns:1.2fr 1fr;gap:14px;margin-top:14px}
.a12-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px 18px}
.a12-card h3{margin:0 0 4px;font-size:18px;color:var(--navy);font-weight:700}
.a12-card p{margin:0 0 10px;color:#64748B;font-size:13.5px}
.a12-msg{padding:11px 14px;border-radius:12px;font-weight:600;margin-bottom:12px}
.a12-msg.ok{background:#ECFDF5;color:#166534}.a12-msg.err{background:#FEE2E2;color:#991B1B}
.a12-poster-head{display:none}
@media(max-width:1100px){.a12-kpis{grid-template-columns:repeat(3,1fr)}.a12-charts{grid-template-columns:1fr}}
@media(max-width:600px){.a12-kpis{grid-template-columns:1fr 1fr}.a12-head h1{font-size:23px}.a12-btn{flex:1;justify-content:center}}
/* ภาพสำหรับ LINE */
.a12-export{position:absolute!important;left:-30000px!important;top:0!important;width:2200px!important;background:#fff!important;padding:28px!important}
.a12-export .a12-poster-head{display:flex!important;align-items:center;gap:20px;padding:0 0 18px;border-bottom:4px solid var(--org);margin-bottom:18px}
.a12-export .a12-poster-head img{height:54px}
.a12-export .a12-poster-head h2{margin:0;font-size:34px;color:var(--navy);font-weight:700}
.a12-export .a12-poster-head span{margin-left:auto;font:700 20px Inter,sans-serif;color:#64748B}
.a12-export .a12-wrap{overflow:visible!important}
.a12-export .a12-t{min-width:0!important}
.a12-export .a12-t thead th,.a12-export .a12-t .nm{position:static!important}
@media print{
  @page{size:A4 landscape;margin:7mm}
  html,body{background:#fff!important}
  .no-print,.topbar,.sidebar,.footer,.a12-bar,.a12-charts,.a12-head,.page-context,.page-steps{display:none!important}
  .main{margin:0!important;padding:0!important}.main>.wrap{max-width:none!important;padding:0!important;margin:0!important}
  .a12-poster{zoom:.56}
  .a12-poster-head{display:flex!important;align-items:center;gap:18px;padding:0 0 12px;border-bottom:4px solid #F26A21;margin-bottom:14px}
  .a12-poster-head img{height:48px}.a12-poster-head h2{margin:0;font-size:30px;color:#0E1A2F}.a12-poster-head span{margin-left:auto;font:700 18px Inter,sans-serif;color:#64748B}
  .a12-wrap{overflow:visible!important;border-radius:0}.a12-t{min-width:0!important}
  .a12-t thead th,.a12-t .nm{position:static!important}
  *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
}
</style>
<div class="a12">
<div class="a12-head no-print">
  <div>
    <h1>ผู้ตรวจ / ประวัติการตรวจ 12 เดือน</h1>
    <p>ปี <?=$year?> • <?=h($teamLabel)?> • 1 เดือนสูงสุด 4 รอบ (R1–R4) ตามวันตรวจในแผนตรวจ 5 ส.</p>
    <span class="auto"><i></i> อัปเดตอัตโนมัติจากแผนตรวจ + ผลตรวจจริง • <?=date('d/m/').(date('Y')+543).' '.date('H:i')?></span>
  </div>
</div>
<?php if($err):?><div class="a12-msg err no-print">⚠ <?=h($err)?></div><?php endif;?>
<div class="a12-bar no-print">
  <form method="get" style="display:flex;gap:10px;align-items:flex-end">
    <label>ปี พ.ศ.<select name="year" onchange="this.form.submit()"><?php for($yy=$year-2;$yy<=$year+1;$yy++):?><option <?=$yy==$year?'selected':''?>><?=$yy?></option><?php endfor;?></select></label>
    <input type="hidden" name="team" value="<?=h($team)?>">
  </form>
  <div class="a12-seg"><?php foreach(['A'=>'ทีม A','B'=>'ทีม B','ALL'=>'ทุกทีม'] as $k=>$lb):?><a class="<?=$team===$k?'on':''?>" href="?year=<?=$year?>&team=<?=$k?>"><?=$lb?></a><?php endforeach;?></div>
  <span class="a12-sp"></span>
  <button type="button" class="a12-btn blue" onclick="window.print()"><?=five_s_icon('printer')?> พิมพ์ A4</button>
  <button type="button" class="a12-btn line" onclick="copyA12Image()"><?=five_s_icon('file')?> คัดลอกรูปส่ง LINE</button>
</div>

<div id="a12Poster" class="a12-poster">
<div class="a12-poster-head"><img src="assets/alumet-logo.png" alt="ALUMET"><h2>สรุปการเข้าตรวจ 5 ส. ของผู้ตรวจ — ปี <?=$year?> • <?=h($teamLabel)?></h2><span>ข้อมูล ณ <?=date('d/m/').(date('Y')+543)?></span></div>
<div class="a12-kpis">
  <div class="a12-kpi" style="--c:#0E1A2F"><div class="l">ผู้ตรวจ</div><div class="v"><?=count($rows)?><small>คน</small></div></div>
  <div class="a12-kpi" style="--c:#2563EB"><div class="l">รอบตรวจทั้งหมด</div><div class="v"><?=$sumPlan?><small>รอบ</small></div></div>
  <div class="a12-kpi" style="--c:#16A34A"><div class="l">มาตรวจ</div><div class="v" id="kOk"><?=$sumOk?><small>รอบ</small></div></div>
  <div class="a12-kpi" style="--c:#DC2626"><div class="l">ไม่มา / ไม่พบผลตรวจ</div><div class="v" id="kNo"><?=$sumNo?><small>รอบ</small></div></div>
  <div class="a12-kpi" style="--c:#F26A21"><div class="l">อัตราการมาตรวจ</div><div class="v" id="kRate"><?=$rate?><small>%</small></div></div>
</div>
<div class="a12-legend">
  <span><i class="ch s1">✓</i> มาตรวจ (พบผลตรวจ)</span>
  <span><i class="ch s2">✕</i> ไม่มา / เลยวันแล้วไม่พบผลตรวจ</span>
  <span><i class="ch s4">•</i> มีแผน ยังไม่ถึงวัน</span>
  <span><i class="ch s0 none"></i> ไม่มีแผน</span>
  <span class="no-print"><i class="ch s1 man">✓</i> แก้ไขเอง (จุดส้ม)</span>
  <span style="color:#64748B">ตัวเลขเหนือช่อง = วันที่ตรวจ</span>
</div>
<div class="a12-wrap"><table class="a12-t">
<thead><tr><th class="no">#</th><th class="nm">ชื่อผู้ตรวจ</th>
<?php foreach($months as $mi=>$mn):?><th class="<?=$curM===$mi+1?'cur':''?>"><?=$mn?><small><?=$monthPlan[$mi+1]?> รอบ</small></th><?php endforeach;?>
<th>มา</th><th>ไม่มา</th><th>% มาตรวจ</th></tr></thead>
<tbody>
<?php foreach($rows as $i=>$row): $pct=$row['pct']; $pcCol=$pct===null?'#94A3B8':($pct>=80?'#16A34A':($pct>=50?'#F59E0B':'#DC2626')); ?>
<tr data-row="<?=$i?>">
  <td class="no"><?=$i+1?></td>
  <td class="nm"><b><?php if($team==='ALL'):?><span class="tbadge <?=$row['team']?>"><?=$row['team']?></span><?php endif;?><?=h($row['name'])?></b><small>ทั้งหมด <?=$row['plan']?> รอบ</small></td>
  <?php for($m=1;$m<=12;$m++):?><td class="mc <?=$curM===$m?'cur':''?>" data-m="<?=$m?>"><div class="rd">
  <?php for($r=1;$r<=4;$r++): $g=$grid[$i][$m][$r]; $s=$g['show']; $dd=$g['d']!==''?(int)substr($g['d'],8,2):''; 
    $tip=$months[$m-1].' รอบ '.$r.($g['d']!==''?' • วันที่ '.date('d/m/',strtotime($g['d'])).((int)substr($g['d'],0,4)+543):' • ไม่มีแผน').($g['dep']!==''?' • '.$g['dep']:'').($g['man']?' • แก้ไขเอง':'');
    $cls='ch s'.$s.($s===0?' none':'').($g['man']?' man':''); $ic=$s===1?'✓':($s===2?'✕':($s===4?'•':'')); ?>
    <div class="rb"><small><?=$dd!==''?$dd:''?></small><?php if($team!=='ALL'):?><button type="button" class="<?=$cls?>" data-auto="<?=$g['auto']?>" data-man="<?=$g['man']?>" data-name="<?=h($row['name'])?>" data-month="<?=$m?>" data-round="<?=$r?>" title="<?=h($tip)?>"><?=$ic?></button><?php else:?><i class="<?=$cls?>" title="<?=h($tip)?>"><?=$ic?></i><?php endif;?></div>
  <?php endfor;?></div></td><?php endfor;?>
  <td class="tot"><?=$row['ok']?></td><td class="tno"><?=$row['no']?></td>
  <td class="pc"><div class="pcv" style="color:<?=$pcCol?>"><?=$pct===null?'—':$pct.'%'?></div><div class="pcb"><i style="width:<?=$pct??0?>%;background:<?=$pcCol?>"></i></div></td>
</tr>
<?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="17" style="padding:28px;color:#64748B">ยังไม่มีรายชื่อผู้ตรวจในแผนตรวจ 5 ส. ของปี/ทีมที่เลือก</td></tr><?php endif;?>
</tbody>
<tfoot><tr><td colspan="2" style="text-align:right;padding-right:14px">รวมรายเดือน (มา / ไม่มา)</td>
<?php for($m=1;$m<=12;$m++):?><td data-fm="<?=$m?>"><span class="ok"><?=$monthOk[$m]?></span> / <span class="bad"><?=$monthNo[$m]?></span></td><?php endfor;?>
<td class="ok" id="fOk"><?=$sumOk?></td><td class="bad" id="fNo"><?=$sumNo?></td><td id="fRate"><?=$rate?>%</td></tr></tfoot>
</table></div>
</div>

<div class="a12-charts no-print">
  <div class="a12-card"><h3>เปรียบเทียบรายบุคคล</h3><p>จำนวนรอบที่มาตรวจ (เขียว) และไม่มา (แดง) ทั้งปี</p><div style="height:<?=max(300,count($rank)*30+60)?>px"><canvas id="pc"></canvas></div></div>
  <div class="a12-card"><h3>การเข้าตรวจรายเดือน</h3><p>รอบที่มาตรวจเทียบกับรอบที่ไม่มา แต่ละเดือน</p><div style="height:360px"><canvas id="mc"></canvas></div></div>
</div>
</div>
<script src="assets/vendor/html2canvas.min.js"></script>
<script>
const A12_YEAR=<?=$year?>,A12_TEAM=<?=json_encode($team)?>;
const rankRows=<?=json_encode(array_map(fn($r)=>['n'=>($team==='ALL'?'['.$r['team'].'] ':'').$r['name'],'ok'=>$r['ok'],'no'=>$r['no']],$rank),JSON_UNESCAPED_UNICODE)?>;
Chart.defaults.font.family='"IBM Plex Sans Thai",Inter,sans-serif';Chart.defaults.font.size=13;
const pcChart=new Chart(document.getElementById('pc'),{type:'bar',data:{labels:rankRows.map(r=>r.n),datasets:[{label:'มาตรวจ',data:rankRows.map(r=>r.ok),backgroundColor:'#16A34A',borderRadius:5},{label:'ไม่มา',data:rankRows.map(r=>r.no),backgroundColor:'#EF4444',borderRadius:5}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{x:{stacked:true,beginAtZero:true,ticks:{precision:0}},y:{stacked:true,ticks:{font:{size:13,weight:'600'}}}}}});
const mcChart=new Chart(document.getElementById('mc'),{type:'bar',data:{labels:<?=json_encode($months,JSON_UNESCAPED_UNICODE)?>,datasets:[{label:'มาตรวจ',data:<?=json_encode(array_values($monthOk))?>,backgroundColor:'#16A34A',borderRadius:5},{label:'ไม่มา',data:<?=json_encode(array_values($monthNo))?>,backgroundColor:'#EF4444',borderRadius:5}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{x:{stacked:true},y:{stacked:true,beginAtZero:true,ticks:{precision:0}}}}});

const ICON={1:'✓',2:'✕',4:'•',0:''};
function showOf(man,auto){return man===1?1:(man===2?2:(man===3?0:auto));}
function paint(b){const man=+b.dataset.man,auto=+b.dataset.auto,s=showOf(man,auto);b.className='ch s'+s+(s===0?' none':'')+(man?' man':'');b.textContent=ICON[s];b.dataset.show=s;}
function recount(){
  const mOk=Array(13).fill(0),mNo=Array(13).fill(0);let tOk=0,tNo=0;
  document.querySelectorAll('tr[data-row]').forEach(tr=>{let ok=0,no=0;
    tr.querySelectorAll('td.mc').forEach(td=>{const m=+td.dataset.m;td.querySelectorAll('.ch').forEach(c=>{const s=c.classList.contains('s1')?1:(c.classList.contains('s2')?2:0);if(s===1){ok++;mOk[m]++;}else if(s===2){no++;mNo[m]++;}});});
    tr.querySelector('.tot').textContent=ok;tr.querySelector('.tno').textContent=no;
    const p=(ok+no)?Math.round(ok/(ok+no)*1000)/10:null,col=p===null?'#94A3B8':(p>=80?'#16A34A':(p>=50?'#F59E0B':'#DC2626'));
    const v=tr.querySelector('.pcv');v.textContent=p===null?'—':p+'%';v.style.color=col;const bar=tr.querySelector('.pcb i');bar.style.width=(p||0)+'%';bar.style.background=col;
    tOk+=ok;tNo+=no;const rr=rankRows.find(r=>r.n===tr.querySelector('.nm b').textContent.trim());if(rr){rr.ok=ok;rr.no=no;}
  });
  for(let m=1;m<=12;m++){const td=document.querySelector('[data-fm="'+m+'"]');if(td)td.innerHTML='<span class="ok">'+mOk[m]+'</span> / <span class="bad">'+mNo[m]+'</span>';}
  const rate=(tOk+tNo)?Math.round(tOk/(tOk+tNo)*1000)/10:0;
  document.getElementById('fOk').textContent=tOk;document.getElementById('fNo').textContent=tNo;document.getElementById('fRate').textContent=rate+'%';
  document.getElementById('kOk').innerHTML=tOk+'<small>รอบ</small>';document.getElementById('kNo').innerHTML=tNo+'<small>รอบ</small>';document.getElementById('kRate').innerHTML=rate+'<small>%</small>';
  pcChart.data.datasets[0].data=rankRows.map(r=>r.ok);pcChart.data.datasets[1].data=rankRows.map(r=>r.no);pcChart.update('none');
  mcChart.data.datasets[0].data=mOk.slice(1);mcChart.data.datasets[1].data=mNo.slice(1);mcChart.update('none');
}
/* คลิก: ✓ มาตรวจ → ✕ ไม่มา → กลับเป็นค่าอัตโนมัติ (ข้ามสถานะที่ซ้ำกับที่แสดงอยู่) */
document.querySelectorAll('button.ch').forEach(b=>b.addEventListener('click',async()=>{
  if(b.disabled)return;
  const man=+b.dataset.man,auto=+b.dataset.auto,cur=showOf(man,auto),order=[1,2,0];
  let idx=order.indexOf(man===3?0:man),next=order[(idx+1)%3];
  if(showOf(next,auto)===cur) next=order[(order.indexOf(next)+1)%3];
  const fd=new URLSearchParams({action:'toggle',ajax:'1',year:A12_YEAR,team:A12_TEAM,name:b.dataset.name,month:b.dataset.month,round:b.dataset.round,status:String(next)});
  b.disabled=true;
  try{const res=await fetch('auditor_12month.php',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
    const data=JSON.parse(await res.text());if(!data.ok)throw new Error(data.message||'บันทึกไม่สำเร็จ');
    b.dataset.man=String(data.status);paint(b);recount();
    if(window.FiveSCopy)FiveSCopy.toast('✓ บันทึกแล้ว');
  }catch(e){alert('บันทึกไม่สำเร็จ: '+e.message);}finally{b.disabled=false;}
}));
function copyA12Image(){FiveSCopy.copyFrom(buildA12Canvas,'ผู้ตรวจ-12เดือน-<?=$team?>-<?=$year?>.png');}
async function buildA12Canvas(){
  if(typeof html2canvas!=='function')throw new Error('ยังโหลดตัวสร้างรูปไม่สำเร็จ');
  if(document.fonts&&document.fonts.ready){try{await document.fonts.ready;}catch(e){}}
  const clone=document.getElementById('a12Poster').cloneNode(true);clone.id='';clone.classList.add('a12-export');
  clone.querySelectorAll('.no-print').forEach(e=>e.remove());
  const host=document.createElement('div');host.className='a12';host.appendChild(clone);document.body.appendChild(host);
  try{await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));
    return await FiveSCopy.render(clone,{scale:2,backgroundColor:'#fff',useCORS:true,logging:false,windowWidth:2300,scrollX:0,scrollY:0});}
  finally{host.remove();}
}
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
