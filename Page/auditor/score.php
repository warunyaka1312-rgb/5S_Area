<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
$title='ลงคะแนน 5 ส. Production';
$p=db();
$areas=$p->query("SELECT * FROM areas WHERE active=1 AND LOWER(TRIM(department))=LOWER('Production') ORDER BY CAST(area_code AS SIGNED),area_name")->fetchAll();
/* Area selector: PURCHASING and Billet&Scarp share one display group.
   Duplicate group+unit options are hidden, while keeping the currently selected area. */
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
$criteria=$p->query("SELECT * FROM criteria WHERE active=1 ORDER BY item_no")->fetchAll();
$area_id=(int)($_GET['area_id']??$_POST['area_id']??0);
$date=$_GET['date']??$_POST['audit_date']??date('Y-m-d');
$auditor1Key=trim((string)($_GET['auditor1']??$_GET['auditor']??$_POST['auditor1']??$_POST['auditor']??''));
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

$existing=[];$auditRow=null;
if($area_id){
 $q=$p->prepare("SELECT * FROM audits WHERE area_id=? AND audit_date=? AND department=? ORDER BY id ASC LIMIT 1");
 $q->execute([$area_id,$date,'Production']);$auditRow=$q->fetch();
 if($auditRow){ $q=$p->prepare("SELECT * FROM scores WHERE audit_id=?");$q->execute([$auditRow['id']]);foreach($q as $r)$existing[$r['criterion_id']]=$r; }
}
if($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['save_scores']??'')==='1' || !isset($_POST['save_scores']))){
 $area_id=(int)($_POST['area_id']??0);$date=(string)($_POST['audit_date']??'');
 $aud1=trim($_POST['auditor1']??'');$aud2=trim($_POST['auditor2']??'');$aud3=trim($_POST['auditor3']??'');
 $period=trim($_POST['period']??'');$overall=trim($_POST['overall_note']??'');
 $saveError=five_s_audit_date_error($date);
 if($saveError==='' && !$area_id) $saveError='กรุณาเลือกพื้นที่ / หน่วย';
 if($saveError==='' && $aud1==='') $saveError='กรุณาระบุผู้ตรวจคนที่ 1';
 if($saveError==='' && !five_s_post_has_answers($criteria)) $saveError='ยังไม่ได้บันทึก: กรุณากรอกคะแนนหรือเลือก N/A อย่างน้อย 1 ข้อ';
 if($saveError==='') try{
  five_s_begin_write($p);
  $ar=$p->prepare("SELECT department,group_name FROM areas WHERE id=?");$ar->execute([$area_id]);$aa=$ar->fetch();
  if(!$aa || trim((string)$aa['department'])!=='Production') throw new RuntimeException('พื้นที่นี้ไม่ใช่ประเภท Production กรุณาบันทึกในหน้าที่ตรงกับประเภทของพื้นที่');
  $auditId=(int)($_POST['audit_id']??0);
  if($auditId){
   $q=$p->prepare("UPDATE audits SET auditor=?,auditor2=?,auditor3=?,period=?,overall_note=?,department=?,group_name=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND area_id=? AND audit_date=?");
   $q->execute([$aud1,$aud2,$aud3,$period,$overall,'Production',$aa['group_name']??'',$auditId,$area_id,$date]);$aid=$auditId;
  } else {
   $q=$p->prepare("SELECT id FROM audits WHERE area_id=? AND audit_date=? AND department=? ORDER BY id ASC LIMIT 1");$q->execute([$area_id,$date,'Production']);$aid=(int)$q->fetchColumn();
   if($aid){ $q=$p->prepare("UPDATE audits SET auditor=?,auditor2=?,auditor3=?,period=?,overall_note=?,group_name=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");$q->execute([$aud1,$aud2,$aud3,$period,$overall,$aa['group_name']??'',$aid]); }
   else { $q=$p->prepare("INSERT INTO audits(area_id,audit_date,auditor,auditor2,auditor3,period,overall_note,department,group_name,updated_at) VALUES(?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)");$q->execute([$area_id,$date,$aud1,$aud2,$aud3,$period,$overall,'Production',$aa['group_name']??'']);$aid=(int)$p->lastInsertId(); }
  }
  $up=$p->prepare("INSERT INTO scores(audit_id,criterion_id,score1,score2,score3,note) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE score1=VALUES(score1),score2=VALUES(score2),score3=VALUES(score3),note=VALUES(note)");
  foreach($criteria as $c){ $id=$c['id'];$v=[];for($i=1;$i<=3;$i++){ $raw=$_POST['score'.$i][$id]??'';$v[$i]=$raw===''?null:max(0,min((float)$c['max_score'],(float)$raw)); }$n=trim($_POST['note'][$id]??'');$na=!empty($_POST['na'][$id]);
   if($na)$up->execute([$aid,$id,null,null,null,'N/A: ไม่ได้ตรวจ'.($n!==''?' | '.$n:'')]);
   elseif($v[1]!==null||$v[2]!==null||$v[3]!==null||$n!=='')$up->execute([$aid,$id,$v[1],$v[2],$v[3],$n]);
   else{ $del=$p->prepare("DELETE FROM scores WHERE audit_id=? AND criterion_id=?");$del->execute([$aid,$id]); }
  }
  $p->commit();
  five_s_sync_after_save($p,$date);
  go('Page/auditor/score.php?area_id='.$area_id.'&date='.urlencode($date).'&audit_id='.$aid.'&saved=1');
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();$saveError='บันทึกไม่สำเร็จ: '.$e->getMessage();}
}
$areaName='';$selected=null;
foreach($areas as $a)if((int)$a['id']===$area_id){$selected=$a;$areaName=$a['area_name'];break;}
$target=target_pct('Production');
/* The responsible team's auditors come first; other names stay selectable for substitutes. */
$__roster=$selected?five_s_team_roster_for_area($p,$selected,(string)$date):null;
if($__roster)$auditorNames=array_values(array_unique(array_merge($__roster['names'],$auditorNames)));
require FIVE_S_ROOT.'/includes/header.php';
?>
<div class="hero"><div class="hero-copy"><h1>ลงคะแนน <span>Production</span></h1><p>1 ใบ = 1 พื้นที่/หน่วย = ผู้ตรวจได้สูงสุด 3 คน • ใช้คะแนนชุดเดียวกับการนำเข้า PDF</p></div><div class="hero-right"><strong>PRODUCTION</strong><small>เป้าหมาย <?=h($target)?>%</small></div></div>
<div class="card p-5 mb-5">
<form class="grid md:grid-cols-5 gap-3">
<div class="md:col-span-2"><label class="text-xs font-bold">พื้นที่ / หน่วยงาน</label><select name="area_id" class="w-full border rounded-xl p-3" required><option value="">-- เลือกพื้นที่ --</option><?php foreach($selectorAreas as $a):?><option value="<?=$a['id']?>" <?=$area_id===(int)$a['id']?'selected':''?>><?=h(display_area_group($a['group_name'] ?: $a['area_name']))?> / หน่วย <?=h($a['unit_no'])?></option><?php endforeach;?></select></div>
<div><label class="text-xs font-bold">วันที่ตรวจ</label><input type="date" name="date" value="<?=h($date)?>" class="w-full border rounded-xl p-3"></div>
<div><label class="text-xs font-bold">ผู้ตรวจ 1</label><select name="auditor1" required class="w-full border rounded-xl p-3"><option value="">-- เลือกผู้ตรวจ --</option><?php foreach($auditorNames as $n):?><option value="<?=h($n)?>" <?=$auditor1Key===$n || (($auditRow['auditor']??'')===$n)?'selected':''?>><?=h($n)?></option><?php endforeach;?></select></div>
<div class="md:col-span-5 grid grid-cols-1 md:grid-cols-3 gap-3">
<div><label class="text-xs font-bold">ผู้ตรวจ 2</label><select name="auditor2" class="w-full border rounded-xl p-3"><option value="">-- ไม่ระบุผู้ตรวจ 2 --</option><?php foreach($auditorNames as $n):?><option value="<?=h($n)?>" <?=$auditor2Key===$n || (($auditRow['auditor2']??'')===$n)?'selected':''?>><?=h($n)?></option><?php endforeach;?></select></div>
<div><label class="text-xs font-bold">ผู้ตรวจ 3</label><select name="auditor3" class="w-full border rounded-xl p-3"><option value="">-- ไม่ระบุผู้ตรวจ 3 --</option><?php foreach($auditorNames as $n):?><option value="<?=h($n)?>" <?=$auditor3Key===$n || (($auditRow['auditor3']??'')===$n)?'selected':''?>><?=h($n)?></option><?php endforeach;?></select></div>
<div class="flex items-end"><button class="w-full bg-slate-800 text-white rounded-xl p-3 font-bold">เปิดแบบประเมิน</button></div>
</div>
</form>
<?php if($area_id): $aq=$p->prepare("SELECT id,auditor,auditor2,auditor3,audit_date,period FROM audits WHERE area_id=? AND audit_date=? AND department=? ORDER BY id"); $aq->execute([$area_id,$date,'Production']); $sameAudits=$aq->fetchAll(); if($sameAudits): ?>
<div class="mt-3 rounded-xl bg-slate-50 border p-3"><div class="font-bold text-sm mb-2">ผู้ตรวจที่ลงคะแนนพื้นที่นี้ในวันที่ <?=h($date)?>:</div><div class="flex flex-wrap gap-2"><?php foreach($sameAudits as $sa): ?><a class="px-3 py-2 rounded-lg border bg-white font-bold <?=((int)($auditRow['id']??0)===(int)$sa['id'])?'ring-2 ring-blue-500':''?>" href="Page/auditor/score.php?area_id=<?=$area_id?>&date=<?=urlencode($date)?>&auditor1=<?=urlencode($sa['auditor'])?>&auditor2=<?=urlencode($sa['auditor2']??'')?>&auditor3=<?=urlencode($sa['auditor3']??'')?>">👤 <?=h($sa['auditor'])?></a><?php endforeach; ?><span class="text-xs text-slate-500 self-center">เลือกชื่อเดิมเพื่อแก้ไข หรือพิมพ์ชื่อใหม่เพื่อสร้างใบตรวจอีก 1 ใบ</span></div></div>
<?php endif; endif; ?>
</form></div>
<?php if(isset($_GET['saved'])):?><div class="bg-emerald-100 text-emerald-800 p-4 rounded-xl mb-4 font-bold">✅ บันทึกคะแนนเรียบร้อยแล้ว — ข้อมูลนี้จะไปใช้ใน Dashboard / Ranking / รายงานชุดเดียวกัน<?php if($auditRow): $__pg=five_s_audit_progress($p,(int)$auditRow['id'],false);?> • สถานะ: <?=$__pg['complete']?'ตรวจแล้ว':($__pg['answered']>0?'กำลังตรวจ':'ยังไม่ได้ตรวจ')?> (<?=$__pg['answered']?>/<?=$__pg['required']?> หัวข้อ)<?php endif;?></div><?php endif;?>
<?php if(!empty($saveError)):?><div class="bg-red-100 text-red-800 border border-red-300 p-4 rounded-xl mb-4 font-bold">❌ <?=h($saveError)?></div><?php endif;?>
<?php if($__roster):?><div class="bg-blue-50 text-blue-800 border border-blue-200 p-3 rounded-xl mb-4 font-bold">👥 ทีมที่รับผิดชอบหน่วยนี้ในเดือนนี้: ทีม <?=h($__roster['team'])?></div><?php endif;?>
<?php $__hint=$selected?five_s_plan_month_hint($p,$selected,(string)$date):null; if($__hint):?><div class="bg-amber-50 text-amber-800 border border-amber-300 p-4 rounded-xl mb-4 font-bold">⚠️ หน่วยนี้ไม่อยู่ในแผนตรวจเดือน <?=h($__hint['month'])?> — ถ้าบันทึกด้วยวันที่นี้ สถานะในแผนจะไม่เปลี่ยน<?php if($__hint['suggest']):?> • <a class="underline" href="Page/auditor/score.php?area_id=<?=$area_id?>&date=<?=urlencode($__hint['suggest'])?>">ใช้วันที่ตามแผน <?=h($__hint['suggest'])?></a><?php endif;?></div><?php endif;?>
<?php if($selected):?>
<form method="post" class="card overflow-hidden" id="scoreForm"><input type="hidden" name="save_scores" value="1">
<input type="hidden" name="area_id" value="<?=$area_id?>"><input type="hidden" name="audit_date" value="<?=h($date)?>"><input type="hidden" name="audit_id" value="<?=h($auditRow['id']??'')?>">
<div class="p-5 border-b bg-slate-50 grid md:grid-cols-4 gap-3">
<div><b>พื้นที่:</b><div class="text-lg font-black"><?=h($areaName)?></div></div>
<div><b>ผู้ตรวจ:</b><div class="grid grid-cols-3 gap-2"><select name="auditor1" required class="border rounded-lg p-2 w-full"><option value="">-- เลือกผู้ตรวจ --</option><?php foreach($auditorNames as $n):?><option value="<?=h($n)?>" <?=$auditor1Key===$n || (($auditRow['auditor']??'')===$n)?'selected':''?>><?=h($n)?></option><?php endforeach;?></select><select name="auditor2" class="border rounded-lg p-2 w-full"><option value="">-- ไม่ระบุผู้ตรวจ 2 --</option><?php foreach($auditorNames as $n):?><option value="<?=h($n)?>" <?=$auditor2Key===$n || (($auditRow['auditor2']??'')===$n)?'selected':''?>><?=h($n)?></option><?php endforeach;?></select><select name="auditor3" class="border rounded-lg p-2 w-full"><option value="">-- ไม่ระบุผู้ตรวจ 3 --</option><?php foreach($auditorNames as $n):?><option value="<?=h($n)?>" <?=$auditor3Key===$n || (($auditRow['auditor3']??'')===$n)?'selected':''?>><?=h($n)?></option><?php endforeach;?></select></div></div>
<div><b>รอบ/กะ:</b><input name="period" value="<?=h($auditRow['period']??'')?>" class="border rounded-lg p-2 w-full" placeholder="เช่น 16.00-17.00"></div>
<div><b>เป้าหมาย:</b><div class="text-lg font-black"><?=number_format($target,1)?>%</div></div>
</div>
<div class="px-5 py-3 bg-blue-50 text-blue-800 text-sm font-bold border-b">ℹ️ ลงคะแนน 1 คนต่อ 1 ใบ • ช่องที่ไม่มีพื้นที่ตรวจให้เว้นว่าง ไม่คิดเป็น 0 • คะแนนจะคำนวณเฉพาะข้อที่มีคะแนน</div>
<div class="overflow-x-auto"><table class="min-w-[1050px] w-full"><thead class="bg-slate-800 text-white"><tr>
<th class="p-3 w-16">ข้อ</th><th class="p-3 text-left min-w-[650px]">หัวข้อตรวจ</th><th>เต็ม</th><th>ผู้ตรวจ 1</th><th>ผู้ตรวจ 2</th><th>ผู้ตรวจ 3</th><th>เฉลี่ย %</th><th>สถานะ</th><th>หมายเหตุ</th></tr></thead><tbody>
<?php foreach($criteria as $c):
$r=$existing[$c['id']]??[];$v=$r['score1']??($r['score2']??($r['score3']??null));$isNa=$v===null && isset($r['note']) && str_starts_with((string)$r['note'],'N/A:');$pc=$v===null?null:percent((float)$v,(float)$c['max_score']);?>
<tr class="border-t score-row" data-max="<?=$c['max_score']?>" data-na="<?=$isNa?'1':'0'?>">
<td class="p-2 text-center font-bold"><?=$c['item_no']?></td>
<td class="p-3 leading-6 text-slate-700"><?=h($c['description'])?></td>
<td class="p-2 text-center font-bold"><?=$c['max_score']?></td>
<td class="p-2"><input class="score score1 w-20 border rounded-lg p-2 text-center font-black" type="number" step=".5" min="0" max="<?=$c['max_score']?>" name="score1[<?=$c['id']?>]" value="<?=h($r['score1']??'')?>"></td>
<td class="p-2"><input class="score score2 w-20 border rounded-lg p-2 text-center font-black" type="number" step=".5" min="0" max="<?=$c['max_score']?>" name="score2[<?=$c['id']?>]" value="<?=h($r['score2']??'')?>"></td>
<td class="p-2"><input class="score score3 w-20 border rounded-lg p-2 text-center font-black" type="number" step=".5" min="0" max="<?=$c['max_score']?>" name="score3[<?=$c['id']?>]" value="<?=h($r['score3']??'')?>"></td>
<td class="p-2 text-center font-black pct">—</td>
<td class="p-2 text-center"><span class="status px-2 py-1 rounded-full text-xs <?= $isNa?'bg-slate-100 text-slate-500':($pc===null?'bg-amber-50 text-amber-700':status_class($pc,'Production'))?>"><?=$isNa?'N/A ไม่ได้ตรวจ':($pc===null?'ยังไม่มีคะแนน':status_text($pc,'Production'))?></span></td>
<td class="p-2"><div class="flex items-center gap-2"><label class="flex items-center gap-1 text-xs font-bold whitespace-nowrap"><input type="checkbox" class="na" name="na[<?=$c['id']?>]" value="1" <?=$isNa?'checked':''?>> N/A ไม่ได้ตรวจ</label><input name="note[<?=$c['id']?>]" value="<?=h($isNa?preg_replace('/^N\/A: (ไม่ได้ตรวจ)( \| )?/u','',$r['note']??''):($r['note']??''))?>" class="border rounded-lg p-2 w-56"></div></td>
</tr><?php endforeach;?></tbody>
<tfoot class="bg-slate-100 font-black"><tr><td colspan="2" class="p-4 text-right">รวมคะแนน</td><td class="p-4 text-center"><?=number_format(array_sum(array_column($criteria,'max_score')),0)?></td><td colspan="3" class="p-4 text-center" id="totalScore">—</td><td id="totalPct" class="p-4 text-center">—</td><td id="totalStatus" class="p-4 text-center">—</td><td></td></tr></tfoot>
</table></div>
<div class="p-5 border-t bg-slate-50"><textarea name="overall_note" rows="3" class="w-full border rounded-xl p-3" placeholder="สรุปผล / ข้อเสนอแนะ"><?=h($auditRow['overall_note']??'')?></textarea>
<div class="mt-3 flex flex-wrap justify-end gap-2"><div class="mr-auto text-sm text-slate-500">หมายเหตุ: คะแนนเต็มที่ใช้จริงจะนับเฉพาะข้อที่มีคะแนน</div><button type="submit" id="saveScoreBtn" class="bg-blue-600 text-white px-7 py-3 rounded-xl font-black">💾 บันทึกคะแนน</button><a target="_blank" href="Page/public/print.php?area_id=<?=$area_id?>&date=<?=urlencode($date)?>" class="border px-5 py-3 rounded-xl font-bold">🖨 พิมพ์ A4</a></div></div>
</form>
<style>
.score:disabled{cursor:not-allowed;background:#f1f5f9!important;color:#94a3b8!important;opacity:.75}
</style>
<script>
const TARGET=<?=json_encode($target)?>;
function calc(){let total=0,maxTotal=0;document.querySelectorAll('.score-row').forEach(row=>{const mx=parseFloat(row.dataset.max),na=row.querySelector('.na')?.checked;const vals=[...row.querySelectorAll('.score')].map(x=>parseFloat(x.value)).filter(v=>!Number.isNaN(v));const avg=na||!vals.length?null:vals.reduce((a,b)=>a+b,0)/vals.length;row.querySelector('.pct').textContent=avg===null?'—':(avg/mx*100).toFixed(1)+'%';if(avg!==null){total+=avg;maxTotal+=mx;}});document.getElementById('totalScore').textContent=maxTotal?total.toFixed(2)+' / '+maxTotal.toFixed(2):'—';const pct=maxTotal?total/maxTotal*100:null;document.getElementById('totalPct').textContent=pct===null?'—':pct.toFixed(1)+'%';document.getElementById('totalStatus').textContent=pct===null?'—':(pct>=TARGET?'ผ่านเป้าหมาย':(pct>=Math.max(0,TARGET-10)?'ต่ำกว่าเป้าหมาย':'ต้องเร่งแก้ไข'));}
function syncNA(row){
 const na=row.querySelector('.na');
 const scores=row.querySelectorAll('.score');
 const status=row.querySelector('.status');
 const pct=row.querySelector('.pct');
 const on=!!(na&&na.checked);
 scores.forEach(x=>{
   x.disabled=on;
   if(on) x.value='';
   x.classList.toggle('bg-slate-100',on);
   x.classList.toggle('text-slate-400',on);
 });
 row.dataset.na=on?'1':'0';
 if(on){
   if(pct) pct.textContent='—';
   if(status){status.textContent='N/A ไม่ได้ตรวจ';status.className='status px-2 py-1 rounded-full text-xs bg-slate-100 text-slate-500';}
 }
}
document.querySelectorAll('.score-row').forEach(row=>{
 const na=row.querySelector('.na');
 if(na) na.addEventListener('change',()=>{syncNA(row);calc();});
 syncNA(row);
});
document.querySelectorAll('.score').forEach(x=>x.addEventListener('input',calc));
calc();
const scoreForm=document.getElementById('scoreForm'),saveScoreBtn=document.getElementById('saveScoreBtn');
if(scoreForm&&saveScoreBtn){scoreForm.addEventListener('submit',function(){saveScoreBtn.disabled=true;saveScoreBtn.textContent='⏳ กำลังบันทึก...';});}
</script>
<?php endif;require FIVE_S_ROOT.'/includes/footer.php';?>
