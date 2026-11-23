<?php
declare(strict_types=1);
require_once 'config.php';
$title='แผนก / กลุ่ม / หน่วย';
$p=db();

function normDept2(string $s): string { $s=trim($s); $s=preg_replace('/\s+/u',' ',$s)??$s; return mb_strtolower($s,'UTF-8'); }
function blockedDept2(string $s): bool { return in_array(normDept2($s),['production','office','prod','สำนักงาน','โปรดักชัน'],true); }
function ensureDeptMaster2(PDO $p): void {
    $p->exec("CREATE TABLE IF NOT EXISTS departments (id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL COLLATE NOCASE UNIQUE,active INTEGER NOT NULL DEFAULT 1,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $seed=['PDC1','PPT1','Billet&Scarp','PURCHASING','EXT2400','EXT2000','MNTE','ANO','PDC 2','WHL','PPT2','Die.Pc','R&D , HUB','CX','Die.Design','Die Making','QA','New Medel','MIS','EXT. 880-1','EXT. 880-2','ACC&BUD','FIN','PLANNING','AO','IT','HRD','GA'];
    foreach(['audit_plans','areas','excel_monthly'] as $tb){
        try { $q=$p->query("SELECT DISTINCT TRIM(department) FROM {$tb} WHERE TRIM(COALESCE(department,''))<>''"); if($q) $seed=array_merge($seed,$q->fetchAll(PDO::FETCH_COLUMN)); } catch(Throwable $e) {}
    }
    $ins=$p->prepare('INSERT OR IGNORE INTO departments(name,active) VALUES(?,1)');
    foreach(array_unique(array_map('strval',$seed)) as $n){$n=trim($n); if($n!==''&&!blockedDept2($n))$ins->execute([$n]);}
}
ensureDeptMaster2($p);
$msg=''; $err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    $a=$_POST['action']??'';
    if($a==='add_department'){
      $name=trim((string)($_POST['name']??''));
      if($name===''||blockedDept2($name)) throw new RuntimeException('กรุณาระบุชื่อแผนกที่ถูกต้อง');
      $q=$p->prepare('SELECT id FROM departments WHERE name=? COLLATE NOCASE LIMIT 1');$q->execute([$name]);$id=$q->fetchColumn();
      if($id!==false){$p->prepare('UPDATE departments SET active=1,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$id]);$msg='เปิดใช้งานแผนกเดิมแล้ว';}
      else {$p->prepare('INSERT INTO departments(name,active) VALUES(?,1)')->execute([$name]);$msg='เพิ่มแผนกเรียบร้อยแล้ว';}
    }
    if($a==='rename_department'){
      $id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));
      if(!$id||$name===''||blockedDept2($name)) throw new RuntimeException('ข้อมูลแก้ไขไม่ถูกต้อง');
      $p->beginTransaction();
      $q=$p->prepare('SELECT name FROM departments WHERE id=?');$q->execute([$id]);$old=$q->fetchColumn(); if($old===false)throw new RuntimeException('ไม่พบแผนก');
      $q=$p->prepare('SELECT id FROM departments WHERE name=? COLLATE NOCASE AND id<>?');$q->execute([$name,$id]); if($q->fetchColumn()!==false)throw new RuntimeException('มีชื่อแผนกนี้แล้ว');
      $p->prepare('UPDATE departments SET name=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$name,$id]);
      foreach(['audit_plans','areas','excel_monthly'] as $tb){try{$q=$p->prepare("UPDATE {$tb} SET department=? WHERE department=?");$q->execute([$name,$old]);}catch(Throwable $e){}}
      $p->commit();$msg='แก้ไขชื่อแผนกและข้อมูลที่อ้างอิงแล้ว';
    }
    if($a==='delete_department'){
      $id=(int)($_POST['id']??0);if(!$id)throw new RuntimeException('ไม่พบแผนก');
      $p->prepare('UPDATE departments SET active=0,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);$msg='ปิดใช้งานแผนกแล้ว (ข้อมูลเดิมไม่ถูกลบ)';
    }
    if($a==='restore_department'){
      $id=(int)($_POST['id']??0);$p->prepare('UPDATE departments SET active=1,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);$msg='เปิดใช้งานแผนกแล้ว';
    }
  }catch(Throwable $e){if($p->inTransaction())$p->rollBack();$err=$e->getMessage();}
}

$search=trim((string)($_GET['q']??''));$showInactive=!empty($_GET['inactive']);
$where=$showInactive?'':'WHERE active=1';$args=[];
if($search!==''){$where.=($where?' AND ':'WHERE').' name LIKE ?';$args[]='%'.$search.'%';}
$q=$p->prepare("SELECT * FROM departments {$where} ORDER BY name COLLATE NOCASE");$q->execute($args);$deps=$q->fetchAll();
$allCount=(int)$p->query('SELECT COUNT(*) FROM departments WHERE active=1')->fetchColumn();
$inactiveCount=(int)$p->query('SELECT COUNT(*) FROM departments WHERE active=0')->fetchColumn();
$groups=[];$units=[];
try{$groups=$p->query("SELECT department,group_name,COUNT(*) unit_count FROM areas WHERE active=1 AND TRIM(group_name)<>'' GROUP BY department,group_name ORDER BY department,group_name")->fetchAll();}catch(Throwable $e){}
try{$units=$p->query("SELECT department,group_name,unit_no,area_name FROM areas WHERE active=1 ORDER BY department,group_name,CAST(unit_no AS INTEGER),unit_no LIMIT 300")->fetchAll();}catch(Throwable $e){}
require 'includes/header.php';
?>
<style>
.master-hero{background:linear-gradient(135deg,#eef7ff,#fff);border:1px solid #dbeafe;border-radius:20px;padding:24px;display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:18px}.master-hero h1{font-size:30px;font-weight:950;color:#173b70;margin:0}.master-hero p{margin:5px 0 0;color:#64748b;font-weight:700}.stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px}.stat{border-radius:16px;padding:17px 20px;font-weight:900}.stat b{display:block;font-size:30px}.stat.blue{background:#e0f2fe;color:#0369a1}.stat.green{background:#dcfce7;color:#166534}.stat.orange{background:#ffedd5;color:#c2410c}.tabs{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap}.tab{border:1px solid #dbe3ef;background:#fff;border-radius:12px;padding:11px 18px;font-weight:900;color:#334155}.tab.active{background:#1677e8;color:#fff;border-color:#1677e8}.panel{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 8px 25px rgba(15,23,42,.05);padding:18px}.grid2{display:grid;grid-template-columns:360px 1fr;gap:18px}.field{margin-bottom:12px}.field label{display:block;font-size:13px;font-weight:900;color:#334155;margin-bottom:5px}.field input{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:10px 12px;font-weight:700}.btn-primary{background:#1677e8;color:#fff;border:0;border-radius:10px;padding:11px 18px;font-weight:900;cursor:pointer}.btn-green{background:#16a34a;color:#fff;border:0;border-radius:10px;padding:11px 18px;font-weight:900;cursor:pointer}.btn-red{background:#ef4444;color:#fff;border:0;border-radius:8px;padding:7px 10px;font-weight:900;cursor:pointer}.btn-edit{background:#1677e8;color:#fff;border:0;border-radius:8px;padding:7px 10px;font-weight:900;cursor:pointer}.table-wrap{overflow:auto}.master-table{width:100%;border-collapse:collapse;min-width:760px}.master-table th{background:#eaf4ff;color:#173b70;padding:11px 9px;text-align:left;font-size:13px}.master-table td{padding:9px;border-bottom:1px solid #e2e8f0;font-weight:650}.badge{display:inline-block;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:900}.on{background:#dcfce7;color:#166534}.off{background:#fee2e2;color:#991b1b}.note{background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:14px;color:#7c2d12;font-weight:700;margin-top:14px}.mini-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:18px}.mini{border-radius:16px;padding:16px;border:1px solid #e2e8f0}.mini h3{margin:0 0 8px;font-weight:950}.mini ul{margin:0;padding-left:18px;color:#475569}.flash{padding:12px 15px;border-radius:12px;margin-bottom:15px;font-weight:900}.ok{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}@media(max-width:900px){.grid2{grid-template-columns:1fr}.stats{grid-template-columns:1fr}.master-hero{display:block}.mini-grid{grid-template-columns:1fr}}

.flash-row{font-size:16px}.flash-ok{background:#dcfce7;color:#166534;border:1px solid #86efac;border-radius:12px;padding:10px 14px;font-weight:800}.flash-err{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:12px;padding:10px 14px;font-weight:800}
</style>
<div class="master-hero"><div><h1>👥 แผนก / กลุ่ม / หน่วย</h1><p>จัดการข้อมูลแผนก กลุ่ม และหน่วยงานที่ใช้ในระบบ 5 ส.</p></div><button class="btn-green" onclick="document.getElementById('newName').focus()">＋ เพิ่มแผนกใหม่</button></div>
<?php if($msg): ?><div class="flash ok">✓ <?=h($msg)?></div><?php endif; ?><?php if($err): ?><div class="flash error">⚠ <?=h($err)?></div><?php endif; ?>
<div class="stats"><div class="stat blue">🏢 จำนวนแผนก<b><?=$allCount?></b></div><div class="stat green">👥 จำนวนกลุ่ม<b><?=count($groups)?></b></div><div class="stat orange">🔗 จำนวนหน่วย<b><?=count($units)?></b></div></div>
<div class="tabs"><a class="tab active" href="master_data.php">🏢 รายการแผนก</a><a class="tab" href="#groups">👥 รายการกลุ่ม</a><a class="tab" href="#units">🔗 รายการหน่วย</a><a class="tab" href="areas.php">⚙️ จัดการพื้นที่ละเอียด</a></div>
<div class="grid2">
  <div>
    <div class="panel"><h2 style="margin-top:0;font-weight:950">เพิ่ม / แก้ไขแผนก</h2><form method="post"><input type="hidden" name="action" value="add_department"><div class="field"><label>รหัส/ชื่อแผนก *</label><input id="newName" name="name" placeholder="เช่น PDC1" required></div><button class="btn-primary" type="submit">💾 บันทึกข้อมูล</button></form><div class="note">เพิ่มแล้วจะสามารถเลือกได้ทันทีในหน้าจัดตารางตรวจ และข้อมูลเดิมจะไม่ถูกลบเมื่อปิดใช้งาน</div></div>
  </div>
  <div class="panel"><div style="display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:12px"><h2 style="margin:0;font-weight:950">รายการแผนก</h2><form style="display:flex;gap:7px" method="get"><input name="q" value="<?=h($search)?>" placeholder="ค้นหารหัส/ชื่อแผนก..." style="border:1px solid #cbd5e1;border-radius:10px;padding:9px 12px"><button class="btn-primary">ค้นหา</button></form></div>
    <div class="table-wrap"><table class="master-table"><thead><tr><th>ลำดับ</th><th>รหัสแผนก</th><th>ชื่อ</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>
    <?php foreach($deps as $i=>$d): ?><tr><td><?=($i+1)?></td><td><b><?=h($d['name'])?></b></td><td><?=h($d['name'])?></td><td><span class="badge <?=((int)$d['active']?'on':'off')?>"><?=((int)$d['active']?'ใช้งาน':'ปิดใช้งาน')?></span></td><td style="white-space:nowrap"><button type="button" class="btn-edit" onclick="editDept(<?=json_encode((int)$d['id'])?>,<?=htmlspecialchars(json_encode($d['name'],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT),ENT_QUOTES,'UTF-8')?>)">✎</button> <?php if((int)$d['active']): ?><form style="display:inline" method="post" onsubmit="return confirm('ยืนยันปิดใช้งานแผนกนี้?')"><input type="hidden" name="action" value="delete_department"><input type="hidden" name="id" value="<?=$d['id']?>"><button class="btn-red">🗑</button></form><?php else: ?><form style="display:inline" method="post"><input type="hidden" name="action" value="restore_department"><input type="hidden" name="id" value="<?=$d['id']?>"><button class="btn-green">↻</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if(!$deps): ?><tr><td colspan="5" style="text-align:center;padding:25px;color:#64748b">ไม่พบข้อมูล</td></tr><?php endif; ?></tbody></table></div>
  </div>
</div>
<div class="mini-grid" id="groups"><div class="panel"><h2 style="font-weight:950;margin-top:0">👥 รายการกลุ่ม</h2><div class="table-wrap"><table class="master-table"><thead><tr><th>ประเภท</th><th>กลุ่ม</th><th>จำนวนหน่วย</th></tr></thead><tbody><?php foreach($groups as $g): ?><tr><td><?=h($g['department'])?></td><td><b><?=h($g['group_name'])?></b></td><td><?=h((string)$g['unit_count'])?></td></tr><?php endforeach; ?></tbody></table></div></div><div class="panel" id="units"><h2 style="font-weight:950;margin-top:0">🔗 รายการหน่วย</h2><div class="table-wrap"><table class="master-table"><thead><tr><th>ประเภท</th><th>กลุ่ม</th><th>หน่วย</th><th>ชื่อพื้นที่</th></tr></thead><tbody><?php foreach(array_slice($units,0,80) as $u): ?><tr><td><?=h($u['department'])?></td><td><?=h($u['group_name'])?></td><td><b><?=h($u['unit_no'])?></b></td><td><?=h($u['area_name'])?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<div id="editBox" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;align-items:center;justify-content:center;padding:20px"><div class="panel" style="width:min(440px,100%)"><h2 style="font-weight:950;margin-top:0">✎ แก้ไขแผนก</h2><form method="post"><input type="hidden" name="action" value="rename_department"><input type="hidden" id="editId" name="id"><div class="field"><label>ชื่อแผนก</label><input id="editName" name="name" required></div><div style="display:flex;gap:8px"><button class="btn-primary">💾 บันทึก</button><button type="button" class="tab" onclick="closeEdit()">ยกเลิก</button></div></form></div></div>
<script>
function editDept(id,name){
  const box=document.getElementById('editBox');
  const idEl=document.getElementById('editId');
  const nameEl=document.getElementById('editName');
  if(!box||!idEl||!nameEl){alert('ไม่พบแบบฟอร์มแก้ไขแผนก');return;}
  idEl.value=String(id||'');
  nameEl.value=String(name||'');
  box.style.display='flex';
  setTimeout(()=>nameEl.focus(),50);
}
function closeEdit(){const box=document.getElementById('editBox');if(box)box.style.display='none';}
</script>
<?php require 'includes/footer.php'; ?>
