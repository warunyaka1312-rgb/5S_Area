<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
$title='ผลนำเข้า 5 ส. ตามพื้นที่ / หน่วย';
$p=db();
$id=(int)($_GET['id']??0);
$q=$p->prepare("SELECT * FROM unit_import_audits WHERE id=?");$q->execute([$id]);$audit=$q->fetch();
if(!$audit)die('ไม่พบข้อมูลนำเข้า');
$kind=$audit['kind']==='office'?'office':'production';
$crTable=$kind==='office'?'office_criteria':'criteria';
$unitsQ=$p->prepare("SELECT slot,unit_no FROM unit_import_units WHERE audit_id=? ORDER BY slot");$unitsQ->execute([$id]);$units=$unitsQ->fetchAll();
$cr=$p->query("SELECT id,item_no,max_score,description FROM {$crTable} WHERE active=1 ORDER BY item_no")->fetchAll();
$q=$p->prepare("SELECT criterion_id,unit_slot,score,na,note FROM unit_import_scores WHERE audit_id=?");$q->execute([$id]);$map=[];foreach($q as $r)$map[$r['criterion_id'].'|'.$r['unit_slot']]=$r;
$target=target_pct();
require FIVE_S_ROOT.'/includes/header.php';
?>
<div class="hero"><div class="hero-copy"><h1>ผลนำเข้า <span>5 ส.</span></h1><p><?=h(strtoupper($kind))?> • 1 ใบ = ผู้ตรวจ 1 คน • ตรวจแก้คะแนนได้จากแบบลงคะแนนของแต่ละหน่วย</p></div><div class="hero-right"><strong><?=h($audit['audit_date'])?></strong><small><?=h($audit['department'])?></small></div></div>
<div class="card p-5 mb-5"><div class="grid md:grid-cols-5 gap-4 text-sm"><div><b>วันที่</b><div><?=h($audit['audit_date'])?></div></div><div><b>แผนก</b><div><?=h($audit['department'])?></div></div><div><b>ผู้ตรวจ</b><div><?=h($audit['auditor'])?></div></div><div><b>รอบ/กะ</b><div><?=h($audit['period'])?></div></div><div><b>เป้าหมาย</b><div class="font-black"><?=number_format($target,1)?>%</div></div></div></div>
<div class="card overflow-auto"><div class="p-4 bg-slate-800 text-white font-black">คะแนนตามพื้นที่ / หน่วย</div><table class="min-w-[1200px] w-full"><thead><tr class="bg-slate-100"><th class="p-2">ข้อ</th><th class="p-2 text-left min-w-[620px]">หัวข้อตรวจ</th><?php foreach($units as $u):?><th class="p-2 text-center">หน่วย <?=h($u['unit_no'])?></th><?php endforeach;?></tr></thead><tbody>
<?php foreach($cr as $c):?><tr class="border-t"><td class="p-2 text-center font-bold"><?=h($c['item_no'])?></td><td class="p-2 text-sm"><?=h($c['description'])?></td><?php foreach($units as $u):$r=$map[$c['id'].'|'.$u['slot']]??null;$v=$r['score']??null;$na=$r&&((int)$r['na']===1);?><td class="p-2 text-center font-bold"><?=$na?'—':($v===null?'':h($v))?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div>
<div class="mt-5 flex flex-wrap gap-3"><a class="border rounded-xl px-5 py-3 font-bold" href="Page/auditor/import.php">← นำเข้าไฟล์ใหม่</a><button class="bg-blue-600 text-white rounded-xl px-5 py-3 font-bold" onclick="window.print()">🖨 พิมพ์</button></div>
<?php foreach($units as $u):
  $areaQ=$p->prepare("SELECT id,area_name FROM areas WHERE department=? AND unit_no=? ORDER BY id DESC LIMIT 1");$areaQ->execute([$kind==='office'?'Office':'Production',$u['unit_no']]);$ar=$areaQ->fetch();
  if($ar):?><a class="hidden" href="Page/auditor/score.php?area_id=<?=$ar['id']?>&date=<?=urlencode($audit['audit_date'])?>"></a><?php endif;
endforeach;?>
<?php require FIVE_S_ROOT.'/includes/footer.php'; ?>
