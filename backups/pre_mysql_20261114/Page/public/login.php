<?php
define('FIVE_S_NO_GUARD', true);
require_once dirname(__DIR__, 2).'/includes/config.php';
five_s_session();
$p = db(); five_s_users_init($p);
$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
// only allow redirects back into this application
if ($next === '' || strpos($next, FIVE_S_BASE) !== 0 || strpos($next, '//') === 0) $next = FIVE_S_BASE.'index.php';
$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $u = trim((string)($_POST['username'] ?? '')); $pw = (string)($_POST['password'] ?? '');
    $q = $p->prepare("SELECT * FROM users WHERE username=? COLLATE NOCASE AND active=1"); $q->execute([$u]);
    $row = $q->fetch();
    if ($row && password_verify($pw, (string)$row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['five_s_uid'] = (int)$row['id'];
        $_SESSION['five_s_default_pw'] = five_s_is_default_password($pw); // drives the warning banner only; the password itself is never stored
        $p->prepare("UPDATE users SET last_login=? WHERE id=?")->execute([date('Y-m-d H:i:s'), (int)$row['id']]);
        header('Location: '.((int)$row['must_change'] ? FIVE_S_BASE.'Page/public/account.php?first=1&next='.rawurlencode($next) : $next)); exit;
    }
    $err = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    usleep(400000);
}
if (five_s_user() && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: '.$next); exit; }
?>
<!doctype html>
<html lang="th"><head><meta charset="utf-8"><base href="<?=htmlspecialchars(FIVE_S_BASE,ENT_QUOTES)?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>เข้าสู่ระบบ | 5S SMART • ALUMET</title>
<link rel="icon" type="image/png" href="assets/alumet-mark.png">
<link rel="stylesheet" href="assets/vendor/fonts.css">
<script src="https://cdn.tailwindcss.com/3.4.16" onload="window.__twCDN=1"></script>
<script>window.__twCDN||document.write('<link rel="stylesheet" href="assets/vendor/tailwind.min.css">')</script>
<style>
body{font-family:"IBM Plex Sans Thai","Inter",Tahoma,sans-serif;margin:0;min-height:100vh;background:#0E1A2F}
.bg{min-height:100vh;display:grid;grid-template-columns:1.1fr 1fr}
.hero{position:relative;overflow:hidden;background:linear-gradient(135deg,#0A1630,#16305C 60%,#1d3d73);color:#fff;padding:56px;display:flex;flex-direction:column;justify-content:space-between}
.hero:after{content:"";position:absolute;right:-160px;top:-160px;width:520px;height:520px;border-radius:50%;background:radial-gradient(circle,rgba(242,106,33,.45),rgba(242,106,33,0) 68%)}
.hero h1{font-size:44px;line-height:1.2;font-weight:700;margin:0}.hero h1 span{color:#F7A21B}
.hero p{color:#CBD5E1;font-size:17px;max-width:460px}
.s5{display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1}.s5 span{border-radius:10px;padding:8px 14px;font-weight:700;font-size:15px}
.panel{background:#F8FAFC;display:flex;align-items:center;justify-content:center;padding:32px}
.card{width:100%;max-width:400px;background:#fff;border-radius:22px;padding:34px 32px;box-shadow:0 24px 60px rgba(15,23,42,.14)}
.card label{display:block;font-size:14px;font-weight:600;color:#334155;margin:14px 0 6px}
.card input{width:100%;height:50px;border:1px solid #CBD5E1;border-radius:12px;padding:0 14px;font-size:16px;font-family:inherit}
.card input:focus{outline:none;border-color:#F26A21;box-shadow:0 0 0 3px rgba(242,106,33,.15)}
.card button{width:100%;height:52px;margin-top:22px;border:0;border-radius:12px;background:#F26A21;color:#fff;font-size:17px;font-weight:700;cursor:pointer;font-family:inherit}
.card button:hover{background:#DB5512}
@media(max-width:860px){.bg{grid-template-columns:1fr}.hero{padding:30px;min-height:auto}.hero h1{font-size:30px}}
</style></head>
<body><div class="bg">
 <section class="hero">
  <div style="position:relative;z-index:1"><img src="assets/alumet-logo.png" alt="ALUMET" style="height:40px;background:#fff;border-radius:10px;padding:6px 10px"></div>
  <div style="position:relative;z-index:1"><h1>5S <span>SMART</span><br>Management System</h1><p>ระบบบริหารกิจกรรม 5 ส. — บันทึกคะแนน แผนตรวจ TAG และรายงานสำหรับติดบอร์ด</p></div>
  <div class="s5"><span style="background:#E5484D">สะสาง</span><span style="background:#F26A21">สะดวก</span><span style="background:#E9A800">สะอาด</span><span style="background:#16A34A">สุขลักษณะ</span><span style="background:#2563EB">สร้างนิสัย</span></div>
 </section>
 <section class="panel">
  <form class="card" method="post" autocomplete="on">
   <h2 style="margin:0;font-size:26px;font-weight:700;color:#0E1A2F">เข้าสู่ระบบ</h2>
   <p style="margin:6px 0 4px;color:#64748B;font-size:14.5px">สำหรับผู้ดูแลระบบและผู้ตรวจ (Auditor)</p>
   <?php if($err):?><div style="margin-top:12px;background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;border-radius:10px;padding:10px 12px;font-weight:600;font-size:14px">⚠ <?=htmlspecialchars($err)?></div><?php endif;?>
   <input type="hidden" name="next" value="<?=htmlspecialchars($next)?>">
   <label>ชื่อผู้ใช้</label><input name="username" required autofocus value="<?=htmlspecialchars((string)($_POST['username']??''))?>">
   <label>รหัสผ่าน</label><input type="password" name="password" required>
   <button type="submit">เข้าสู่ระบบ</button>
   <a href="index.php" style="display:block;text-align:center;margin-top:16px;color:#475569;font-size:14.5px;text-decoration:none">← ดูรายงานโดยไม่ต้องเข้าสู่ระบบ</a>
  </form>
 </section>
</div></body></html>
