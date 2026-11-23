<?php
require_once 'config.php';
$title='สรุปความครบถ้วนการลงคะแนน 5 ส.';
$p=db();
// Default to the current Thai-local month, while allowing any month/year to be reviewed.
$currentMonth=(int)date('n');
$currentYear=(int)date('Y')+543;
$month=(int)($_GET['month']??$currentMonth);
$year=(int)($_GET['year']??$currentYear);
$dept=$_GET['department']??'ALL';
if($month<1||$month>12)$month=$currentMonth;
if($year<2400)$year+=543;
if($year<2500||$year>2700)$year=$currentYear;
if(!in_array($dept,['ALL','Production','Office'],true))$dept='ALL';
$yearDb=$year-543;
$start=sprintf('%04d-%02d-01',$yearDb,$month);
$end=date('Y-m-t',strtotime($start));
require_once __DIR__.'/includes/auto_schedule_status.php';
try { sync_monthly_audit_plan_statuses($p,$month,$year); } catch(Throwable $syncError) { /* keep report available if a legacy plan row cannot be matched */ }
$months=[1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
$critCount=['Production'=>(int)$p->query("SELECT COUNT(*) FROM criteria WHERE active=1")->fetchColumn(),'Office'=>(int)$p->query("SELECT COUNT(*) FROM office_criteria WHERE active=1")->fetchColumn()];


function build_completion(PDO $p,string $dept,string $start,string $end,int $expectedCriteria):array{
  // Use active Master areas plus inactive areas that actually have an audit in this month.
  // This keeps historical monthly checks visible after a unit is later disabled.
  $areasQ=$p->prepare("SELECT DISTINCT ar.id,ar.group_name,ar.unit_no,ar.area_name
      FROM areas ar
      LEFT JOIN audits ax ON ax.area_id=ar.id AND ax.audit_date BETWEEN ? AND ?
      WHERE TRIM(ar.department)=? AND (ar.active=1 OR ax.id IS NOT NULL)
      ORDER BY ar.group_name, CAST(ar.unit_no AS INTEGER), ar.unit_no");
  $areasQ->execute([$start,$end,$dept]);

  $groups=[];$areaMap=[];
  foreach($areasQ as $r){
    $g=display_area_group((string)$r['group_name']); if($g==='') $g=(string)$r['group_name'];
    $u=(string)$r['unit_no'];
    if(!isset($groups[$g]))$groups[$g]=['expected_units'=>[],'area_ids'=>[],'names'=>[]];
    $groups[$g]['expected_units'][$u]=1;
    $groups[$g]['area_ids'][(int)$r['id']]=1;
    $groups[$g]['names'][]=$r['area_name'];
    $areaMap[(int)$r['id']]=['group'=>$g,'unit'=>$u];
  }

  /* Latest audit per expected area in the selected month (including a now-inactive area if it was inspected during this period). */
  /* Use the department stored on the linked Master area, not the
     denormalized audits.department field. Older audits can have stale or
     blank department values while area_id and the saved scores are valid. */
  $aq=$p->prepare("SELECT a.id,a.area_id,a.audit_date,a.auditor
                   FROM audits a
                   JOIN areas ar ON ar.id=a.area_id
                   WHERE TRIM(ar.department)=? AND a.audit_date BETWEEN ? AND ?
                   ORDER BY a.audit_date DESC,a.id DESC");
  $aq->execute([$dept,$start,$end]);
  $latestAuditByArea=[];
  foreach($aq as $r){
    $areaId=(int)$r['area_id'];
    if(!isset($areaMap[$areaId])) continue;
    if(!isset($latestAuditByArea[$areaId])) $latestAuditByArea[$areaId]=$r;
  }

  $scoreTable=$dept==='Office'?'office_scores':'scores';
  $critTable=$dept==='Office'?'office_criteria':'criteria';

  /*
   * A unit is considered "ครบ" only when every active criterion is answered
   * by at least one score OR explicitly marked N/A. Blank score fields do not count.
   */
  $auditState=[];
  $statesQ=$p->prepare("
      SELECT c.id criterion_id, c.max_score,
             s.score1, s.score2, s.score3, COALESCE(s.note,'') note
      FROM {$critTable} c
      LEFT JOIN {$scoreTable} s
        ON s.criterion_id=c.id AND s.audit_id=?
      WHERE c.active=1
      ORDER BY c.item_no, c.id
  ");
  foreach($latestAuditByArea as $areaId=>$ar){
    $statesQ->execute([(int)$ar['id']]);
    $covered=0; $scored=0; $sum=0.0; $max=0.0;
    foreach($statesQ as $sr){
      $v=score_avg($sr['score1'],$sr['score2'],$sr['score3']);
      $isNA=stripos(trim((string)$sr['note']),'N/A:')===0;
      if($v!==null){
        $covered++; $scored++; $sum+=(float)$v; $max+=(float)$sr['max_score'];
      } elseif($isNA){
        $covered++;
      }
    }
    $auditState[(int)$ar['id']]=[
      'complete'=>($covered >= $expectedCriteria),
      'covered'=>$covered,
      'scored'=>$scored,
      'sum'=>$sum,
      'max'=>$max,
      'pct'=>$max>0?round($sum/$max*100,2):null,
      'audit_date'=>$ar['audit_date'],
      'auditor'=>(string)($ar['auditor']??''),
      'expected'=>$expectedCriteria
    ];
  }

  $out=[];$completeGroups=$partialGroups=$noneGroups=0;
  $expectedUnits=0;$recordedUnits=0;$completeUnits=0;$partialUnits=0;$missingUnits=0;
  $auditTotal=count($latestAuditByArea); $scoreComplete=0; $scoreAudits=count($latestAuditByArea);

  foreach($groups as $g=>$info){
    $exp=array_keys($info['expected_units']); sort($exp,SORT_NATURAL);
    $complete=[];$partial=[];$missing=[];$recorded=[];$auditDetails=[];

    foreach($exp as $u){
      $foundAreaId=null;
      foreach($info['area_ids'] as $areaId=>$dummy){
        if(($areaMap[$areaId]['unit']??'')===(string)$u){$foundAreaId=$areaId;break;}
      }
      if($foundAreaId===null || !isset($latestAuditByArea[$foundAreaId])){ $missing[]=$u; continue; }
      $latestAudit=$latestAuditByArea[$foundAreaId];
      $state=$auditState[(int)$latestAudit['id']]??null;
      if(!$state){$missing[]=$u;continue;}
      $recorded[]=$u;$recordedUnits++;
      $auditDetails[(string)$u]=[
        'date'=>(string)$state['audit_date'],
        'auditor'=>(string)$state['auditor'],
        'covered'=>(int)$state['covered'],
        'expected'=>(int)$state['expected']
      ];
      if($state['complete']){$complete[]=$u;$completeUnits++;} else {$partial[]=$u;$partialUnits++;}
    }

    $expectedUnits+=count($exp);
    $missingUnits+=count($missing);
    $nComplete=count($complete); $nOutstanding=count($partial)+count($missing);

    if(count($recorded)===0 && count($exp)>0){$status='ยังไม่มีใบตรวจ';$statusClass='bad';$noneGroups++;}
    elseif($nOutstanding===0){$status='ครบทุกหน่วย';$statusClass='ok';$completeGroups++; }
    else {$status='ลงคะแนนบางส่วน';$statusClass='warn';$partialGroups++;}

    $completeAuditsForGroup=0;
    foreach($info['area_ids'] as $areaId=>$dummy){
      if(!isset($latestAuditByArea[$areaId])) continue;
      $state=$auditState[$latestAuditByArea[$areaId]['id']]??null;
      if($state && $state['complete']) $completeAuditsForGroup++;
    }
    $scoreComplete+=$completeAuditsForGroup;

    $out[]=[
      'group'=>$g,
      'expected'=>$exp,
      'recorded'=>$recorded,
      'audit_details'=>$auditDetails,
      'actual'=>$complete,
      'partial'=>$partial,
      'missing'=>$missing,
      'audits'=>$nComplete + count($partial),
      'score_complete'=>$completeAuditsForGroup,
      'score_audits'=>$nComplete + count($partial),
      'status'=>$status,
      'statusClass'=>$statusClass
    ];
  }

  return [
    'dept'=>$dept,
    'groups'=>$out,
    'group_total'=>count($groups),
    'complete_groups'=>$completeGroups,
    'partial_groups'=>$partialGroups,
    'none_groups'=>$noneGroups,
    'expected_units'=>$expectedUnits,
    'recorded_units'=>$recordedUnits,
    'audited_units'=>$completeUnits,
    'partial_units'=>$partialUnits,
    'missing_units'=>$missingUnits,
    'unit_pct'=>$expectedUnits?round($completeUnits/$expectedUnits*100,1):0,
    'audit_total'=>$auditTotal,
    'score_complete'=>$scoreComplete,
    'score_audits'=>$scoreAudits,
    'score_pct'=>$scoreAudits?round($scoreComplete/$scoreAudits*100,1):0
  ];
}

$prod=build_completion($p,'Production',$start,$end,$critCount['Production']);
$office=build_completion($p,'Office',$start,$end,$critCount['Office']);
$view=$dept==='Production'?$prod:($dept==='Office'?$office:null);
$overallComplete=$prod['complete_groups']+$office['complete_groups'];
$overallGroups=$prod['group_total']+$office['group_total'];

// Monthly archive: list every month that has real inspection records.
// The selected month can also be any month without records; those units remain visible as missing.
$historyPeriods=[];
try {
  $historyQ=$p->query("SELECT substr(a.audit_date,1,7) AS ym,
      COUNT(DISTINCT CASE WHEN TRIM(ar.department)='Production' THEN a.id END) AS prod_audits,
      COUNT(DISTINCT CASE WHEN TRIM(ar.department)='Office' THEN a.id END) AS office_audits,
      COUNT(DISTINCT CASE WHEN TRIM(ar.department)='Production' THEN a.area_id END) AS prod_units,
      COUNT(DISTINCT CASE WHEN TRIM(ar.department)='Office' THEN a.area_id END) AS office_units
    FROM audits a JOIN areas ar ON ar.id=a.area_id
    WHERE a.audit_date GLOB '????-??-??' AND TRIM(ar.department) IN ('Production','Office')
    GROUP BY substr(a.audit_date,1,7) ORDER BY ym DESC");
  foreach($historyQ as $hp){
    if(!preg_match('/^(\d{4})-(\d{2})$/',(string)$hp['ym'],$mm)) continue;
    $historyPeriods[]=[
      'month'=>(int)$mm[2], 'year'=>(int)$mm[1]+543,
      'prod_audits'=>(int)$hp['prod_audits'], 'office_audits'=>(int)$hp['office_audits'],
      'prod_units'=>(int)$hp['prod_units'], 'office_units'=>(int)$hp['office_units']
    ];
  }
} catch(Throwable $e) { $historyPeriods=[]; }
$selectedAuditCounts=['Production'=>0,'Office'=>0];
$countQ=$p->prepare("SELECT TRIM(ar.department) department, COUNT(DISTINCT a.id) audits, COUNT(DISTINCT a.area_id) units
  FROM audits a JOIN areas ar ON ar.id=a.area_id
  WHERE a.audit_date BETWEEN ? AND ? AND TRIM(ar.department) IN ('Production','Office')
  GROUP BY TRIM(ar.department)");
$countQ->execute([$start,$end]);
foreach($countQ as $cr){$selectedAuditCounts[$cr['department']]=(int)$cr['audits'];}
$selectedAuditTotal=$selectedAuditCounts['Production']+$selectedAuditCounts['Office'];
require 'includes/header.php';
?>
<style>
.cs-head{background:linear-gradient(90deg,#ff5a00,#ff8a00);color:#fff;border-radius:18px;padding:20px 24px;margin-bottom:18px}.cs-head h1{margin:0;font-size:28px}.cs-head p{margin:4px 0 0}.cs-filter{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px;margin-bottom:18px}.cs-kpi{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}.cs-box{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px}.cs-box b{display:block;font-size:24px;color:#173b70}.cs-box small{color:#64748b}.cs-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.cs-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden}.cs-card h2{margin:0;padding:14px 18px;background:#172238;color:#fff;font-size:18px}.cs-inner{padding:16px}.cs-banner{padding:12px 16px;border-radius:12px;margin-bottom:12px;font-weight:900}.bad{background:#fee2e2;color:#991b1b}.warn{background:#fef3c7;color:#92400e}.ok{background:#dcfce7;color:#166534}.cs-table{width:100%;border-collapse:collapse;font-size:13px}.cs-table th,.cs-table td{padding:9px 8px;border-bottom:1px solid #e5e7eb;vertical-align:top}.cs-table th{text-align:left;background:#f8fafc}.unit-record{display:block;margin:2px 0;white-space:nowrap}.unit-record small{color:#64748b}.pill{display:inline-block;padding:4px 8px;border-radius:999px;font-weight:800;font-size:11px}.pill.ok{background:#dcfce7;color:#166534}.pill.warn{background:#fef3c7;color:#92400e}.pill.bad{background:#fee2e2;color:#991b1b}.missing{color:#b91c1c;font-weight:800}.muted{color:#64748b}.cs-summary{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}.cs-summary span{background:#f1f5f9;padding:6px 10px;border-radius:10px;font-weight:700}.no-print{display:block}.cs-history-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.cs-period{display:block;border:1px solid #dbe3ee;border-radius:12px;padding:11px 13px;background:#f8fafc;color:#172238;text-decoration:none;transition:.15s}.cs-period:hover{border-color:#ff5a00;background:#fff7ed}.cs-period.current{border:2px solid #ff5a00;background:#fff7ed}.cs-period strong{display:block;font-size:14px}.cs-period small{display:block;color:#64748b;margin-top:4px;line-height:1.45}.cs-period .period-tag{display:inline-block;font-size:10px;font-weight:900;color:#9a3412;background:#ffedd5;border-radius:99px;padding:2px 7px;margin-top:5px}.cs-empty{padding:12px 14px;background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;color:#9a3412;font-weight:700}.cs-print-meta{display:none}@media(max-width:1100px){.cs-history-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:900px){.cs-kpi,.cs-grid{grid-template-columns:1fr 1fr}}@media(max-width:600px){.cs-kpi,.cs-grid,.cs-history-grid{grid-template-columns:1fr}.cs-table{font-size:12px}}
@media print{.no-print,.cs-history{display:none!important}.cs-print-meta{display:block!important;font-size:11px;color:#475569;margin:4px 0 12px}.cs-card{box-shadow:none;break-inside:avoid}.cs-table th,.cs-table td{border-color:#222}.main{margin-left:0!important;padding:10px}.sidebar,.topbar{display:none!important}}
</style>
<div class="cs-head"><h1>📊 สรุปความครบถ้วนการลงคะแนน 5 ส.</h1><p><?=h($months[$month])?> <?=$year?> • ทวนสอบความครบถ้วนรายเดือน • Production + Office • ข้อมูลจากใบตรวจที่บันทึกในงวดที่เลือก</p></div>
<div class="cs-filter no-print"><form class="flex flex-wrap gap-3 items-end"><div><label class="text-xs font-bold">ประเภท</label><select name="department" class="border rounded-xl p-3"><option value="ALL" <?=$dept==='ALL'?'selected':''?>>Production + Office</option><option value="Production" <?=$dept==='Production'?'selected':''?>>Production</option><option value="Office" <?=$dept==='Office'?'selected':''?>>Office</option></select></div><div><label class="text-xs font-bold">เดือน</label><select name="month" class="border rounded-xl p-3"><?php foreach($months as $mi=>$mn): ?><option value="<?=$mi?>" <?=$month===$mi?'selected':''?>><?=h($mn)?></option><?php endforeach;?></select></div><div><label class="text-xs font-bold">ปี</label><input name="year" type="number" value="<?=$year?>" class="border rounded-xl p-3 w-32"></div><button class="bg-slate-800 text-white rounded-xl px-5 py-3 font-bold">🔎 แสดงสรุป</button><button type="button" onclick="window.print()" class="border rounded-xl px-5 py-3 font-bold">🖨 พิมพ์ A4</button></form></div>

<div class="cs-card cs-history no-print" style="margin-bottom:18px">
  <h2>🗓️ ประวัติการทวนสอบย้อนหลังรายเดือน</h2>
  <div class="cs-inner">
    <p class="muted" style="margin:0 0 12px">เลือกเดือนที่เคยตรวจเพื่อเปิดรายงานย้อนหลังได้ทันที หรือเลือกเดือนที่ยังไม่มีข้อมูลจากตัวกรองด้านบนเพื่อตรวจหาหน่วยที่ตกหล่น</p>
    <?php if($historyPeriods): ?>
      <div class="cs-history-grid">
        <?php foreach($historyPeriods as $hp): $isCurrent=($hp['month']===$month && $hp['year']===$year); ?>
          <a class="cs-period <?=$isCurrent?'current':''?>" href="?department=<?=urlencode($dept)?>&month=<?=$hp['month']?>&year=<?=$hp['year']?>">
            <strong><?=h($months[$hp['month']])?> <?=$hp['year']?></strong>
            <small>Production: <?=$hp['prod_units']?> หน่วย / <?=$hp['prod_audits']?> ใบตรวจ<br>Office: <?=$hp['office_units']?> หน่วย / <?=$hp['office_audits']?> ใบตรวจ</small>
            <?php if($isCurrent): ?><span class="period-tag">เดือนที่กำลังดู</span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="cs-empty">ยังไม่พบประวัติใบตรวจในฐานข้อมูล</div>
    <?php endif; ?>
  </div>
</div>
<div class="cs-print-meta">งวดที่ทวนสอบ: <?=h($months[$month])?> <?=$year?> • พิมพ์เมื่อ <?=date('d/m/Y H:i')?> • จำนวนใบตรวจในงวดนี้: <?=$selectedAuditTotal?> ใบ</div>
<?php if($selectedAuditTotal===0): ?><div class="cs-empty" style="margin-bottom:16px">⚠️ เดือน <?=h($months[$month])?> <?=$year?> ยังไม่พบใบตรวจในฐานข้อมูล รายงานจะแสดงหน่วยที่คาดว่าต้องตรวจทั้งหมดเป็นรายการที่ยังไม่มีใบตรวจ</div><?php endif; ?>

<?php foreach(($dept==='Production'||$dept==='Office')?[$view]:[$prod,$office] as $r): ?>
<div class="cs-card" style="margin-bottom:18px"><h2><?=h($r['dept'])?> — <?=$months[$month]?> <?=$year?></h2><div class="cs-inner">
 <div class="cs-banner <?=$r['none_groups']||$r['partial_groups']||$r['partial_units']||$r['missing_units']?'warn':'ok'?>"><?=($r['none_groups']+$r['partial_groups']+$r['partial_units']+$r['missing_units'])===0?'✅ ครบทุกกลุ่มและทุกหน่วย':'⚠️ ยังไม่ครบทุกกลุ่ม/ทุกหน่วย'?> • หน่วยคะแนนครบ <?=$r['audited_units']?> / <?=$r['expected_units']?> (<?=$r['unit_pct']?>%)</div>
 <div class="cs-summary"><span>ใบตรวจในงวดนี้ <?=$selectedAuditCounts[$r['dept']]??0?> ใบ</span><span>มีใบตรวจแล้ว <?=$r['recorded_units']?> / <?=$r['expected_units']?> หน่วย</span><span>กลุ่มทั้งหมด <?=$r['group_total']?></span><span>กลุ่มครบ <?=$r['complete_groups']?></span><span>กลุ่มบางส่วน <?=$r['partial_groups']?></span><span>กลุ่มยังไม่มีใบตรวจ <?=$r['none_groups']?></span><span>หน่วยคะแนนครบ <?=$r['audited_units']?> / <?=$r['expected_units']?></span><span>มีใบตรวจแต่คะแนนยังไม่ครบ <?=$r['partial_units']?></span><span>ยังไม่มีใบตรวจ <?=$r['missing_units']?></span></div>
 <div style="overflow:auto"><table class="cs-table"><thead><tr><th style="min-width:130px">กลุ่ม</th><th>หน่วยที่ควรมี</th><th>มีใบตรวจแล้ว / วันที่</th><th>คะแนนครบ</th><th>ตรวจแล้วแต่คะแนนยังไม่ครบ</th><th>ยังไม่มีใบตรวจ</th><th>สถานะ</th></tr></thead><tbody>
 <?php foreach($r['groups'] as $g): ?><tr><td><b><?=h($g['group'])?></b></td><td><?=h(implode(', ',$g['expected']))?></td><td><?php if(!empty($g['recorded'])): foreach($g['recorded'] as $ru): $rd=$g['audit_details'][(string)$ru]??[]; ?><span class="unit-record"><b><?=h($ru)?></b> <small><?=!empty($rd['date'])?date('d/m/Y',strtotime($rd['date'])):''?><?=!empty($rd['auditor'])?' • '.h($rd['auditor']):''?></small></span><?php endforeach; else: ?>-<?php endif; ?></td><td><?=h(implode(', ',$g['actual'])?:'-')?></td><td class="missing"><?php if(!empty($g['partial'])): foreach($g['partial'] as $pu): $pd=$g['audit_details'][(string)$pu]??[]; ?><span class="unit-record"><b><?=h($pu)?></b> <small>(<?= (int)($pd['covered']??0) ?>/<?= (int)($pd['expected']??0) ?>)</small></span><?php endforeach; else: ?>-<?php endif; ?></td><td class="missing"><?=h(implode(', ',$g['missing'])?:'-')?></td><td><span class="pill <?=$g['statusClass']?>"><?=h($g['status'])?></span><?php if($g['audits']>0): ?><div class="muted" style="margin-top:4px">คะแนนครบ <?=$g['score_complete']?> / <?=$g['score_audits']?> ใบ</div><?php endif;?></td></tr><?php endforeach; ?>
 </tbody></table></div>
 </div></div>
<?php endforeach; ?>
<div class="cs-card"><h2>สรุปรวม Production + Office</h2><div class="cs-inner"><div class="cs-summary"><span>กลุ่มทั้งหมด <?=$overallGroups?></span><span>กลุ่มครบ <?=$overallComplete?></span><span>กลุ่มยังไม่ครบ <?=($prod['group_total']-$prod['complete_groups'])+($office['group_total']-$office['complete_groups'])?></span><span>มีใบตรวจแล้ว <?=$prod['recorded_units']+$office['recorded_units']?> / <?=$prod['expected_units']+$office['expected_units']?></span><span>หน่วยคะแนนครบ <?=$prod['audited_units']+$office['audited_units']?> / <?=$prod['expected_units']+$office['expected_units']?></span><span>มีใบตรวจแต่คะแนนไม่ครบ <?=$prod['partial_units']+$office['partial_units']?></span><span>ยังไม่มีใบตรวจ <?=$prod['missing_units']+$office['missing_units']?></span></div><div class="cs-banner <?=($prod['partial_units']+$prod['missing_units']+$office['partial_units']+$office['missing_units'])?'warn':'ok'?>"><?=($prod['partial_units']+$prod['missing_units']+$office['partial_units']+$office['missing_units'])?'สรุป: ยังมีบางหน่วยที่คะแนนไม่ครบหรือยังไม่มีใบตรวจ':'สรุป: ครบทุกกลุ่ม/หน่วยและคะแนนครบแล้ว'?></div></div></div>
<div class="muted" style="font-size:11px;margin:14px 2px 4px">หมายเหตุ: ระบบใช้พื้นที่ที่เปิดใช้งานใน Master ปัจจุบันเป็นฐาน และรวมหน่วยที่มีใบตรวจในเดือนย้อนหลังแม้ปัจจุบันจะปิดใช้งานแล้ว เพื่อไม่ให้ประวัติการตรวจเดิมหายไป หากมีการเพิ่ม/ย้าย/ยกเลิกหน่วยโดยไม่มีใบตรวจในเดือนนั้น ควรตรวจสอบ Master ประกอบก่อนรับรองผลย้อนหลัง</div>
<?php require 'includes/footer.php'; ?>
