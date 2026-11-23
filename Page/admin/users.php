<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
$p = db(); five_s_users_init($p);
$roles = five_s_roles(); $me = five_s_user(); $msg=''; $err='';
$depts = [];
try { $depts = $p->query("SELECT name FROM departments WHERE active=1 ORDER BY name COLLATE utf8mb4_general_ci")->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    try {
        if ($a === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $un = trim((string)($_POST['username'] ?? '')); $dn = trim((string)($_POST['display_name'] ?? ''));
            $dep = trim((string)($_POST['department'] ?? ''));
            $role = (string)($_POST['role'] ?? 'auditor'); if (!isset($roles[$role])) $role = 'auditor';
            $pw = (string)($_POST['password'] ?? ''); $active = isset($_POST['active']) ? 1 : 0;
            if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $un)) throw new RuntimeException('User ใช้ได้เฉพาะ a-z 0-9 . _ - ยาว 3–30 ตัว');
            if ($id === (int)$me['id'] && ($role !== 'admin' || !$active)) throw new RuntimeException('ไม่สามารถลดสิทธิ์หรือปิดบัญชีของตัวเองได้');
            if ($pw !== '' && mb_strlen($pw) < 6) throw new RuntimeException('Password ต้องยาวอย่างน้อย 6 ตัว');
            if ($id > 0) {
                $p->prepare("UPDATE users SET username=?,display_name=?,department=?,role=?,active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$un,$dn,$dep,$role,$active,$id]);
                if ($pw !== '') $p->prepare("UPDATE users SET password_hash=?,must_change=? WHERE id=?")->execute([password_hash($pw,PASSWORD_DEFAULT), $id===(int)$me['id']?0:1, $id]);
                $msg = 'บันทึก '.$un.' แล้ว'.($pw!==''?' • ตั้ง Password ใหม่แล้ว':'');
            } else {
                if ($pw === '') throw new RuntimeException('กรุณาตั้ง Password เริ่มต้น');
                $p->prepare("INSERT INTO users(username,password_hash,display_name,department,role,active,must_change) VALUES(?,?,?,?,?,?,1)")->execute([$un,password_hash($pw,PASSWORD_DEFAULT),$dn,$dep,$role,$active]);
                $msg = 'เพิ่ม '.$un.' แล้ว (ผู้ใช้จะถูกให้เปลี่ยน Password เมื่อเข้าครั้งแรก)';
            }
        }
        if ($a === 'setpw') {
            $id = (int)($_POST['id'] ?? 0); $pw = (string)($_POST['password'] ?? '');
            if (mb_strlen($pw) < 6) throw new RuntimeException('Password ต้องยาวอย่างน้อย 6 ตัว');
            $p->prepare("UPDATE users SET password_hash=?,must_change=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([password_hash($pw,PASSWORD_DEFAULT), $id===(int)$me['id']?0:1, $id]);
            $un = $p->query("SELECT username FROM users WHERE id=".$id)->fetchColumn();
            $msg = 'ตั้ง Password ใหม่ของ '.$un.' แล้ว';
        }
        if ($a === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === (int)$me['id']) throw new RuntimeException('ไม่สามารถลบบัญชีของตัวเองได้');
            $p->prepare("DELETE FROM users WHERE id=?")->execute([$id]); $msg = 'ลบผู้ใช้แล้ว';
        }
    } catch (Throwable $e) { $err = (str_contains($e->getMessage(),'UNIQUE') || str_contains($e->getMessage(),'Duplicate entry')) ? 'User นี้มีอยู่แล้ว' : $e->getMessage(); }
    if ($err === '') { $_SESSION['five_s_flash'] = $msg; header('Location: '.FIVE_S_BASE.'Page/admin/users.php'); exit; }
}
if (!empty($_SESSION['five_s_flash'])) { $msg = (string)$_SESSION['five_s_flash']; unset($_SESSION['five_s_flash']); }
$users = $p->query("SELECT * FROM users ORDER BY CASE role WHEN 'admin' THEN 0 WHEN 'auditor' THEN 1 ELSE 2 END, username")->fetchAll();
$auditorNames = [];
try { foreach ($p->query("SELECT DISTINCT TRIM(auditor_name) n FROM audit_plans WHERE TRIM(auditor_name)<>'' ORDER BY n")->fetchAll(PDO::FETCH_COLUMN) as $n) $auditorNames[] = preg_replace('/\s+/u',' ',$n); } catch (Throwable $e) {}
$title = 'ผู้ใช้งานและสิทธิ์';
require FIVE_S_ROOT.'/includes/header.php';
$rc = ['admin'=>['#FFEDD5','#C2410C'],'auditor'=>['#DBEAFE','#1D4ED8'],'viewer'=>['#F1F5F9','#475569']];
?>
<style>
.us-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.us-head h1{margin:0;font-size:26px;font-weight:700;color:#0E1A2F}.us-head p{margin:2px 0 0;color:#64748B}
.us-card{background:#fff;border:1px solid #E4E8EF;border-radius:18px;padding:16px 18px;margin-bottom:14px}
.us-t{width:100%;border-collapse:separate;border-spacing:0}
.us-t th{background:#0E1A2F;color:#fff;text-align:left;font-size:14px;font-weight:700;padding:12px 12px}
.us-t th:first-child{border-radius:12px 0 0 0;text-align:center;width:60px}.us-t th:last-child{border-radius:0 12px 0 0}
.us-t td{padding:12px;border-bottom:1px solid #EEF1F5;font-size:15px;vertical-align:middle}
.us-t tr:hover td{background:#FFF8F2}
.us-t td.no{text-align:center;font:700 15px Inter,sans-serif;color:#64748B}
.us-t .un{font:700 16px Inter,sans-serif;color:#0E1A2F}.us-t .dn{font-size:13px;color:#64748B}
.pw{display:flex;align-items:center;gap:8px}.pw .dots{font:700 16px Inter,sans-serif;letter-spacing:2px;color:#94A3B8}
.pill{display:inline-block;border-radius:999px;padding:4px 12px;font-size:13px;font-weight:700;white-space:nowrap}
.btn{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 11px;border-radius:9px;border:1px solid #CBD5E1;background:#fff;font-weight:600;font-size:13.5px;cursor:pointer;color:#0E1A2F;font-family:inherit;white-space:nowrap}
.btn.o{background:#F26A21;border-color:#F26A21;color:#fff}.btn.r{color:#B91C1C;border-color:#FECACA}
.in{height:34px;border:1px solid #CBD5E1;border-radius:9px;padding:0 10px;font-size:14px;font-family:inherit;width:140px}
.us-f{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;align-items:end}
.us-f label{display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:5px}
.us-f input,.us-f select{width:100%;height:44px;border:1px solid #CBD5E1;border-radius:11px;padding:0 12px;font-size:15px;font-family:inherit;background:#fff}
.roles{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.roles div{border:1px solid #E4E8EF;border-radius:12px;padding:10px 12px;font-size:13px;color:#475569;background:#fff}.roles b{display:block;font-size:14.5px;color:#0E1A2F}
@media(max-width:1100px){.us-f{grid-template-columns:repeat(2,minmax(0,1fr))}.roles{grid-template-columns:1fr}}
</style>
<div x-data="{f:{id:0,username:'',display_name:'',department:'',role:'auditor',active:true},pw:0}">
<div class="us-head"><div><h1>ผู้ใช้งานและสิทธิ์</h1><p>กำหนดผู้เข้าใช้ระบบ • หน้ารายงานเปิดดูได้โดยไม่ต้องล็อกอิน การบันทึกต้องล็อกอิน</p></div>
 <button type="button" class="btn o" style="height:44px;padding:0 18px;font-size:15px" @click="f={id:0,username:'',display_name:'',department:'',role:'auditor',active:true};$refs.form.scrollIntoView({behavior:'smooth'})">＋ เพิ่มผู้ใช้</button></div>
<?php if($msg):?><div style="background:#ECFDF5;color:#166534;border:1px solid #BBF7D0;border-radius:12px;padding:11px 14px;font-weight:600;margin-bottom:12px">✓ <?=h($msg)?></div><?php endif;?>
<?php if($err):?><div style="background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;border-radius:12px;padding:11px 14px;font-weight:600;margin-bottom:12px">⚠ <?=h($err)?></div><?php endif;?>
<div class="us-card" style="padding:0;overflow-x:auto">
 <table class="us-t"><thead><tr><th>No</th><th>User</th><th>Password</th><th>Department</th><th>Role</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>
 <?php foreach($users as $i=>$u): $c=$rc[$u['role']]??$rc['viewer'];?>
  <tr>
   <td class="no"><?=$i+1?></td>
   <td><div class="un"><?=h($u['username'])?><?php if((int)$u['id']===(int)$me['id']):?> <span class="pill" style="background:#ECFDF5;color:#166534;font-size:11px">คุณ</span><?php endif;?></div><div class="dn"><?=h($u['display_name']?:'—')?></div></td>
   <td><div class="pw" x-show="pw!==<?=$u['id']?>"><span class="dots">••••••••</span><button type="button" class="btn" @click="pw=<?=$u['id']?>">ตั้งใหม่</button></div>
       <form method="post" class="pw" x-show="pw===<?=$u['id']?>" x-cloak><input type="hidden" name="action" value="setpw"><input type="hidden" name="id" value="<?=$u['id']?>"><input class="in" name="password" placeholder="Password ใหม่" minlength="6" required><button class="btn o">บันทึก</button><button type="button" class="btn" @click="pw=0">ยกเลิก</button></form></td>
   <td style="font-weight:600"><?=h($u['department']??'')?:'<span style="color:#CBD5E1">—</span>'?></td>
   <td><span class="pill" style="background:<?=$c[0]?>;color:<?=$c[1]?>"><?=h($roles[$u['role']]??$u['role'])?></span></td>
   <td><?=$u['active']?'<span class="pill" style="background:#DCFCE7;color:#166534">ใช้งาน</span>':'<span class="pill" style="background:#FEE2E2;color:#B91C1C">ปิด</span>'?><?=$u['must_change']?'<div style="font-size:12px;color:#B45309;margin-top:3px">รอเปลี่ยนรหัสครั้งแรก</div>':''?></td>
   <td style="white-space:nowrap"><button type="button" class="btn" @click='f=<?=h(json_encode(["id"=>(int)$u["id"],"username"=>$u["username"],"display_name"=>$u["display_name"],"department"=>$u["department"]??"","role"=>$u["role"],"active"=>(bool)$u["active"]],JSON_UNESCAPED_UNICODE))?>;$refs.form.scrollIntoView({behavior:"smooth"})'>แก้ไข</button>
   <?php if((int)$u['id']!==(int)$me['id']):?><form method="post" style="display:inline" onsubmit="return confirm('ลบผู้ใช้ <?=h($u['username'])?> ?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$u['id']?>"><button class="btn r">ลบ</button></form><?php endif;?></td>
  </tr>
 <?php endforeach;?></tbody></table>
</div>
<form class="us-card" method="post" x-ref="form">
 <div style="font-size:18px;font-weight:700;color:#0E1A2F;margin-bottom:10px" x-text="f.id?'แก้ไขผู้ใช้: '+f.username:'เพิ่มผู้ใช้ใหม่'"></div>
 <input type="hidden" name="action" value="save"><input type="hidden" name="id" :value="f.id">
 <div class="us-f">
  <div><label>User *</label><input name="username" x-model="f.username" required placeholder="เช่น somjet"></div>
  <div><label x-text="f.id?'Password (เว้นว่าง = เดิม)':'Password *'"></label><input name="password" autocomplete="new-password" placeholder="อย่างน้อย 6 ตัว"></div>
  <div><label>ชื่อ - สกุล</label><input name="display_name" x-model="f.display_name" list="auditorNames" placeholder="ชื่อที่แสดง"><datalist id="auditorNames"><?php foreach($auditorNames as $n):?><option value="<?=h($n)?>"><?php endforeach;?></datalist></div>
  <div><label>Department</label><select name="department" x-model="f.department"><option value="">— ไม่ระบุ —</option><?php foreach($depts as $d):?><option value="<?=h($d)?>"><?=h($d)?></option><?php endforeach;?></select></div>
  <div><label>Role</label><select name="role" x-model="f.role"><?php foreach($roles as $k=>$v):?><option value="<?=$k?>"><?=h($v)?></option><?php endforeach;?></select></div>
  <div style="display:flex;gap:8px;align-items:center"><label style="display:flex;align-items:center;gap:6px;margin:0 0 12px"><input type="checkbox" name="active" x-model="f.active" style="width:18px;height:18px"> ใช้งาน</label></div>
 </div>
 <div style="display:flex;gap:8px;margin-top:12px"><button class="btn o" style="height:44px;padding:0 22px;font-size:15px" x-text="f.id?'บันทึกการแก้ไข':'เพิ่มผู้ใช้'"></button>
 <button type="button" class="btn" style="height:44px" x-show="f.id" @click="f={id:0,username:'',display_name:'',department:'',role:'auditor',active:true}">ยกเลิก</button></div>
</form>
<div class="roles"><div><b>ผู้ดูแลระบบ (Admin)</b>ทุกหน้า: ข้อมูลพื้นฐาน แผนตรวจ ตั้งค่า สำรองข้อมูล และผู้ใช้</div><div><b>ผู้ตรวจ (Auditor)</b>บันทึกคะแนน Production/Office, TAG, นำเข้าผลตรวจ และดูรายงาน</div><div><b>ดูอย่างเดียว</b>ดู Dashboard รายงาน กราฟ และพิมพ์เอกสาร</div></div>
</div>
<?php require FIVE_S_ROOT.'/includes/footer.php'; ?>
