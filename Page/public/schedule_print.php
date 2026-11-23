<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
function thdate($d){if(!$d)return ''; $p=explode('-',$d); if(count($p)!==3)return $d; $y=(int)$p[0]; $y=$y>2400?$y:$y+543; return $p[2].'/'.$p[1].'/'.$y;}
function unit_count($area){return count(array_filter(array_map('trim',explode(',',(string)$area)),fn($x)=>$x!==''));}
function dcls($d){$day=(int)substr((string)$d,-2);return $day===22?'d22':($day===23?'d23':($day===24?'d24':($day===25?'d25':'')));}
function team_cycle_default($month,$year){$offset=(($year-2569)*12)+($month-10);return ($offset%2===0)?'A':'B';}
function setting_get($p,$key,$default=''){ $st=$p->prepare('SELECT `value` FROM app_settings WHERE `key`=?');$st->execute([$key]);$v=$st->fetchColumn();return $v===false?$default:$v; }
$p=db();
/* V34 schedule enhancements: status + per-row department/date colors. */
function ensure_schedule_columns($p){
  $cols=array_fill_keys(db_table_columns('audit_plans',$p),true);
  $defs=['status'=>"VARCHAR(50) NOT NULL DEFAULT 'รอตรวจ'",'date_color'=>"VARCHAR(20) NOT NULL DEFAULT '#ffffff'",'department_color'=>"VARCHAR(20) NOT NULL DEFAULT '#ffffff'"];
  foreach($defs as $name=>$def){if(!isset($cols[$name]))$p->exec("ALTER TABLE audit_plans ADD COLUMN {$name} {$def}");}
}
ensure_schedule_columns($p);
[$__dm,$__dy]=five_s_default_plan_period(); $month=(int)($_GET['month']??$__dm); $year=(int)($_GET['year']??$__dy); $size=strtoupper($_GET['size']??'A4'); if(!in_array($size,['A4','A3'],true))$size='A4';
$months=[1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
$requestedTeam=strtoupper(trim((string)($_GET['team']??'')));
$activeTeam=in_array($requestedTeam,['A','B'],true)?$requestedTeam:team_cycle_default($month,$year); // Respect the team selected on the planning page; otherwise use the monthly default.
require_once FIVE_S_ROOT.'/includes/auto_schedule_status.php';
try { sync_monthly_audit_plan_statuses($p,$month,$year); } catch(Throwable $syncError) { /* keep print available */ }
$q=$p->prepare('SELECT * FROM audit_plans WHERE active=1 AND month=? AND year=? AND team=? ORDER BY sort_no,id');$q->execute([$month,$year,$activeTeam]);$rows=$q->fetchAll();
$groups=[];$idx=[];foreach($rows as $r){$sort=(int)($r['sort_no']??0);$key='sort:'.$sort;if(!isset($idx[$key])){$idx[$key]=count($groups);$groups[]=['sort_no'=>$sort,'name'=>trim((string)$r['auditor_name']),'rows'=>[]];}if($groups[$idx[$key]]['name']==='' && trim((string)$r['auditor_name'])!=='')$groups[$idx[$key]]['name']=trim((string)$r['auditor_name']);$groups[$idx[$key]]['rows'][]=$r;}
while(count($groups)<20)$groups[]=['sort_no'=>999999+count($groups),'name'=>'','rows'=>[['id'=>0,'auditor_name'=>'','department'=>'','inspection_date'=>'','inspection_time'=>'','area_5s'=>'','unit_count'=>0]]];
$groups=array_slice($groups,0,20);
$teamPeople=0; foreach($groups as $g){ if(trim((string)($g['name']??''))!=='') $teamPeople++; }
$totalUnits=0;foreach($groups as $g)foreach($g['rows'] as $r)$totalUnits+=unit_count($r['area_5s']??'');
$monthName=$months[$month]??'';
require FIVE_S_ROOT.'/includes/header.php';
?>
<?php
$__stat=['ตรวจแล้ว'=>0,'ตรวจบางส่วน'=>0,'รอตรวจ'=>0,'เลื่อนการตรวจ'=>0]; $__depts=0;
foreach($groups as $g){ foreach($g['rows'] as $r){ if(trim((string)($r['department']??''))==='') continue; $__depts++; $sv=trim((string)($r['status']??'')); if(isset($__stat[$sv])) $__stat[$sv]++; else $__stat['รอตรวจ']++; } }
$__q='month='.$month.'&year='.$year;
?>
<style>
.sp{--brand:#F26A21;--navy:#0E1A2F;--ink:#0F172A;--muted:#64748B;--line:#E2E8F0;font-family:"IBM Plex Sans Thai","Inter",Tahoma,sans-serif}
.sp-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#fff;border:1px solid var(--line);border-radius:16px;padding:12px 14px;margin-bottom:16px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 18px rgba(15,23,42,.05)}
.sp-bar .grp{display:inline-flex;background:#F1F4F8;border-radius:10px;padding:4px}
.sp-bar .grp a{padding:7px 14px;border-radius:7px;font-weight:600;font-size:14px;color:#475569;text-decoration:none}
.sp-bar .grp a.on{background:var(--navy);color:#fff}
.sp-btn{display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 16px;border-radius:10px;font-weight:600;font-size:14px;text-decoration:none;cursor:pointer;border:1px solid #D5DBE5;background:#fff;color:#1E293B;font-family:inherit}
.sp-btn .ico{width:16px;height:16px}.sp-btn:hover{border-color:var(--brand);color:var(--brand)}
.sp-btn.primary{background:var(--brand);border-color:var(--brand);color:#fff}.sp-btn.primary:hover{background:#DB5512;color:#fff}
.sp-bar .sp-right{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap}
.poster-print{width:100%;max-width:1500px;margin:0 auto;background:#fff;border:1px solid var(--line);border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.08);position:relative;color:var(--ink)}
.poster-print::before{content:"";display:block;height:8px;background:linear-gradient(90deg,#F7A21B,#F26A21 40%,#0E1A2F)}
.ph{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:24px;padding:20px 28px 16px;border-bottom:1px solid var(--line)}
.ph-logo{display:flex;align-items:center;gap:14px}.ph-logo img{height:42px;width:auto}.ph-logo .sep{width:2px;height:38px;background:var(--line)}
.ph-logo .ph-app{font:800 20px/1 Inter,sans-serif;color:var(--navy)}.ph-logo .ph-app span{color:var(--brand)}.ph-logo .ph-app small{display:block;font:600 11px "IBM Plex Sans Thai",sans-serif;color:var(--muted);letter-spacing:.6px;margin-top:5px}
.ph-title{text-align:center}.ph-title h1{margin:0;font-size:32px;font-weight:700;color:var(--navy);line-height:1.2}.ph-title h1 span{color:var(--brand)}
.ph-title p{margin:4px 0 0;font:700 12.5px Inter,sans-serif;letter-spacing:2.5px;color:var(--muted);text-transform:uppercase}
.ph-team{background:var(--navy);color:#fff;border-radius:16px;padding:10px 22px;text-align:center;min-width:130px}
.ph-team small{display:block;font-size:12px;color:#CBD5E1;font-weight:600;letter-spacing:1px}.ph-team b{display:block;font:800 40px/1 Inter,sans-serif;color:#F7A21B}
.ps{display:grid;grid-template-columns:repeat(6,1fr);gap:0;border-bottom:1px solid var(--line);background:#FAFBFC}
.ps div{padding:12px 18px;border-right:1px solid var(--line)}.ps div:last-child{border-right:0}
.ps small{display:block;font-size:12.5px;color:var(--muted);font-weight:600}.ps b{font:800 26px/1.2 Inter,sans-serif;color:var(--navy)}
.ps .g b{color:#15803D}.ps .a b{color:#B45309}.ps .r b{color:#475569}.ps .x b{color:#B91C1C}
.pt{width:100%;border-collapse:separate;border-spacing:0;table-layout:fixed;font-size:14.5px}
.pt th{background:var(--navy);color:#fff;font-weight:600;font-size:13.5px;padding:11px 8px;text-align:center;letter-spacing:.2px}
.pt th.l{text-align:left;padding-left:14px}
.pt td{padding:7px 8px;border-bottom:1px solid #E8ECF2;vertical-align:middle;text-align:center}
.pt tbody tr:nth-child(even) td{background:#FAFBFD}
.pt tr.empty td{height:38px;color:#CBD5E1}
.pt .c-no{width:4.5%}.pt .c-nm{width:20%}.pt .c-dp{width:15%}.pt .c-dt{width:11%}.pt .c-tm{width:10%}.pt .c-ar{width:22%}.pt .c-un{width:6%}.pt .c-st{width:11.5%}
.pt td.no{font:700 14px Inter,sans-serif;color:var(--muted)}
.pt td.nm{text-align:left;padding-left:14px;font-weight:700;color:var(--navy);font-size:15px}
.stk{display:flex;flex-direction:column;gap:4px}
.dpill{display:block;text-align:left;padding:4px 10px;border-radius:8px;font-weight:600;font-size:13.5px;color:#0F172A;border:1px solid rgba(15,23,42,.06)}
.dt{display:inline-block;padding:5px 10px;border-radius:8px;font:700 14px Inter,sans-serif;color:var(--navy);min-width:96px;-webkit-print-color-adjust:exact;print-color-adjust:exact}.dt.tmp{min-width:0;font-weight:600}
.tm{font:600 13.5px Inter,sans-serif;color:#334155}
.chips{display:flex;flex-wrap:wrap;gap:4px;justify-content:flex-start;min-height:26px;align-items:center}
.chips span{display:inline-block;min-width:30px;padding:3px 8px;border-radius:7px;background:#EEF2F7;border:1px solid #DCE3EC;font:700 13px Inter,sans-serif;color:#1E293B;text-align:center}
.un{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:#FFF1E7;color:#C2410C;font:800 14px Inter,sans-serif}
.st{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-weight:700;font-size:13px;white-space:nowrap}
.st i{width:8px;height:8px;border-radius:50%;background:currentColor;display:inline-block}
.st.done{background:#E8F7EE;color:#15803D}.st.partial{background:#FEF4E2;color:#B45309}.st.wait{background:#EEF2F7;color:#475569}.st.postpone{background:#FDECEC;color:#B91C1C}
.pf{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 28px;border-top:1px solid var(--line);background:#FAFBFC;flex-wrap:wrap}
.pf .tot{display:flex;align-items:center;gap:12px;font-weight:700;color:var(--navy);font-size:16px}
.pf .tot b{font:800 30px Inter,sans-serif;color:#fff;background:var(--brand);border-radius:12px;padding:4px 16px}
.pf .legend{display:flex;gap:8px;flex-wrap:wrap}
.pf .meta{font-size:12.5px;color:var(--muted)}
@media print{
 @page{size:<?=$size==='A3'?'A3':'A4'?> landscape;margin:6mm}
 body{background:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
 .no-print,.sidebar,.topbar,.footer,.page-context,.page-steps{display:none!important}.main{margin:0!important;padding:0!important}
 .poster-print{max-width:none;border-radius:0;box-shadow:none;border:0}
 html,body,.app,.main,.wrap{min-height:0!important;height:auto!important}.palette,.nav-backdrop{display:none!important}
 <?php if($size!=='A3'):?>.poster-print{zoom:.57}<?php else:?>.poster-print{zoom:.8}<?php endif;?>
}
</style>
<div class="sp">
<div class="sp-bar no-print">
  <a class="sp-btn" href="Page/admin/schedule_manage.php?<?=$__q?>&team=<?=h($activeTeam)?>"><?=five_s_icon('arrow-left')?> กลับหน้าแก้ไขแผน</a>
  <div class="grp"><a class="<?=$activeTeam==='A'?'on':''?>" href="<?=h(five_s_self())?>?<?=$__q?>&team=A&size=<?=$size?>">ทีม A</a><a class="<?=$activeTeam==='B'?'on':''?>" href="<?=h(five_s_self())?>?<?=$__q?>&team=B&size=<?=$size?>">ทีม B</a></div>
  <div class="grp"><a class="<?=$size==='A4'?'on':''?>" href="<?=h(five_s_self())?>?<?=$__q?>&team=<?=h($activeTeam)?>&size=A4">A4</a><a class="<?=$size==='A3'?'on':''?>" href="<?=h(five_s_self())?>?<?=$__q?>&team=<?=h($activeTeam)?>&size=A3">A3</a></div>
  <div class="sp-right">
    <button class="sp-btn" onclick="downloadPoster()"><?=five_s_icon('file')?> คัดลอกรูปส่ง LINE</button>
    <button class="sp-btn primary" onclick="window.print()"><?=five_s_icon('printer')?> พิมพ์ <?=$size?> แนวนอน</button>
  </div>
</div>
<div class="poster-print" id="posterPrint">
  <div class="ph">
    <div class="ph-logo"><img src="assets/alumet-logo.png" alt="ALUMET"><span class="sep"></span><div class="ph-app">5S <span>SMART</span><small>AUDIT SCHEDULE</small></div></div>
    <div class="ph-title"><h1>แผนการตรวจ 5 ส. ประจำเดือน <span><?=h($monthName)?> <?=$year?></span></h1><p>Monthly 5S Audit Plan • สลับชุดทีม A/B เดือนละครั้ง</p></div>
    <div class="ph-team"><small>TEAM</small><b><?=h($activeTeam)?></b></div>
  </div>
  <div class="ps">
    <div><small>ผู้ตรวจในทีม</small><b><?=$teamPeople?></b> <small style="display:inline">คน</small></div>
    <div><small>แผนก / พื้นที่</small><b><?=$__depts?></b></div>
    <div><small>หน่วยพื้นที่ 5 ส.</small><b><?=$totalUnits?></b> <small style="display:inline">หน่วย</small></div>
    <div class="g"><small>ตรวจแล้ว</small><b><?=$__stat['ตรวจแล้ว']?></b></div>
    <div class="a"><small>กำลังตรวจ</small><b><?=$__stat['ตรวจบางส่วน']?></b></div>
    <div class="r"><small>ยังไม่ได้ตรวจ / เลื่อน</small><b><?=$__stat['รอตรวจ']?><?=$__stat['เลื่อนการตรวจ']?' / '.$__stat['เลื่อนการตรวจ']:''?></b></div>
  </div>
  <table class="pt"><thead><tr><th class="c-no">ลำดับ</th><th class="c-nm l">ชื่อ - สกุล (ผู้ตรวจ)</th><th class="c-dp l">แผนก</th><th class="c-dt">วันที่ตรวจ</th><th class="c-tm">เวลา</th><th class="c-ar l">หน่วยพื้นที่ 5 ส.</th><th class="c-un">หน่วย</th><th class="c-st">สถานะ</th></tr></thead><tbody>
  <?php foreach($groups as $gi=>$g):$grows=$g['rows'];$first=$grows[0];$date=$first['inspection_date']??'';$time=$first['inspection_time']??'';$dateColor=$first['date_color']??'#ffffff';if(!preg_match('/^#[0-9a-fA-F]{6}$/',$dateColor))$dateColor='#ffffff';$statusCounts=['รอตรวจ'=>0,'เลื่อนการตรวจ'=>0,'ตรวจบางส่วน'=>0,'ตรวจแล้ว'=>0];foreach($grows as $sr){$sv=trim((string)($sr['status']??''));if(isset($statusCounts[$sv]))$statusCounts[$sv]++;}arsort($statusCounts);$status=(string)array_key_first($statusCounts);if($statusCounts[$status]===0)$status='รอตรวจ';$statusClass=$status==='ตรวจแล้ว'?'done':($status==='ตรวจบางส่วน'?'partial':($status==='เลื่อนการตรวจ'?'postpone':'wait'));$isEmpty=true;foreach($grows as $__r){if(trim((string)($__r['department']??''))!==''){$isEmpty=false;break;}} /* V69: ไม่มีแผนก = แถวว่าง (เหลือแค่ลำดับ/ชื่อ) */?>
    <tr class="<?=$isEmpty?'empty':''?>">
      <td class="no"><?=($gi+1)?></td>
      <td class="nm"><?=$isEmpty?"":h($g["name"])?></td>
      <td><div class="stk"><?php foreach($grows as $r): if(trim((string)($r['department']??''))==='') continue; $dcol=$r['department_color']??'#ffffff'; if(!preg_match('/^#[0-9a-fA-F]{6}$/',$dcol)||strtolower($dcol)==='#ffffff')$dcol='#F1F5F9';?><span class="dpill" style="background:<?=h($dcol)?>"><?=h($r['department'])?></span><?php endforeach;?></div></td>
      <?php $__dc=strtolower($dateColor)==='#ffffff'?'#F1F5F9':h($dateColor); ?>
      <td><?php if(!$isEmpty && $date!==''):?><span class="dt" style="background:<?=$__dc?>"><?=h(thdate($date))?></span><?php endif;?></td>
      <td class="tm"><?php if(!$isEmpty && trim((string)$time)!==''):?><span class="dt tmp" style="background:<?=$__dc?>"><?=h($time)?></span><?php endif;?></td>
      <td><div class="stk"><?php foreach(($isEmpty?[]:$grows) as $r): $units=array_filter(array_map('trim',explode(',',(string)($r['area_5s']??''))),fn($x)=>$x!==''); ?><div class="chips"><?php foreach($units as $u):?><span><?=h($u)?></span><?php endforeach;?></div><?php endforeach;?></div></td>
      <td><div class="stk" style="align-items:center"><?php foreach(($isEmpty?[]:$grows) as $r): $uc=unit_count($r['area_5s']??''); if(!$uc) continue;?><span class="un"><?=$uc?></span><?php endforeach;?></div></td>
      <td><?php if(!$isEmpty):?><span class="st <?=$statusClass?>"><i></i><?=h(five_s_plan_status_label($status))?></span><?php endif;?></td>
    </tr>
  <?php endforeach;?></tbody></table>
  <div class="pf">
    <div class="tot">รวมหน่วยพื้นที่ 5 ส. ทีม <?=h($activeTeam)?> <b><?=$totalUnits?></b> หน่วย</div>
    <div class="legend"><span class="st done"><i></i>ตรวจแล้ว</span><span class="st partial"><i></i>กำลังตรวจ</span><span class="st wait"><i></i>ยังไม่ได้ตรวจ</span><span class="st postpone"><i></i>เลื่อนการตรวจ</span></div>
    <div class="meta">สถานะอัปเดตอัตโนมัติจากคะแนนที่บันทึก • พิมพ์ <?=date('d/m/').(date('Y')+543)?></div>
  </div>
</div>
</div>
<script>
/* V67: รูปส่ง LINE = เลย์เอาต์เดียวกับหน้าพิมพ์ (กว้างเท่ากระดาษแนวนอน) ใช้ได้ทั้งหน้านี้และหน้าจัดตารางแผนตรวจ */
window.buildPosterCanvas=async function(){
  if(document.fonts&&document.fonts.ready){try{await document.fonts.ready;}catch(e){}}
  const src=document.getElementById('posterPrint'),W=1745;
  const host=document.createElement('div');host.style.cssText='position:absolute;left:-30000px;top:0;width:'+W+'px';
  const cl=src.cloneNode(true);cl.removeAttribute('id');cl.style.cssText='width:'+W+'px;max-width:none;border-radius:0;box-shadow:none;border:0;margin:0';
  host.appendChild(cl);document.body.appendChild(host);
  try{return await FiveSCopy.render(cl,{scale:2,backgroundColor:'#ffffff',windowWidth:W+100});}finally{host.remove();}
};
function downloadPoster(){FiveSCopy.copyFrom(window.buildPosterCanvas,'5S_Schedule_<?=$month?>_<?=$year?>_Team<?=$activeTeam?>.png');}
</script><script src="assets/vendor/html2canvas.min.js"></script><script src="assets/copy-line.js?v=64"></script>
<?php require FIVE_S_ROOT.'/includes/footer.php';?>
