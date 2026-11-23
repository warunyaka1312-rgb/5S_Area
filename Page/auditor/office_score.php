<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
$title='ลงคะแนน 5 ส. Office';
$p=db();
$areas=$p->query("SELECT * FROM areas WHERE active=1 AND department='Office' ORDER BY group_name,CAST(unit_no AS SIGNED),area_name")->fetchAll();
/* Area selector: treat PURCHASING and Billet&Scarp as one display group,
   and suppress duplicate group+unit options while retaining the selected area. */
$selectorAreas=[];
$seenSelector=[];
foreach($areas as $ar){
  $grp=display_area_group($ar['group_name']??'');
  $key=mb_strtolower(trim($grp).'|'.trim((string)($ar['unit_no']??'')),'UTF-8');
  if(!isset($seenSelector[$key])){
    $selectorAreas[]=$ar;
    $seenSelector[$key]=(int)$ar['id'];
  } elseif(isset($area_id) && (int)$ar['id']===$area_id){
    foreach($selectorAreas as $i=>$oldAr){
      if((int)$oldAr['id']===$seenSelector[$key]){$selectorAreas[$i]=$ar;break;}
    }
    $seenSelector[$key]=$area_id;
  }
}
$criteria=$p->query("SELECT * FROM office_criteria WHERE active=1 ORDER BY item_no")->fetchAll();
$area_id=(int)($_GET['area_id']??$_POST['area_id']??0);
$date=$_GET['date']??$_POST['audit_date']??date('Y-m-d');
$auditorKey=trim((string)($_GET['auditor']??$_POST['auditor']??''));
$existing=[];$auditRow=null;$selected=null;
$auditor1Key=trim((string)($_GET['auditor1']??$_POST['auditor1']??$auditorKey));
$auditor2Key=trim((string)($_GET['auditor2']??$_POST['auditor2']??''));
$auditor3Key=trim((string)($_GET['auditor3']??$_POST['auditor3']??''));
/* รายชื่อผู้ตรวจสำหรับหน้า Production/Office: ดึงจาก "ผู้ตรวจ / ประวัติ 12 เดือน" โดยตรง
 * ประวัติใช้ พ.ศ. จึงแปลงปีของวันที่ตรวจจาก ค.ศ. -> พ.ศ. ก่อนค้นหา
 * ทำความสะอาดช่องว่างและตัดชื่อซ้ำเพื่อให้ Dropdown สะอาด
 */
$auditorNames=[];
try{
  $historyYear=((int)date('Y',strtotime($date)))+543;
  $aqn=$p->prepare("SELECT auditor_name FROM auditor_monthly_history_unified WHERE history_year=? AND TRIM(auditor_name)<>'' ORDER BY auditor_name COLLATE utf8mb4_general_ci");
  $aqn->execute([$historyYear]);
  $seen=[];
  foreach($aqn as $rr){
    $nn=preg_replace('/\\s+/u',' ',trim((string)$rr['auditor_name']));
    if($nn!=='' && !isset($seen[$nn])){$seen[$nn]=true;$auditorNames[]=$nn;}
  }
  /* รักษาชื่อเดิมของใบตรวจที่เปิดอยู่ แม้ประวัติ 12 เดือนยังไม่ได้ดึงชื่อรอบนั้น */
  foreach([$auditor1Key,$auditor2Key,$auditor3Key] as $nn0){
    $nn=preg_replace('/\\s+/u',' ',trim((string)$nn0));
    if($nn!=='' && !isset($seen[$nn])){$seen[$nn]=true;$auditorNames[]=$nn;}
  }
}catch(Throwable $e){
  $auditorNames=[];
  foreach([$auditor1Key,$auditor2Key,$auditor3Key] as $nn0){
    $nn=preg_replace('/\\s+/u',' ',trim((string)$nn0));
    if($nn!=='' && !in_array($nn,$auditorNames,true))$auditorNames[]=$nn;
  }
}

if($area_id){
 $requestedAuditId=(int)($_GET['audit_id']??$_POST['audit_id']??0);
 if($requestedAuditId){
  $q=$p->prepare("SELECT * FROM audits WHERE id=? AND area_id=? AND audit_date=? AND department=? LIMIT 1");
  $q->execute([$requestedAuditId,$area_id,$date,'Office']);
 } else {
  $q=$p->prepare("SELECT * FROM audits WHERE area_id=? AND audit_date=? AND department=? ORDER BY id ASC LIMIT 1");
  $q->execute([$area_id,$date,'Office']);
 }
 $auditRow=$q->fetch();
 if($auditRow){ $q=$p->prepare("SELECT * FROM office_scores WHERE audit_id=?");$q->execute([$auditRow['id']]);foreach($q as $r)$existing[$r['criterion_id']]=$r; }
}
if($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['save_scores']??'')==='1' || !isset($_POST['save_scores']))){
 $saveError='';
 $area_id=(int)($_POST['area_id']??0);
 $date=(string)($_POST['audit_date']??'');
 $aud1=trim((string)($_POST['auditor1']??''));
 $aud2=trim((string)($_POST['auditor2']??''));
 $aud3=trim((string)($_POST['auditor3']??''));
 $period=trim((string)($_POST['period']??''));
 $overall=trim((string)($_POST['overall_note']??''));
 $saveError=five_s_audit_date_error($date);
 if($saveError==='' && !$area_id) $saveError='ไม่พบพื้นที่ / หน่วยที่ต้องการบันทึก';
 if($saveError==='' && $aud1==='') $saveError='กรุณาระบุผู้ตรวจคนที่ 1';
 if($saveError==='' && !five_s_post_has_answers($criteria)) $saveError='ยังไม่ได้บันทึก: กรุณากรอกคะแนนหรือเลือก N/A อย่างน้อย 1 ข้อ';
 if($saveError===''){
  try{
   five_s_begin_write($p);
   $ar=$p->prepare("SELECT department,group_name FROM areas WHERE id=? AND active=1 LIMIT 1");
   $ar->execute([$area_id]);$aa=$ar->fetch();
   if(!$aa) throw new RuntimeException('ไม่พบพื้นที่ / หน่วยนี้ในฐานข้อมูล');
   if(trim((string)$aa['department'])!=='Office') throw new RuntimeException('พื้นที่นี้ไม่ใช่ประเภท Office กรุณาบันทึกในหน้าที่ตรงกับประเภทของพื้นที่');
   $auditId=(int)($_POST['audit_id']??0);
   if($auditId){
    $q=$p->prepare("SELECT id FROM audits WHERE id=? AND area_id=? AND audit_date=? AND department='Office' LIMIT 1");
    $q->execute([$auditId,$area_id,$date]);
    if(!$q->fetchColumn()) $auditId=0;
   }
   if($auditId){
    $q=$p->prepare("UPDATE audits SET auditor=?,auditor2=?,auditor3=?,period=?,overall_note=?,department='Office',group_name=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
    $q->execute([$aud1,$aud2,$aud3,$period,$overall,$aa['group_name']??'',$auditId]);
    $aid=$auditId;
   }else{
    $q=$p->prepare("SELECT id FROM audits WHERE area_id=? AND audit_date=? AND department='Office' ORDER BY id ASC LIMIT 1");
    $q->execute([$area_id,$date]);$aid=(int)$q->fetchColumn();
    if($aid){
     $q=$p->prepare("UPDATE audits SET auditor=?,auditor2=?,auditor3=?,period=?,overall_note=?,group_name=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
     $q->execute([$aud1,$aud2,$aud3,$period,$overall,$aa['group_name']??'',$aid]);
    }else{
     $q=$p->prepare("INSERT INTO audits(area_id,audit_date,auditor,auditor2,auditor3,period,overall_note,department,group_name,updated_at) VALUES(?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)");
     $q->execute([$area_id,$date,$aud1,$aud2,$aud3,$period,$overall,'Office',$aa['group_name']??'']);
     $aid=(int)$p->lastInsertId();
    }
   }
   $findScore=$p->prepare("SELECT id FROM office_scores WHERE audit_id=? AND criterion_id=? LIMIT 1");
   $upd=$p->prepare("UPDATE office_scores SET score1=?,score2=?,score3=?,note=? WHERE id=?");
   $ins=$p->prepare("INSERT INTO office_scores(audit_id,criterion_id,score1,score2,score3,note) VALUES(?,?,?,?,?,?)");
   $del=$p->prepare("DELETE FROM office_scores WHERE audit_id=? AND criterion_id=?");
   foreach($criteria as $c){
    $id=(int)$c['id'];$v=[];
    for($i=1;$i<=3;$i++){
     $raw=$_POST['score'.$i][$id]??'';
     $v[$i]=$raw===''?null:max(0,min((float)$c['max_score'],(float)$raw));
    }
    $n=trim((string)($_POST['note'][$id]??''));$na=!empty($_POST['na'][$id]);
    if($na){$v=[1=>null,2=>null,3=>null];$n='N/A: ไม่ได้ตรวจ'.($n!==''?' | '.$n:'');}
    if($v[1]!==null||$v[2]!==null||$v[3]!==null||$n!==''){
     $findScore->execute([$aid,$id]);$sid=$findScore->fetchColumn();
     if($sid!==false)$upd->execute([$v[1],$v[2],$v[3],$n,(int)$sid]);
     else $ins->execute([$aid,$id,$v[1],$v[2],$v[3],$n]);
    }else{$del->execute([$aid,$id]);}
   }
   $verify=$p->prepare('SELECT COUNT(*) FROM office_scores WHERE audit_id=?');
   $verify->execute([$aid]);
   $savedItems=(int)$verify->fetchColumn();
   /* A valid save may legitimately contain 0 items when every row is blank; N/A rows are still stored. */
   if($savedItems<0) throw new RuntimeException('ตรวจสอบหลังบันทึกคะแนนไม่สำเร็จ');
   $p->commit();
   five_s_sync_after_save($p,$date);
   go('Page/auditor/office_score.php?area_id='.$area_id.'&date='.urlencode($date).'&audit_id='.$aid.'&saved=1');
  }catch(Throwable $e){
   if($p->inTransaction())$p->rollBack();
   $saveError='บันทึกไม่สำเร็จ: '.$e->getMessage();
  }
 }
}
foreach($areas as $a)if((int)$a['id']===$area_id){$selected=$a;break;}
$target=target_pct('Office');$full=array_sum(array_column($criteria,'max_score'));
/* The responsible team's auditors come first; other names stay selectable for substitutes. */
$__roster=$selected?five_s_team_roster_for_area($p,$selected,(string)$date):null;
if($__roster)$auditorNames=array_values(array_unique(array_merge($__roster['names'],$auditorNames)));
require FIVE_S_ROOT.'/includes/header.php';
?>
<div class="hero"><div class="hero-copy"><h1>ลงคะแนน <span>Office</span></h1><p>1 ใบ = 1 พื้นที่/หน่วย = ผู้ตรวจได้สูงสุด 3 คน • ใช้คะแนนชุดเดียวกับการนำเข้าไฟล์</p></div><div class="hero-right"><strong>OFFICE</strong><small>เป้าหมาย <?=h($target)?>%</small></div></div>
<div class="card p-5 mb-5">
<form method="get" class="grid md:grid-cols-7 gap-3">
<div class="md:col-span-2"><label class="text-xs font-bold">กลุ่ม / หน่วย</label><select name="area_id" class="w-full border rounded-xl p-3" required><option value="">-- เลือกกลุ่ม / หน่วย --</option><?php foreach($selectorAreas as $a):?><option value="<?=$a['id']?>" <?=$area_id===(int)$a['id']?'selected':''?>><?=h(display_area_group($a['group_name']))?> / หน่วย <?=h($a['unit_no'])?></option><?php endforeach;?></select></div>
<div><label class="text-xs font-bold">วันที่ตรวจ</label><input type="date" name="date" value="<?=h($date)?>" class="w-full border rounded-xl p-3"></div>
<div><label class="text-xs font-bold">ผู้ตรวจ 1</label><select name="auditor1" required class="w-full border rounded-xl p-3"><option value="">-- เลือกผู้ตรวจ --</option><?php foreach($auditorNames as $n):?><option value="<?=h($n)?>" <?=$auditor1Key===$n || (($auditRow['auditor']??'')===$n)?'selected':''?>><?=h($n)?></option><?php endforeach;?></select></div>
<div><label class="text-xs font-bold">ผู้ตรวจ 2</label><select name="auditor2" class="w-full border rounded-xl p-3"><option value="">-- ไม่ระบุผู้ตรวจ 2 --</option><?php foreach($auditorNames as $n):?><option value="<?=h($n)?>" <?=$auditor2Key===$n || (($auditRow['auditor2']??'')===$n)?'selected':''?>><?=h($n)?></option><?php endforeach;?></select></div>
<div><label class="text-xs font-bold">ผู้ตรวจ 3</label><select name="auditor3" class="w-full border rounded-xl p-3"><option value="">-- ไม่ระบุผู้ตรวจ 3 --</option><?php foreach($auditorNames as $n):?><option value="<?=h($n)?>" <?=$auditor3Key===$n || (($auditRow['auditor3']??'')===$n)?'selected':''?>><?=h($n)?></option><?php endforeach;?></select></div>
<div class="flex items-end"><button class="w-full bg-slate-900 text-white rounded-xl p-3 font-bold">เปิดแบบตรวจ Office</button></div>
</form>
<?php if($auditRow): ?>
<div class="mt-3 rounded-xl bg-blue-50 border border-blue-200 p-3 text-sm"><b>ใบตรวจนี้มีผู้ตรวจ:</b> <?=h($auditRow['auditor'])?><?php if(trim((string)($auditRow['auditor2']??''))!==''): ?>, <?=h($auditRow['auditor2'])?><?php endif; ?><?php if(trim((string)($auditRow['auditor3']??''))!==''): ?>, <?=h($auditRow['auditor3'])?><?php endif; ?> <span class="text-slate-500">— ใช้ใบเดียวกันและบันทึกคะแนนได้สูงสุด 3 คน</span></div>
<?php endif; ?>
</div>
<?php if(isset($_GET['saved'])):?><div class="bg-emerald-100 text-emerald-800 p-4 rounded-xl mb-4 font-bold">✅ บันทึกคะแนน Office แล้ว — Dashboard / Ranking ใช้ข้อมูลชุดเดียวกัน<?php if($auditRow): $__pg=five_s_audit_progress($p,(int)$auditRow['id'],true);?> • สถานะ: <?=$__pg['complete']?'ตรวจแล้ว':($__pg['answered']>0?'กำลังตรวจ':'ยังไม่ได้ตรวจ')?> (<?=$__pg['answered']?>/<?=$__pg['required']?> หัวข้อ)<?php endif;?></div><?php endif;?><?php if(!empty($saveError)):?><div class="bg-red-100 text-red-800 border border-red-300 p-4 rounded-xl mb-4 font-bold">❌ <?=h($saveError)?></div><?php endif;?>
<?php if($__roster):?><div class="bg-blue-50 text-blue-800 border border-blue-200 p-3 rounded-xl mb-4 font-bold">👥 ทีมที่รับผิดชอบหน่วยนี้ในเดือนนี้: ทีม <?=h($__roster['team'])?></div><?php endif;?>
<?php $__hint=$selected?five_s_plan_month_hint($p,$selected,(string)$date):null; if($__hint):?><div class="bg-amber-50 text-amber-800 border border-amber-300 p-4 rounded-xl mb-4 font-bold">⚠️ หน่วยนี้ไม่อยู่ในแผนตรวจเดือน <?=h($__hint['month'])?> — ถ้าบันทึกด้วยวันที่นี้ สถานะในแผนจะไม่เปลี่ยน<?php if($__hint['suggest']):?> • <a class="underline" href="Page/auditor/office_score.php?area_id=<?=$area_id?>&date=<?=urlencode($__hint['suggest'])?>">ใช้วันที่ตามแผน <?=h($__hint['suggest'])?></a><?php endif;?></div><?php endif;?>
<?php if($selected):?>
<form id="officeScoreForm" method="post" action="Page/auditor/office_score.php" class="card overflow-hidden"><input type="hidden" name="save_scores" value="1"><input type="hidden" name="area_id" value="<?=$area_id?>"><input type="hidden" name="audit_date" value="<?=h($date)?>"><input type="hidden" name="audit_id" value="<?=h($auditRow['id']??'')?>">
<div class="p-5 bg-slate-50 border-b grid md:grid-cols-4 gap-3"><div><b>แผนก</b><div class="text-lg font-black">Office</div></div><div><b>กลุ่ม / หน่วย</b><div class="text-lg font-black"><?=h($selected['group_name'])?> / <?=h($selected['unit_no'])?></div></div><div><b>ผู้ตรวจ</b><div class="grid grid-cols-3 gap-2"><select name="auditor1" required class="border rounded-lg p-2 w-full"><option value="">-- เลือกผู้ตรวจ --</option><?php foreach($auditorNames as $n):?><option value="<?=h($n)?>" <?=$auditor1Key===$n || (($auditRow['auditor']??'')===$n)?'selected':''?>><?=h($n)?></option><?php endforeach;?></select><select name="auditor2" class="border rounded-lg p-2 w-full"><option value="">-- ไม่ระบุผู้ตรวจ 2 --</option><?php foreach($auditorNames as $n):?><option value="<?=h($n)?>" <?=$auditor2Key===$n || (($auditRow['auditor2']??'')===$n)?'selected':''?>><?=h($n)?></option><?php endforeach;?></select><select name="auditor3" class="border rounded-lg p-2 w-full"><option value="">-- ไม่ระบุผู้ตรวจ 3 --</option><?php foreach($auditorNames as $n):?><option value="<?=h($n)?>" <?=$auditor3Key===$n || (($auditRow['auditor3']??'')===$n)?'selected':''?>><?=h($n)?></option><?php endforeach;?></select></div></div><div><b>เป้าหมาย</b><div class="text-lg font-black"><?=number_format($target,1)?>%</div></div></div>
<div class="px-5 py-3 bg-blue-50 text-blue-800 text-sm font-bold">ℹ️ 1 ใบ = 1 พื้นที่/หน่วย • ผู้ตรวจได้สูงสุด 3 คน • ช่องว่างไม่ถูกนับเป็น 0 • N/A ไม่ถูกนำมาคำนวณ</div>
<div class="overflow-x-auto"><table class="min-w-[1050px] w-full"><thead class="bg-slate-900 text-white"><tr><th class="p-3">ข้อ</th><th class="p-3 text-left min-w-[650px]">หัวข้อตรวจ Office</th><th>เต็ม</th><th>ผู้ตรวจ 1</th><th>ผู้ตรวจ 2</th><th>ผู้ตรวจ 3</th><th>เฉลี่ย %</th><th>สถานะ</th><th>หมายเหตุ</th></tr></thead><tbody>
<?php foreach($criteria as $c):$r=$existing[$c['id']]??[];$v=$r['score1']??($r['score2']??($r['score3']??null));$isNa=$v===null && isset($r['note']) && str_starts_with((string)$r['note'],'N/A:');$pc=$v===null?null:percent((float)$v,(float)$c['max_score']);?>
<tr class="border-t score-row" data-max="<?=$c['max_score']?>" data-na="<?=$isNa?'1':'0'?>"><td class="p-3 text-center font-black"><?=$c['item_no']?></td><td class="p-3 leading-6"><?=h($c['description'])?></td><td class="p-2 text-center font-black"><?=$c['max_score']?></td><td class="p-2"><input class="score score1 w-20 border rounded-lg p-2 text-center font-black" type="number" step=".5" min="0" max="<?=$c['max_score']?>" name="score1[<?=$c['id']?>]" value="<?=h($r['score1']??'')?>"></td><td class="p-2"><input class="score score2 w-20 border rounded-lg p-2 text-center font-black" type="number" step=".5" min="0" max="<?=$c['max_score']?>" name="score2[<?=$c['id']?>]" value="<?=h($r['score2']??'')?>"></td><td class="p-2"><input class="score score3 w-20 border rounded-lg p-2 text-center font-black" type="number" step=".5" min="0" max="<?=$c['max_score']?>" name="score3[<?=$c['id']?>]" value="<?=h($r['score3']??'')?>"></td><td class="p-2 text-center font-black pct">—</td><td class="p-2 text-center"><span class="status px-2 py-1 rounded-full text-xs <?= $isNa?'bg-slate-100 text-slate-500':($pc===null?'bg-amber-50 text-amber-700':status_class($pc,'Office'))?>"><?=$isNa?'N/A ไม่ได้ตรวจ':($pc===null?'ยังไม่มีคะแนน':status_text($pc,'Office'))?></span></td><td class="p-2"><div class="flex items-center gap-2"><label class="flex items-center gap-1 text-xs font-bold whitespace-nowrap"><input type="checkbox" class="na" name="na[<?=$c['id']?>]" value="1" <?=$isNa?'checked':''?>> N/A ไม่ได้ตรวจ</label><input name="note[<?=$c['id']?>]" value="<?=h($isNa?preg_replace('/^N\/A: (ไม่ได้ตรวจ)( \| )?/u','',$r['note']??''):($r['note']??''))?>" class="border rounded-lg p-2 w-56"></div></td></tr>
<?php endforeach;?></tbody><tfoot class="bg-slate-100 font-black"><tr><td colspan="2" class="p-4 text-right">รวมคะแนน</td><td class="p-4 text-center"><?=$full?></td><td colspan="3" class="p-4 text-center" id="totalScore">—</td><td id="totalPct" class="p-4 text-center">—</td><td id="totalStatus" class="p-4 text-center">—</td><td></td></tr></tfoot></table></div>
<div class="p-5 bg-slate-50 border-t"><textarea name="overall_note" rows="3" class="w-full border rounded-xl p-3" placeholder="สรุปผล / ข้อเสนอแนะ"><?=h($auditRow['overall_note']??'')?></textarea><div class="mt-3 flex justify-end gap-2"><button type="submit" id="saveOfficeBtn" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-black">💾 บันทึกคะแนน Office</button><a target="_blank" href="Page/public/print_office.php?id=<?= (int)($auditRow['id']??0) ?>&area_id=<?= $area_id ?>&date=<?=urlencode($date)?>" class="border px-5 py-3 rounded-xl font-bold">🖨 พิมพ์ A4</a><a href="index.php?department=Office" class="border px-5 py-3 rounded-xl font-bold">Dashboard</a></div></div></form>
<style>
.score:disabled{
  cursor:not-allowed;
  background:#f1f5f9 !important;
  color:#94a3b8 !important;
  opacity:.75;
}
</style>
<script>
const TARGET=<?=json_encode($target)?>;
function calc(){let total=0,maxTotal=0;document.querySelectorAll('.score-row').forEach(row=>{const mx=parseFloat(row.dataset.max),na=row.querySelector('.na')?.checked;const vals=[...row.querySelectorAll('.score')].map(x=>parseFloat(x.value)).filter(v=>!Number.isNaN(v));const avg=na||!vals.length?null:vals.reduce((a,b)=>a+b,0)/vals.length;row.querySelector('.pct').textContent=avg===null?'—':(avg/mx*100).toFixed(1)+'%';if(avg!==null){total+=avg;maxTotal+=mx;}});document.getElementById('totalScore').textContent=maxTotal?total.toFixed(2)+' / '+maxTotal.toFixed(2):'—';const pct=maxTotal?total/maxTotal*100:null;document.getElementById('totalPct').textContent=pct===null?'—':pct.toFixed(1)+'%';document.getElementById('totalStatus').textContent=pct===null?'—':(pct>=TARGET?'ผ่านเป้าหมาย':(pct>=Math.max(0,TARGET-10)?'ต่ำกว่าเป้าหมาย':'ต้องเร่งแก้ไข'));}
function syncNA(row){
 const na=row.querySelector('.na');
 const scores=row.querySelectorAll('.score');
 const on=!!(na&&na.checked);
 scores.forEach(x=>{
   x.disabled=on;
   if(on) x.value='';
   x.classList.toggle('bg-slate-100',on);
   x.classList.toggle('text-slate-400',on);
 });
 row.dataset.na=on?'1':'0';
}
document.querySelectorAll('.score-row').forEach(row=>{
 const na=row.querySelector('.na');
 if(na) na.addEventListener('change',()=>{syncNA(row);calc();});
 syncNA(row);
});
document.querySelectorAll('.score').forEach(x=>x.addEventListener('input',calc));
calc();
const officeForm=document.getElementById('officeScoreForm'),saveBtn=document.getElementById('saveOfficeBtn');
if(officeForm&&saveBtn){officeForm.addEventListener('submit',function(){
 document.querySelectorAll('.score-row').forEach(row=>{ if(row.querySelector('.na')?.checked){ row.querySelectorAll('.score').forEach(x=>x.value=''); } });
 saveBtn.disabled=true;saveBtn.textContent='⏳ กำลังบันทึก...';
});}
</script>
<?php endif;require FIVE_S_ROOT.'/includes/footer.php';?>
