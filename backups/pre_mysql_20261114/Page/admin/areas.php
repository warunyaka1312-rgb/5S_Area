<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2).'/includes/config.php';
$title='จัดการพื้นที่ / หน่วย';
$p=db();

$msg='';
$msgType='info';

function go_areas(string $q=''): never {
    header('Location: '.FIVE_S_BASE.'Page/admin/areas.php'.($q!==''?'?'.$q:''));
    exit;
}

/* CREATE / UPDATE / DELETE / RENAME DEPARTMENT / BULK ADD */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=$_POST['action']??'';
    try {
        if ($action==='save') {
            $id=(int)($_POST['id']??0);
            $department=trim((string)($_POST['department']??'Production'));
            $group=trim((string)($_POST['group_name']??''));
            $unit=trim((string)($_POST['unit_no']??''));
            $name=trim((string)($_POST['area_name']??''));

            if (!in_array($department,['Production','Office'],true)) {
                throw new RuntimeException('ประเภทต้องเป็น Production หรือ Office');
            }
            if ($group==='') throw new RuntimeException('กรุณากรอก แผนก / Section');
            if ($unit==='') throw new RuntimeException('กรุณากรอกเลขพื้นที่ / หน่วย');

            if ($name==='') $name=$group.' / หน่วย '.$unit;

            if ($id) {
                $q=$p->prepare("UPDATE areas SET area_name=?,department=?,group_name=?,unit_no=?,active=1 WHERE id=?");
                $q->execute([$name,$department,$group,$unit,$id]);
                go_areas('ok=updated');
            } else {
                $code=$department.'-'.$group.'-'.$unit;
                $q=$p->prepare("SELECT id FROM areas WHERE department=? AND group_name=? AND unit_no=?");
                $q->execute([$department,$group,$unit]);
                $old=$q->fetchColumn();
                if ($old) {
                    $q=$p->prepare("UPDATE areas SET area_name=?,active=1,area_code=? WHERE id=?");
                    $q->execute([$name,$code,(int)$old]);
                    go_areas('ok=restored');
                }
                $q=$p->prepare("INSERT INTO areas(area_code,area_name,active,department,group_name,unit_no) VALUES(?,?,?,?,?,?)");
                $q->execute([$code,$name,1,$department,$group,$unit]);
                go_areas('ok=created');
            }
        }

        if ($action==='bulk_add') {
            $department=trim((string)($_POST['department']??'Production'));
            $group=trim((string)($_POST['group_name']??''));
            $unitsRaw=trim((string)($_POST['unit_list']??''));
            if (!in_array($department,['Production','Office'],true)) throw new RuntimeException('ประเภทต้องเป็น Production หรือ Office');
            if ($group==='') throw new RuntimeException('กรุณากรอกแผนก / Section');
            if ($unitsRaw==='') throw new RuntimeException('กรุณากรอกเลขพื้นที่ เช่น 22,23,24');
            $parts=preg_split('/[,\s;|]+/u',$unitsRaw,-1,PREG_SPLIT_NO_EMPTY);
            $added=0;
            foreach($parts as $unit){
                $unit=trim((string)$unit); if($unit==='') continue;
                $name=$group.' / หน่วย '.$unit;
                $code=$department.'-'.$group.'-'.$unit;
                $q=$p->prepare("SELECT id FROM areas WHERE department=? AND group_name=? AND unit_no=?");
                $q->execute([$department,$group,$unit]); $old=$q->fetchColumn();
                if($old){
                    $q=$p->prepare("UPDATE areas SET active=1,area_code=?,area_name=? WHERE id=?");
                    $q->execute([$code,$name,(int)$old]);
                } else {
                    $q=$p->prepare("INSERT INTO areas(area_code,area_name,active,department,group_name,unit_no) VALUES(?,?,?,?,?,?)");
                    $q->execute([$code,$name,1,$department,$group,$unit]);
                }
                $added++;
            }
            go_areas('ok=bulk&n='.$added);
        }

        if ($action==='rename_group') {
            $department=trim((string)($_POST['department']??'Production'));
            $oldGroup=trim((string)($_POST['old_group']??''));
            $newGroup=trim((string)($_POST['new_group']??''));
            if(!in_array($department,['Production','Office'],true)) throw new RuntimeException('ประเภทไม่ถูกต้อง');
            if($oldGroup===''||$newGroup==='') throw new RuntimeException('กรุณาระบุชื่อแผนกเดิมและชื่อใหม่');
            if($oldGroup===$newGroup) throw new RuntimeException('ชื่อใหม่ต้องแตกต่างจากชื่อเดิม');
            $q=$p->prepare("SELECT COUNT(*) FROM areas WHERE department=? AND group_name=?");
            $q->execute([$department,$newGroup]);
            if((int)$q->fetchColumn()>0) throw new RuntimeException('มีชื่อแผนกนี้อยู่แล้วในประเภทเดียวกัน');
            $p->beginTransaction();
            try {
                $q=$p->prepare("UPDATE areas SET group_name=?, area_name=REPLACE(area_name, ?, ?) WHERE department=? AND group_name=?");
                $q->execute([$newGroup,$oldGroup,$newGroup,$department,$oldGroup]);
                if($p->query("SELECT name FROM sqlite_master WHERE type='table' AND name='audits'")->fetchColumn()){
                    $q=$p->prepare("UPDATE audits SET group_name=? WHERE department=? AND group_name=?");
                    $q->execute([$newGroup,$department,$oldGroup]);
                }
                if($p->query("SELECT name FROM sqlite_master WHERE type='table' AND name='excel_monthly'")->fetchColumn()){
                    $q=$p->prepare("UPDATE excel_monthly SET group_name=? WHERE department=? AND group_name=?");
                    $q->execute([$newGroup,$department,$oldGroup]);
                }
                $p->commit();
            } catch(Throwable $e){ if($p->inTransaction()) $p->rollBack(); throw $e; }
            go_areas('ok=renamed');
        }

        if ($action==='delete') {
            $id=(int)($_POST['id']??0);
            if (!$id) throw new RuntimeException('ไม่พบพื้นที่ที่ต้องการลบ');
            $q=$p->prepare("SELECT COUNT(*) FROM audits WHERE area_id=?");
            $q->execute([$id]);
            $hasAudit=(int)$q->fetchColumn()>0;

            if ($hasAudit) {
                /* Do NOT cascade-delete historical scores. Hide the area instead. */
                $q=$p->prepare("UPDATE areas SET active=0 WHERE id=?");
                $q->execute([$id]);
                go_areas('ok=hidden');
            } else {
                $q=$p->prepare("DELETE FROM areas WHERE id=?");
                $q->execute([$id]);
                go_areas('ok=deleted');
            }
        }

        if ($action==='restore') {
            $id=(int)($_POST['id']??0);
            $q=$p->prepare("UPDATE areas SET active=1 WHERE id=?");
            $q->execute([$id]);
            go_areas('ok=restored');
        }
    } catch(Throwable $e) {
        go_areas('error='.urlencode($e->getMessage()));
    }
}

$editId=(int)($_GET['edit']??0);
$edit=null;
if($editId){
    $q=$p->prepare("SELECT * FROM areas WHERE id=?");
    $q->execute([$editId]);
    $edit=$q->fetch();
}

$filterDept=$_GET['department']??'';
$showInactive=!empty($_GET['inactive']);
$where=[];$args=[];
if($filterDept!==''){ $where[]='department=?';$args[]=$filterDept; }
if(!$showInactive) $where[]='active=1';
$sql='SELECT a.*, (SELECT COUNT(*) FROM audits x WHERE x.area_id=a.id) audit_count FROM areas a';
if($where) $sql.=' WHERE '.implode(' AND ',$where);
$sql.=' ORDER BY department,group_name,CAST(unit_no AS INTEGER),unit_no';
$q=$p->prepare($sql);$q->execute($args);$rows=$q->fetchAll();

$counts=['Production'=>0,'Office'=>0,'inactive'=>0];
foreach($p->query("SELECT department,active,COUNT(*) c FROM areas GROUP BY department,active") as $r){
    if((int)$r['active']===1 && isset($counts[$r['department']])) $counts[$r['department']]=(int)$r['c'];
    if((int)$r['active']===0) $counts['inactive']+=(int)$r['c'];
}
require FIVE_S_ROOT.'/includes/header.php';
?>
<div class="hero">
  <div class="hero-copy">
    <h1>จัดการ <span>พื้นที่ / หน่วย</span></h1>
    <p>เพิ่ม • แก้ไข • ลบ/ปิดใช้งาน • เรียกคืนพื้นที่เดิม โดยข้อมูลคะแนนย้อนหลังไม่ถูกลบ</p>
  </div>
  <div class="hero-right"><strong>AREA MASTER</strong><small>Production <?=h($counts['Production'])?> หน่วย • Office <?=h($counts['Office'])?> หน่วย</small></div>
</div>

<?php if(isset($_GET['ok'])): ?>
<div class="bg-emerald-100 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-4 font-bold">
<?php
$map=['created'=>'เพิ่มพื้นที่เรียบร้อยแล้ว','updated'=>'แก้ไขพื้นที่เรียบร้อยแล้ว','deleted'=>'ลบพื้นที่เรียบร้อยแล้ว','hidden'=>'พื้นที่มีประวัติการตรวจ จึงเปลี่ยนเป็น “ปิดใช้งาน” เพื่อรักษาคะแนนย้อนหลัง','restored'=>'เปิดใช้งานพื้นที่เรียบร้อยแล้ว','bulk'=>'เพิ่ม/เปิดใช้งานพื้นที่จำนวน '.(int)($_GET['n']??0).' หน่วยเรียบร้อยแล้ว','renamed'=>'เปลี่ยนชื่อแผนก/Section เรียบร้อยแล้ว'];
echo h($map[$_GET['ok']]??'ดำเนินการเรียบร้อยแล้ว');
?>
</div>
<?php endif; ?>
<?php if(isset($_GET['error'])): ?>
<div class="bg-red-100 border border-red-200 text-red-800 p-4 rounded-xl mb-4 font-bold"><?=h($_GET['error'])?></div>
<?php endif; ?>

<div class="grid md:grid-cols-3 gap-4 mb-5">
  <div class="card p-5"><div class="text-slate-500 text-sm font-bold">Production</div><div class="text-3xl font-black text-orange-600"><?=$counts['Production']?></div><div class="text-xs text-slate-500">พื้นที่/หน่วยที่เปิดใช้งาน</div></div>
  <div class="card p-5"><div class="text-slate-500 text-sm font-bold">Office</div><div class="text-3xl font-black text-blue-600"><?=$counts['Office']?></div><div class="text-xs text-slate-500">พื้นที่/หน่วยที่เปิดใช้งาน</div></div>
  <div class="card p-5"><div class="text-slate-500 text-sm font-bold">ปิดใช้งาน</div><div class="text-3xl font-black text-slate-500"><?=$counts['inactive']?></div><div class="text-xs text-slate-500">ข้อมูลเก่าจะยังคงอยู่</div></div>
</div>

<div class="card p-5 mb-5">
  <div class="flex items-center justify-between gap-3 mb-4">
    <div><h2 class="text-xl font-black"><?= $edit ? '✏️ แก้ไขพื้นที่ / หน่วย' : '➕ เพิ่มพื้นที่ / หน่วย' ?></h2>
    <p class="text-sm text-slate-500">กำหนดแผนก/Section และเลขพื้นที่ที่ต้องการให้ปรากฏในแบบลงคะแนน</p></div>
    <?php if($edit): ?><a href="Page/admin/areas.php" class="border rounded-xl px-4 py-2 font-bold">ยกเลิกแก้ไข</a><?php endif; ?>
  </div>
  <form method="post" class="grid md:grid-cols-5 gap-3">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?=$edit?(int)$edit['id']:0?>">
    <div><label class="text-xs font-bold">ประเภทแบบตรวจ</label>
      <select name="department" class="w-full border rounded-xl p-3" required>
        <option value="Production" <?=(!$edit||$edit['department']==='Production')?'selected':''?>>Production</option>
        <option value="Office" <?=($edit&&$edit['department']==='Office')?'selected':''?>>Office</option>
      </select>
    </div>
    <div><label class="text-xs font-bold">แผนก / Section</label>
      <input name="group_name" list="deptList" value="<?=h($edit['group_name']??'')?>" class="w-full border rounded-xl p-3" placeholder="เช่น DIE Making" required>
      <datalist id="deptList">
      <?php
      $ds=$p->query("SELECT DISTINCT group_name FROM areas WHERE TRIM(group_name)<>'' ORDER BY group_name")->fetchAll(PDO::FETCH_COLUMN);
      foreach($ds as $d) echo '<option value="'.h($d).'">';
      ?>
      </datalist>
    </div>
    <div><label class="text-xs font-bold">เลขพื้นที่ / หน่วย</label>
      <input name="unit_no" value="<?=h($edit['unit_no']??'')?>" class="w-full border rounded-xl p-3" placeholder="เช่น 19" required>
    </div>
    <div><label class="text-xs font-bold">ชื่อพื้นที่ (ถ้ามี)</label>
      <input name="area_name" value="<?=h($edit['area_name']??'')?>" class="w-full border rounded-xl p-3" placeholder="เว้นว่างให้ระบบสร้างอัตโนมัติ">
    </div>
    <div class="flex items-end"><button class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded-xl p-3 font-black"><?= $edit?'💾 บันทึกการแก้ไข':'➕ เพิ่มพื้นที่' ?></button></div>
  </form>
</div>

<div class="grid lg:grid-cols-2 gap-5 mb-5">
  <div class="card p-5 border-l-4 border-orange-500">
    <h2 class="text-xl font-black mb-1">➕ เพิ่มหลายพื้นที่พร้อมกัน</h2>
    <p class="text-sm text-slate-500 mb-4">เช่น 22,23,24 ระบบจะสร้างเลขหน่วยให้ครบในครั้งเดียว</p>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="bulk_add">
      <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="text-xs font-bold">ประเภท</label><select name="department" class="w-full border rounded-xl p-3"><option>Production</option><option>Office</option></select></div>
        <div><label class="text-xs font-bold">แผนก / Section</label><input name="group_name" class="w-full border rounded-xl p-3" placeholder="เช่น 2000" required></div>
      </div>
      <div><label class="text-xs font-bold">เลขพื้นที่หลายรายการ</label><input name="unit_list" class="w-full border rounded-xl p-3 text-lg font-black" placeholder="22,23,24" required></div>
      <button class="w-full bg-orange-500 hover:bg-orange-600 text-white rounded-xl p-3 font-black">➕ เพิ่มพื้นที่ทั้งหมด</button>
    </form>
  </div>
  <div class="card p-5 border-l-4 border-blue-500">
    <h2 class="text-xl font-black mb-1">✏️ เปลี่ยนชื่อแผนก / Section</h2>
    <p class="text-sm text-slate-500 mb-4">เปลี่ยนชื่อทีเดียวทุกหน่วยของแผนกนั้น และปรับชื่อในข้อมูลอ้างอิงที่เกี่ยวข้อง</p>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="rename_group">
      <div><label class="text-xs font-bold">ประเภท</label><select name="department" class="w-full border rounded-xl p-3"><option>Production</option><option>Office</option></select></div>
      <div><label class="text-xs font-bold">ชื่อเดิม</label><input name="old_group" list="deptList" class="w-full border rounded-xl p-3" placeholder="เลือก/พิมพ์ชื่อเดิม" required></div>
      <div><label class="text-xs font-bold">ชื่อใหม่</label><input name="new_group" class="w-full border rounded-xl p-3" placeholder="ชื่อแผนกใหม่" required></div>
      <button onclick="return confirm('ยืนยันเปลี่ยนชื่อแผนกนี้ทั้งชุด?')" class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded-xl p-3 font-black">💾 เปลี่ยนชื่อทั้งแผนก</button>
    </form>
  </div>
</div>

<div class="card p-5">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h2 class="text-xl font-black">รายการพื้นที่ / หน่วย</h2>
    <div class="flex gap-2">
      <a href="Page/admin/areas.php" class="border rounded-lg px-3 py-2 font-bold <?=(!$filterDept&&!$showInactive)?'bg-slate-900 text-white':''?>">ทั้งหมด</a>
      <a href="Page/admin/areas.php?department=Production" class="border rounded-lg px-3 py-2 font-bold">Production</a>
      <a href="Page/admin/areas.php?department=Office" class="border rounded-lg px-3 py-2 font-bold">Office</a>
      <a href="Page/admin/areas.php?inactive=1<?= $filterDept?'&department='.urlencode($filterDept):'' ?>" class="border rounded-lg px-3 py-2 font-bold">ดูปิดใช้งาน</a>
    </div>
  </div>
  <div class="overflow-x-auto">
    <table id="areaTable" class="display w-full">
      <thead><tr><th>ประเภท</th><th>แผนก / Section</th><th>หน่วย</th><th>ชื่อพื้นที่</th><th>ประวัติการตรวจ</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><span class="px-2 py-1 rounded-full text-xs font-bold <?=$r['department']==='Production'?'bg-orange-100 text-orange-700':'bg-blue-100 text-blue-700'?>"><?=h($r['department'])?></span></td>
          <td><?=h($r['group_name'])?></td>
          <td class="font-black">หน่วย <?=h($r['unit_no'])?></td>
          <td><?=h($r['area_name'])?></td>
          <td class="text-center"><?=number_format((int)$r['audit_count'])?> ครั้ง</td>
          <td><?=$r['active']?'🟢 ใช้งาน':'⚪ ปิดใช้งาน'?></td>
          <td>
            <div class="flex gap-1">
              <a href="Page/admin/areas.php?edit=<?=$r['id']?>" class="bg-blue-600 text-white rounded-lg px-3 py-1 font-bold">แก้ไข</a>
              <?php if($r['active']): ?>
              <form method="post" onsubmit="return confirm('ยืนยันการลบ/ปิดใช้งานพื้นที่นี้? ถ้ามีประวัติการตรวจ ระบบจะไม่ลบคะแนนย้อนหลัง แต่จะปิดการใช้งานแทน');">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>">
                <button class="bg-red-600 text-white rounded-lg px-3 py-1 font-bold">ลบ</button>
              </form>
              <?php else: ?>
              <form method="post"><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="bg-emerald-600 text-white rounded-lg px-3 py-1 font-bold">เปิดใช้งาน</button></form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
$(function(){
  $('#areaTable').DataTable({
    pageLength:50,order:[[0,'asc'],[1,'asc'],[2,'asc']],scrollX:true,
    language:{search:'ค้นหา:',lengthMenu:'แสดง _MENU_',info:'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
      emptyTable:'ไม่มีข้อมูล',zeroRecords:'ไม่พบข้อมูล',paginate:{next:'ถัดไป',previous:'ก่อนหน้า'}}
  });
});
</script>
<?php require FIVE_S_ROOT.'/includes/footer.php';?>
