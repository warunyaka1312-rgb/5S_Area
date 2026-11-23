<?php
declare(strict_types=1);
/* Display-only group alias for area selectors.
   PURCHASING and Billet&Scarp are the same group for selection/display. */
if(!function_exists('display_area_group')){
  function display_area_group($name): string {
    $v=trim((string)$name);
    $n=mb_strtolower(preg_replace('/^[\x{0E00}\x{0E31}\x{0E32}\x{0E34}-\x{0E3A}\x{0E40}-\x{0E4E}\s]+/u','',$v),'UTF-8');
    if(str_contains(str_replace([' ','-'],'',$n),'billet&scarp') || $n==='purchasing'){
      return 'PURCHASING (Billet&Scarp)';
    }
    return $v;
  }
}

/* ค่าตั้งค่าทั้งหมดอ่านจากไฟล์ .env ที่ root ของแอพ (ดู .env.example) */
require_once __DIR__.'/env.php';
if(!defined('APP_NAME')) define('APP_NAME', five_s_env_str('APP_NAME','5S Smart Score'));
if(!defined('FIVE_S_ROOT')) define('FIVE_S_ROOT', dirname(__DIR__));
/* ฐานข้อมูลหลักย้ายจาก SQLite มาเป็น MySQL/MariaDB แล้ว — ตั้งค่าการเชื่อมต่อที่ includes/db_config.php
   ไฟล์ SQLite เดิมเก็บไว้เป็นต้นฉบับสำหรับย้ายข้อมูล/กู้คืนเท่านั้น */
const SQLITE_LEGACY_PATH=FIVE_S_ROOT.'/db/database.sqlite';
require_once __DIR__.'/paths.php';
require_once __DIR__.'/db_tools.php';
if(!@date_default_timezone_set(five_s_env_str('APP_TIMEZONE','Asia/Bangkok'))) date_default_timezone_set('Asia/Bangkok');

function db():PDO{
    static $p;
    if(!$p){
        $p=five_s_connect();
        /* Create any missing table (MySQL schema = db/schema_mysql.sql). Runs once per schema version. */
        five_s_ensure_schema($p);
        $p->exec("INSERT IGNORE INTO app_settings(`key`,`value`) VALUES('target_pct','85')");
        $p->exec("INSERT IGNORE INTO app_settings(`key`,`value`) VALUES('production_target_pct','85')");
        $p->exec("INSERT IGNORE INTO app_settings(`key`,`value`) VALUES('office_target_pct','87')");
        /* V31/V33 SQLite migrations (multi-auditor audits, auditor2/auditor3, unit import tables,
           monthly scan archive, auditor history tables) are already part of the MySQL schema. */
        try {
            /* = SUM + GROUP_CONCAT(DISTINCT ...) GROUP BY year,month,name — done in PHP so the
               value order stays first-seen like SQLite (MySQL would sort the values). */
            $q=five_s_group_concat_rows($p->query("SELECT history_year,history_month,TRIM(auditor_name) auditor_name,
                    inspection_count,inspection_dates,departments
                    FROM auditor_monthly_history
                    WHERE TRIM(auditor_name)<>'' ORDER BY id"),
                    ['history_year','history_month','auditor_name'],['inspection_count'],['inspection_dates','departments']);
            $up=$p->prepare("INSERT INTO auditor_monthly_history_unified(history_year,history_month,auditor_name,inspection_count,inspection_dates,departments,updated_at)
                    VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE
                    inspection_count=VALUES(inspection_count),inspection_dates=VALUES(inspection_dates),
                    departments=VALUES(departments),updated_at=CURRENT_TIMESTAMP");
            foreach($q as $r){
                $up->execute([(int)$r['history_year'],(int)$r['history_month'],(string)$r['auditor_name'],(int)$r['inspection_count'],(string)($r['inspection_dates']??''),(string)($r['departments']??'')]);
            }
        } catch(Throwable $e) { /* never block startup */ }

        /* Keep the monthly archive in sync with the current schedule. Existing historical rows remain. */
        try {
            /* = COUNT(*) + GROUP_CONCAT(DISTINCT ...) GROUP BY year,month,team,name (first-seen order, like SQLite) */
            $q=five_s_group_concat_rows($p->query("SELECT year,month,team,TRIM(auditor_name) auditor_name,
                    1 inspection_count,inspection_date inspection_dates,department departments
                    FROM audit_plans
                    WHERE active=1 AND TRIM(auditor_name)<>'' ORDER BY id"),
                    ['year','month','team','auditor_name'],['inspection_count'],['inspection_dates','departments']);
            $up=$p->prepare("INSERT INTO auditor_monthly_history(history_year,history_month,team,auditor_name,inspection_count,inspection_dates,departments,updated_at)
                    VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE
                    inspection_count=VALUES(inspection_count),inspection_dates=VALUES(inspection_dates),
                    departments=VALUES(departments),updated_at=CURRENT_TIMESTAMP");
            foreach($q as $r){
                $up->execute([(int)$r['year'],(int)$r['month'],(string)$r['team'],(string)$r['auditor_name'],(int)$r['inspection_count'],(string)($r['inspection_dates']??''),(string)($r['departments']??'')]);
            }
        } catch(Throwable $e) { /* archive must never stop the application */ }
        /* Backfill department/group_name for old V29 audits so Dashboard can see them. */
        $p->exec("UPDATE audits SET department=(SELECT department FROM areas WHERE areas.id=audits.area_id),
                  group_name=(SELECT group_name FROM areas WHERE areas.id=audits.area_id)
                  WHERE COALESCE(department,'')='' OR COALESCE(group_name,'')=''");
    }
    return $p;
}
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function score_avg($a,$b=null,$c=null):?float{
    $v=[]; foreach([$a,$b,$c] as $x) if($x!==null&&$x!=='') $v[]=(float)$x;
    return $v?array_sum($v)/count($v):null;
}
function percent($s,$m):float{return $m>0?round($s/$m*100,1):0;}
function go($u):never{
  $u=(string)$u;
  if($u!=='' && $u[0]==='?') $u=five_s_self().$u;            // same page with new query
  elseif(!preg_match('#^(/|https?:)#i',$u)) $u=FIVE_S_BASE.$u; // paths are relative to the app root
  header('Location: '.$u);exit;}

function app_setting(string $key,string $default=''):string{
    $p=db();
    $q=$p->prepare("SELECT `value` FROM app_settings WHERE `key`=?");
    $q->execute([$key]);
    $v=$q->fetchColumn();
    return $v===false?$default:(string)$v;
}
function save_app_setting(string $key,string $value):void{
    $p=db();
    $q=$p->prepare("INSERT INTO app_settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
    $q->execute([$key,$value]);
}
function target_pct(?string $department=null):float{
    $key = $department==='Office' ? 'office_target_pct' : ($department==='Production' ? 'production_target_pct' : 'target_pct');
    $default = $department==='Office' ? '87' : '85';
    $v=(float)app_setting($key,$default);
    return ($v>0&&$v<=100)?$v:(float)$default;
}
function status_text(float $p, ?string $department=null):string{
    $t=target_pct($department);
    if($p >= $t) return 'ผ่านเป้าหมาย';
    if($p >= max(0,$t-10)) return 'ต่ำกว่าเป้าหมาย';
    return 'ต้องเร่งแก้ไข';
}
function status_class(float $p, ?string $department=null):string{
    $t=target_pct($department);
    return $p >= $t ? 'good' : ($p >= max(0,$t-10) ? 'warn' : 'bad');
}

/* คะแนนของแบบ Production: คิดเฉพาะช่องที่มีคะแนนจริง */
function production_audit_summary(PDO $p,int $auditId):array{
    $q=$p->prepare("SELECT s.score1,s.score2,s.score3,c.max_score
                    FROM scores s JOIN criteria c ON c.id=s.criterion_id
                    WHERE s.audit_id=?");
    $q->execute([$auditId]);
    $score=0.0;$max=0.0;$answered=0;
    foreach($q as $r){
        $v=score_avg($r['score1'],$r['score2'],$r['score3']);
        if($v===null) continue;
        $score += $v; $max += (float)$r['max_score']; $answered++;
    }
    return ['score'=>$score,'max'=>$max,'pct'=>$max?round($score/$max*100,1):0,'answered'=>$answered];
}
function office_audit_summary(PDO $p,int $auditId):array{
    $q=$p->prepare("SELECT s.score1,s.score2,s.score3,c.max_score
                    FROM office_scores s JOIN office_criteria c ON c.id=s.criterion_id
                    WHERE s.audit_id=?");
    $q->execute([$auditId]);
    $score=0.0;$max=0.0;$answered=0;
    foreach($q as $r){
        $v=score_avg($r['score1'],$r['score2'],$r['score3']);
        if($v===null) continue;
        $score += $v; $max += (float)$r['max_score']; $answered++;
    }
    return ['score'=>$score,'max'=>$max,'pct'=>$max?round($score/$max*100,1):0,'answered'=>$answered];
}
/* Resolve a paper section + unit to an existing area. */
function normalize_area_key(string $s):string{
    $s=mb_strtolower(trim($s),'UTF-8');
    $s=preg_replace('/[\s_\-\/]+/u','',$s)??$s;
    $s=preg_replace('/^(ext|officeext)/u','',$s)??$s;
    return $s;
}
function find_or_create_area(PDO $p,string $kind,string $section,string $unit):int{
    $section=trim($section);$unit=trim($unit);
    $q=$p->prepare("SELECT id FROM areas WHERE active=1 AND department=? AND unit_no=? ORDER BY id");
    $q->execute([$kind,$unit]);
    $cands=$q->fetchAll(PDO::FETCH_COLUMN);
    $want=normalize_area_key($section);
    foreach($cands as $id){
        $r=$p->prepare("SELECT group_name,area_name FROM areas WHERE id=?");$r->execute([$id]);$a=$r->fetch();
        if($a && normalize_area_key((string)$a['group_name'])===$want) return (int)$id;
        if($a && normalize_area_key((string)$a['area_name'])!=='' &&
           str_contains(normalize_area_key((string)$a['area_name']),$want)) return (int)$id;
    }
    if(count($cands)===1) return (int)$cands[0];
    $code='AUTO-'.strtoupper($kind).'-'.preg_replace('/[^A-Za-z0-9]+/','',strtoupper($section)).'-'.$unit;
    $name=($section!==''?$section:'ไม่ระบุแผนก').' / หน่วย '.$unit;
    $ins=$p->prepare("INSERT IGNORE INTO areas(area_code,area_name,active,department,group_name,unit_no) VALUES(?,?,1,?,?,?)");
    $ins->execute([$code,$name,$kind,$section,$unit]);
    $q=$p->prepare("SELECT id FROM areas WHERE department=? AND unit_no=? AND area_name=? ORDER BY id DESC LIMIT 1");
    $q->execute([$kind,$unit,$name]);
    return (int)$q->fetchColumn();
}

/* ===== V60 automation helpers: pick sensible default periods automatically ===== */
if(!function_exists('five_s_thai_months')){
  function five_s_thai_months():array{
    return [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
  }
}
if(!function_exists('five_s_latest_data_month')){
  /** Latest [year(AD), month] that has saved audits (optionally for one department); falls back to today. */
  function five_s_latest_data_month(?string $department=null):array{
    try{
      $p=db();
      if($department){$q=$p->prepare("SELECT MAX(audit_date) FROM audits WHERE department=?");$q->execute([$department]);}
      else{$q=$p->query("SELECT MAX(audit_date) FROM audits");}
      $d=(string)$q->fetchColumn();
      if(preg_match('/^(\d{4})-(\d{2})/',$d,$m)) return [(int)$m[1],(int)$m[2]];
    }catch(Throwable $e){}
    return [(int)date('Y'),(int)date('n')];
  }
}
if(!function_exists('five_s_default_plan_period')){
  /** Current [month, BE year] if a plan exists, otherwise the latest planned month. */
  function five_s_default_plan_period():array{
    $m=(int)date('n');$y=(int)date('Y')+543;
    try{
      $p=db();
      $q=$p->prepare("SELECT year,month FROM audit_plans WHERE active=1 AND (year*100+month)<=? ORDER BY year DESC,month DESC LIMIT 1");
      $q->execute([$y*100+$m]);
      if($r=$q->fetch()) return [(int)$r['month'],(int)$r['year']];
    }catch(Throwable $e){}
    return [$m,$y];
  }
}

require_once __DIR__.'/audit_metrics.php';
/* V80: login / role guard for the folder layout (public/ auditor/ admin/) */
require_once __DIR__.'/auth.php';
five_s_guard();
