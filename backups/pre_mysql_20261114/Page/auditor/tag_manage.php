<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
$title='รับ Tag / ปิด Tag';
$p=db();
/* Additive migration for before/after evidence photos; preserves existing Tag records. */
$tagCols = $p->query("PRAGMA table_info(tags)")->fetchAll(PDO::FETCH_ASSOC);
$tagColNames = array_column($tagCols, 'name');
foreach (['before_photo','after_photo','source_photo'] as $photoCol) {
    if (!in_array($photoCol, $tagColNames, true)) {
        $p->exec("ALTER TABLE tags ADD COLUMN $photoCol TEXT NOT NULL DEFAULT ''");
    }
}
/* Department corrective-action fields: additive migration, keeps existing records intact. */
$tagCols = $p->query("PRAGMA table_info(tags)")->fetchAll(PDO::FETCH_ASSOC);
$tagColNames = array_column($tagCols, 'name');
foreach (['corrective_action','corrective_by','corrective_date','verification_note','tag_type','defect_types','suggestion','issued_by','receiver'] as $col) {
    if (!in_array($col, $tagColNames, true)) {
        $p->exec("ALTER TABLE tags ADD COLUMN $col TEXT NOT NULL DEFAULT ''");
    }
}
$uploadDir = FIVE_S_ROOT.'/uploads/tag_photos';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
$notice='';
$noticeType='success';
function save_tag_photos(string $field, string $uploadDir): string {
    if (!isset($_FILES[$field])) return '';
    $f = $_FILES[$field];
    $names = is_array($f['name'] ?? null) ? $f['name'] : [$f['name'] ?? ''];
    $tmpNames = is_array($f['tmp_name'] ?? null) ? $f['tmp_name'] : [$f['tmp_name'] ?? ''];
    $errors = is_array($f['error'] ?? null) ? $f['error'] : [$f['error'] ?? UPLOAD_ERR_NO_FILE];
    $sizes = is_array($f['size'] ?? null) ? $f['size'] : [$f['size'] ?? 0];
    $paths = [];
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $count = 0;
    foreach ($names as $i => $originalName) {
        $err = $errors[$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE || $originalName === '') continue;
        if ($err !== UPLOAD_ERR_OK) throw new RuntimeException('อัปโหลดรูปไม่สำเร็จ กรุณาลองใหม่ (รหัส ' . (int)$err . ')');
        if (($sizes[$i] ?? 0) > 12 * 1024 * 1024) throw new RuntimeException('รูปแต่ละภาพต้องมีขนาดไม่เกิน 12 MB');
        $tmp = $tmpNames[$i] ?? '';
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset($allowed[$mime]) || @getimagesize($tmp) === false) throw new RuntimeException('รองรับเฉพาะรูป JPG, PNG หรือ WEBP ที่ถูกต้อง');
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์เก็บรูปได้');
        $name = 'tag_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '_' . $field . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmp, $uploadDir . '/' . $name)) throw new RuntimeException('บันทึกรูปลงโฟลเดอร์ไม่สำเร็จ กรุณาตรวจสิทธิ์โฟลเดอร์ uploads/tag_photos');
        $paths[] = 'uploads/tag_photos/' . $name;
        $count++;
        if ($count > 10) throw new RuntimeException('แนบได้ไม่เกิน 10 รูปต่อช่อง');
    }
    return $paths ? json_encode($paths, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
}
function tag_photo_paths($raw): array {
    if (!$raw) return [];
    $decoded = json_decode((string)$raw, true);
    if (is_array($decoded)) return array_values(array_filter($decoded, 'is_string'));
    // Compatibility with older records that store one image path as plain text.
    return [(string)$raw];
}


/* Build dropdown options from the live 5S database without rewriting history. */
$departments = [];
foreach (["SELECT DISTINCT department AS v FROM areas WHERE TRIM(department)<>''",
           "SELECT DISTINCT department AS v FROM audit_plans WHERE TRIM(department)<>''",
           "SELECT DISTINCT department AS v FROM tags WHERE TRIM(department)<>''"] as $sqlOpt) {
    try { foreach ($p->query($sqlOpt)->fetchAll(PDO::FETCH_COLUMN) as $v) if (trim((string)$v)!=='') $departments[] = trim((string)$v); } catch(Throwable $e) {}
}
$departments = array_values(array_unique($departments)); natcasesort($departments); $departments = array_values($departments);
$unitMap = [];
try { foreach ($p->query("SELECT department, unit_no AS unit FROM areas WHERE TRIM(department)<>'' AND TRIM(unit_no)<>''")->fetchAll() as $r) { $d=trim((string)$r['department']); $u=trim((string)$r['unit']); if($d!==''&&$u!=='') $unitMap[$d][]=$u; } } catch(Throwable $e) {}
try { foreach ($p->query("SELECT department, unit_no AS unit FROM tags WHERE TRIM(department)<>'' AND TRIM(unit_no)<>''")->fetchAll() as $r) { $d=trim((string)$r['department']); $u=trim((string)$r['unit']); if($d!==''&&$u!=='') $unitMap[$d][]=$u; } } catch(Throwable $e) {}
try { foreach ($p->query("SELECT department, area_5s AS unit FROM audit_plans WHERE TRIM(department)<>'' AND TRIM(area_5s)<>''")->fetchAll() as $r) { $d=trim((string)$r['department']); $u=trim((string)$r['unit']); if($d!==''&&$u!=='') $unitMap[$d][]=$u; } } catch(Throwable $e) {}
foreach($unitMap as $d=>$units){
  $expanded=[];
  foreach($units as $unitValue){
    foreach(preg_split('/\s*[,;，、]\s*/u',trim((string)$unitValue),-1,PREG_SPLIT_NO_EMPTY) as $unitPart){
      $unitPart=trim($unitPart); if($unitPart!=='') $expanded[]=$unitPart;
    }
  }
  $units=array_values(array_unique($expanded));natcasesort($units);$unitMap[$d]=array_values($units);
}
$auditors=[];
foreach ([
"SELECT DISTINCT auditor_name AS v FROM audit_plans WHERE TRIM(auditor_name)<>''",
"SELECT DISTINCT auditor AS v FROM audits WHERE TRIM(auditor)<>''",
"SELECT DISTINCT auditor2 AS v FROM audits WHERE TRIM(auditor2)<>''",
"SELECT DISTINCT auditor3 AS v FROM audits WHERE TRIM(auditor3)<>''",
"SELECT DISTINCT auditor_name AS v FROM auditor_monthly_history WHERE TRIM(auditor_name)<>''",
"SELECT DISTINCT auditor_name AS v FROM auditor_monthly_history_unified WHERE TRIM(auditor_name)<>''",
"SELECT DISTINCT auditor AS v FROM tags WHERE TRIM(auditor)<>''"
] as $sqlOpt) { try { foreach($p->query($sqlOpt)->fetchAll(PDO::FETCH_COLUMN) as $v) if(trim((string)$v)!=='') $auditors[]=trim((string)$v); } catch(Throwable $e) {} }
$auditors=array_values(array_unique($auditors)); natcasesort($auditors); $auditors=array_values($auditors);
$editId=(int)($_GET['edit']??0); $editTag=null;
if($editId>0){$qEdit=$p->prepare("SELECT * FROM tags WHERE id=?");$qEdit->execute([$editId]);$editTag=$qEdit->fetch()?:null;}
$savedDefectTypes = json_decode((string)($editTag['defect_types'] ?? '[]'), true);
if (!is_array($savedDefectTypes)) $savedDefectTypes = [];

/* ---------- V65: ช่วยกรอกอัตโนมัติ ---------- */
$dueDays = max(1,(int)app_setting('tag_due_days','14'));
function tag_next_no(PDO $p): string {
    $max=0; $width=3;
    foreach($p->query("SELECT tag_no FROM tags")->fetchAll(PDO::FETCH_COLUMN) as $t){
        if(preg_match('/^TAG-?(\d+)$/i',trim((string)$t),$m)){ $max=max($max,(int)$m[1]); $width=max($width,strlen($m[1])); }
    }
    return 'TAG-'.str_pad((string)($max+1),$width,'0',STR_PAD_LEFT);
}
/** จัดบรรทัดข้อความให้สะอาด: ตัดช่องว่างซ้ำ จำกัดจำนวนบรรทัดและตัวอักษร */
function tag_clean(string $v,int $max,int $lines=1): string {
    $v=str_replace(["\r\n","\r"],"\n",trim($v));
    $rows=array_values(array_filter(array_map(fn($x)=>trim(preg_replace('/[ \t]+/u',' ',$x)),explode("\n",$v)),fn($x)=>$x!==''));
    $v=implode("\n",array_slice($rows,0,$lines));
    return mb_substr($v,0,$max);
}
$LIM=['note'=>[120,2],'suggestion'=>[200,3],'corrective_action'=>[200,3],'verification_note'=>[150,2],'issued_by'=>[60,1],'receiver'=>[60,1],'auditor'=>[60,1],'corrective_by'=>[60,1]];

if($_SERVER['REQUEST_METHOD']==='POST'){
 $action=$_POST['action']??'';
 if($action==='save'){
   try {
     foreach($LIM as $k=>[$mx,$ln]) $_POST[$k]=tag_clean((string)($_POST[$k]??''),$mx,$ln);
     $tag=strtoupper(trim($_POST['tag_no']??''));$dept=trim($_POST['department']??'');$unit=trim($_POST['unit_no']??'');
     if($tag==='') $tag=tag_next_no($p);
     $td=$_POST['tag_date']??''; if($td==='') $td=date('Y-m-d');
     $due=$_POST['due_date']??''; if($due==='') $due=date('Y-m-d',strtotime($td.' +'.$dueDays.' days'));
     $closed=$_POST['closed_date']??'';
     $status=($closed!=='')?'CLOSED':'OPEN';
     $aud=$_POST['auditor'];$note=$_POST['note'];
     $correctiveAction=$_POST['corrective_action'];$correctiveBy=$_POST['corrective_by'];$correctiveDate=trim($_POST['corrective_date']??'');$verificationNote=$_POST['verification_note'];
     $tagType=strtoupper(trim((string)($_POST['tag_type']??''))); if(!in_array($tagType,['RED','WHITE'],true)) $tagType='';
     $rawDefects=$_POST['defect_types']??[]; if(!is_array($rawDefects)) $rawDefects=[]; $defectOptions=['1','2','3','4','5','6','7','8','9','10']; $rawDefects=array_values(array_unique(array_intersect($defectOptions,array_map('strval',$rawDefects))));
     $defectTypes=json_encode($rawDefects,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
     $suggestion=$_POST['suggestion']; $issuedBy=$_POST['issued_by']; $receiver=$_POST['receiver'];
     if($dept==='') throw new RuntimeException('กรุณาเลือกแผนก');
     $editId=(int)($_POST['id']??0);
     $dup=$p->prepare('SELECT id FROM tags WHERE UPPER(TRIM(tag_no))=UPPER(TRIM(?)) AND id<>? LIMIT 1');
     $dup->execute([$tag,$editId]);
     if($dup->fetchColumn()) throw new RuntimeException('Tag No. '.$tag.' มีอยู่แล้วในระบบ กรุณาใช้หมายเลขใหม่ หรือเปิดรายการเดิมเพื่อแก้ไข');
     $before=save_tag_photos('before_photo',$uploadDir);
     $after=save_tag_photos('after_photo',$uploadDir);
     $sourcePhoto=save_tag_photos('source_photo_file',$uploadDir);
     if($editId>0){
       $oldQ=$p->prepare("SELECT before_photo,after_photo,source_photo FROM tags WHERE id=?");$oldQ->execute([$editId]);$old=$oldQ->fetch();
       if(!$old) throw new RuntimeException('ไม่พบรายการ Tag ที่ต้องการแก้ไข');
       // รูปใหม่ต่อท้ายรูปเดิม (ไม่ทับ) และลบรูปที่ผู้ใช้กดเอาออก
       $merge=function(string $oldRaw,string $newRaw,string $field){ $keep=tag_photo_paths($oldRaw); $rm=(array)($_POST['remove_'.$field]??[]); $keep=array_values(array_diff($keep,$rm)); $all=array_merge($keep,tag_photo_paths($newRaw)); return $all?json_encode(array_slice($all,0,20),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):''; };
       $before=$merge((string)$old['before_photo'],$before,'before_photo');
       $after=$merge((string)$old['after_photo'],$after,'after_photo');
       if($sourcePhoto==='') $sourcePhoto=(string)($old['source_photo']??'');
       $q=$p->prepare("UPDATE tags SET tag_no=?,department=?,unit_no=?,tag_date=?,due_date=?,closed_date=?,status=?,auditor=?,note=?,before_photo=?,after_photo=?,source_photo=?,corrective_action=?,corrective_by=?,corrective_date=?,verification_note=?,tag_type=?,defect_types=?,suggestion=?,issued_by=?,receiver=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
       $q->execute([$tag,$dept,$unit,$td,$due,$closed,$status,$aud,$note,$before,$after,$sourcePhoto,$correctiveAction,$correctiveBy,$correctiveDate,$verificationNote,$tagType,$defectTypes,$suggestion,$issuedBy,$receiver,$editId]);$msg='แก้ไข '.$tag.' เรียบร้อย';
     }else{
       $q=$p->prepare("INSERT INTO tags(tag_no,department,unit_no,tag_date,due_date,closed_date,status,auditor,note,before_photo,after_photo,source_photo,corrective_action,corrective_by,corrective_date,verification_note,tag_type,defect_types,suggestion,issued_by,receiver,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)");
       $q->execute([$tag,$dept,$unit,$td,$due,$closed,$status,$aud,$note,$before,$after,$sourcePhoto,$correctiveAction,$correctiveBy,$correctiveDate,$verificationNote,$tagType,$defectTypes,$suggestion,$issuedBy,$receiver]);$msg='บันทึก '.$tag.' เรียบร้อย';
     }
     header('Location: '.FIVE_S_BASE.'Page/auditor/tag_manage.php?msg='.urlencode($msg)); exit;
   } catch (Throwable $e) {
     $notice=$e->getMessage(); $noticeType='error';
     $editTag=array_merge($editTag??[], array_map(fn($v)=>is_array($v)?$v:(string)$v,$_POST));
     $editTag['defect_types']=json_encode(array_values((array)($_POST['defect_types']??[])));
     $savedDefectTypes=array_values((array)($_POST['defect_types']??[]));
     if((int)($_POST['id']??0)===0) unset($editTag['id']);
   }
 } elseif($action==='delete'){
   $id=(int)($_POST['id']??0);
   if($id>0){$p->prepare('DELETE FROM tags WHERE id=?')->execute([$id]);}
   header('Location: '.FIVE_S_BASE.'Page/auditor/tag_manage.php?msg='.urlencode('ลบรายการ Tag เรียบร้อย')); exit;
 } elseif($action==='close'){
   $id=(int)$_POST['id'];$cd=($_POST['closed_date']??'')?:date('Y-m-d');$p->prepare("UPDATE tags SET status='CLOSED',closed_date=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$cd,$id]);
   header('Location: '.FIVE_S_BASE.'Page/auditor/tag_manage.php?msg='.urlencode('ปิด Tag เรียบร้อย')); exit;
 } elseif($action==='reopen'){
   $id=(int)$_POST['id'];$p->prepare("UPDATE tags SET status='OPEN',closed_date='',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
   header('Location: '.FIVE_S_BASE.'Page/auditor/tag_manage.php?msg='.urlencode('เปิด Tag กลับมาเพื่อติดตาม')); exit;
 }
}
if(isset($_GET['msg']) && $notice===''){ $notice=(string)$_GET['msg']; $noticeType='success'; }

/* ---------- ข้อมูลสรุป ---------- */
$today=date('Y-m-d');
$allTags=$p->query("SELECT * FROM tags ORDER BY CASE WHEN status='OPEN' THEN 0 ELSE 1 END, tag_date DESC, id DESC")->fetchAll();
$monthsAvail=[]; foreach($allTags as $r){ if(preg_match('/^\d{4}-\d{2}/',(string)$r['tag_date'])) $monthsAvail[substr($r['tag_date'],0,7)]=true; } krsort($monthsAvail);
$selM=(string)($_GET['m']??''); if($selM!=='' && !isset($monthsAvail[$selM])) $selM='';
$thM=[1=>'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$mLabel=function(string $ym) use($thM){ return $thM[(int)substr($ym,5,2)].' '.((int)substr($ym,0,4)+543); };
$thDate=function(string $d){ if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})/',$d,$m)) return '—'; return (int)$m[3].'/'.(int)$m[2].'/'.(((int)$m[1]+543)%100); };
$tags=array_values(array_filter($allTags,fn($r)=>$selM===''||substr((string)$r['tag_date'],0,7)===$selM));
$S=['total'=>0,'open'=>0,'over'=>0,'closed'=>0,'red'=>0,'white'=>0,'days'=>[]];$byDept=[];$byDef=array_fill(1,10,0);
foreach($tags as &$r){
  $closed=$r['status']==='CLOSED';
  $over=!$closed && trim((string)$r['due_date'])!=='' && $r['due_date']<$today;
  $r['_over']=$over;
  $r['_left']=(!$closed && preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$r['due_date'])) ? (int)round((strtotime($r['due_date'])-strtotime($today))/86400) : null;
  $S['total']++; $closed?$S['closed']++:$S['open']++; if($over)$S['over']++;
  if(($r['tag_type']??'')==='RED')$S['red']++; elseif(($r['tag_type']??'')==='WHITE')$S['white']++;
  if($closed && preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$r['closed_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$r['tag_date'])) $S['days'][]=max(0,(strtotime($r['closed_date'])-strtotime($r['tag_date']))/86400);
  $d=trim((string)$r['department'])?:'ไม่ระบุ'; $byDept[$d]??=['t'=>0,'o'=>0,'v'=>0,'c'=>0]; $byDept[$d]['t']++; $closed?$byDept[$d]['c']++:$byDept[$d]['o']++; if($over)$byDept[$d]['v']++;
  foreach((array)json_decode((string)($r['defect_types']??'[]'),true) as $k){ if(isset($byDef[(int)$k])) $byDef[(int)$k]++; }
}
unset($r);
uasort($byDept,fn($a,$b)=>[$b['o'],$b['t']]<=>[$a['o'],$a['t']]);
$closeRate=$S['total']?round($S['closed']/$S['total']*100,1):0;
$avgDays=$S['days']?round(array_sum($S['days'])/count($S['days']),1):null;
$defectLabels=['1'=>'ก่อให้เกิดการชำรุด','2'=>'Basic Condition ไม่ดี','3'=>'ยากลำบากในการทำงาน','4'=>'ก่อให้เกิดความสกปรก','5'=>'ก่อให้เกิดของเสีย','6'=>'มากเกินความจำเป็น / ระเกะระกะ','7'=>'ก่อให้เกิดอันตราย','8'=>'ก่อให้เกิดการเปลืองพลังงาน','9'=>'ความปลอดภัย','10'=>'สิ่งแวดล้อม'];
$F=$editTag??[]; $isEdit=!empty($F['id']);
$fv=fn(string $k,string $d='')=>h((string)($F[$k]??$d));
$nextNo=tag_next_no($p);
$periodLabel=$selM===''?'ทุกเดือน':$mLabel($selM);
$openForm=$isEdit || $noticeType==='error' || isset($_GET['new']);
require FIVE_S_ROOT.'/includes/header.php';?>
<style>
.tg{--navy:#0E1A2F;--org:#F26A21;--ok:#16A34A;--bad:#DC2626;--warn:#D97706;--line:#E4E8EF;--mut:#64748B;font-size:15px;color:#0F172A}
.tg .hidden{display:none!important}
.tg-head{display:flex;align-items:center;gap:16px;flex-wrap:wrap;background:linear-gradient(115deg,#0E1A2F,#16305C 70%,#1d3d73);color:#fff;border-radius:20px;padding:22px 26px;margin-bottom:14px;position:relative;overflow:hidden}
.tg-head:after{content:"";position:absolute;right:-90px;top:-140px;width:360px;height:360px;border-radius:50%;background:radial-gradient(circle,rgba(242,106,33,.45),rgba(242,106,33,0) 70%);pointer-events:none}
.tg-head h1{margin:0;font-size:30px;font-weight:700;line-height:1.3}.tg-head p{margin:4px 0 0;color:#CBD5E1}
.tg-auto{display:inline-flex;align-items:center;gap:7px;margin-top:10px;background:rgba(22,163,74,.18);border:1px solid rgba(74,222,128,.5);color:#BBF7D0;border-radius:999px;padding:4px 12px;font-size:13px;font-weight:600}
.tg-auto i{width:8px;height:8px;border-radius:50%;background:#4ADE80;box-shadow:0 0 0 4px rgba(74,222,128,.25)}
.tg-head .acts{margin-left:auto;display:flex;gap:10px;flex-wrap:wrap;position:relative;z-index:1}
.tg-btn{display:inline-flex;align-items:center;gap:8px;height:44px;padding:0 16px;border-radius:11px;border:1px solid transparent;font-weight:700;font-size:15px;cursor:pointer;text-decoration:none;white-space:nowrap}
.tg-btn svg{width:18px;height:18px}
.tg-btn.org{background:var(--org);color:#fff}.tg-btn.org:hover{background:#DB5512}
.tg-btn.line{background:#06C755;color:#fff}.tg-btn.ghost{background:#fff;color:var(--navy);border-color:#CBD5E1}.tg-btn.wh{background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.3)}
.tg-btn.navy{background:var(--navy);color:#fff}
.tg-msg{padding:12px 16px;border-radius:12px;font-weight:600;margin-bottom:12px}.tg-msg.ok{background:#ECFDF5;color:#166534;border:1px solid #BBF7D0}.tg-msg.err{background:#FEF2F2;color:#991B1B;border:1px solid #FECACA}
.tg-card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px 20px;margin-bottom:14px;box-shadow:0 1px 2px rgba(15,23,42,.03)}
.tg-card h2{margin:0;font-size:19px;font-weight:700;color:var(--navy)}.tg-card .sub{color:var(--mut);font-size:13.5px;margin-top:2px}
.tg-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:space-between;margin-bottom:12px}
.tg-kpis{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:14px}
.tg-kpi{background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px 16px;border-top:4px solid var(--c);text-decoration:none;color:inherit;display:block}
.tg-kpi .l{font-size:14px;color:var(--mut);font-weight:600}.tg-kpi .v{font:800 34px/1.15 Inter,sans-serif;color:var(--c);margin-top:4px}.tg-kpi .v small{font:600 14px "IBM Plex Sans Thai",sans-serif;color:var(--mut);margin-left:4px}
.tg-grid2{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,1fr);gap:14px}
.tg-tbl{width:100%;border-collapse:collapse}.tg-tbl th{text-align:left;font-size:13px;color:var(--mut);font-weight:600;padding:8px 10px;border-bottom:2px solid #EDF1F6}.tg-tbl td{padding:9px 10px;border-bottom:1px solid #F1F4F8;font-size:15px}
.tg-tbl td.n{font:700 16px Inter,sans-serif;text-align:center}.tg-tbl th.n{text-align:center}
.bar{height:8px;background:#EEF2F7;border-radius:9px;overflow:hidden;min-width:90px}.bar i{display:block;height:100%;border-radius:9px;background:var(--ok)}
.defl{display:grid;grid-template-columns:22px 1fr 34px;gap:8px;align-items:center;margin:7px 0;font-size:14px}.defl b{font:700 13px Inter,sans-serif;color:var(--mut)}.defl .bar{min-width:0}.defl .bar i{background:var(--org)}.defl em{font:700 15px Inter,sans-serif;font-style:normal;text-align:right}
.tg-split{display:flex;height:12px;border-radius:9px;overflow:hidden;margin:8px 0 4px;background:#EEF2F7}.tg-split i{display:block;height:100%}
.tg-sel{height:44px;border:1px solid #CBD5E1;border-radius:11px;padding:0 12px;font-size:15px;font-weight:600;color:var(--navy);background:#fff}
/* form */
.tg-form{display:grid;gap:14px}
.tg-sec{border:1px solid var(--line);border-radius:16px;padding:16px 18px;background:#fff}
.tg-sec>h3{margin:0 0 12px;font-size:17px;font-weight:700;color:var(--navy);display:flex;align-items:center;gap:10px}
.tg-sec>h3 .no{width:28px;height:28px;border-radius:50%;background:var(--navy);color:#fff;font:700 14px/28px Inter,sans-serif;text-align:center;flex:none}
.tg-sec>h3 small{font-weight:500;color:var(--mut);font-size:13px}
.tg-fg{display:grid;gap:12px;grid-template-columns:repeat(4,minmax(0,1fr))}
.tg-f{display:flex;flex-direction:column;gap:5px;min-width:0}.tg-f.s2{grid-column:span 2}.tg-f.s4{grid-column:1/-1}
.tg-f>label,.tg-lb{font-size:13.5px;font-weight:600;color:#334155;display:flex;justify-content:space-between;gap:8px}
.tg-f>label .cnt{font:500 12px Inter,sans-serif;color:#94A3B8}.tg-f>label .cnt.full{color:var(--bad)}
.tg-in{width:100%;height:46px;border:1px solid #CBD5E1;border-radius:11px;padding:0 12px;font-size:16px;color:#0F172A;background:#fff;font-family:inherit}
textarea.tg-in{height:auto;padding:10px 12px;line-height:1.6;resize:vertical;min-height:52px}
.tg-in:focus{outline:none;border-color:var(--org);box-shadow:0 0 0 3px rgba(242,106,33,.15)}
.tg-help{font-size:12.5px;color:var(--mut)}
.tg-inline{display:flex;gap:6px}.tg-inline .tg-in{flex:1}
.tg-mini{height:46px;padding:0 12px;border-radius:11px;border:1px solid #CBD5E1;background:#F8FAFC;font-weight:600;cursor:pointer;color:var(--navy);white-space:nowrap}
.tg-chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}.tg-chips button{border:1px solid #CBD5E1;background:#fff;border-radius:999px;padding:3px 10px;font-size:12.5px;font-weight:600;cursor:pointer;color:#334155}
.tg-type{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.tg-type input{position:absolute;opacity:0;pointer-events:none}
.tg-type label{display:flex;align-items:center;justify-content:center;gap:8px;height:46px;border-radius:11px;border:2px solid #E2E8F0;font-weight:700;cursor:pointer;background:#fff}
.tg-type label i{width:16px;height:16px;border-radius:4px;border:1px solid rgba(0,0,0,.15)}
.tg-type input:checked+label.red{border-color:var(--bad);background:#FEF2F2;color:#B91C1C}
.tg-type input:checked+label.white{border-color:#475569;background:#F1F5F9;color:#0F172A}
.tg-defs{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}
.tg-defs input{position:absolute;opacity:0;pointer-events:none}
.tg-defs label{display:flex;gap:8px;align-items:flex-start;border:1.5px solid #E2E8F0;border-radius:11px;padding:9px 10px;font-size:14px;cursor:pointer;background:#fff;line-height:1.35;min-height:58px}
.tg-defs label b{flex:none;width:24px;height:24px;border-radius:7px;background:#F1F5F9;color:#475569;font:700 13px/24px Inter,sans-serif;text-align:center}
.tg-defs input:checked+label{border-color:var(--org);background:#FFF7ED;color:#9A3412}.tg-defs input:checked+label b{background:var(--org);color:#fff}
.tg-photos{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.tg-drop{border:2px dashed #CBD5E1;border-radius:14px;padding:14px;background:#F8FAFC;display:flex;flex-direction:column;gap:8px;transition:.15s}
.tg-drop.over{border-color:var(--org);background:#FFF7ED}
.tg-drop h4{margin:0;font-size:15px;font-weight:700;display:flex;align-items:center;gap:8px}.tg-drop h4 i{width:10px;height:10px;border-radius:50%;background:var(--c)}
.tg-drop .pick{display:flex;align-items:center;justify-content:center;gap:8px;height:48px;border-radius:11px;background:#fff;border:1px solid #CBD5E1;font-weight:700;cursor:pointer;color:var(--navy)}
.tg-drop .pick:hover{border-color:var(--org);color:var(--org)}
.tg-drop input[type=file]{display:none}
.tg-thumbs{display:flex;flex-wrap:wrap;gap:8px}
.tg-th{position:relative;width:84px;height:84px;border-radius:10px;overflow:hidden;border:1px solid #E2E8F0;background:#fff}
.tg-th img{width:100%;height:100%;object-fit:cover;cursor:zoom-in;display:block}
.tg-th button{position:absolute;right:3px;top:3px;width:22px;height:22px;border-radius:50%;border:0;background:rgba(15,23,42,.75);color:#fff;font-size:13px;line-height:22px;cursor:pointer;padding:0}
.tg-th.rm{opacity:.35}.tg-th.rm:after{content:"จะลบ";position:absolute;inset:auto 0 0 0;background:var(--bad);color:#fff;font-size:11px;text-align:center}
.tg-th .tag{position:absolute;left:3px;bottom:3px;background:rgba(15,23,42,.7);color:#fff;font-size:10.5px;border-radius:5px;padding:0 5px}
.tg-ocr{margin-top:4px}.tg-ocr summary{cursor:pointer;font-weight:600;color:#4338CA;font-size:13.5px}
.tg-save{position:sticky;bottom:0;z-index:20;display:flex;gap:10px;align-items:center;flex-wrap:wrap;background:rgba(255,255,255,.96);border:1px solid var(--line);border-radius:16px;padding:12px 16px;box-shadow:0 -6px 20px rgba(15,23,42,.08)}
.tg-save .st{margin-right:auto;font-size:14px;color:var(--mut)}
.tg-close-auto{display:flex;align-items:center;gap:10px;background:#ECFDF5;border:1px solid #BBF7D0;border-radius:12px;padding:10px 12px;font-size:14px;color:#166534;font-weight:600}
/* list */
.tg-filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.tg-seg{display:inline-flex;background:#F1F5F9;border-radius:12px;padding:4px;gap:4px;flex-wrap:wrap}
.tg-seg button{border:0;background:transparent;padding:8px 14px;border-radius:9px;font-weight:700;color:#475569;cursor:pointer;font-size:14.5px;font-family:inherit}
.tg-seg button.on{background:var(--navy);color:#fff}.tg-seg button span{font:700 12px Inter,sans-serif;margin-left:4px;opacity:.8}
.tg-search{height:44px;border:1px solid #CBD5E1;border-radius:11px;padding:0 12px;font-size:15px;min-width:220px;flex:1;max-width:340px}
.tg-list{width:100%;border-collapse:separate;border-spacing:0}
.tg-list th{text-align:left;font-size:13px;font-weight:600;color:var(--mut);padding:10px;border-bottom:2px solid #EDF1F6;white-space:nowrap}
.tg-list td{padding:12px 10px;border-bottom:1px solid #F1F4F8;vertical-align:top}
.tg-list tr.over td{background:#FFFBFB}
.tg-no{font:800 16px Inter,sans-serif;color:var(--navy)}
.pill{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:2px 10px;font-size:12.5px;font-weight:700;white-space:nowrap}
.pill.red{background:#FEE2E2;color:#B91C1C}.pill.white{background:#F1F5F9;color:#334155;border:1px solid #CBD5E1}.pill.none{background:#FEF3C7;color:#92400E}
.pill.open{background:#FFEDD5;color:#C2410C}.pill.closed{background:#DCFCE7;color:#166534}.pill.over{background:#DC2626;color:#fff}
.tg-note{max-width:340px;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tg-defmini{display:flex;gap:4px;flex-wrap:wrap;margin-top:5px}.tg-defmini span{font:700 11px Inter,sans-serif;background:#FFF7ED;color:#C2410C;border-radius:5px;padding:1px 6px}
.tg-pics{display:flex;gap:6px}.tg-pics figure{margin:0;text-align:center}.tg-pics figcaption{font-size:11px;color:var(--mut);margin-top:2px}
.tg-pics img{width:56px;height:56px;border-radius:9px;object-fit:cover;border:1px solid #E2E8F0;cursor:zoom-in;display:block}
.tg-pics .em{width:56px;height:56px;border-radius:9px;border:1.5px dashed #E2E8F0;display:flex;align-items:center;justify-content:center;color:#CBD5E1;font-size:20px}
.tg-pics .more{font-size:11px;color:var(--mut)}
.tg-dt{font-size:14px;line-height:1.6;white-space:nowrap}.tg-dt small{color:var(--mut)}
.tg-acts{display:flex;gap:6px;flex-wrap:wrap}
.ib{display:inline-flex;align-items:center;gap:5px;height:34px;padding:0 10px;border-radius:9px;border:1px solid #CBD5E1;background:#fff;font-size:13.5px;font-weight:600;color:var(--navy);cursor:pointer;text-decoration:none;font-family:inherit}
.ib svg{width:15px;height:15px}.ib.g{border-color:#86EFAC;color:#15803D;background:#F0FDF4}.ib.r{border-color:#FECACA;color:#B91C1C}
.tg-empty{text-align:center;color:var(--mut);padding:30px}
.tg-lb-ov{position:fixed;inset:0;background:rgba(2,6,23,.85);z-index:9999;display:flex;align-items:center;justify-content:center;padding:30px;cursor:zoom-out}
.tg-lb-ov img{max-width:100%;max-height:100%;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
@media(max-width:1200px){.tg-kpis{grid-template-columns:repeat(3,1fr)}.tg-defs{grid-template-columns:repeat(3,minmax(0,1fr))}.tg-grid2{grid-template-columns:minmax(0,1fr)}}
@media(max-width:900px){.tg-fg{grid-template-columns:repeat(2,minmax(0,1fr))}.tg-photos{grid-template-columns:1fr}.tg-list thead{display:none}.tg-list,.tg-list tbody,.tg-list tr,.tg-list td{display:block;width:100%}.tg-list tr{border:1px solid var(--line);border-radius:14px;margin-bottom:10px;padding:6px}.tg-list td{border:0;padding:6px 8px}}
@media(max-width:600px){.tg-kpis{grid-template-columns:1fr 1fr}.tg-defs{grid-template-columns:1fr 1fr}.tg-fg{grid-template-columns:1fr}.tg-f.s2{grid-column:auto}.tg-head h1{font-size:23px}.tg-btn{flex:1;justify-content:center}}
</style>
<div class="tg">
<div class="tg-head">
  <div><h1>รับ / ติดตาม / ปิด TAG 5 ส.</h1><p>รับ Tag → แก้ไข → แนบรูปหลังแก้ → ปิดจบ → สรุปอัตโนมัติ</p>
  <span class="tg-auto"><i></i> เลข Tag, กำหนดปิด <?=$dueDays?> วัน, สถานะเกินกำหนด และสรุป คำนวณอัตโนมัติ</span></div>
  <div class="acts">
    <button type="button" class="tg-btn org" onclick="tgOpenForm()"><?=five_s_icon('tag')?> รับ Tag ใหม่</button>
    <button type="button" class="tg-btn line" onclick="FiveSCopy.copyElement('#tgSummary','5S_TAG_Summary.png',{windowWidth:1500})"><?=five_s_icon('file')?> คัดลอกสรุปส่ง LINE</button>
    <a class="tg-btn wh" target="_blank" href="Page/public/tag_print.php"><?=five_s_icon('printer')?> พิมพ์สรุป</a>
  </div>
</div>
<?php if($notice):?><div class="tg-msg <?=$noticeType==='error'?'err':'ok'?>"><?=$noticeType==='error'?'⚠ ':'✓ '?><?=h($notice)?></div><?php endif;?>

<!-- ===== สรุป ===== -->
<div id="tgSummary">
<div class="tg-row" style="margin-bottom:10px">
  <div><h2 style="margin:0;font-size:21px;color:#0E1A2F">สรุป TAG 5 ส. — <?=h($periodLabel)?></h2><div class="sub" style="color:#64748B;font-size:13.5px">ข้อมูล ณ <?=$thDate($today)?> • ALUMET 5S SMART</div></div>
  <form method="get" class="no-copy"><select name="m" class="tg-sel" onchange="this.form.submit()"><option value="">ทุกเดือน</option><?php foreach(array_keys($monthsAvail) as $ym):?><option value="<?=$ym?>" <?=$ym===$selM?'selected':''?>><?=$mLabel($ym)?></option><?php endforeach;?></select></form>
</div>
<div class="tg-kpis">
  <a class="tg-kpi" style="--c:#0E1A2F" href="<?=h(five_s_self())?>#tgList" data-f="all"><div class="l">Tag ทั้งหมด</div><div class="v"><?=$S['total']?><small>ใบ</small></div></a>
  <a class="tg-kpi" style="--c:#EA580C" href="<?=h(five_s_self())?>#tgList" data-f="open"><div class="l">ยังไม่ปิด</div><div class="v"><?=$S['open']?><small>ใบ</small></div></a>
  <a class="tg-kpi" style="--c:#DC2626" href="<?=h(five_s_self())?>#tgList" data-f="over"><div class="l">เกินกำหนดปิด</div><div class="v"><?=$S['over']?><small>ใบ</small></div></a>
  <a class="tg-kpi" style="--c:#16A34A" href="<?=h(five_s_self())?>#tgList" data-f="closed"><div class="l">ปิดแล้ว</div><div class="v"><?=$S['closed']?><small>ใบ</small></div></a>
  <div class="tg-kpi" style="--c:#2563EB"><div class="l">อัตราปิด Tag</div><div class="v"><?=$closeRate?><small>%</small></div></div>
  <div class="tg-kpi" style="--c:#7C3AED"><div class="l">เวลาปิดเฉลี่ย</div><div class="v"><?=$avgDays===null?'—':$avgDays?><small>วัน</small></div></div>
</div>
<div class="tg-grid2">
  <div class="tg-card"><h2>สรุปรายแผนก</h2><div class="sub">เรียงจากแผนกที่ยังมี Tag ค้างมากที่สุด</div>
    <div style="overflow-x:auto"><table class="tg-tbl" style="margin-top:8px"><thead><tr><th>แผนก</th><th class="n">ทั้งหมด</th><th class="n">ค้าง</th><th class="n">เกินกำหนด</th><th class="n">ปิดแล้ว</th><th>% ปิด</th></tr></thead><tbody>
    <?php foreach($byDept as $d=>$v): $pc=$v['t']?round($v['c']/$v['t']*100):0;?><tr><td style="font-weight:700"><?=h($d)?></td><td class="n"><?=$v['t']?></td><td class="n" style="color:#EA580C"><?=$v['o']?></td><td class="n" style="color:<?=$v['v']?'#DC2626':'#CBD5E1'?>"><?=$v['v']?></td><td class="n" style="color:#16A34A"><?=$v['c']?></td><td><div style="display:flex;align-items:center;gap:8px"><div class="bar" style="flex:1"><i style="width:<?=$pc?>%"></i></div><b style="font:700 14px Inter,sans-serif;min-width:38px;text-align:right"><?=$pc?>%</b></div></td></tr><?php endforeach;?>
    <?php if(!$byDept):?><tr><td colspan="6" class="tg-empty">ยังไม่มี Tag ในช่วงนี้</td></tr><?php endif;?>
    </tbody></table></div></div>
  <div class="tg-card"><h2>ประเภทจุดบกพร่องที่พบ</h2><div class="sub">นับจากทุก Tag ในช่วงที่เลือก (1 Tag เลือกได้หลายข้อ)</div>
    <?php $mx=max(1,max($byDef)); foreach($defectLabels as $k=>$lb):?><div class="defl"><b><?=$k?></b><div><div style="font-size:13.5px;margin-bottom:3px"><?=h($lb)?></div><div class="bar"><i style="width:<?=round($byDef[(int)$k]/$mx*100)?>%"></i></div></div><em><?=$byDef[(int)$k]?></em></div><?php endforeach;?>
    <div style="margin-top:12px;font-size:14px;font-weight:600">Tag แดง <b style="color:#DC2626"><?=$S['red']?></b> • Tag ขาว <b><?=$S['white']?></b> • ไม่ระบุ <b style="color:#D97706"><?=$S['total']-$S['red']-$S['white']?></b></div>
    <?php $tt=max(1,$S['total']);?><div class="tg-split"><i style="width:<?=$S['red']/$tt*100?>%;background:#DC2626"></i><i style="width:<?=$S['white']/$tt*100?>%;background:#94A3B8"></i><i style="width:<?=($S['total']-$S['red']-$S['white'])/$tt*100?>%;background:#FBBF24"></i></div>
  </div>
</div>
</div>

<!-- ===== ฟอร์มรับ / แก้ไข Tag ===== -->
<form method="post" enctype="multipart/form-data" id="tgForm" class="tg-card <?=$openForm?'':'hidden'?>" style="padding:20px">
<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$fv('id')?>">
<div class="tg-row"><div><h2><?=$isEdit?'แก้ไข '.h((string)$F['tag_no']):'รับ Tag ใหม่'?></h2><div class="sub">ช่องที่มี * จำเป็น • ระบบจัดบรรทัดและจำกัดความยาวข้อความให้พอดีแบบฟอร์มพิมพ์อัตโนมัติ</div></div>
<button type="button" class="tg-btn ghost" onclick="tgCloseForm()">ซ่อนฟอร์ม</button></div>
<div class="tg-form">
 <section class="tg-sec"><h3><span class="no">1</span>ข้อมูล Tag</h3>
  <div class="tg-fg">
   <div class="tg-f"><label>Tag No. *<span class="tg-help">เว้นว่าง = ใช้เลขถัดไป</span></label><div class="tg-inline"><input name="tag_no" class="tg-in" style="font:700 16px Inter,sans-serif" placeholder="<?=h($nextNo)?>" value="<?=$fv('tag_no')?>"><button type="button" class="tg-mini" onclick="this.previousElementSibling.value='<?=h($nextNo)?>'" title="ใช้เลขถัดไปอัตโนมัติ">↻ <?=h($nextNo)?></button></div></div>
   <div class="tg-f"><span class="tg-lb">ประเภท Tag</span><div class="tg-type"><input type="radio" id="ttR" name="tag_type" value="RED" <?=($F['tag_type']??'')==='RED'?'checked':''?>><label for="ttR" class="red"><i style="background:#DC2626"></i>Tag แดง</label><input type="radio" id="ttW" name="tag_type" value="WHITE" <?=($F['tag_type']??'')==='WHITE'?'checked':''?>><label for="ttW" class="white"><i style="background:#fff"></i>Tag ขาว</label></div></div>
   <div class="tg-f"><label>แผนก *</label><select id="tagDepartment" name="department" required class="tg-in"><option value="">เลือกแผนก</option><?php foreach($departments as $d):?><option value="<?=h($d)?>" <?=((string)($F['department']??'')===$d?'selected':'')?>><?=h($d)?></option><?php endforeach;?><?php if(!empty($F['department']) && !in_array($F['department'],$departments,true)):?><option selected value="<?=$fv('department')?>"><?=$fv('department')?></option><?php endif;?></select></div>
   <div class="tg-f"><label>หน่วย / พื้นที่</label><select id="tagUnit" name="unit_no" class="tg-in"><option value="">เลือกหน่วย</option><?php $uu=$unitMap[(string)($F['department']??'')]??[]; foreach($uu as $u):?><option value="<?=h($u)?>" <?=((string)($F['unit_no']??'')===$u?'selected':'')?>><?=h($u)?></option><?php endforeach;?><?php if(!empty($F['unit_no']) && !in_array((string)$F['unit_no'],$uu,true)):?><option selected value="<?=$fv('unit_no')?>"><?=$fv('unit_no')?></option><?php endif;?></select></div>
   <div class="tg-f"><label>วันที่รับ Tag</label><input name="tag_date" id="tgDate" type="date" class="tg-in" value="<?=$fv('tag_date',date('Y-m-d'))?>"></div>
   <div class="tg-f"><label>กำหนดปิด<span class="tg-help">อัตโนมัติ +<?=$dueDays?> วัน</span></label><input name="due_date" id="tgDue" type="date" class="tg-in" value="<?=$fv('due_date')?>"><div class="tg-chips"><?php foreach([7,14,30] as $dd):?><button type="button" data-due="<?=$dd?>">+<?=$dd?> วัน</button><?php endforeach;?></div></div>
   <div class="tg-f s2"><label>ผู้ตรวจ / ผู้รับผิดชอบ<span class="cnt" data-for="auditor"></span></label><input list="tagAuditorOptions" name="auditor" maxlength="60" class="tg-in" value="<?=$fv('auditor')?>" placeholder="เลือกหรือพิมพ์ชื่อ"><datalist id="tagAuditorOptions"><?php foreach($auditors as $a):?><option value="<?=h($a)?>"><?php endforeach;?></datalist></div>
  </div>
 </section>

 <section class="tg-sec"><h3><span class="no">2</span>จุดบกพร่อง <small>เลือกประเภทได้หลายข้อ</small></h3>
  <div class="tg-defs"><?php foreach($defectLabels as $key=>$label):?><input type="checkbox" id="df<?=$key?>" name="defect_types[]" value="<?=h($key)?>" <?=in_array($key,array_map('strval',$savedDefectTypes),true)?'checked':''?>><label for="df<?=$key?>"><b><?=$key?></b><span><?=h($label)?></span></label><?php endforeach;?></div>
  <div class="tg-fg" style="margin-top:12px">
   <div class="tg-f s2"><label>จุดบกพร่องที่ตรวจพบ<span class="cnt" data-for="note"></span></label><textarea name="note" rows="2" maxlength="120" data-lines="2" class="tg-in" placeholder="เช่น มีของวางกีดขวางทางเดินหน้าเครื่อง EXT 2400"><?=$fv('note')?></textarea></div>
   <div class="tg-f s2"><label>ข้อเสนอแนะในการดำเนินการ<span class="cnt" data-for="suggestion"></span></label><textarea name="suggestion" rows="2" maxlength="200" data-lines="3" class="tg-in" placeholder="เช่น จัดเก็บเข้าชั้นวาง และตีเส้นพื้นที่วางของ"><?=$fv('suggestion')?></textarea></div>
   <div class="tg-f s2"><label>ติด Tag โดย<span class="cnt" data-for="issued_by"></span></label><input name="issued_by" list="tagAuditorOptions" maxlength="60" class="tg-in" value="<?=$fv('issued_by')?>" placeholder="ชื่อผู้ติด Tag"></div>
   <div class="tg-f s2"><label>ผู้รับ Tag<span class="cnt" data-for="receiver"></span></label><input name="receiver" maxlength="60" class="tg-in" value="<?=$fv('receiver')?>" placeholder="ผู้รับผิดชอบพื้นที่"></div>
  </div>
 </section>

 <section class="tg-sec"><h3><span class="no">3</span>รูปภาพ <small>ลากรูปมาวาง หรือกดเลือก • ย่อขนาดอัตโนมัติ (มือถือถ่ายได้เลย) • สูงสุด 10 รูป/ช่อง</small></h3>
  <div class="tg-photos">
   <div class="tg-drop" style="--c:#4F46E5" data-input="tagSourceImage"><h4><i></i>ภาพแบบฟอร์ม Tag (กระดาษ)</h4>
     <label class="pick" for="tagSourceImage"><?=five_s_icon('upload')?> เลือก / ถ่ายภาพฟอร์ม</label>
     <input id="tagSourceImage" type="file" name="source_photo_file" accept="image/*">
     <div id="tagSourcePreview" class="tg-thumbs"></div>
     <?php if(!empty($F['source_photo']) && is_string($F['source_photo'])):?><div class="tg-thumbs"><?php foreach(tag_photo_paths($F['source_photo']) as $ph):?><div class="tg-th"><img src="<?=h($ph)?>" data-zoom><span class="tag">เดิม</span></div><?php endforeach;?></div><?php endif;?>
     <button type="button" id="tagReadOcr" class="tg-btn navy" style="justify-content:center">🔎 อ่านฟอร์มและเติมข้อมูลอัตโนมัติ</button>
     <div id="tagOcrStatus" class="tg-help" aria-live="polite">เลือกรูปฟอร์มก่อน แล้วกดอ่าน (ต้องต่ออินเทอร์เน็ต)</div>
     <details class="tg-ocr"><summary>ข้อความที่อ่านได้ (ตรวจทาน)</summary><textarea id="tagOcrRaw" rows="6" class="tg-in" style="margin-top:6px;font-size:13px" placeholder="ข้อความจากภาพจะแสดงที่นี่"></textarea></details>
     <div id="tagOcrDuplicate" class="hidden tg-msg err" style="margin:0;font-size:13.5px"></div>
   </div>
   <?php foreach(['before_photo'=>['รูปก่อนแก้ไข (Before)','#DC2626'],'after_photo'=>['รูปหลังแก้ไข (After)','#16A34A']] as $fld=>[$lb,$col]):?>
   <div class="tg-drop" style="--c:<?=$col?>" data-input="in_<?=$fld?>"><h4><i></i><?=$lb?></h4>
     <label class="pick" for="in_<?=$fld?>"><?=five_s_icon('upload')?> เลือก / ถ่ายรูป (หลายรูปได้)</label>
     <input id="in_<?=$fld?>" type="file" name="<?=$fld?>[]" multiple accept="image/*">
     <div class="tg-thumbs" id="pv_<?=$fld?>"></div>
     <?php $olds=($isEdit && is_string($F[$fld]??null))?tag_photo_paths($F[$fld]):[]; if($olds):?><div class="tg-help">รูปที่บันทึกไว้แล้ว (กด × เพื่อลบ)</div><div class="tg-thumbs"><?php foreach($olds as $ph):?><div class="tg-th"><img src="<?=h($ph)?>" data-zoom><button type="button" class="tg-rm" data-field="<?=$fld?>" data-path="<?=h($ph)?>" title="ลบรูปนี้">×</button></div><?php endforeach;?></div><?php endif;?>
   </div>
   <?php endforeach;?>
  </div>
 </section>

 <section class="tg-sec"><h3><span class="no">4</span>การแก้ไขและปิด Tag <small>สำหรับผู้ดูแลแผนก</small></h3>
  <div class="tg-fg">
   <div class="tg-f s2"><label>รายละเอียดการแก้ไข<span class="cnt" data-for="corrective_action"></span></label><textarea name="corrective_action" rows="2" maxlength="200" data-lines="3" class="tg-in" placeholder="วิธีแก้ไขและการป้องกันการเกิดซ้ำ"><?=$fv('corrective_action')?></textarea></div>
   <div class="tg-f s2"><label>ผลการตรวจยืนยัน<span class="cnt" data-for="verification_note"></span></label><textarea name="verification_note" rows="2" maxlength="150" data-lines="2" class="tg-in" placeholder="ผลตรวจหลังแก้ไข"><?=$fv('verification_note')?></textarea></div>
   <div class="tg-f"><label>ผู้ดำเนินการแก้ไข</label><input name="corrective_by" maxlength="60" class="tg-in" value="<?=$fv('corrective_by')?>"></div>
   <div class="tg-f"><label>วันที่แก้ไข</label><input name="corrective_date" id="tgCorDate" type="date" class="tg-in" value="<?=$fv('corrective_date')?>"></div>
   <div class="tg-f"><label>วันที่ปิด Tag<span class="tg-help">ใส่วันที่ = ปิดจบ</span></label><input name="closed_date" id="tgClosed" type="date" class="tg-in" value="<?=$fv('closed_date')?>"></div>
   <div class="tg-f" style="justify-content:flex-end"><div class="tg-close-auto" id="tgCloseHint">แนบรูป After แล้วระบบจะใส่วันที่ปิดให้อัตโนมัติ</div></div>
  </div>
 </section>
</div>
<div id="tgRemoved"></div>
<div class="tg-save" style="margin-top:14px"><span class="st" id="tgSaveState"><?=$isEdit?'กำลังแก้ไข '.$fv('tag_no'):'Tag ใหม่ • เลขที่จะได้: '.h($nextNo)?></span>
  <?php if($isEdit):?><a class="tg-btn ghost" href="Page/auditor/tag_manage.php">ยกเลิก</a><a class="tg-btn ghost" target="_blank" href="Page/public/tag_form_print.php?id=<?=(int)$F['id']?>"><?=five_s_icon('printer')?> พิมพ์แบบฟอร์ม</a><?php endif;?>
  <button class="tg-btn org" id="tgSubmit"><?=five_s_icon('check')?> <?=$isEdit?'บันทึกการแก้ไข':'บันทึก Tag'?></button></div>
</form>

<!-- ===== รายการ ===== -->
<div class="tg-card" id="tgList">
 <div class="tg-row"><div><h2>รายการ Tag — <?=h($periodLabel)?></h2><div class="sub">Tag ค้างและเกินกำหนดแสดงก่อน • กดรูปเพื่อดูขยาย</div></div>
 <div class="tg-filters"><div class="tg-seg" id="tgSeg"><button type="button" data-f="all" class="on">ทั้งหมด<span><?=$S['total']?></span></button><button type="button" data-f="open">ยังไม่ปิด<span><?=$S['open']?></span></button><button type="button" data-f="over">เกินกำหนด<span><?=$S['over']?></span></button><button type="button" data-f="closed">ปิดแล้ว<span><?=$S['closed']?></span></button></div>
 <input class="tg-search" id="tgSearch" placeholder="ค้นหา Tag / แผนก / หน่วย / รายละเอียด"></div></div>
 <div style="overflow-x:auto"><table class="tg-list"><thead><tr><th>Tag</th><th>แผนก / หน่วย</th><th>จุดบกพร่อง</th><th>รูป ก่อน / หลัง</th><th>วันที่</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>
 <?php foreach($tags as $r): $bp=tag_photo_paths($r['before_photo']??''); $ap=tag_photo_paths($r['after_photo']??''); $defs=(array)json_decode((string)($r['defect_types']??'[]'),true); $closed=$r['status']==='CLOSED'; $f=$closed?'closed':($r['_over']?'over open':'open');?>
 <tr class="<?=$r['_over']?'over':''?>" data-f="<?=$f?>" data-q="<?=h(mb_strtolower($r['tag_no'].' '.$r['department'].' '.$r['unit_no'].' '.$r['note'].' '.$r['auditor']))?>">
  <td><div class="tg-no"><?=h($r['tag_no'])?></div><div style="margin-top:5px"><?php if(($r['tag_type']??'')==='RED'):?><span class="pill red">Tag แดง</span><?php elseif(($r['tag_type']??'')==='WHITE'):?><span class="pill white">Tag ขาว</span><?php else:?><span class="pill none">ไม่ระบุประเภท</span><?php endif;?></div></td>
  <td><b style="font-size:15.5px"><?=h($r['department'])?></b><div style="color:#64748B;font-size:14px">หน่วย <?=h($r['unit_no']?:'—')?></div><?php if($r['auditor']):?><div style="color:#64748B;font-size:13px"><?=h($r['auditor'])?></div><?php endif;?></td>
  <td><div class="tg-note"><?=h($r['note']?:'—')?></div><?php if($defs):?><div class="tg-defmini"><?php foreach($defs as $dk):?><span title="<?=h($defectLabels[(string)$dk]??'')?>">#<?=h((string)$dk)?></span><?php endforeach;?></div><?php endif;?><?php if(!empty($r['corrective_action'])):?><div style="font-size:13px;color:#15803D;margin-top:4px">แก้ไข: <?=h(mb_strimwidth($r['corrective_action'],0,70,'…'))?></div><?php endif;?></td>
  <td><div class="tg-pics"><?php foreach([[$bp,'ก่อน'],[$ap,'หลัง']] as [$ps,$cap]):?><figure><?php if($ps):?><img src="<?=h($ps[0])?>" data-zoom data-set='<?=h(json_encode($ps,JSON_UNESCAPED_SLASHES))?>' alt="<?=$cap?>"><?php else:?><div class="em">+</div><?php endif;?><figcaption><?=$cap?><?=count($ps)>1?' ('.count($ps).')':''?></figcaption></figure><?php endforeach;?></div></td>
  <td class="tg-dt">รับ <?=$thDate((string)$r['tag_date'])?><br><small>กำหนด <?=$thDate((string)$r['due_date'])?></small><?php if($closed):?><br><small style="color:#15803D">ปิด <?=$thDate((string)$r['closed_date'])?></small><?php endif;?></td>
  <td><?php if($closed):?><span class="pill closed">✓ ปิดจบ</span><?php elseif($r['_over']):?><span class="pill over">เกิน <?=abs((int)$r['_left'])?> วัน</span><?php else:?><span class="pill open">ค้าง<?=$r['_left']!==null?' • เหลือ '.$r['_left'].' วัน':''?></span><?php endif;?></td>
  <td><div class="tg-acts">
   <a class="ib" href="Page/auditor/tag_manage.php?edit=<?=$r['id']?>#tgForm"><?=five_s_icon('edit')?> แก้ไข</a>
   <a class="ib" target="_blank" href="Page/public/tag_form_print.php?id=<?=$r['id']?>"><?=five_s_icon('printer')?> ฟอร์ม</a>
   <?php if(!$closed):?><form method="post" style="display:inline"><input type="hidden" name="action" value="close"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="ib g"><?=five_s_icon('check')?> ปิด Tag</button></form><?php else:?><form method="post" style="display:inline"><input type="hidden" name="action" value="reopen"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="ib">↻ เปิดติดตาม</button></form><?php endif;?>
   <form method="post" style="display:inline" onsubmit="return confirm('ยืนยันลบ <?=h($r['tag_no'])?> ? การลบย้อนกลับไม่ได้');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="ib r">ลบ</button></form>
  </div></td>
 </tr>
 <?php endforeach;?>
 <tr id="tgNone" class="<?=$tags?'hidden':''?>"><td colspan="7" class="tg-empty">ไม่มีรายการ Tag ตามเงื่อนไข</td></tr>
 </tbody></table></div>
</div>
</div>
<script src="assets/vendor/html2canvas.min.js"></script>
<script>
window.tagUnitMap = <?=json_encode($unitMap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const tagUnitMap = window.tagUnitMap, TG_DUE_DAYS=<?=$dueDays?>;
function tgOpenForm(){const f=document.getElementById('tgForm');f.classList.remove('hidden');f.scrollIntoView({behavior:'smooth',block:'start'});}
function tgCloseForm(){document.getElementById('tgForm').classList.add('hidden');}
document.getElementById('tagDepartment')?.addEventListener('change',function(){
 const dept=this.value, sel=document.getElementById('tagUnit'), previous=sel.value;
 const units=tagUnitMap[dept]||[];
 sel.innerHTML='<option value="">เลือกหน่วย</option>';
 units.forEach(function(unit){const o=document.createElement('option');o.value=unit;o.textContent=unit;sel.appendChild(o);});
 if(units.includes(previous)) sel.value=previous;
});
/* กำหนดปิดอัตโนมัติ */
(function(){
 const d=document.getElementById('tgDate'),due=document.getElementById('tgDue');let manual=!!due.value;
 const add=(iso,n)=>{const t=new Date(iso+'T00:00:00');t.setDate(t.getDate()+n);return t.getFullYear()+'-'+String(t.getMonth()+1).padStart(2,'0')+'-'+String(t.getDate()).padStart(2,'0');};
 const auto=()=>{if(!manual&&d.value)due.value=add(d.value,TG_DUE_DAYS);};
 d.addEventListener('change',auto);due.addEventListener('change',()=>manual=true);auto();
 document.querySelectorAll('[data-due]').forEach(b=>b.addEventListener('click',()=>{if(d.value){due.value=add(d.value,+b.dataset.due);manual=true;}}));
})();
/* จัดบรรทัด + นับตัวอักษร */
document.querySelectorAll('.tg [maxlength]').forEach(el=>{
 const c=document.querySelector('.cnt[data-for="'+el.name+'"]'),max=+el.getAttribute('maxlength'),lines=+(el.dataset.lines||1);
 const upd=()=>{if(el.tagName==='TEXTAREA'){let rows=el.value.split('\n');if(rows.length>lines){el.value=rows.slice(0,lines-1).join('\n')+'\n'+rows.slice(lines-1).join(' ');}el.style.height='auto';el.style.height=Math.min(el.scrollHeight+2,40+lines*26)+'px';}
   if(c){c.textContent=el.value.length+'/'+max;c.classList.toggle('full',el.value.length>=max);}};
 el.addEventListener('input',upd);el.addEventListener('blur',()=>{el.value=el.value.replace(/[ \t]+/g,' ').replace(/ *\n */g,'\n').trim();upd();});upd();
});
/* รูปภาพ: ย่ออัตโนมัติ + ลากวาง + พรีวิว */
async function tgShrink(file,maxSide){
 if(!/^image\//.test(file.type))return null;
 try{const bmp=await createImageBitmap(file,{imageOrientation:'from-image'});let w=bmp.width,h=bmp.height;const k=Math.min(1,maxSide/Math.max(w,h));
  if(k===1&&file.size<1.5*1024*1024&&/jpe?g|png|webp/.test(file.type))return file;
  w=Math.round(w*k);h=Math.round(h*k);const c=document.createElement('canvas');c.width=w;c.height=h;c.getContext('2d').drawImage(bmp,0,0,w,h);
  const blob=await new Promise(r=>c.toBlob(r,'image/jpeg',.85));return new File([blob],(file.name||'photo').replace(/\.\w+$/,'')+'.jpg',{type:'image/jpeg'});}
 catch(e){return file.size<=12*1024*1024?file:null;}
}
function tgPreview(box,files,tag){box.innerHTML='';files.forEach(f=>{const d=document.createElement('div');d.className='tg-th';const im=document.createElement('img');im.src=URL.createObjectURL(f);im.dataset.zoom='';d.appendChild(im);const s=document.createElement('span');s.className='tag';s.textContent=tag;d.appendChild(s);box.appendChild(d);});}
async function tgHandle(input,list){
 const multi=input.multiple,maxSide=input.id==='tagSourceImage'?2400:1600;let files=Array.from(list);if(!multi)files=files.slice(0,1);
 if(files.length>10){alert('แนบได้ไม่เกิน 10 รูปต่อช่อง');files=files.slice(0,10);}
 const out=[];for(const f of files){const s=await tgShrink(f,maxSide);if(s)out.push(s);}
 const dt=new DataTransfer();out.forEach(f=>dt.items.add(f));input.files=dt.files;
 const box=input.id==='tagSourceImage'?document.getElementById('tagSourcePreview'):document.getElementById('pv_'+input.id.replace('in_',''));
 tgPreview(box,out,'ใหม่');
 if(input.id==='in_after_photo'&&out.length){const cl=document.getElementById('tgClosed'),cd=document.getElementById('tgCorDate'),t=new Date().toISOString().slice(0,10);
   if(!cd.value)cd.value=t;if(!cl.value){cl.value=t;document.getElementById('tgCloseHint').textContent='✓ ใส่วันที่ปิดให้แล้ว ('+t.split('-').reverse().join('/')+') — ลบวันที่ได้ถ้ายังไม่ปิด';}}
 if(input.id==='tagSourceImage'){const st=document.getElementById('tagOcrStatus');if(st&&out.length)st.textContent='เลือกรูปแล้ว พร้อมอ่านข้อความ';}
}
document.querySelectorAll('.tg-drop').forEach(zone=>{
 const input=document.getElementById(zone.dataset.input);
 input.addEventListener('change',e=>{if(input._busy)return;input._busy=true;tgHandle(input,input.files).finally(()=>input._busy=false);e.stopImmediatePropagation();},true);
 ['dragenter','dragover'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();zone.classList.add('over');}));
 ['dragleave','drop'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();zone.classList.remove('over');}));
 zone.addEventListener('drop',e=>{if(e.dataTransfer.files.length){input._busy=true;tgHandle(input,e.dataTransfer.files).finally(()=>input._busy=false);}});
});
document.addEventListener('paste',e=>{const f=document.getElementById('tgForm');if(f.classList.contains('hidden'))return;const imgs=Array.from(e.clipboardData?.files||[]).filter(x=>/^image\//.test(x.type));if(imgs.length){const inp=document.getElementById('in_before_photo');tgHandle(inp,imgs);}});
/* ลบรูปเดิม */
document.querySelectorAll('.tg-rm').forEach(b=>b.addEventListener('click',()=>{const th=b.closest('.tg-th'),box=document.getElementById('tgRemoved');const key=b.dataset.field+'|'+b.dataset.path;
 if(th.classList.toggle('rm')){const i=document.createElement('input');i.type='hidden';i.name='remove_'+b.dataset.field+'[]';i.value=b.dataset.path;i.dataset.k=key;box.appendChild(i);b.textContent='↺';}
 else{box.querySelector('[data-k="'+CSS.escape(key)+'"]')?.remove();b.textContent='×';}}));
/* ดูรูปขยาย */
document.addEventListener('click',e=>{const im=e.target.closest('img[data-zoom]');if(!im)return;const set=im.dataset.set?JSON.parse(im.dataset.set):[im.src];let i=0;
 const ov=document.createElement('div');ov.className='tg-lb-ov';const big=document.createElement('img');big.src=set[0];ov.appendChild(big);
 if(set.length>1){ov.title='คลิกเพื่อดูรูปถัดไป';ov.addEventListener('click',ev=>{i++;if(i>=set.length){ov.remove();return;}big.src=set[i];ev.stopPropagation();});}else ov.addEventListener('click',()=>ov.remove());
 document.addEventListener('keydown',function k(ev){if(ev.key==='Escape'){ov.remove();document.removeEventListener('keydown',k);}});document.body.appendChild(ov);});
/* ตัวกรองรายการ */
(function(){let f='all';const q=document.getElementById('tgSearch');
 const apply=()=>{const s=q.value.trim().toLowerCase();let n=0;document.querySelectorAll('.tg-list tbody tr[data-f]').forEach(tr=>{const ok=(f==='all'||tr.dataset.f.split(' ').includes(f))&&(!s||tr.dataset.q.includes(s));tr.style.display=ok?'':'none';if(ok)n++;});document.getElementById('tgNone').classList.toggle('hidden',n>0);};
 const set=v=>{f=v;document.querySelectorAll('#tgSeg button').forEach(b=>b.classList.toggle('on',b.dataset.f===v));apply();};
 document.querySelectorAll('#tgSeg button').forEach(b=>b.addEventListener('click',()=>set(b.dataset.f)));
 document.querySelectorAll('.tg-kpi[data-f]').forEach(a=>a.addEventListener('click',()=>set(a.dataset.f)));
 q.addEventListener('input',apply);
})();
document.getElementById('tgForm').addEventListener('submit',()=>{const b=document.getElementById('tgSubmit');b.disabled=true;b.textContent='กำลังบันทึก…';});
</script>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script>
(function(){
 const input=document.getElementById('tagSourceImage');
 const readBtn=document.getElementById('tagReadOcr');
 const status=document.getElementById('tagOcrStatus');
 const rawBox=document.getElementById('tagOcrRaw');
 const preview=document.getElementById('tagSourcePreview');
 const duplicate=document.getElementById('tagOcrDuplicate');
 if(!input||!readBtn) return;
 let imageUrl='';
 const knownDepartments=Array.from(document.querySelectorAll('#tagDepartment option')).map(o=>o.value).filter(Boolean);
 const unitMap=window.tagUnitMap||{};
 const knownTags=<?=json_encode(array_map(static function($r){return ['id'=>(int)$r['id'],'tag_no'=>(string)$r['tag_no'],'department'=>(string)$r['department'],'unit_no'=>(string)$r['unit_no']];},$tags), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
 const currentEditId=<?=json_encode((int)$editId)?>;
 input.addEventListener('tg-ocr-unused',function(){
   preview.innerHTML=''; duplicate.classList.add('hidden');
   const f=input.files&&input.files[0]; if(!f) return;
   if(!['image/jpeg','image/png','image/webp'].includes(f.type)||f.size>5*1024*1024){alert('กรุณาใช้รูป JPG, PNG หรือ WEBP ขนาดไม่เกิน 5 MB');input.value='';return;}
   if(imageUrl) URL.revokeObjectURL(imageUrl); imageUrl=URL.createObjectURL(f);
   const im=document.createElement('img'); im.src=imageUrl; im.alt='ภาพแบบฟอร์ม Tag ต้นฉบับ'; im.style.cssText='max-width:100%;max-height:280px;border:1px solid #cbd5e1;border-radius:10px;background:white;padding:4px'; preview.appendChild(im);
   status.textContent='เลือกรูปแล้ว พร้อมอ่านข้อความ';
 });
 function thaiDigits(s){const th='๐๑๒๓๔๕๖๗๘๙';return String(s||'').replace(/[๐-๙]/g,c=>String(th.indexOf(c)));}
 function normalize(s){return thaiDigits(s).toUpperCase().replace(/[\s\-_.:;,()\[\]{}]/g,'');}
 function findNearby(text, labels, max=80){
   const lines=String(text).split(/\r?\n/).map(x=>x.trim()).filter(Boolean);
   for(let i=0;i<lines.length;i++){
     const ln=normalize(lines[i]);
     if(labels.some(l=>ln.includes(normalize(l)))){
       let result=lines[i];
       if(i+1<lines.length) result+=' '+lines[i+1];
       result=result.replace(/^(?:.*?(?:จุดบกพร่องที่ตรวจพบ|หมายเลข\s*Tag|พื้นที่กลุ่มย่อย|แผนก|วันที่ออก\s*Tag|ติด\s*Tag\s*โดย|ผู้รับ\s*Tag|ข้อเสนอแนะในการดำเนินการ)\s*[:：]?)/i,'').trim();
       result=result.replace(/(?:แผนก|พื้นที่กลุ่มย่อย|หมายเลข\s*Tag|วันที่ออก\s*Tag|ติด\s*Tag\s*โดย|ผู้รับ\s*Tag|ข้อเสนอแนะในการดำเนินการ).*$/i,'').trim();
       if(result && result.length<=max && !/^(?:วันที่|กำหนด|ประเภท|ประจำเดือน)/.test(result)) return result;
     }
   }
   return '';
 }
 function isoDate(raw){
   const m=thaiDigits(raw).match(/(\d{1,2})\s*[\/.-]\s*(\d{1,2})\s*[\/.-]\s*(\d{2,4})/); if(!m)return '';
   let d=Number(m[1]),mo=Number(m[2]),y=Number(m[3]); if(y<100)y+= y>=60?2500:2000; if(y>=2400)y-=543;
   if(mo<1||mo>12||d<1||d>31)return ''; const dt=new Date(Date.UTC(y,mo-1,d)); if(dt.getUTCFullYear()!==y||dt.getUTCMonth()!==mo-1||dt.getUTCDate()!==d)return '';
   return String(y).padStart(4,'0')+'-'+String(mo).padStart(2,'0')+'-'+String(d).padStart(2,'0');
 }
 function setField(name,value){const el=document.querySelector('[name="'+name+'"]');if(el&&value!==undefined&&value!==null&&String(value).trim()!==''){el.value=String(value).trim();el.dispatchEvent(new Event('input',{bubbles:true}));el.dispatchEvent(new Event('change',{bubbles:true}));return true;}return false;}
 function setSelect(el,value){if(!el||!value)return false;let opt=Array.from(el.options).find(o=>normalize(o.value)===normalize(value));if(!opt){opt=new Option(value,value,true,true);el.add(opt);}el.value=opt.value;el.dispatchEvent(new Event('change',{bubbles:true}));return true;}
 function parseTagNo(text){
   const labels=[/หมายเลข\s*tag/i,/หมายเลข\s*tag/i,/tag\s*(?:no\.?|number)/i];
   for(const re of labels){const i=text.search(re);if(i>=0){const part=text.slice(i,i+100);let m=part.match(/(?:หมายเลข\s*tag|tag\s*(?:no\.?|number))[^A-Z0-9]{0,20}([A-Z]{1,6}\s*[-]?\s*\d{1,8}|\d{1,8})/i);if(m)return formatTagNo(m[1]);}}
   return '';
 }
 function formatTagNo(v){let x=String(v||'').toUpperCase().replace(/\s+/g,'').replace(/[^A-Z0-9-]/g,'');if(!x)return '';if(/^\d+$/.test(x))return 'TAG-'+x.padStart(3,'0');if(/^TAG\d+$/.test(x))return 'TAG-'+x.slice(3);return x;}
 function extractDepartment(text){const n=normalize(text);let candidates=knownDepartments.slice().sort((a,b)=>normalize(b).length-normalize(a).length);for(const d of candidates){const nd=normalize(d);if(nd.length>=2&&n.includes(nd))return d;}const aliases={'WHEEL':'WHL','WHEELL':'WHL'};for(const [a,d] of Object.entries(aliases)){if(n.includes(a)&&knownDepartments.some(x=>normalize(x)===normalize(d)))return knownDepartments.find(x=>normalize(x)===normalize(d));}return '';}
 function detectTypeFromImage(file){
   return new Promise(resolve=>{const img=new Image();img.onload=()=>{try{const c=document.createElement('canvas');c.width=900;c.height=Math.round(900*img.height/img.width);const x=c.getContext('2d',{willReadFrequently:true});x.drawImage(img,0,0,c.width,c.height);const w=c.width,h=c.height;
     const ink=(rx,ry,rw,rh)=>{const X=Math.max(0,Math.round(rx*w)),Y=Math.max(0,Math.round(ry*h)),W=Math.max(2,Math.round(rw*w)),H=Math.max(2,Math.round(rh*h));const d=x.getImageData(X,Y,Math.min(W,w-X),Math.min(H,h-Y)).data;let n=0,total=d.length/4;for(let i=0;i<d.length;i+=4){const lum=(d[i]+d[i+1]+d[i+2])/3;if(lum<95)n++;}return n/Math.max(1,total);};
     // The stock PLANT PATROL form puts the Tag White/Red checkboxes on the same row.
     const white=ink(.355,.535,.018,.025),red=ink(.445,.535,.018,.025); if(Math.abs(white-red)>.11)resolve(white>red?'WHITE':'RED');else resolve('');
   }catch(e){resolve('');}};img.onerror=()=>resolve('');img.src=URL.createObjectURL(file);});
 }
 readBtn.addEventListener('click',async function(){
   const f=input.files&&input.files[0];if(!f){alert('กรุณาเลือกรูปแบบฟอร์ม Tag ก่อน');return;}
   if(!window.Tesseract){status.textContent='โหลดเครื่องมืออ่านข้อความไม่สำเร็จ กรุณาตรวจอินเทอร์เน็ต หรือกรอกข้อมูลเองในช่องด้านล่าง';return;}
   readBtn.disabled=true;readBtn.classList.add('opacity-60');status.textContent='กำลังอ่านตัวพิมพ์และลายมือ อาจใช้เวลาสักครู่…';
   try{
     const result=await Tesseract.recognize(f,'tha+eng',{logger:m=>{if(m.status==='recognizing text')status.textContent='กำลังอ่านข้อความ '+Math.round((m.progress||0)*100)+'%';}});
     const text=thaiDigits(result.data.text||'');rawBox.value=text;
     let filled=[];
     const tagNo=parseTagNo(text);if(tagNo&&setField('tag_no',tagNo))filled.push('Tag No.');
     const dept=extractDepartment(text);const deptEl=document.getElementById('tagDepartment');
     if(dept&&setSelect(deptEl,dept)){filled.push('แผนก');const unitEl=document.getElementById('tagUnit');unitEl.innerHTML='<option value="">เลือกหน่วย</option>'; (unitMap[dept]||[]).forEach(u=>unitEl.add(new Option(u,u)));}
     let unit='';const unitMatch=text.match(/(?:พื้นที่\s*กลุ่มย่อย|กลุ่มย่อย|พื้นที่กลุ่ม|unit\s*(?:no\.?)?)\s*[:：#-]?\s*([0-9]{1,4}[A-Z]?)/i);if(unitMatch)unit=unitMatch[1];
     if(!unit){const lines=text.split(/\r?\n/);for(let i=0;i<lines.length;i++){if(/กลุ่มย่อย|พื้นที่/i.test(lines[i])){const m=lines.slice(i,i+2).join(' ').match(/\b(\d{1,4})\b/);if(m){unit=m[1];break;}}}}
     if(unit){const uel=document.getElementById('tagUnit');if(setSelect(uel,unit))filled.push('หน่วย');}
     const dateContext=text.match(/(?:วันที่ออก\s*Tag|วันที่ออก|ออก\s*Tag)[\s\S]{0,100}/i);let dateRaw=dateContext&&dateContext[0].match(/\d{1,2}\s*[\/.-]\s*\d{1,2}\s*[\/.-]\s*\d{2,4}/);if(!dateRaw)dateRaw=text.match(/\d{1,2}\s*[\/.-]\s*\d{1,2}\s*[\/.-]\s*\d{2,4}/);
     const dateValue=dateRaw?isoDate(dateRaw[0]):'';if(dateValue&&setField('tag_date',dateValue))filled.push('วันที่รับ Tag');
     const dueContext=text.match(/(?:วันที่คาดว่าจะแล้วเสร็จ|กำหนดปิด|กำหนดเสร็จ)[\s\S]{0,90}/i);const dueRaw=dueContext&&dueContext[0].match(/\d{1,2}\s*[\/.-]\s*\d{1,2}\s*[\/.-]\s*\d{2,4}/);const dueValue=dueRaw?isoDate(dueRaw[0]):'';if(dueValue&&setField('due_date',dueValue))filled.push('กำหนดปิด');
     const checkMarks=Array.from(text.matchAll(/[✓✔☑√]\s*(10|[1-9])(?:\s*[.．])/g)).map(m=>m[1]);if(checkMarks.length){document.querySelectorAll('input[name=\"defect_types[]\"]').forEach(el=>{el.checked=checkMarks.includes(el.value);});filled.push('ประเภทจุดบกพร่องที่ OCR พบเครื่องหมาย (ตรวจยืนยันอีกครั้ง)');}
     const note=findNearby(text,['จุดบกพร่องที่ตรวจพบ','จุดบกพร่องที่ตรวจพบ'],100);if(note&&setField('note',note))filled.push('จุดบกพร่อง');
     const suggestion=findNearby(text,['ข้อเสนอแนะในการดำเนินการ'],180);if(suggestion&&setField('suggestion',suggestion))filled.push('ข้อเสนอแนะ');
     const issued=findNearby(text,['ติด Tag โดย','ติด Tag โดย'],70);if(issued&&setField('issued_by',issued))filled.push('ผู้ติด Tag');
     const receiver=findNearby(text,['ผู้รับ Tag'],70);if(receiver&&setField('receiver',receiver))filled.push('ผู้รับ Tag');
     const type=await detectTypeFromImage(f);if(type){const r=document.querySelector('input[name="tag_type"][value="'+type+'"]');if(r){r.checked=true;filled.push('ประเภท Tag (ตรวจยืนยันอีกครั้ง)');}}
     const found=knownTags.find(t=>Number(t.id)!==Number(currentEditId)&&normalize(t.tag_no)===normalize(tagNo));if(found){duplicate.classList.remove('hidden');duplicate.textContent='พบหมายเลข '+String(found.tag_no)+' อยู่ในระบบแล้ว: '+String(found.department)+' / หน่วย '+String(found.unit_no)+'. หากเป็น Tag เดียวกัน แนะนำกดแก้ไขรายการเดิม ไม่ควรสร้างรายการซ้ำ.';}
     status.textContent='อ่านเสร็จแล้ว เติมข้อมูลได้ '+filled.length+' ช่อง: '+(filled.join(', ')||'ยังไม่พบข้อมูลที่มั่นใจพอ');
     if(filled.length===0)status.textContent+=' — ตรวจข้อความด้านขวาและกรอก/แก้ไขช่องด้วยตนเอง';
   }catch(err){console.error(err);status.textContent='อ่านข้อความไม่สำเร็จ กรุณาลองภาพที่ชัดขึ้น หรือกรอกข้อมูลด้วยตนเอง';}
   finally{readBtn.disabled=false;readBtn.classList.remove('opacity-60');}
 });
})();
</script>
<?php require FIVE_S_ROOT.'/includes/footer.php';?>
