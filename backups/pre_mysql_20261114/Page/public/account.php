<?php
define('FIVE_S_NO_GUARD', true);
require_once dirname(__DIR__, 2).'/includes/config.php';
$u = five_s_user();
if (!$u) { header('Location: '.FIVE_S_BASE.'Page/public/login.php?next='.rawurlencode(FIVE_S_BASE.'Page/public/account.php')); exit; }
$p = db(); $msg = ''; $err = '';
$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
if ($next === '' || strpos($next, FIVE_S_BASE) !== 0) $next = FIVE_S_BASE.'index.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $q = $p->prepare("SELECT password_hash FROM users WHERE id=?"); $q->execute([(int)$u['id']]);
    $cur = (string)$q->fetchColumn();
    $old = (string)($_POST['old'] ?? ''); $n1 = (string)($_POST['new1'] ?? ''); $n2 = (string)($_POST['new2'] ?? '');
    $name = trim((string)($_POST['display_name'] ?? ''));
    if (!password_verify($old, $cur)) $err = 'รหัสผ่านเดิมไม่ถูกต้อง';
    elseif ($n1 !== '' && mb_strlen($n1) < 6) $err = 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 6 ตัวอักษร';
    elseif ($n1 !== $n2) $err = 'รหัสผ่านใหม่ทั้งสองช่องไม่ตรงกัน';
    elseif ($n1 !== '' && five_s_is_default_password($n1)) $err = 'ห้ามใช้รหัสผ่านเริ่มต้นของระบบ กรุณาตั้งรหัสใหม่';
    else {
        if ($name !== '') $p->prepare("UPDATE users SET display_name=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$name, (int)$u['id']]);
        if ($n1 !== '') { $p->prepare("UPDATE users SET password_hash=?,must_change=0,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([password_hash($n1, PASSWORD_DEFAULT), (int)$u['id']]); unset($_SESSION['five_s_default_pw']); }
        // ตั้งรหัสผ่านครั้งแรกเสร็จ -> พากลับไปหน้าที่ต้องการใช้งานทันที
        if (!empty($_POST['first']) && $n1 !== '') { header('Location: '.$next); exit; }
        header('Location: '.FIVE_S_BASE.'Page/public/account.php?saved=1&next='.rawurlencode($next)); exit;
    }
}
if (isset($_GET['saved'])) $msg = 'บันทึกเรียบร้อยแล้ว';
$title = 'บัญชีของฉัน';
require FIVE_S_ROOT.'/includes/header.php';
?>
<div style="max-width:560px;margin:10px auto">
 <div class="card p-6">
  <h2 style="margin:0 0 4px;font-size:22px;font-weight:700;color:#0E1A2F">บัญชีของฉัน</h2>
  <p style="margin:0 0 14px;color:#64748B">ชื่อผู้ใช้ <b><?=h($u['username'])?></b> • สิทธิ์ <?=h(five_s_roles()[$u['role']] ?? $u['role'])?></p>
  <?php if(isset($_GET['first'])):?><div style="background:#FEF3C7;color:#92400E;border-radius:10px;padding:10px 12px;font-weight:600;margin-bottom:12px">เข้าสู่ระบบครั้งแรก กรุณาตั้งรหัสผ่านใหม่</div><?php endif;?>
  <?php if($msg):?><div style="background:#ECFDF5;color:#166534;border-radius:12px;padding:14px;font-weight:600;margin-bottom:14px">✓ <?=h($msg)?> — ใช้งานระบบต่อได้เลย
   <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap"><a href="<?=h($next)?>" style="background:#16A34A;color:#fff;border-radius:10px;padding:9px 16px;text-decoration:none">ไปหน้าที่ใช้งาน →</a><a href="Page/admin/schedule_manage.php" style="border:1px solid #86EFAC;color:#166534;border-radius:10px;padding:9px 16px;text-decoration:none">จัดตารางแผนตรวจ</a><a href="index.php" style="border:1px solid #86EFAC;color:#166534;border-radius:10px;padding:9px 16px;text-decoration:none">หน้าหลัก</a></div></div><?php endif;?>
  <?php if($err):?><div style="background:#FEF2F2;color:#991B1B;border-radius:10px;padding:10px 12px;font-weight:600;margin-bottom:12px">⚠ <?=h($err)?></div><?php endif;?>
  <form method="post" style="display:grid;gap:12px">
   <input type="hidden" name="next" value="<?=h($next)?>"><?php if(isset($_GET['first'])):?><input type="hidden" name="first" value="1"><?php endif;?>
   <div style="color:#64748B;font-size:13.5px">ต้องการเปลี่ยนชื่อหรือรหัสผ่านเท่านั้นจึงกรอกฟอร์มนี้ (ใส่รหัสผ่านเดิมเพื่อยืนยัน)</div>
   <label style="font-weight:600;font-size:14px">ชื่อที่แสดง<input name="display_name" value="<?=h($u['display_name'])?>" class="w-full border rounded-xl p-3" style="margin-top:6px"></label>
   <label style="font-weight:600;font-size:14px">รหัสผ่านเดิม *<input type="password" name="old" required class="w-full border rounded-xl p-3" style="margin-top:6px"></label>
   <label style="font-weight:600;font-size:14px">รหัสผ่านใหม่ (อย่างน้อย 6 ตัว)<input type="password" name="new1" class="w-full border rounded-xl p-3" style="margin-top:6px"></label>
   <label style="font-weight:600;font-size:14px">ยืนยันรหัสผ่านใหม่<input type="password" name="new2" class="w-full border rounded-xl p-3" style="margin-top:6px"></label>
   <button class="rounded-xl p-3 font-bold" style="background:#F26A21;color:#fff">บันทึก</button>
  </form>
 </div>
</div>
<?php require FIVE_S_ROOT.'/includes/footer.php'; ?>
