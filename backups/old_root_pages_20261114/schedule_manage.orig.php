<?php
require_once 'config.php';
$month=(int)($_GET['month']??9); $year=(int)($_GET['year']??2569);
$p=db(); $notice='';
function pv($k){return trim($_POST[$k]??'');}
function unit_count($area){
  $parts=array_filter(array_map('trim', explode(',', (string)$area)), fn($x)=>$x!=='');
  return count($parts);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  $a=$_POST['action']??'';
  if($a==='quick_date'){
    $id=(int)($_POST['id']??0); $date=pv('inspection_date');
    $st=$p->prepare("UPDATE audit_plans SET inspection_date=?,updated_at=CURRENT_TIMESTAMP WHERE id=?"); $ok=$st->execute([$date,$id]);
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>$ok,'date'=>$date],JSON_UNESCAPED_UNICODE); exit;
  }
  if($a==='quick_field'){
    $id=(int)($_POST['id']??0); $field=$_POST['field']??''; $value=pv('value');
    $allowed=['auditor_name','department','inspection_time','area_5s'];
    if(!in_array($field,$allowed,true)){ header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false]); exit; }
    if($field==='area_5s'){
      $units=unit_count($value);
      $st=$p->prepare("UPDATE audit_plans SET area_5s=?,unit_count=?,updated_at=CURRENT_TIMESTAMP WHERE id=?"); $ok=$st->execute([$value,$units,$id]);
    }else{
      $st=$p->prepare("UPDATE audit_plans SET $field=?,updated_at=CURRENT_TIMESTAMP WHERE id=?"); $ok=$st->execute([$value,$id]);
    }
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>$ok ?? true,'units'=>$field==='area_5s'?unit_count($value):null],JSON_UNESCAPED_UNICODE); exit;
  }
  if($a==='save_row'){
    $id=(int)($_POST['id']??0);
    $area=pv('area_5s'); $units=unit_count($area);
    $st=$p->prepare("UPDATE audit_plans SET auditor_name=?,department=?,inspection_date=?,inspection_time=?,area_5s=?,unit_count=?,note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
    $ok=$st->execute([pv('auditor_name'),pv('department'),pv('inspection_date'),pv('inspection_time'),$area,$units,pv('note'),$id]);
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>$ok,'id'=>$id,'units'=>$units],JSON_UNESCAPED_UNICODE); exit;
  }
  if($a==='insert_row'){
    $team=pv('team'); $after=(int)($_POST['after_id']??0);
    $q=$p->prepare("SELECT COALESCE(MAX(sort_no),0) FROM audit_plans WHERE month=? AND year=? AND team=?");
    $q->execute([$month,$year,$team]); $sort=(int)$q->fetchColumn()+1;
    if($after){
      $q=$p->prepare("SELECT sort_no FROM audit_plans WHERE id=?"); $q->execute([$after]); $base=$q->fetchColumn();
      if($base!==false){ $sort=(int)$base+1; $p->prepare("UPDATE audit_plans SET sort_no=sort_no+1 WHERE month=? AND year=? AND team=? AND sort_no>=?")->execute([$month,$year,$team,$sort]); }
    }
    $st=$p->prepare("INSERT INTO audit_plans(month,year,team,auditor_name,department,inspection_date,inspection_time,area_5s,unit_count,sort_no) VALUES(?,?,?,?,?,?,?,?,?,?)");
    $ok=$st->execute([$month,$year,$team,'','', '', '16:00 - 17:00','',0,$sort]);
    if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) || (($_POST['ajax']??'')==='1')){ header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>$ok,'id'=>$p->lastInsertId()],JSON_UNESCAPED_UNICODE); exit; }
    $notice='เพิ่มแถวใหม่แล้ว กรุณากรอกข้อมูลในแถวที่เพิ่ม';
  }
  if($a==='save'){
    $id=(int)($_POST['id']??0);
    $area= pv('area_5s'); $autoUnits = unit_count($area); $vals=[pv('team'),pv('auditor_name'),pv('department'),pv('inspection_date'),pv('inspection_time'),$area,$autoUnits,pv('note')];
    if($id){
      $q=$p->prepare("UPDATE audit_plans SET team=?,auditor_name=?,department=?,inspection_date=?,inspection_time=?,area_5s=?,unit_count=?,note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
      $q->execute([...$vals,$id]); $notice='แก้ไขข้อมูลเรียบร้อย';
    }else{
      $q=$p->prepare("INSERT INTO audit_plans(month,year,team,auditor_name,department,inspection_date,inspection_time,area_5s,unit_count,note) VALUES(?,?,?,?,?,?,?,?,?,?)");
      $q->execute([$month,$year,...$vals]); $notice='เพิ่มรายการเรียบร้อย';
    }
  }elseif($a==='delete'){
    $p->prepare("UPDATE audit_plans SET active=0 WHERE id=?")->execute([(int)$_POST['id']]); $notice='ลบรายการเรียบร้อย';
  }
}
$q=$p->prepare("SELECT * FROM audit_plans WHERE active=1 AND month=? AND year=? ORDER BY CASE team WHEN 'B' THEN 1 ELSE 2 END, sort_no,id");
$q->execute([$month,$year]); $rows=$q->fetchAll();
$B=array_values(array_filter($rows,fn($r)=>$r['team']==='B'));
$A=array_values(array_filter($rows,fn($r)=>$r['team']==='A'));
$totalB=array_sum(array_map(fn($r)=>unit_count($r['area_5s']??''),$B));
$totalA=array_sum(array_map(fn($r)=>unit_count($r['area_5s']??''),$A));
$peopleB=count(array_filter($B,fn($r)=>trim($r['auditor_name']??'')!==''));
$peopleA=count(array_filter($A,fn($r)=>trim($r['auditor_name']??'')!==''));
$months=[1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
function dcls($d){$day=(int)substr($d,-2); return $day===22?'d22':($day===23?'d23':($day===24?'d24':($day===25?'d25':'')));}
require 'includes/header.php';
?>
<style>
.v16-wrap{max-width:1240px;margin:0 auto;padding-bottom:40px}
.v16-title{display:flex;align-items:center;gap:12px;color:#173b70;font-weight:950;font-size:30px;margin:8px 0 12px}
.v16-title .cal{background:#ff5a00;color:#fff;border-radius:12px;padding:8px 11px}
.v16-filter{background:#fff;border:1px solid #dbe3ee;border-radius:14px;padding:14px;box-shadow:0 4px 14px rgba(15,23,42,.06)}
.v16-filter select,.v16-filter input{border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;background:#fff}
.v16-btn{border:0;border-radius:8px;padding:9px 13px;font-weight:800;color:#fff;cursor:pointer}
.orange{background:#ff5a00}.blue{background:#1677e8}.green{background:#0b9b58}.red{background:#ef4444}.purple{background:#7c3aed}
.poster{background:#fff;border:2px solid #ff8a00;border-radius:16px;overflow:hidden;margin-top:16px;box-shadow:0 8px 24px rgba(15,23,42,.08)}
.poster-head{position:relative;background:linear-gradient(120deg,#ff7a00,#ff4d00 55%,#ff9a00);color:#fff;padding:18px 24px;display:flex;align-items:center;gap:18px}
.poster-logo{font-size:70px;font-weight:1000;line-height:.8;color:#fff;text-shadow:3px 3px 0 #173b70;letter-spacing:-7px}
.poster-copy{flex:1}.poster-copy .slogan{font-size:23px;font-weight:900}.poster-copy .small{font-size:13px;margin-top:4px}
.poster-month{background:#173b70;border-radius:10px;padding:9px 18px;font-size:25px;font-weight:950;white-space:nowrap}
.teambar{color:#fff;font-size:25px;font-weight:950;padding:9px 15px;display:flex;justify-content:space-between;align-items:center}
.team-b{background:linear-gradient(90deg,#ff4d00,#ff7a00)} .team-a{background:linear-gradient(90deg,#0b4f9c,#1769d1)}
.v16table{width:100%;border-collapse:collapse;font-size:12px}
.v16table th{background:#ff5a00;color:#fff;padding:8px 5px;border:1px solid #fff}
.team-a-box .v16table th{background:#173b70}
.v16table td{border:1px solid #64748b;padding:7px 5px;text-align:center;background:#fff}
.v16table td.name,.v16table td.dept{text-align:left}
.v16table .d22{background:#fff14a}.v16table .d23{background:#31a9e6}.v16table .d24{background:#fff14a}.v16table .d25{background:#ef86d2}
.action{display:flex;gap:4px;justify-content:center}
.iconbtn{border:0;border-radius:5px;color:#fff;padding:4px 7px;font-weight:900;cursor:pointer}.save{background:#0b9b58}.add{background:#16a34a}.edit{background:#1677e8}.trash{background:#ef4444}
.cell-edit{width:100%;border:1px solid #d7dee8;border-radius:6px;background:#fff;padding:5px 6px;color:#173b70;font-weight:650}.cell-edit:focus{outline:2px solid #ff7a00;border-radius:4px;background:#fff}.time-edit{text-align:center}.area-edit{text-align:center}.cell-saved{box-shadow:inset 0 0 0 2px #16a34a}

.v16-footer{display:flex;justify-content:space-around;align-items:center;padding:16px;background:linear-gradient(#fff,#fff7ed);border-top:1px solid #f97316}
.v16-footer .fitem{text-align:center;font-weight:900;color:#173b70}.v16-footer .circle{margin:auto auto 5px;width:46px;height:46px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:22px;background:#ff5a00}
.no-print{}
@media(max-width:900px){.poster-month{font-size:18px}.poster-logo{font-size:48px}.poster-copy .slogan{font-size:17px}.v16table{font-size:10px}.v16-title{font-size:23px}}
@media print{
 @page{size:A4 portrait;margin:5mm}
 body{background:#fff!important}.no-print,.v16-title,.v16-filter,.sidebar,.topbar{display:none!important}
 .v16-wrap{max-width:none;padding:0}.poster{border:0;box-shadow:none;margin:0}.poster-head{padding:10px 14px}
 .poster-logo{font-size:45px}.poster-copy .slogan{font-size:16px}.poster-month{font-size:18px}
 .teambar{font-size:17px;padding:6px 10px}.v16table{font-size:8px}.v16table td{padding:4px 3px}.v16table th{padding:5px 3px}
 .v16-footer{padding:8px}.v16-footer .circle{width:30px;height:30px;font-size:14px}.v16-footer .fitem{font-size:9px}
}

.multi-note{font-size:11px;color:#64748b;margin-top:3px}
.auto-unit{font-weight:950;color:#173b70}

.row-date{border:1px solid #d7dee8;border-radius:6px;background:#fff;width:100%;min-width:125px;text-align:center;font-weight:800;color:#173b70;padding:4px}
.row-date:focus{outline:2px solid #ff7a00;border-radius:5px}
.date-saving{opacity:.45}
.date-ok{box-shadow:inset 0 0 0 2px #16a34a}
</style>

<div class="v16-wrap">
  <div class="v16-title"><span class="cal">📅</span> แผนการตรวจ 5 ส. ประจำเดือน <?=$months[$month]?> <?=$year?></div>

  <div class="v16-filter no-print">
    <form method="get" class="flex flex-wrap gap-2 items-end">
      <div><label class="text-xs font-bold">ประเภท</label><select name="type"><option>Production</option><option>Office</option></select></div>
      <div><label class="text-xs font-bold">เดือน</label><select name="month"><?php foreach($months as $m=>$n):?><option value="<?=$m?>" <?=$month==$m?'selected':''?>><?=$n?></option><?php endforeach;?></select></div>
      <div><label class="text-xs font-bold">ปี</label><select name="year"><option value="2569" <?=$year==2569?'selected':''?>>2569</option><option value="2570">2570</option></select></div>
      <div class="relative">
<label class="text-xs font-bold">แผนก (เลือกได้หลายแผนก)</label>
<button type="button" class="border rounded-lg px-3 py-2 bg-white" onclick="toggleBox('deptBox')">เลือกแผนก ▾</button>
<div id="deptBox" class="hidden absolute z-40 bg-white border rounded-xl shadow-xl p-3 w-64 max-h-64 overflow-auto">
<label class="block"><input type="checkbox" checked> ทั้งหมด</label>
<?php $depts=array_values(array_unique(array_filter(array_map(fn($r)=>trim($r['department']),$rows)))); foreach($depts as $d):?>
<label class="block py-1"><input type="checkbox" class="dept-filter" value="<?=h($d)?>"> <?=h($d)?></label>
<?php endforeach;?>
</div></div>
<div class="relative">
<label class="text-xs font-bold">พื้นที่ 5 ส. (เลือกได้หลายพื้นที่)</label>
<button type="button" class="border rounded-lg px-3 py-2 bg-white" onclick="toggleBox('areaBox')">เลือกพื้นที่ ▾</button>
<div id="areaBox" class="hidden absolute z-40 bg-white border rounded-xl shadow-xl p-3 w-64 max-h-64 overflow-auto">
<label class="block"><input type="checkbox" checked> ทั้งหมด</label>
<?php
$areas=[];
foreach($rows as $rr){foreach(array_filter(array_map('trim',explode(',',$rr['area_5s']??''))) as $aa){$areas[$aa]=1;}}
foreach(array_keys($areas) as $aa):?>
<label class="block py-1"><input type="checkbox" class="area-filter" value="<?=h($aa)?>"> <?=h($aa)?></label>
<?php endforeach;?>
</div></div>
      <button class="v16-btn orange">🔎 แสดงข้อมูล</button>
      <button type="button" class="v16-btn green" onclick="saveAllRows()">💾 บันทึกทั้งหมด</button>
      <button type="button" class="v16-btn blue" onclick="openModal()">＋ เพิ่มรายการ</button>
      <a class="v16-btn blue" href="schedule_print.php?month=<?=$month?>&year=<?=$year?>" target="_blank">🖨 พิมพ์ (A4)</a>
      <a class="v16-btn blue" href="schedule_print.php?month=<?=$month?>&year=<?=$year?>&size=A3" target="_blank">🖨 พิมพ์ (A3)</a>
      <a class="v16-btn red" href="schedule_print.php?month=<?=$month?>&year=<?=$year?>&line=1" target="_blank">📄 ส่งออก</a>
      <button type="button" class="v16-btn green" onclick="copyLine()">💬 คัดลอกข้อความ LINE</button>
    </form>
  </div>

  <?php if($notice):?><div class="no-print" style="display:flex;justify-content:flex-end;margin-bottom:6px"><a href="schedule_manage.php?month=<?= $month ?>&year=<?= $year ?>" style="background:#1677e8;color:#fff;padding:8px 14px;border-radius:8px;font-weight:900;text-decoration:none">✏️ ไปหน้าแก้ไขข้อมูล</a></div><div id="schedule-manage-note" class="no-print" style="background:#fff7ed;border:1px solid #fdba74;border-radius:10px;padding:8px 12px;margin-bottom:10px;color:#9a3412;font-weight:800">✏️ โหมดแก้ไข: คลิกในช่อง <b>ชื่อ / แผนก / วันที่ / เวลา / พื้นที่ 5 ส.</b> เพื่อแก้ไขได้ทันที และใช้ปุ่ม ＋ เพื่อเพิ่มแถว</div><div class="no-print bg-emerald-100 text-emerald-800 p-3 rounded-xl mt-3 font-bold">✓ <?=h($notice)?></div><?php endif;?>

  <div class="poster" id="poster">
    <div class="poster-head">
      <div class="poster-logo">5ส.</div>
      <div class="poster-copy"><div class="slogan">สะอาด สะดวก สะอาด • สุขลักษณะ สร้างนิสัย</div><div class="small">“พื้นที่ดี...เริ่มที่เรา”</div></div>
      <div class="poster-month">📅 แผนการตรวจ 5 ส. ประจำเดือน<br><strong><?=$months[$month]?> <?=$year?></strong></div>
    </div>

    <div class="team-b-box">
      <div class="teambar team-b"><span>👥 Auditor Team B</span><span>ตรวจวันนี้... เพื่อพรุ่งนี้ที่ดีกว่า <button class="v16-btn green no-print" onclick="insertAfter('B',<?=!empty($B)?(int)$B[count($B)-1]['id']:0?>)">＋ เพิ่มแถว Team B</button></span></div>
      <table class="v16table"><thead><tr><th>ลำดับ</th><th>ชื่อ - สกุล</th><th>แผนก</th><th>วันที่ตรวจ</th><th>เวลา</th><th>หน่วยพื้นที่ 5 ส.</th><th>หน่วย</th><th class="no-print">จัดการ</th></tr></thead><tbody>
      <?php foreach($B as $i=>$r):?><tr><td><?=$i+1?></td><td class="name"><input class="cell-edit" value="<?=h($r['auditor_name'])?>" data-id="<?=$r['id']?>" data-field="auditor_name" onchange="saveCell(this)"></td><td class="dept"><input class="cell-edit" value="<?=h($r['department'])?>" data-id="<?=$r['id']?>" data-field="department" onchange="saveCell(this)"></td><td class="<?=dcls($r['inspection_date'])?>"><input type="date" class="row-date" value="<?=h($r['inspection_date'])?>" data-id="<?=$r['id']?>" onchange="saveRowDate(this)"></td><td><input class="cell-edit time-edit" value="<?=h($r['inspection_time'])?>" data-id="<?=$r['id']?>" data-field="inspection_time" onchange="saveCell(this)"></td><td><input class="cell-edit area-edit" value="<?=h($r['area_5s'])?>" data-id="<?=$r['id']?>" data-field="area_5s" onchange="saveCell(this)"></td><td><strong id="units-<?=$r['id']?>"><?=unit_count($r['area_5s'])?></strong></td><td class="no-print action"><button class="iconbtn add" title="เพิ่มแถวหลังรายการนี้" onclick="insertAfter('<?=h($r['team'])?>',<?=$r['id']?>)">＋</button><button class="iconbtn save" title="บันทึกแถวนี้" onclick="saveRow(<?=$r['id']?>,this)">💾</button><button class="iconbtn edit" onclick='editRow(<?=json_encode($r,JSON_UNESCAPED_UNICODE)?>)'>✎</button><form method="post"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="iconbtn trash" onclick="return confirm('ลบรายการนี้?')">🗑</button></form></td></tr><?php endforeach;?>
      </tbody></table>
    </div>

    <div class="team-a-box mt-3">
      <div class="teambar team-a"><span>👥 Auditor Team A</span><span>“จุดเล็กๆ ที่เราใส่ใจ เปลี่ยนพื้นที่ทำงานให้ดีขึ้นได้เสมอ” <button class="v16-btn green no-print" onclick="insertAfter('A',<?=!empty($A)?(int)$A[count($A)-1]['id']:0?>)">＋ เพิ่มแถว Team A</button></span></div>
      <table class="v16table"><thead><tr><th>ลำดับ</th><th>ชื่อ - สกุล</th><th>แผนก</th><th>วันที่ตรวจ</th><th>เวลา</th><th>หน่วยพื้นที่ 5 ส.</th><th>หน่วย</th><th class="no-print">จัดการ</th></tr></thead><tbody>
      <?php foreach($A as $i=>$r):?><tr><td><?=$i+1?></td><td class="name"><input class="cell-edit" value="<?=h($r['auditor_name'])?>" data-id="<?=$r['id']?>" data-field="auditor_name" onchange="saveCell(this)"></td><td class="dept"><input class="cell-edit" value="<?=h($r['department'])?>" data-id="<?=$r['id']?>" data-field="department" onchange="saveCell(this)"></td><td class="<?=dcls($r['inspection_date'])?>"><input type="date" class="row-date" value="<?=h($r['inspection_date'])?>" data-id="<?=$r['id']?>" onchange="saveRowDate(this)"></td><td><input class="cell-edit time-edit" value="<?=h($r['inspection_time'])?>" data-id="<?=$r['id']?>" data-field="inspection_time" onchange="saveCell(this)"></td><td><input class="cell-edit area-edit" value="<?=h($r['area_5s'])?>" data-id="<?=$r['id']?>" data-field="area_5s" onchange="saveCell(this)"></td><td><strong id="units-<?=$r['id']?>"><?=unit_count($r['area_5s'])?></strong></td><td class="no-print action"><button class="iconbtn add" title="เพิ่มแถวหลังรายการนี้" onclick="insertAfter('<?=h($r['team'])?>',<?=$r['id']?>)">＋</button><button class="iconbtn save" title="บันทึกแถวนี้" onclick="saveRow(<?=$r['id']?>,this)">💾</button><button class="iconbtn edit" onclick='editRow(<?=json_encode($r,JSON_UNESCAPED_UNICODE)?>)'>✎</button><form method="post"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="iconbtn trash" onclick="return confirm('ลบรายการนี้?')">🗑</button></form></td></tr><?php endforeach;?>
      </tbody></table>
    </div>
    <div class="v16-footer">
      <div class="fitem"><div class="circle">▥</div>ประสิทธิภาพ</div><div class="fitem"><div class="circle" style="background:#ef4444">⚙</div>คุณภาพงาน</div><div class="fitem"><div class="circle" style="background:#16a34a">✓</div>ความปลอดภัย</div><div class="fitem"><div class="circle" style="background:#1677e8">👥</div>ทีมเวิร์ค</div><div class="fitem"><div class="circle" style="background:#7c3aed">◒</div>องค์กรยั่งยืน</div>
    </div>
  </div>
</div>

<div id="modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 no-print"><form method="post" class="bg-white rounded-2xl p-6 w-[95%] max-w-2xl"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id"><h2 class="text-2xl font-black mb-4">เพิ่ม / แก้ไขรายการตรวจ 5 ส.</h2><div class="grid md:grid-cols-2 gap-3">
<div><label>ทีม</label><select name="team" id="f_team" class="w-full border rounded-lg p-2"><option value="A">Team A</option><option value="B">Team B</option></select></div>
<div><label>ชื่อ-สกุล</label><input name="auditor_name" id="f_name" class="w-full border rounded-lg p-2"></div>
<div><label>แผนก</label><input name="department" id="f_dept" class="w-full border rounded-lg p-2"></div>
<div><label>วันที่ตรวจ</label><input name="inspection_date" id="f_date" type="date" class="w-full border rounded-lg p-2"></div>
<div><label>เวลา</label><input name="inspection_time" id="f_time" class="w-full border rounded-lg p-2"></div>
<div><label>หน่วยพื้นที่ 5 ส.</label><input name="area_5s" id="f_area" class="w-full border rounded-lg p-2"></div>
<div><label>จำนวนหน่วย <span class="text-orange-600">(คำนวณอัตโนมัติ)</span></label>
<input id="f_units" type="number" readonly class="w-full border rounded-lg p-2 bg-slate-100 font-bold">
<div class="multi-note">เช่น 12,13,17 = 3 หน่วย</div></div>
<div><label>หมายเหตุ</label><input name="note" id="f_note" class="w-full border rounded-lg p-2"></div>
</div><div class="flex justify-end gap-2 mt-5"><button type="button" onclick="closeModal()" class="px-4 py-2 border rounded-lg">ยกเลิก</button><button class="v16-btn orange">บันทึก</button></div></form></div>

<script>
function toggleBox(id){document.getElementById(id).classList.toggle('hidden')}
document.addEventListener('click',function(e){
  if(!e.target.closest('#deptBox') && !e.target.closest('#areaBox') && !e.target.closest('button[onclick^="toggleBox"]')){
    document.getElementById('deptBox')?.classList.add('hidden');
    document.getElementById('areaBox')?.classList.add('hidden');
  }
});

function openModal(){document.getElementById('modal').classList.remove('hidden');document.getElementById('modal').classList.add('flex');document.getElementById('f_id').value='';['f_name','f_dept','f_date','f_time','f_area','f_units','f_note'].forEach(id=>document.getElementById(id).value='');}
function closeModal(){document.getElementById('modal').classList.add('hidden');document.getElementById('modal').classList.remove('flex')}
function editRow(r){openModal();[['f_id','id'],['f_team','team'],['f_name','auditor_name'],['f_dept','department'],['f_date','inspection_date'],['f_time','inspection_time'],['f_area','area_5s'],['f_units','unit_count'],['f_note','note']].forEach(([a,b])=>document.getElementById(a).value=r[b]??'');}
function copyLine(){
  const monthName=<?=json_encode($months[$month],JSON_UNESCAPED_UNICODE)?>;
  const year=<?=json_encode($year)?>;
  const totalB=<?=json_encode($totalB)?>, totalA=<?=json_encode($totalA)?>;
  const peopleB=<?=json_encode($peopleB)?>, peopleA=<?=json_encode($peopleA)?>;
  const rowsB=<?=json_encode(array_values(array_map(function($r){return ['name'=>$r['auditor_name'],'dept'=>$r['department'],'date'=>$r['inspection_date'],'time'=>$r['inspection_time'],'area'=>$r['area_5s'],'unit'=>unit_count($r['area_5s'])];},$B)),JSON_UNESCAPED_UNICODE)?>;
  const rowsA=<?=json_encode(array_values(array_map(function($r){return ['name'=>$r['auditor_name'],'dept'=>$r['department'],'date'=>$r['inspection_date'],'time'=>$r['inspection_time'],'area'=>$r['area_5s'],'unit'=>unit_count($r['area_5s'])];},$A)),JSON_UNESCAPED_UNICODE)?>;
  const th=d=>{if(!d)return '-';const [y,m,day]=d.split('-');return `${day}/${m}/${y}`};
  const dateSummary=(rows)=>{
    const m={}; rows.forEach(r=>{if(!r.date)return; if(!m[r.date])m[r.date]={unit:0,count:0,time:r.time||''}; m[r.date].unit+=Number(r.unit||0); m[r.date].count++; if(r.time)m[r.date].time=r.time;});
    return Object.keys(m).sort().map(d=>`• ${th(d)}${m[d].time?` | ${m[d].time}`:''} | ${m[d].count} รายการ | ${m[d].unit} หน่วย`).join('\n')||'• ยังไม่ได้กำหนดวันที่';
  };
  const text=`📢 แจ้งแผนการตรวจ 5 ส.\nประจำเดือน ${monthName} ${year}\n\n👥 Auditor Team B\n👤 ผู้ตรวจ ${peopleB} คน\n📍 พื้นที่ตรวจรวม ${totalB} หน่วย\n${dateSummary(rowsB)}\n\n👥 Auditor Team A\n👤 ผู้ตรวจ ${peopleA} คน\n📍 พื้นที่ตรวจรวม ${totalA} หน่วย\n${dateSummary(rowsA)}\n\n📌 ขอให้ Auditor ทุกท่านตรวจสอบชื่อ แผนก วันที่ เวลา และพื้นที่ 5 ส. ของตนเองก่อนเข้าตรวจ\n\n🙏 ขอความร่วมมือทุกท่านดำเนินการตามแผนที่กำหนด\n\n✨ “ตรวจวันนี้...เพื่อพรุ่งนี้ที่ดีกว่า”`;
  const done=()=>alert('คัดลอกข้อความ LINE เรียบร้อยแล้ว สามารถนำไปวางใน LINE ได้เลย');
  if(navigator.clipboard && window.isSecureContext){navigator.clipboard.writeText(text).then(done).catch(()=>fallbackLineCopy(text));}
  else fallbackLineCopy(text);
}
function fallbackLineCopy(text){
  const ta=document.createElement('textarea');ta.value=text;ta.style.position='fixed';ta.style.left='-9999px';document.body.appendChild(ta);ta.select();
  try{document.execCommand('copy');alert('คัดลอกข้อความ LINE เรียบร้อยแล้ว สามารถนำไปวางใน LINE ได้เลย');}catch(e){alert('คัดลอกไม่สำเร็จ กรุณาลองอีกครั้ง');}ta.remove();
}
document.getElementById('f_area').addEventListener('input',function(){
  const n=this.value.split(',').map(x=>x.trim()).filter(Boolean).length;
  document.getElementById('f_units').value=n;
});
function postQuick(data){
  const fd=new FormData();
  Object.keys(data).forEach(k=>fd.append(k,data[k]??''));
  return fetch('schedule_manage.php?month=<?= $month ?>&year=<?= $year ?>',{method:'POST',body:fd,credentials:'same-origin'});
}
function rowData(id){
  const q=s=>document.querySelector(s);
  const el=s=>document.querySelector(s);
  return {
    action:'save_row', id:id,
    auditor_name:document.querySelector(`input[data-id=\"${id}\"][data-field=\"auditor_name\"]`)?.value||'',
    department:document.querySelector(`input[data-id=\"${id}\"][data-field=\"department\"]`)?.value||'',
    inspection_date:document.querySelector(`input[data-id=\"${id}\"].row-date`)?.value||'',
    inspection_time:document.querySelector(`input[data-id=\"${id}\"][data-field=\"inspection_time\"]`)?.value||'',
    area_5s:document.querySelector(`input[data-id=\"${id}\"][data-field=\"area_5s\"]`)?.value||'',
    note:''
  };
}
function saveRow(id,btn){
  if(btn){btn.disabled=true;btn.textContent='…';}
  postQuick(rowData(id)).then(r=>r.json()).then(x=>{
    if(x.ok){
      const u=document.getElementById('units-'+id); if(u) u.textContent=x.units;
      if(btn){btn.textContent='✓';btn.classList.add('cell-saved');setTimeout(()=>{btn.textContent='💾';btn.classList.remove('cell-saved');btn.disabled=false;},900);}
      showSaveMessage('บันทึกข้อมูลแถวนี้เรียบร้อยแล้ว','ok');
    }else throw new Error();
  }).catch(()=>{if(btn){btn.textContent='💾';btn.disabled=false;} showSaveMessage('บันทึกไม่สำเร็จ กรุณาลองอีกครั้ง','bad');});
}
function saveAllRows(){
  const ids=[...new Set([...document.querySelectorAll('input[data-id]')].map(x=>x.dataset.id))];
  if(!ids.length){showSaveMessage('ไม่มีข้อมูลให้บันทึก','bad');return;}
  let done=0,fail=0;
  Promise.all(ids.map(id=>postQuick(rowData(id)).then(r=>r.json()).then(x=>{if(x.ok){done++;const u=document.getElementById('units-'+id);if(u)u.textContent=x.units;}else fail++;}).catch(()=>fail++)))
  .then(()=>showSaveMessage(fail?`บันทึกแล้ว ${done} แถว แต่มี ${fail} แถวที่บันทึกไม่สำเร็จ`:`บันทึกข้อมูลทั้งหมด ${done} แถวเรียบร้อยแล้ว` ,fail?'bad':'ok'));
}
function showSaveMessage(msg,type){
  let box=document.getElementById('saveMessage');
  if(!box){box=document.createElement('div');box.id='saveMessage';box.style.cssText='position:fixed;right:20px;bottom:20px;z-index:9999;padding:12px 18px;border-radius:10px;color:#fff;font-weight:800;box-shadow:0 8px 25px rgba(0,0,0,.18)';document.body.appendChild(box);}
  box.style.background=type==='ok'?'#0b9b58':'#dc2626';box.textContent=(type==='ok'?'✅ ':'❌ ')+msg;box.style.display='block';clearTimeout(window._saveTimer);window._saveTimer=setTimeout(()=>box.style.display='none',2200);
}
function saveCell(el){
  const id=el.dataset.id, field=el.dataset.field, value=el.value;
  el.classList.add('date-saving');
  postQuick({action:'quick_field',id:id,field:field,value:value})
    .then(r=>r.text())
    .then(t=>{
      let d; try{d=JSON.parse(t)}catch(e){throw new Error(t.slice(0,200));}
      el.classList.remove('date-saving');
      if(!d.ok) throw new Error('save failed');
      el.classList.add('date-ok');
      setTimeout(()=>el.classList.remove('date-ok'),900);
      if(field==='area_5s'){
        const u=document.getElementById('units-'+id); if(u) u.textContent=d.units ?? 0;
      }
    })
    .catch(err=>{el.classList.remove('date-saving'); alert('บันทึกไม่สำเร็จ กรุณาตรวจสอบ XAMPP/ฐานข้อมูล'); console.error(err);});
}
function insertAfter(team,id){
  postQuick({action:'insert_row',team:team,after_id:id,ajax:'1'})
    .then(r=>r.text())
    .then(t=>{let d; try{d=JSON.parse(t)}catch(e){location.reload(); return;} if(d.ok===false) throw new Error('insert failed'); location.reload();})
    .catch(()=>{alert('เพิ่มแถวไม่สำเร็จ');});
}
function saveRowDate(el){
  const id=el.dataset.id;
  el.classList.add('date-saving');
  const fd=new FormData(); fd.append('action','quick_date'); fd.append('id',id); fd.append('inspection_date',el.value);
  fetch('schedule_manage.php?month=<?= $month ?>&year=<?= $year ?>',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      el.classList.remove('date-saving');
      if(d.ok){el.classList.add('date-ok'); setTimeout(()=>el.classList.remove('date-ok'),900);}
    }).catch(()=>{el.classList.remove('date-saving'); alert('บันทึกวันที่ไม่สำเร็จ');});
}
</script>
<?php require 'includes/footer.php';?>
