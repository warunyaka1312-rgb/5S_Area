<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
$title='จัดตารางแผนตรวจ 5 ส.';
/* V60: open the current month automatically (new months are auto-created from the previous plan). */
$month=(int)($_GET['month']??date('n')); $year=(int)($_GET['year']??((int)date('Y')+543));
$p=db(); $notice='';
/* V34 schedule enhancements: status + per-row department/date colors.
   These columns are added automatically to existing SQLite databases. */
function ensure_schedule_columns($p){
  $cols=[];
  foreach($p->query('PRAGMA table_info(audit_plans)') as $c){ $cols[$c['name']]=true; }
  $defs=[
    'status'=>"TEXT NOT NULL DEFAULT 'รอตรวจ'",
    'date_color'=>"TEXT NOT NULL DEFAULT '#ffffff'",
    'department_color'=>"TEXT NOT NULL DEFAULT '#ffffff'"
  ];
  foreach($defs as $name=>$def){
    if(!isset($cols[$name])){ $p->exec("ALTER TABLE audit_plans ADD COLUMN {$name} {$def}"); }
  }
}
ensure_schedule_columns($p);

function ensure_department_master($p){
  $p->exec("CREATE TABLE IF NOT EXISTS departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL COLLATE NOCASE UNIQUE,
    active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
  )");
  $blocked=['production','office','prod','สำนักงาน','โปรดักชัน'];
  $fallback=['PDC1','PPT1','Billet&Scarp','PURCHASING','EXT2400','EXT2000','MNTE','ANO','PDC 2','WHL','PPT2','Die.Pc','R&D , HUB','CX','Die.Design','Die Making','QA','New Medel','MIS','EXT. 880-1','EXT. 880-2','ACC&BUD','FIN','PLANNING','AO','IT','HRD','GA'];
  $vals=$fallback;
  foreach(['audit_plans','areas','excel_monthly'] as $tb){
    try{
      $q=$p->query("SELECT DISTINCT TRIM(department) FROM {$tb} WHERE TRIM(COALESCE(department,''))<>''");
      if($q!==false){ foreach($q as $rr){ if(isset($rr[0])) $vals[]=(string)$rr[0]; } }
    }catch(Throwable $e){}
  }
  $ins=$p->prepare("INSERT OR IGNORE INTO departments(name,active) VALUES(?,1)");
  foreach($vals as $v){
    $v=trim((string)$v); $k=mb_strtolower(preg_replace('/\s+/u',' ',$v)??$v,'UTF-8');
    if($v!=='' && !in_array($k,$blocked,true))$ins->execute([$v]);
  }
}
ensure_department_master($p);
function pv($k){return trim($_POST[$k]??'');}
function unit_count($area){
  $parts=array_filter(array_map('trim', explode(',', (string)$area)), fn($x)=>$x!=='');
  return count($parts);
}
function team_cycle_default($month,$year){
  // Fixed monthly rotation: Oct 2569 = A, Nov = B, Dec = A, then alternate.
  // This is a pure calculation; it never depends on page refresh or saved view state.
  $offset=(($year-2569)*12)+($month-10);
  return ($offset % 2 === 0) ? 'A' : 'B';
}
/* Close the numbering gaps of one team in one month. Rows sharing a sort_no belong to the
   same inspection team (one person, several departments), so they keep sharing a number. */
function renumberTeamRows(PDO $p,int $month,int $year,string $team):void{
  $q=$p->prepare('SELECT id,sort_no FROM audit_plans WHERE month=? AND year=? AND team=? AND active=1 ORDER BY sort_no,id');
  $q->execute([$month,$year,$team]); $rows=$q->fetchAll(PDO::FETCH_ASSOC);
  $u=$p->prepare('UPDATE audit_plans SET sort_no=? WHERE id=?'); $n=0; $prev=null;
  foreach($rows as $r){ if($prev===null || (int)$r['sort_no']!==$prev){$n++; $prev=(int)$r['sort_no'];} $u->execute([$n,(int)$r['id']]); }
}
function setting_get($p,$key,$default=''){
  $st=$p->prepare('SELECT value FROM app_settings WHERE key=?'); $st->execute([$key]);
  $v=$st->fetchColumn(); return $v===false?$default:$v;
}
function setting_set($p,$key,$value){
  $st=$p->prepare('INSERT INTO app_settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');
  return $st->execute([$key,$value]);
}

/* Monthly team swap migration.
   October 2569 is the anchor: Team A receives Team B's department/area assignments,
   while each team's original auditor names remain with that team. Only schedule fields
   are swapped; scores/audits/history tables are never touched. Run once per database. */
try {
  $swapDone=setting_get($p,'monthly_ab_department_swap_oct2569','0');
  if($swapDone!=='1'){
    $p->beginTransaction();
    $fetch=$p->prepare("SELECT * FROM audit_plans WHERE month=10 AND year=2569 AND active=1 AND team=? ORDER BY sort_no,id");
    $fetch->execute(['A']); $aa=$fetch->fetchAll(PDO::FETCH_ASSOC);
    $fetch->execute(['B']); $bb=$fetch->fetchAll(PDO::FETCH_ASSOC);
    $fields=['department','inspection_date','inspection_time','area_5s','unit_count','status','date_color','department_color'];
    $upd=$p->prepare("UPDATE audit_plans SET department=?,inspection_date=?,inspection_time=?,area_5s=?,unit_count=?,status=?,date_color=?,department_color=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
    $n=min(count($aa),count($bb));
    for($i=0;$i<$n;$i++){
      $av=[];$bv=[];foreach($fields as $f){$av[]=$aa[$i][$f]??'';$bv[]=$bb[$i][$f]??'';}
      $upd->execute([...$bv,(int)$aa[$i]['id']]);
      $upd->execute([...$av,(int)$bb[$i]['id']]);
    }
    setting_set($p,'monthly_ab_department_swap_oct2569','1');
    $p->commit();
  }
}catch(Throwable $e){ if($p->inTransaction())$p->rollBack(); }

/* For a new month, each team keeps its own auditor names and the department sets rotate by month.
   Rows are per month, so editing one month's names never changes another month's history. */
$autoSeedNotice='';
if($month>=1 && $month<=12 && $year>=2500){
  try{
    $preCount=$p->prepare("SELECT COUNT(*) FROM audit_plans WHERE month=? AND year=? AND active=1 AND team IN ('A','B')");
    $preCount->execute([$month,$year]); $monthWasEmpty=((int)$preCount->fetchColumn()===0);
    foreach(['A','B'] as $seedTeam){
      $exists=$p->prepare('SELECT COUNT(*) FROM audit_plans WHERE month=? AND year=? AND team=? AND active=1');
      $exists->execute([$month,$year,$seedTeam]);
      if((int)$exists->fetchColumn()>0) continue;

      $sourceMonth=$p->prepare("SELECT own.month,own.year FROM audit_plans own
        WHERE own.team=? AND own.active=1 AND ((own.year < ?) OR (own.year=? AND own.month<?))
          AND EXISTS (SELECT 1 FROM audit_plans other WHERE other.month=own.month AND other.year=own.year AND other.team=? AND other.active=1)
        GROUP BY own.year,own.month ORDER BY own.year DESC,own.month DESC LIMIT 1");
      $sourceMonth->execute([$seedTeam,$year,$year,$month,$seedTeam==='A'?'B':'A']);
      $src=$sourceMonth->fetch(PDO::FETCH_ASSOC);
      if(!$src) continue;
      $ownQ=$p->prepare('SELECT * FROM audit_plans WHERE month=? AND year=? AND team=? AND active=1 ORDER BY sort_no,id LIMIT 20');
      /* Each team always keeps its own people. If the source month used the manual "swap people" button, its rows hold the other team's names. */
      $nameTeam=app_setting('schedule_swapped_'.(int)$src['year'].'_'.(int)$src['month'],'0')==='1' ? ($seedTeam==='A'?'B':'A') : $seedTeam;
      $ownQ->execute([(int)$src['month'],(int)$src['year'],$nameTeam]); $own=$ownQ->fetchAll(PDO::FETCH_ASSOC);
      /* Rotation by month: the team on duty takes department set 1, the other team takes set 2.
         In the September 2569 baseline, set 1 sits under team B and set 2 under team A. */
      $baselineTeam=$seedTeam===team_cycle_default($month,$year)?'B':'A';
      $otherQ=$p->prepare('SELECT * FROM audit_plans WHERE month=9 AND year=2569 AND team=? AND active=1 ORDER BY sort_no,id LIMIT 20');
      $otherQ->execute([$baselineTeam]); $other=$otherQ->fetchAll(PDO::FETCH_ASSOC);
      $n=min(20,max(count($own),count($other)));
      $ins=$p->prepare("INSERT INTO audit_plans(month,year,team,auditor_name,department,inspection_date,inspection_time,area_5s,unit_count,sort_no,active,note,status,date_color,department_color)
        VALUES(?,?,?,?,?,?,?,?,?,?,1,?,?,?,?)");
      $added=0;
      for($i=0;$i<$n;$i++){
        $person=$own[$i]??[]; $assignment=$other[$i]??[]; $sort=$i+1;
        $check=$p->prepare('SELECT 1 FROM audit_plans WHERE month=? AND year=? AND team=? AND sort_no=? AND active=1 LIMIT 1');
        $check->execute([$month,$year,$seedTeam,$sort]); if($check->fetchColumn()) continue;
        $ins->execute([$month,$year,$seedTeam,(string)($person['auditor_name']??''),(string)($assignment['department']??''),'',
          (string)($assignment['inspection_time']??'16:00 - 17:00'),(string)($assignment['area_5s']??''),(int)($assignment['unit_count']??0),$sort,
          (string)($person['note']??''),'รอตรวจ','#ffffff',(string)($assignment['department_color']??'#ffffff')]);
        $added++;
      }
      if($added>0)$autoSeedNotice='สร้างแผนทีม '.$seedTeam.' จำนวน '.$added.' รายการแล้ว กรุณากำหนดวันที่ตรวจ';
    }
  }catch(Throwable $e){if($p->inTransaction())$p->rollBack();$autoSeedNotice='ยังสร้างแผนเดือนนี้อัตโนมัติไม่ได้: '.$e->getMessage();}
}
if($autoSeedNotice!=='' && $notice==='') $notice=$autoSeedNotice;

$overrideKey='schedule_team_override_'.$year.'_'.$month;
$defaultTeam=team_cycle_default($month,$year);
/* The monthly responsibility is always calculated from the selected month.
   ?team=A/B is only a temporary view selector and must not save an override.
   Therefore refresh/login/month navigation cannot accidentally change the cycle. */
$requestedTeam=strtoupper(trim((string)($_GET['team']??'')));
$activeTeam=in_array($requestedTeam,['A','B'],true) ? $requestedTeam : $defaultTeam;

if(isset($_GET['n']) && $_SERVER['REQUEST_METHOD']!=='POST') $notice=mb_substr((string)$_GET['n'],0,300);
if($_SERVER['REQUEST_METHOD']==='POST'){
  $a=$_POST['action']??'';


  if($a==='department_add'){
    $name=trim((string)($_POST['name']??''));
    if($name===''){ header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'message'=>'กรุณากรอกชื่อแผนก'],JSON_UNESCAPED_UNICODE); exit; }
    $chk=$p->prepare("SELECT id FROM departments WHERE name=? COLLATE NOCASE LIMIT 1"); $chk->execute([$name]); $id=$chk->fetchColumn();
    if($id!==false){
      $p->prepare("UPDATE departments SET active=1,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([(int)$id]);
    }else{
      $st=$p->prepare("INSERT INTO departments(name,active) VALUES(?,1)"); $st->execute([$name]); $id=$p->lastInsertId();
    }
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>true,'id'=>(int)$id,'name'=>$name],JSON_UNESCAPED_UNICODE); exit;
  }

  if($a==='department_delete'){
    $id=(int)($_POST['id']??0);
    if($id<=0){ header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false],JSON_UNESCAPED_UNICODE); exit; }
    $p->prepare("UPDATE departments SET active=0,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>true,'id'=>$id],JSON_UNESCAPED_UNICODE); exit;
  }

  if($a==='set_team'){
    $newTeam=strtoupper(trim((string)($_POST['team']??'')));
    if(!in_array($newTeam,['A','B'],true)){
      http_response_code(400);
      echo 'ทีมไม่ถูกต้อง';
      exit;
    }
    // เปลี่ยนเฉพาะทีมที่กำลังเปิดดู ไม่บันทึกทับทีมรับผิดชอบประจำเดือน
    header('Location: '.FIVE_S_BASE.'Page/admin/schedule_manage.php?month='.$month.'&year='.$year.'&team='.$newTeam);
    exit;
  }

  /* V66: สลับคนตรวจทีม A ⇄ B — แผนก/หน่วย/วันที่/สถานะ อยู่ที่เดิม ย้ายเฉพาะรายชื่อผู้ตรวจ */
  if($a==='swap_people' || $a==='swap_people_undo'){
    $bkKey='schedule_swap_backup_'.$year.'_'.$month; $flagKey='schedule_swapped_'.$year.'_'.$month;
    try{
      $p->beginTransaction();
      if($a==='swap_people_undo'){
        $bk=json_decode(app_setting($bkKey,''),true);
        if(!is_array($bk)||!$bk) throw new RuntimeException('ไม่พบข้อมูลก่อนสลับของเดือนนี้');
        $u=$p->prepare('UPDATE audit_plans SET auditor_name=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND month=? AND year=?');
        foreach($bk['rows']??[] as $r) $u->execute([(string)$r['n'],(int)$r['id'],$month,$year]);
        foreach($bk['added']??[] as $id) $p->prepare('UPDATE audit_plans SET active=0 WHERE id=?')->execute([(int)$id]);
        save_app_setting($flagKey,'0'); save_app_setting($bkKey,'');
        $p->commit();
        $notice='คืนรายชื่อผู้ตรวจทีม A / ทีม B กลับเหมือนก่อนสลับแล้ว';
      }else{
        $load=function(string $t) use($p,$month,$year){
          $q=$p->prepare('SELECT id,sort_no,auditor_name FROM audit_plans WHERE month=? AND year=? AND team=? AND active=1 ORDER BY sort_no,id');
          $q->execute([$month,$year,$t]); $g=[];
          foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){ $k=(int)$r['sort_no']; $g[$k]??=['ids'=>[],'name'=>'']; $g[$k]['ids'][]=(int)$r['id']; if($g[$k]['name']==='' && trim((string)$r['auditor_name'])!=='') $g[$k]['name']=trim((string)$r['auditor_name']); }
          return array_values($g);
        };
        $gA=$load('A'); $gB=$load('B');
        $names=fn(array $g)=>array_values(array_filter(array_map(fn($x)=>$x['name'],$g),fn($n)=>$n!==''));
        $nA=$names($gA); $nB=$names($gB);
        if(!$nA && !$nB) throw new RuntimeException('ยังไม่มีรายชื่อผู้ตรวจในเดือนนี้');
        // เก็บข้อมูลก่อนสลับไว้สำหรับปุ่มย้อนกลับ
        $snap=[]; $q=$p->prepare("SELECT id,auditor_name FROM audit_plans WHERE month=? AND year=? AND team IN ('A','B') AND active=1"); $q->execute([$month,$year]);
        foreach($q as $r) $snap[]=['id'=>(int)$r['id'],'n'=>(string)$r['auditor_name']];
        $added=[];
        $u=$p->prepare('UPDATE audit_plans SET auditor_name=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
        $assign=function(array $groups,array $newNames,string $team) use($p,$u,$month,$year,&$added){
          foreach($groups as $i=>$g){ foreach($g['ids'] as $id) $u->execute([$newNames[$i]??'',$id]); }
          // ถ้าคนมากกว่าช่องเดิม เพิ่มแถวใหม่ (ยังไม่มีแผนก) ท้ายตาราง
          if(count($newNames)>count($groups)){
            $mx=$p->prepare('SELECT COALESCE(MAX(sort_no),0) FROM audit_plans WHERE month=? AND year=? AND team=? AND active=1'); $mx->execute([$month,$year,$team]); $sort=(int)$mx->fetchColumn();
            $ins=$p->prepare("INSERT INTO audit_plans(month,year,team,auditor_name,department,inspection_date,inspection_time,area_5s,unit_count,sort_no,active,status) VALUES(?,?,?,?,'','','16:00 - 17:00','',0,?,1,'รอตรวจ')");
            foreach(array_slice($newNames,count($groups)) as $n){ $ins->execute([$month,$year,$team,$n,++$sort]); $added[]=(int)$p->lastInsertId(); }
          }
        };
        $assign($gA,$nB,'A'); $assign($gB,$nA,'B');
        if(app_setting($flagKey,'0')!=='1') save_app_setting($bkKey,json_encode(['rows'=>$snap,'added'=>$added],JSON_UNESCAPED_UNICODE));
        save_app_setting($flagKey, app_setting($flagKey,'0')==='1' ? '0' : '1');
        $p->commit();
        $notice='สลับคนตรวจแล้ว: ผู้ตรวจทีม B '.count($nB).' คน ไปตรวจแผนกชุด A และผู้ตรวจทีม A '.count($nA).' คน ไปตรวจแผนกชุด B (แผนก/หน่วย/วันที่ อยู่ที่เดิม)';
      }
    }catch(Throwable $e){ if($p->inTransaction()) $p->rollBack(); $notice='สลับไม่สำเร็จ: '.$e->getMessage(); }
    header('Location: '.FIVE_S_BASE.'Page/admin/schedule_manage.php?month='.$month.'&year='.$year.'&team='.$activeTeam.'&n='.urlencode($notice)); exit;
  }

  if($a==='swap_team'){
    $newTeam=$activeTeam==='A'?'B':'A';
    $notice='เปลี่ยนไปดูทีม '.$newTeam.' สำหรับเดือนนี้เรียบร้อยแล้ว';
    $activeTeam=$newTeam;
  }

  if($a==='create_team_name'){
    $name=pv('value');
    $sort=(int)($_POST['sort_no']??0);
    if($name===''){
      header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'message'=>'กรุณากรอกชื่อทีมตรวจ'],JSON_UNESCAPED_UNICODE); exit;
    }
    if($sort<1) $sort=1;
    // If this sequence already exists, use the next free sequence number.
    $chk=$p->prepare('SELECT id FROM audit_plans WHERE month=? AND year=? AND team=? AND sort_no=? AND active=1 LIMIT 1');
    $chk->execute([$month,$year,$activeTeam,$sort]);
    if($chk->fetchColumn()!==false){
      $q=$p->prepare('SELECT COALESCE(MAX(sort_no),0)+1 FROM audit_plans WHERE month=? AND year=? AND team=? AND active=1');
      $q->execute([$month,$year,$activeTeam]); $sort=(int)$q->fetchColumn();
    }
    $st=$p->prepare('INSERT INTO audit_plans(month,year,team,auditor_name,department,inspection_date,inspection_time,area_5s,unit_count,sort_no,active) VALUES(?,?,?,?,?,?,?,?,?,?,1)');
    $ok=$st->execute([$month,$year,$activeTeam,$name,'','','16:00 - 17:00','',0,$sort]);
    $newId=(int)$p->lastInsertId();
    if($ok) renumberTeamRows($p,$month,$year,$activeTeam);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>$ok,'id'=>$newId,'sort_no'=>$sort],JSON_UNESCAPED_UNICODE); exit;
  }

  if($a==='save_all_rows'){
    header('Content-Type: application/json; charset=utf-8');
    $rows=json_decode($_POST['rows']??'[]',true);
    $names=json_decode($_POST['names']??'[]',true);
    if(!is_array($rows) || !is_array($names)){
      http_response_code(400); echo json_encode(['ok'=>false,'message'=>'รูปแบบข้อมูลไม่ถูกต้อง'],JSON_UNESCAPED_UNICODE); exit;
    }
    $updated=0;
    try{
      $p->beginTransaction();
      $up=$p->prepare("UPDATE audit_plans SET department=?,inspection_date=?,inspection_time=?,area_5s=?,unit_count=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND month=? AND year=? AND team=? AND active=1");
      foreach($rows as $row){
        if(!is_array($row)) continue;
        $id=(int)($row['id']??0); if($id<1) continue;
        $date=trim((string)($row['inspection_date']??''));
        $time=trim((string)($row['inspection_time']??''));
        $area=trim((string)($row['area_5s']??''));
        $status=trim((string)($row['status']??'รอตรวจ'));
        if($date!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new RuntimeException('รูปแบบวันที่ไม่ถูกต้องในรายการ ID '.$id);
        if(mb_strlen($time,'UTF-8')>80) throw new RuntimeException('เวลาไม่ถูกต้องในรายการ ID '.$id);
        if(!in_array($status,['รอตรวจ','เลื่อนการตรวจ','ตรวจบางส่วน','ตรวจแล้ว'],true)) $status='รอตรวจ';
        $ok=$up->execute([trim((string)($row['department']??'')),$date,$time,$area,unit_count($area),$status,$id,$month,$year,$activeTeam]);
        if(!$ok) throw new RuntimeException('บันทึกรายการ ID '.$id.' ไม่สำเร็จ');
        if($up->rowCount()>0) $updated++;
      }
      $nameUp=$p->prepare("UPDATE audit_plans SET auditor_name=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND month=? AND year=? AND team=? AND active=1");
      foreach($names as $entry){
        if(!is_array($entry)) continue;
        $value=trim((string)($entry['value']??''));
        $ids=$entry['ids']??[]; if(!is_array($ids)) continue;
        foreach($ids as $rawId){$id=(int)$rawId;if($id<1)continue;
          $ok=$nameUp->execute([$value,$id,$month,$year,$activeTeam]);
          if(!$ok) throw new RuntimeException('บันทึกชื่อผู้ตรวจไม่สำเร็จ');
        }
      }
      $p->commit();
      echo json_encode(['ok'=>true,'updated'=>$updated,'message'=>'บันทึกข้อมูลทั้งหมดเรียบร้อยแล้ว'],JSON_UNESCAPED_UNICODE); exit;
    }catch(Throwable $e){
      if($p->inTransaction()) $p->rollBack();
      http_response_code(400); echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE); exit;
    }
  }

  if($a==='quick_group_name'){
    $ids=json_decode($_POST['ids']??'[]',true); $ids=is_array($ids)?array_values(array_filter(array_map('intval',$ids))):[];
    $value=pv('value'); $ok=true;
    if($ids){
      $st=$p->prepare('UPDATE audit_plans SET auditor_name=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
      foreach($ids as $id){$ok=$ok && $st->execute([$value,$id]);}
    }
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>$ok],JSON_UNESCAPED_UNICODE); exit;
  }

  if($a==='quick_group_date_time'){
    $ids=json_decode($_POST['ids']??'[]',true); $ids=is_array($ids)?array_values(array_unique(array_filter(array_map('intval',$ids),fn($v)=>$v>0))):[];
    $date=pv('inspection_date'); $time=pv('inspection_time'); $ok=!empty($ids);
    if(($date!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) || mb_strlen($time,'UTF-8')>80) $ok=false;
    if($ok){
      $st=$p->prepare('UPDATE audit_plans SET inspection_date=?,inspection_time=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND month=? AND year=? AND team=? AND active=1');
      foreach($ids as $id){
        $done=$st->execute([$date,$time,$id,$month,$year,$activeTeam]);
        if(!$done || $st->rowCount()<1) $ok=false;
      }
    }
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>$ok,'message'=>$ok?'บันทึกวันที่/เวลาแล้ว':'บันทึกไม่สำเร็จ ตรวจรายการและทีมอีกครั้ง'],JSON_UNESCAPED_UNICODE); exit;
  }

  if($a==='quick_group_status'){
    $ids=json_decode($_POST['ids']??'[]',true); $ids=is_array($ids)?array_values(array_filter(array_map('intval',$ids))):[];
    $value=pv('value'); $allowed=['รอตรวจ','เลื่อนการตรวจ','ตรวจบางส่วน','ตรวจแล้ว'];
    if(!in_array($value,$allowed,true)) $value='รอตรวจ';
    $ok=true;
    if($ids){
      $st=$p->prepare('UPDATE audit_plans SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
      foreach($ids as $id){$ok=$ok && $st->execute([$value,$id]);}
    }
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>$ok],JSON_UNESCAPED_UNICODE); exit;
  }

  if($a==='quick_group_date_color'){
    $ids=json_decode($_POST['ids']??'[]',true); $ids=is_array($ids)?array_values(array_filter(array_map('intval',$ids))):[];
    $value=pv('value'); if(!preg_match('/^#[0-9a-fA-F]{6}$/',$value)) $value='#ffffff';
    $ok=true;
    if($ids){
      $st=$p->prepare('UPDATE audit_plans SET date_color=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
      foreach($ids as $id){$ok=$ok && $st->execute([$value,$id]);}
    }
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>$ok],JSON_UNESCAPED_UNICODE); exit;
  }

  if($a==='quick_color'){
    $id=(int)($_POST['id']??0); $field=$_POST['field']??''; $value=pv('value');
    $allowed=['department_color','date_color'];
    if(!in_array($field,$allowed,true) || !preg_match('/^#[0-9a-fA-F]{6}$/',$value)){
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok'=>false,'message'=>'ค่าสีไม่ถูกต้อง'],JSON_UNESCAPED_UNICODE); exit;
    }
    if($id<=0){
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok'=>false,'message'=>'ไม่พบรายการที่จะบันทึกสี'],JSON_UNESCAPED_UNICODE); exit;
    }
    $st=$p->prepare("UPDATE audit_plans SET $field=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
    $ok=$st->execute([$value,$id]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>$ok,'id'=>$id,'field'=>$field,'value'=>$value,'message'=>$ok?'บันทึกสีเรียบร้อย':'บันทึกสีไม่สำเร็จ'],JSON_UNESCAPED_UNICODE); exit;
  }

  if($a==='quick_group_color'){
    $ids=json_decode($_POST['ids']??'[]',true); $ids=is_array($ids)?array_values(array_filter(array_map('intval',$ids))):[];
    $field=$_POST['field']??''; $value=pv('value');
    $allowed=['date_color','department_color'];
    if(!in_array($field,$allowed,true) || !preg_match('/^#[0-9a-fA-F]{6}$/',$value)){
      header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'message'=>'ค่าสีไม่ถูกต้อง'],JSON_UNESCAPED_UNICODE); exit;
    }
    $ok=true;
    if($ids){
      $st=$p->prepare("UPDATE audit_plans SET $field=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
      foreach($ids as $id){$ok=$ok && $st->execute([$value,$id]);}
    } else $ok=false;
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>$ok,'count'=>count($ids),'value'=>$value],JSON_UNESCAPED_UNICODE); exit;
  }

  if($a==='quick_field'){
    $id=(int)($_POST['id']??0); $field=$_POST['field']??''; $value=pv('value');
    $allowed=['department','area_5s'];
    if(!in_array($field,$allowed,true)){ header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false]); exit; }
    if($field==='area_5s'){
      $units=unit_count($value);
      $st=$p->prepare('UPDATE audit_plans SET area_5s=?,unit_count=?,updated_at=CURRENT_TIMESTAMP WHERE id=?'); $ok=$st->execute([$value,$units,$id]);
    }else{
      $st=$p->prepare("UPDATE audit_plans SET $field=?,updated_at=CURRENT_TIMESTAMP WHERE id=?"); $ok=$st->execute([$value,$id]); $units=null;
    }
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>$ok,'units'=>$units],JSON_UNESCAPED_UNICODE); exit;
  }

  if($a==='insert_row'){
    $team=pv('team');
    $after=(int)($_POST['after_id']??0);
    $clone=(int)($_POST['clone_person']??0);
    $result=['ok'=>false,'id'=>0,'message'=>''];

    try{
      $q=$p->prepare('SELECT COALESCE(MAX(sort_no),0) FROM audit_plans WHERE month=? AND year=? AND team=? AND active=1');
      $q->execute([$month,$year,$team]);
      $sort=(int)$q->fetchColumn()+1;

      $baseRow=null;
      if($after>0){
        $q=$p->prepare('SELECT * FROM audit_plans WHERE id=? LIMIT 1');
        $q->execute([$after]);
        $baseRow=$q->fetch(PDO::FETCH_ASSOC);

        if($baseRow){
          $sort=(int)$baseRow['sort_no']+1;
          $p->prepare('UPDATE audit_plans SET sort_no=sort_no+1 WHERE month=? AND year=? AND team=? AND active=1 AND sort_no>=?')
             ->execute([$month,$year,$team,$sort]);
        }
      }

      $name=($clone && $baseRow) ? (string)($baseRow['auditor_name']??'') : '';
      $date=($clone && $baseRow) ? (string)($baseRow['inspection_date']??'') : '';
      $time=($clone && $baseRow) ? (string)($baseRow['inspection_time']??'16:00 - 17:00') : '16:00 - 17:00';

      $st=$p->prepare('INSERT INTO audit_plans
        (month,year,team,auditor_name,department,inspection_date,inspection_time,area_5s,unit_count,sort_no)
        VALUES(?,?,?,?,?,?,?,?,?,?)');

      $ok=$st->execute([$month,$year,$team,$name,'',$date,$time,'',0,$sort]);
      $newId=(int)$p->lastInsertId();

      if($ok) renumberTeamRows($p,$month,$year,$team);

      $result=['ok'=>(bool)$ok,'id'=>$newId,'message'=>$ok?'':'เพิ่มช่องไม่สำเร็จ'];
    }catch(Throwable $e){
      $result=['ok'=>false,'id'=>0,'message'=>'เพิ่มช่องไม่สำเร็จ: '.$e->getMessage()];
    }

    if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) || (($_POST['ajax']??'')==='1')){
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode($result,JSON_UNESCAPED_UNICODE);
      exit;
    }

    $notice=$result['ok'] ? 'เพิ่มช่องแล้ว กรุณากรอกข้อมูล' : $result['message'];
  }

  if($a==='save_row'){
    $id=(int)($_POST['id']??0); $area=pv('area_5s'); $units=unit_count($area);
    $st=$p->prepare('UPDATE audit_plans SET department=?,area_5s=?,unit_count=?,note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
    $ok=$st->execute([pv('department'),$area,$units,pv('note'),$id]);
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>$ok,'id'=>$id,'units'=>$units],JSON_UNESCAPED_UNICODE); exit;
  }

  if($a==='save'){
    $id=(int)($_POST['id']??0); $team=pv('team'); $area=pv('area_5s'); $units=unit_count($area);
    if($id){
      $q=$p->prepare('UPDATE audit_plans SET team=?,auditor_name=?,department=?,inspection_date=?,inspection_time=?,area_5s=?,unit_count=?,note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
      $q->execute([$team,pv('auditor_name'),pv('department'),pv('inspection_date'),pv('inspection_time'),$area,$units,pv('note'),$id]); $notice='แก้ไขข้อมูลเรียบร้อย';
    }else{
      $q=$p->prepare('INSERT INTO audit_plans(month,year,team,auditor_name,department,inspection_date,inspection_time,area_5s,unit_count,note) VALUES(?,?,?,?,?,?,?,?,?,?)');
      $q->execute([$month,$year,$team,pv('auditor_name'),pv('department'),pv('inspection_date'),pv('inspection_time'),$area,$units,pv('note')]); $notice='เพิ่มรายการเรียบร้อย';
    }
  }

  if($a==='delete'){
    $did=(int)($_POST['id']??0); $dq=$p->prepare('SELECT team FROM audit_plans WHERE id=?'); $dq->execute([$did]); $dteam=(string)$dq->fetchColumn();
    $p->prepare('UPDATE audit_plans SET active=0 WHERE id=?')->execute([$did]); if($dteam!=='') renumberTeamRows($p,$month,$year,$dteam); $notice='ลบรายการเรียบร้อย';
  }
}

require_once FIVE_S_ROOT.'/includes/auto_schedule_status.php';
try { sync_monthly_audit_plan_statuses($p,$month,$year); } catch(Throwable $syncError) { /* leave schedule editable if a legacy record is malformed */ }
$q=$p->prepare('SELECT * FROM audit_plans WHERE active=1 AND month=? AND year=? AND team=? ORDER BY sort_no,id');
$q->execute([$month,$year,$activeTeam]); $rows=$q->fetchAll();

// One visible row = one inspection team.
// A team may contain several department/5S-area sub-items inside the same row.
// IMPORTANT: do not rowspan/merge the sequence or team-name columns.
$groups=[]; $groupIndex=[];
foreach($rows as $r){
  $sort=(int)($r['sort_no']??0);
  $key='sort:'.$sort;
  if(!isset($groupIndex[$key])){
    $groupIndex[$key]=count($groups);
    $groups[]=['sort_no'=>$sort,'name'=>trim((string)$r['auditor_name']),'rows'=>[]];
  }
  // Keep the first non-empty team name in the team row.
  if($groups[$groupIndex[$key]]['name']==='' && trim((string)$r['auditor_name'])!=='')
    $groups[$groupIndex[$key]]['name']=trim((string)$r['auditor_name']);
  $groups[$groupIndex[$key]]['rows'][]=$r;
}

// Always show exactly 20 team rows.
while(count($groups)<20){
  $groups[]=['sort_no'=>999999+count($groups),'name'=>'','rows'=>[['id'=>0,'team'=>$activeTeam,'auditor_name'=>'','department'=>'','inspection_date'=>'','inspection_time'=>'','area_5s'=>'','unit_count'=>0,'note'=>'']]];
}
$groups=array_slice($groups,0,20);

// รายการแผนกสำหรับช่องเลือกแผนก: ใช้ Master ที่บันทึกถาวร
$departmentOptions=[];
$departmentItems=[];
try{
  $dq=$p->query("SELECT id,name FROM departments WHERE active=1 ORDER BY name COLLATE NOCASE");
  foreach($dq as $dr){
    $departmentItems[]=['id'=>(int)$dr['id'],'name'=>trim((string)$dr['name'])];
    $departmentOptions[]=$dr['name'];
  }
}catch(Throwable $e){ $departmentOptions=[]; $departmentItems=[]; }

$teamPeople=0; foreach($groups as $g){ if(trim((string)($g['name']??''))!=='') $teamPeople++; }
$totalUnits=0;
foreach($groups as $g){foreach($g['rows'] as $r){$totalUnits+=unit_count($r['area_5s']??'');}}
$months=[1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
function dcls($d){$day=(int)substr((string)$d,-2); return $day===22?'d22':($day===23?'d23':($day===24?'d24':($day===25?'d25':'')));}
function thdate_short($d){
  if(!$d)return '';
  $p=explode('-',$d); if(count($p)!==3)return $d;
  $y=(int)$p[0]; $y=$y>2400?$y:$y+543;
  return $p[2].'/'.$p[1].'/'.$y;
}
require FIVE_S_ROOT.'/includes/header.php';
?>
<style>
.v16-wrap{max-width:1240px;margin:0 auto;padding-bottom:40px}
.v16-title{display:flex;align-items:center;gap:12px;color:#173b70;font-weight:950;font-size:28px;margin:8px 0 12px}
.v16-title .cal{background:#ff5a00;color:#fff;border-radius:12px;padding:8px 11px}
.v16-filter{background:#fff;border:1px solid #dbe3ee;border-radius:14px;padding:14px;box-shadow:0 4px 14px rgba(15,23,42,.06)}
.v16-filter select,.v16-filter input{border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;background:#fff}
.v16-btn{border:0;border-radius:8px;padding:9px 13px;font-weight:800;color:#fff;cursor:pointer}.orange{background:#ff5a00}.blue{background:#1677e8}.green{background:#0b9b58}.red{background:#ef4444}.dark{background:#173b70}.purple{background:#7c3aed}
.schedule-card{background:#fff;border:2px solid #ff8a00;border-radius:16px;overflow:hidden;margin-top:16px;box-shadow:0 8px 24px rgba(15,23,42,.08)}
.schedule-top{background:linear-gradient(120deg,#ff7a00,#ff4d00 55%,#ff9a00);color:#fff;padding:13px 18px;display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px}
.schedule-top .left{font-size:22px;font-weight:950}.schedule-top .center-team{display:flex;justify-content:center;align-items:center}.schedule-top .right{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}.team-pill{background:#fff;color:#173b70;border-radius:999px;padding:9px 26px;font-weight:1000;font-size:24px;line-height:1;min-width:150px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.18);border:2px solid rgba(255,255,255,.75)}
.team-toggle{display:inline-flex;align-items:center;gap:4px;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.4);padding:4px;border-radius:999px}.team-toggle form{margin:0}.team-toggle-btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;min-width:76px;padding:8px 16px;border:2px solid rgba(255,255,255,.65);border-radius:999px;background:transparent;color:#fff;font-weight:950;font-size:18px;line-height:1.1;cursor:pointer}.team-toggle-btn.active{background:#fff;color:#173b70;box-shadow:0 2px 8px rgba(0,0,0,.18)}.team-toggle-btn:focus{outline:2px solid #fff;outline-offset:2px}.only-print{display:none}
.cycle-note{background:#fff7ed;color:#9a3412;border:1px solid #fdba74;border-radius:9px;padding:6px 10px;font-weight:800}
.v16table{width:100%;min-width:1120px;border-collapse:collapse;font-size:12px;table-layout:fixed;white-space:normal}
.v16table colgroup col:nth-child(3){width:14%!important}
.v16table th,.v16table td{box-sizing:border-box;overflow-wrap:anywhere}
.v16table .department-select{width:100%;min-width:0;box-sizing:border-box}
.v16table th{background:#ff5a00;color:#fff;padding:9px 5px;border:1px solid #fff;font-size:12px}
.v16table td{border:1px solid #9aa9bb;padding:5px 5px;text-align:center;background:#fff;vertical-align:middle}
.v16table td.order,.v16table td.name{text-align:left;background:#fff;border-top-color:transparent!important;border-bottom-color:transparent!important}
.v16table td.order{text-align:center;font-weight:900;color:#173b70;width:5%}.v16table td.name{font-weight:800;color:#173b70}
.v16table .name-input{width:100%;border:0!important;background:#fff;color:#173b70;font-weight:850;padding:7px 6px;font-size:13px;box-shadow:none!important}
.v16table .name-input:focus{outline:2px solid #ff7a00;border-radius:5px}
.v16table .dept,.v16table .area{background:#fff}
.cell-edit{width:100%;border:1px solid #d7dee8;border-radius:6px;background:#fff;padding:6px;color:#173b70;font-weight:650}.cell-edit:focus{outline:2px solid #ff7a00}
.area-edit{text-align:center}.time-edit{text-align:center;font-weight:800}.date-cell,.time-cell{font-weight:850;color:#173b70}.date-cell.d22,.time-cell.d22{background:#f9dff0}.date-cell.d23,.time-cell.d23{background:#d9f0fb}.date-cell.d24,.time-cell.d24{background:#dcf6d9}.date-cell.d25,.time-cell.d25{background:#eadff7}
.row-date{border:1px solid #d7dee8;border-radius:6px;background:#fff;width:100%;text-align:center;font-weight:800;color:#173b70;padding:5px}
.status-select{width:100%;border:1px solid #d7dee8;border-radius:6px;padding:6px 7px;font-weight:900;color:#173b70;background:#fff;cursor:pointer}
.status-select.wait{background:#fff7ed;color:#9a3412;border-color:#fdba74}
.status-select.postpone{background:#fff1f2;color:#be123c;border-color:#fda4af}
.status-select.done{background:#ecfdf5;color:#166534;border-color:#86efac}.status-select.partial{background:#fff7ed;color:#9a3412;border-color:#fdba74}
.color-tools{display:flex;align-items:center;gap:4px;width:100%;min-width:0}
.color-tools>.cell-edit{flex:1 1 auto;min-width:0;width:auto}
.color-tools>.row-date{flex:1 1 auto;min-width:0;width:auto}
.department-select{appearance:auto;font-weight:850;color:#173b70;border:1px solid #cbd5e1;border-radius:7px;padding:7px 30px 7px 9px;cursor:pointer;background-clip:padding-box}.department-select:focus{outline:2px solid #ff7a00;outline-offset:1px}
.color-select{width:28px;height:28px;padding:2px;border:1px solid #94a3b8;border-radius:5px;background:#fff;cursor:pointer;flex:0 0 28px;box-sizing:border-box;display:inline-grid;place-items:center}
.color-swatch-btn:hover{border-color:#ff7a00;transform:scale(1.03)}
.color-swatch-btn:focus{outline:2px solid #ff7a00;outline-offset:1px}
.color-dot{display:block;width:20px;height:20px;border-radius:3px;border:1px solid #64748b;box-sizing:border-box}
.small-color-palette{position:fixed;z-index:99999;display:none;grid-template-columns:repeat(6,24px);gap:5px;padding:7px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.22)}
.small-color-palette.show{display:grid}
.small-color-palette button{width:24px;height:24px;border:1px solid #64748b;border-radius:4px;cursor:pointer;padding:0}
.small-color-palette button:hover{outline:2px solid #ff7a00;outline-offset:1px}
.color-save{display:none!important}
.color-swatch,.color-hex{display:none!important}
.action{display:flex;gap:4px;justify-content:center}.iconbtn{border:0;border-radius:5px;color:#fff;padding:5px 7px;font-weight:900;cursor:pointer}.add{background:#16a34a}.save{background:#173b70}.edit{background:#1677e8}.trash{background:#ef4444}
.unit{font-weight:950;color:#173b70;background:#fff}.subrow{background:#fff}
.v16-footer{display:flex;justify-content:space-around;align-items:center;padding:14px;background:#fff;border-top:1px solid #dbe3ee}.v16-footer .fitem{text-align:center;font-weight:900;color:#173b70}.v16-footer .circle{margin:auto auto 5px;width:42px;height:42px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:20px;background:#ff5a00}
.blank-note{color:#94a3b8;font-weight:700}
.stack{display:flex;flex-direction:column;gap:3px}.stack .cell-edit{box-sizing:border-box}.unit-stack span{display:block;min-height:29px;line-height:29px}.action-stack .action-line{display:flex;gap:4px;justify-content:center;min-height:29px;align-items:center}.team-row td{background:#fff}.team-row .date-cell.d22,.team-row .time-cell.d22{background:#f9dff0}.team-row .date-cell.d23,.team-row .time-cell.d23{background:#d9f0fb}.team-row .date-cell.d24,.team-row .time-cell.d24{background:#dcf6d9}.team-row .date-cell.d25,.team-row .time-cell.d25{background:#eadff7}.team-row{height:auto}.team-row .name{background:#fff}.team-row .order{background:#fff}.team-row .name-input{min-height:36px}
/* Standard row alignment: sequence and auditor name stay aligned to the first line of each numbered slot. */
.v16table .team-row>td.order,.v16table .team-row>td.name{vertical-align:top;padding-top:8px;}
.v16table .team-row>td.order{font-variant-numeric:tabular-nums;font-size:13px;}
.v16table .team-row>td.name .name-input{display:block;min-height:32px;line-height:18px;padding:6px 5px;}
.v16table .team-row .stack{gap:4px;}
.v16table .team-row .stack>div,.v16table .team-row .stack>input,.v16table .team-row .stack>select,.v16table .team-row .stack>span{min-height:32px;box-sizing:border-box;}
.v16table .team-row .unit-stack span{line-height:32px;min-height:32px;}
.v16table .team-row .action-stack .action-line{min-height:32px;}
.team-row .cell-edit{min-height:30px}.team-row .action-line .iconbtn{padding:5px 6px}

@media(max-width:900px){.schedule-top{grid-template-columns:1fr;justify-items:center;text-align:center}.schedule-top .left,.schedule-top .right{justify-content:center}.team-pill{font-size:22px;padding:8px 24px}.v16table{font-size:9px}.v16table th{font-size:9px;padding:6px 2px}.v16table td{padding:3px 2px}.v16table .name-input{font-size:10px}.v16-title{font-size:21px}.schedule-top .left{font-size:17px}}
@media print{ @page{size:A4 landscape;margin:5mm} body{background:#fff!important}.no-print,.v16-title,.v16-filter,.sidebar,.topbar,.footer{display:none!important}.v16-wrap{max-width:none;padding:0}.schedule-card{border:0;box-shadow:none;margin:0}.schedule-top{padding:7px 10px;grid-template-columns:1fr auto 1fr}.schedule-top .left{font-size:16px}.team-pill{font-size:15px;padding:5px 14px;min-width:90px}.only-print{display:inline-block}.schedule-top .right{font-size:8px}.v16table{font-size:8px}.v16table th{padding:4px 2px;font-size:8px}.v16table td{padding:3px 2px}.v16table .name-input{font-size:8px}.v16-footer{padding:6px}.v16-footer .circle{width:28px;height:28px;font-size:13px}.v16-footer .fitem{font-size:7px}}

/* Resizing controls for planning table */
.v16table { table-layout: fixed; }
.v16table th { position:relative; }
.v16table .col-resize-grip { position:absolute; top:0; right:-2px; width:7px; height:100%; cursor:col-resize; z-index:10; touch-action:none; }
.v16table .col-resize-grip:hover, .v16table .col-resize-grip.dragging { background:rgba(255,255,255,.65); }
.v16table tbody tr { position:relative; }
.v16table .row-resize-grip { position:absolute; left:0; bottom:-3px; width:100%; height:7px; cursor:row-resize; z-index:8; touch-action:none; }
.v16table .row-resize-grip:hover, .v16table .row-resize-grip.dragging { background:rgba(255,90,0,.25); }
@media print { .col-resize-grip,.row-resize-grip { display:none!important; } }


/* ===== V63 international redesign (overrides only; behaviour unchanged) ===== */
.v16-wrap{max-width:1560px;font-family:"IBM Plex Sans Thai","Inter",Tahoma,sans-serif}
.sm-head{display:flex;align-items:center;justify-content:space-between;gap:16px;background:#fff;border:1px solid #E4E8EF;border-radius:18px;padding:18px 22px;margin-bottom:14px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 18px rgba(15,23,42,.05);position:relative;overflow:hidden;flex-wrap:wrap}
.sm-head::before{content:"";position:absolute;left:0;top:0;bottom:0;width:5px;background:linear-gradient(180deg,#F7A21B,#F26A21)}
.sm-title{display:flex;align-items:center;gap:14px}.sm-ic{width:52px;height:52px;border-radius:14px;background:#FFF1E7;color:#F26A21;display:flex;align-items:center;justify-content:center}.sm-ic .ico{width:28px;height:28px}
.sm-title h1{margin:0;font-size:26px;font-weight:700;color:#0E1A2F}.sm-title h1 span{color:#F26A21}.sm-title p{margin:3px 0 0;font-size:13.5px;color:#64748B}
.sm-team-now{background:#0E1A2F;color:#fff;border-radius:14px;padding:10px 20px;text-align:center}.sm-team-now small{display:block;font-size:12px;color:#CBD5E1}.sm-team-now b{font:800 24px Inter,"IBM Plex Sans Thai",sans-serif;color:#F7A21B}
.sm-bar{display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;background:#fff;border:1px solid #E4E8EF;border-radius:16px;padding:12px 14px;margin-bottom:14px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 18px rgba(15,23,42,.05)}
.sm-filter{display:flex;gap:8px;align-items:flex-end;margin:0}
.sm-bar .fld{display:flex;flex-direction:column;gap:2px}.sm-bar .fld label{font-size:11px;font-weight:600;color:#64748B;padding-left:2px}
.sm-bar .fld select{height:42px;border:1px solid #D5DBE5;border-radius:10px;padding:0 12px;font-weight:600;font-size:15px;color:#0F172A;background:#fff;min-width:130px}
.sm-group{display:flex;gap:8px;flex-wrap:wrap;padding-left:12px;border-left:1px solid #E4E8EF}.sm-group.right{margin-left:auto}
.sm-btn{display:inline-flex;align-items:center;gap:7px;height:42px;padding:0 15px;border-radius:10px;font-weight:600;font-size:14px;text-decoration:none;cursor:pointer;border:1px solid #D5DBE5;background:#fff;color:#1E293B;font-family:inherit;white-space:nowrap}
.sm-btn .ico{width:17px;height:17px}.sm-btn:hover{border-color:#F26A21;color:#F26A21}
.sm-btn.primary{background:#F26A21;border-color:#F26A21;color:#fff}.sm-btn.primary:hover{background:#DB5512;color:#fff}
.sm-btn.line{background:#06C755;border-color:#06C755;color:#fff}.sm-btn.line:hover{background:#05A847;color:#fff}
.sm-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:14px}
.sm-stats>div{background:#fff;border:1px solid #E4E8EF;border-radius:14px;padding:12px 16px;position:relative;overflow:hidden}
.sm-stats small{display:block;font-size:13px;color:#64748B;font-weight:600}.sm-stats b{font:800 28px/1.15 Inter,sans-serif;color:#0E1A2F}.sm-stats em{font-style:normal;font-size:13px;color:#64748B;margin-left:5px}
.sm-stats .g b{color:#15803D}.sm-stats .a b{color:#B45309}.sm-stats .r b{color:#475569}.sm-stats em.x{color:#B91C1C;font-weight:600}
.sm-stats .bar{display:block;height:6px;background:#EEF1F5;border-radius:9px;margin-top:6px;overflow:hidden}.sm-stats .bar i{display:block;height:100%;background:#16A34A}
.schedule-card{border:1px solid #E4E8EF;border-radius:18px;margin-top:0;box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px rgba(15,23,42,.06)}
.schedule-top{background:#0E1A2F;padding:12px 18px}
.schedule-top .left{font-size:18px;font-weight:700}.schedule-top .left span{display:inline-block;margin-left:8px;padding:3px 12px;border-radius:999px;background:rgba(255,255,255,.12);font-size:14px;font-weight:600;color:#F7A21B}
.team-toggle{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.18)}
.team-toggle-btn{border:0;font-size:16px;font-weight:700;color:#CBD5E1;min-width:88px;padding:8px 18px}
.team-toggle-btn.active{background:#F26A21;color:#fff;box-shadow:0 2px 8px rgba(242,106,33,.4)}
.cycle-note{background:rgba(255,255,255,.1);color:#E2E8F0;border:0;font-weight:500;font-size:13px}
.v16table{font-size:14px}
.v16table th{background:#F1F4F8;color:#334155;border:0;border-bottom:2px solid #E2E8F0;padding:11px 6px;font-size:13px;font-weight:700}
.v16table td{border:0;border-bottom:1px solid #EEF1F5;padding:7px 6px}
.v16table tbody tr.team-row:nth-child(even)>td{background:#FAFBFD}
.v16table tbody tr.team-row:hover>td{background:#FFF8F2}
.v16table td.order{color:#94A3B8;font:700 14px Inter,sans-serif}
.v16table .name-input{font-size:15px;font-weight:700;color:#0E1A2F;background:transparent}
.v16table .name-input::placeholder{color:#CBD5E1;font-weight:500}
.cell-edit,.row-date,.department-select,.status-select{min-height:38px!important;border-radius:9px!important;font-size:14px!important;border-color:#D5DBE5!important}
.cell-edit:focus,.row-date:focus,.department-select:focus,.status-select:focus{outline:none!important;border-color:#F26A21!important;box-shadow:0 0 0 3px rgba(242,106,33,.15)}
.area-edit{font:600 14px Inter,sans-serif!important;letter-spacing:.3px}
.v16table .team-row .stack>div,.v16table .team-row .stack>input,.v16table .team-row .stack>select,.v16table .team-row .stack>span{min-height:38px}
.v16table .team-row .unit-stack span{line-height:38px;min-height:38px}
.v16table .team-row .action-stack .action-line{min-height:38px}
.unit-stack span{font:800 15px Inter,sans-serif;color:#C2410C}
.status-select{font-weight:700!important;border-width:1px!important}
.status-select.done{background:#E8F7EE;color:#15803D;border-color:#9AE0B4!important}
.status-select.partial{background:#FEF4E2;color:#B45309;border-color:#F8D18D!important}
.status-select.wait{background:#F1F5F9;color:#475569;border-color:#D5DBE5!important}
.status-select.postpone{background:#FDECEC;color:#B91C1C;border-color:#F5B5B5!important}
.color-select{width:32px;height:32px;flex-basis:32px;border-radius:8px;border-color:#D5DBE5}
.iconbtn{width:32px;height:32px;padding:0!important;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:16px;line-height:1;background:#fff!important;border:1px solid #D5DBE5!important;color:#475569}
.iconbtn .ico{width:16px;height:16px}
.iconbtn.add:hover{color:#15803D;border-color:#15803D!important;background:#E8F7EE!important}
.iconbtn.save{color:#1D4ED8}.iconbtn.save:hover{background:#EAF1FF!important;border-color:#1D4ED8!important}
.iconbtn.edit:hover{color:#F26A21;border-color:#F26A21!important;background:#FFF1E7!important}
.iconbtn.trash{color:#B91C1C}.iconbtn.trash:hover{background:#FDECEC!important;border-color:#B91C1C!important}
.v16-footer{justify-content:space-between;padding:14px 20px;background:#FAFBFC;flex-wrap:wrap;gap:10px}
.sm-legend{display:flex;gap:8px;flex-wrap:wrap}.lg{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:13px;font-weight:700}
.lg::before{content:"";width:8px;height:8px;border-radius:50%;background:currentColor}
.lg.done{background:#E8F7EE;color:#15803D}.lg.partial{background:#FEF4E2;color:#B45309}.lg.wait{background:#F1F5F9;color:#475569}.lg.postpone{background:#FDECEC;color:#B91C1C}
.sm-total{font-weight:700;color:#0E1A2F;font-size:15px}.sm-total b{font:800 22px Inter,sans-serif;color:#fff;background:#F26A21;border-radius:10px;padding:2px 12px;margin:0 6px}
@media(max-width:1200px){.sm-stats{grid-template-columns:repeat(3,minmax(0,1fr))}.sm-group.right{margin-left:0}}
@media(max-width:640px){.sm-stats{grid-template-columns:1fr 1fr}.sm-group{border-left:0;padding-left:0}}
@media print{.sm-head,.sm-bar,.sm-stats{display:none!important}.schedule-top{background:#0E1A2F!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>

<div class="v16-wrap">
  <?php
  $__st=['ตรวจแล้ว'=>0,'ตรวจบางส่วน'=>0,'รอตรวจ'=>0,'เลื่อนการตรวจ'=>0]; $__rowsN=0; $__dated=0;
  foreach($groups as $__g){ foreach($__g['rows'] as $__r){ if(!(int)($__r['id']??0) || trim((string)($__r['department']??''))==='') continue; /* V70: นับเฉพาะแถวที่มีแผนก */ $__rowsN++; $sv=trim((string)($__r['status']??'')); if(isset($__st[$sv])) $__st[$sv]++; else $__st['รอตรวจ']++; if(trim((string)($__r['inspection_date']??''))!=='') $__dated++; } }
  $__pct=$__rowsN?round($__st['ตรวจแล้ว']/$__rowsN*100):0;
  ?>
  <div class="sm-head">
    <div class="sm-title"><span class="sm-ic"><?=five_s_icon('calendar')?></span><div><h1>แผนการตรวจ 5 ส. <span><?=$months[$month]?> <?=$year?></span></h1><p>จัดทีม • กำหนดวันตรวจ • ติดตามสถานะ (สถานะอัปเดตอัตโนมัติจากคะแนนที่บันทึก)</p></div></div>
    <div class="sm-team-now"><small>ทีมรับผิดชอบเดือนนี้</small><b>ทีม <?=h($defaultTeam)?></b><?php if(app_setting('schedule_swapped_'.$year.'_'.$month,'0')==='1'):?><small style="color:#C4B5FD;margin-top:2px">⇄ สลับคนตรวจ A/B แล้ว</small><?php endif;?></div>
  </div>

  <div class="sm-bar no-print">
    <form method="get" class="sm-filter"><input type="hidden" name="team" value="<?=h($activeTeam)?>">
      <div class="fld"><label>เดือน</label><select name="month" onchange="this.form.submit()"><?php foreach($months as $m=>$n):?><option value="<?=$m?>" <?=$month==$m?'selected':''?>><?=$n?></option><?php endforeach;?></select></div>
      <div class="fld"><label>ปี พ.ศ.</label><select name="year" onchange="this.form.submit()"><?php for($yy=2569;$yy<=max(2571,(int)date('Y')+544);$yy++):?><option value="<?=$yy?>" <?=$year==$yy?'selected':''?>><?=$yy?></option><?php endfor;?></select></div>
    </form>
    <div class="sm-group">
      <button type="button" class="sm-btn primary" onclick="saveAllRows()"><?=five_s_icon('check')?> บันทึกทั้งหมด</button>
      <button type="button" class="sm-btn" onclick="addNewTeam()"><?=five_s_icon('users')?> เพิ่มทีมตรวจ</button>
      <button type="button" class="sm-btn" onclick="openDepartmentManager()"><?=five_s_icon('building')?> จัดการแผนก</button>
      <?php $__swapped=app_setting('schedule_swapped_'.$year.'_'.$month,'0')==='1'; ?>
      <form method="post" style="display:inline" onsubmit="return confirm('สลับคนตรวจทีม A ⇄ ทีม B ของเดือน <?=$months[$month]?> <?=$year?> ?\n\n• แผนก / หน่วย / วันที่ / เวลา / สถานะ อยู่ที่เดิม\n• ย้ายเฉพาะรายชื่อผู้ตรวจ ตามลำดับแถว\n• ย้อนกลับได้ด้วยปุ่ม “คืนรายชื่อเดิม”');"><input type="hidden" name="action" value="swap_people"><button class="sm-btn" style="border-color:#7C3AED;color:#6D28D9" title="สลับรายชื่อผู้ตรวจระหว่างทีม A และทีม B โดยแผนกอยู่ที่เดิม">⇄ สลับคนตรวจ A ⇄ B</button></form>
      <?php if($__swapped):?><form method="post" style="display:inline" onsubmit="return confirm('คืนรายชื่อผู้ตรวจกลับเหมือนก่อนสลับ?');"><input type="hidden" name="action" value="swap_people_undo"><button class="sm-btn" title="คืนรายชื่อผู้ตรวจเหมือนก่อนสลับ">↺ คืนรายชื่อเดิม</button></form><?php endif;?>
    </div>
    <div class="sm-group right">
      <a class="sm-btn" href="Page/public/schedule_print.php?month=<?=$month?>&year=<?=$year?>&team=<?=h($activeTeam)?>" target="_blank"><?=five_s_icon('printer')?> พิมพ์ A4</a>
      <a class="sm-btn" href="Page/public/schedule_print.php?month=<?=$month?>&year=<?=$year?>&size=A3&team=<?=h($activeTeam)?>" target="_blank"><?=five_s_icon('printer')?> A3</a>
      <a class="sm-btn" href="Page/public/schedule_signoff.php?month=<?=$month?>&year=<?=$year?>&team=<?=h($activeTeam)?>" target="_blank"><?=five_s_icon('pen')?> แบบลงชื่อ</a>
      <button type="button" class="sm-btn line" onclick="copyLineImage()"><?=five_s_icon('file')?> คัดลอกรูปส่ง LINE</button>
    </div>
  </div>

  <div class="sm-stats">
    <div><small>ผู้ตรวจในทีม <?=h($activeTeam)?></small><b><?=$teamPeople?></b><em>คน</em></div>
    <div><small>หน่วยพื้นที่ 5 ส.</small><b><?=$totalUnits?></b><em>หน่วย</em></div>
    <div><small>กำหนดวันแล้ว</small><b id="stDated"><?=$__dated?></b><em id="stDatedOf">/ <?=$__rowsN?> แผนก</em></div>
    <div class="g"><small>ตรวจแล้ว</small><b id="stDone"><?=$__st['ตรวจแล้ว']?></b><em id="stPct"><?=$__pct?>%</em><span class="bar"><i id="stBar" style="width:<?=$__pct?>%"></i></span></div>
    <div class="a"><small>กำลังตรวจ</small><b id="stPart"><?=$__st['ตรวจบางส่วน']?></b></div>
    <div class="r"><small>ยังไม่ได้ตรวจ</small><b id="stWait"><?=$__st['รอตรวจ']?></b><?php if($__st['เลื่อนการตรวจ']):?><em class="x">เลื่อน <?=$__st['เลื่อนการตรวจ']?></em><?php endif;?></div>
  </div>

  <?php if($notice):?><div class="no-print" style="background:#ecfdf5;border:1px solid #86efac;color:#166534;border-radius:10px;padding:9px 12px;margin-top:10px;font-weight:850">✓ <?=h($notice)?></div><?php endif;?>

  <div class="schedule-card" id="poster">
    <div class="schedule-top">
      <div class="left">ตารางทีมตรวจ <span>ทีม <?=h($activeTeam)?> • <?=$teamPeople?> คน</span></div>
      <div class="center-team">
        <div class="team-toggle no-print" role="group" aria-label="เลือกทีมตรวจ">
          <?php foreach(['A','B'] as $teamOption): ?>
            <a href="Page/admin/schedule_manage.php?month=<?=$month?>&year=<?=$year?>&team=<?=$teamOption?>"
               class="team-toggle-btn <?=$activeTeam===$teamOption?'active':''?>"
               aria-pressed="<?=$activeTeam===$teamOption?'true':'false'?>"
               title="แสดงรายชื่อและแผนตรวจของทีม <?=$teamOption?>">ทีม <?=$teamOption?></a>
          <?php endforeach; ?>
        </div>
        <span class="team-pill only-print">ทีม <?=$activeTeam?></span>
      </div>
      <div class="right"><span class="cycle-note">รวม <?=$totalUnits?> หน่วย • พิมพ์ข้อมูลแล้วกดบันทึกอัตโนมัติ</span></div>
    </div>

    <table class="v16table">
      <colgroup><col style="width:3.5%"><col style="width:16%"><col style="width:14%"><col style="width:13.5%"><col style="width:9%"><col style="width:17%"><col style="width:4%"><col style="width:11%"><col style="width:12%"></colgroup>
      <thead><tr><th>ลำดับ</th><th>ชื่อ - สกุล (ทีมตรวจ)</th><th>แผนก</th><th>วันที่ตรวจ</th><th>เวลา</th><th>หน่วยพื้นที่ 5 ส.</th><th>หน่วย</th><th>สถานะ</th><th class="no-print">จัดการ</th></tr></thead>
      <tbody>
      <?php foreach($groups as $gi=>$g):
        $grows=$g['rows']; $first=$grows[0];
        $ids=array_values(array_filter(array_map(fn($r)=>(int)($r['id']??0),$grows)));
        $date=$first['inspection_date']??''; $time=$first['inspection_time']??''; $dateClass=dcls($date);
        $name=$g['name']??'';
        $statusCounts=['รอตรวจ'=>0,'เลื่อนการตรวจ'=>0,'ตรวจบางส่วน'=>0,'ตรวจแล้ว'=>0];
        foreach($grows as $sr){$sv=trim((string)($sr['status']??'')); if(isset($statusCounts[$sv])) $statusCounts[$sv]++;}
        arsort($statusCounts); $status=(string)array_key_first($statusCounts);
        if($statusCounts[$status]===0) $status='รอตรวจ';
        $dateColor=$first['date_color']??'#ffffff'; if(!preg_match('/^#[0-9a-fA-F]{6}$/',$dateColor)) $dateColor='#ffffff';
      ?>
        <tr class="team-row">
          <td class="order"><?=($gi+1)?></td>
          <td class="name">
            <?php if(!empty($ids)): ?>
              <input class="name-input" value="<?=h($name)?>" data-group-ids='<?=h(json_encode($ids))?>' onchange="saveGroupName(this)">
            <?php else: ?>
              <input class="name-input new-team-name" value="" placeholder="เพิ่มชื่อทีมตรวจ" data-sort="<?=($gi+1)?>" onchange="createTeamName(this)">
            <?php endif; ?>
          </td>
          <td class="dept <?=$dateClass?>">
            <div class="stack">
            <?php foreach($grows as $r): $rid=(int)($r['id']??0); ?>
              <div class="color-tools">
                <select class="cell-edit department-select" style="background:<?=h($r['department_color']??'#ffffff')?>;flex:1;min-width:0" data-id="<?=$rid?>" data-field="department" onchange="saveDepartmentAndRecalc(this)" <?=!$rid?'disabled':''?>>
                  <option value="">เลือกแผนก</option>
                  <?php foreach($departmentOptions as $depOpt): ?>
                    <option value="<?=h($depOpt)?>" <?=((string)($r['department']??'')===$depOpt?'selected':'')?>><?=h($depOpt)?></option>
                  <?php endforeach; ?>
                </select>
                <?php if($rid): ?><?php $dc=$r['department_color']??'#ffffff'; if(!preg_match('/^#[0-9a-fA-F]{6}$/',$dc))$dc='#ffffff'; ?><button type="button" class="color-select color-swatch-btn" title="เลือกสีแผนก" data-color-field="department_color" data-id="<?=$rid?>" data-value="<?=h($dc)?>" onclick="toggleColorPalette(this)"><span class="color-dot" style="background:<?=h($dc)?>"></span></button><?php endif; ?>
              </div>
            <?php endforeach; ?>
            </div>
          </td>
          <td class="date-cell">
            <div class="stack">
            <?php foreach($grows as $r): $rid=(int)($r['id']??0);
              $rd=$r['inspection_date']??''; $rtc=$r['date_color']??'#ffffff';
              if(!preg_match('/^#[0-9a-fA-F]{6}$/',$rtc))$rtc='#ffffff';
              $rclass=dcls($rd);
            ?>
              <div class="color-tools <?=$rclass?>" style="background:<?=h($rtc)?>!important">
                <?php if($rid): ?>
                  <input type="date" class="row-date" value="<?=h($rd)?>" data-last-saved="<?=h($rd)?>" data-group-ids='<?=h(json_encode([$rid]))?>' onchange="saveGroupDateTime(this)" style="background:<?=h($rtc)?>!important">
                  <button type="button" class="color-select color-swatch-btn" title="เลือกสีวันที่ / เวลา" data-color-field="date_color" data-group-ids="<?=h(json_encode([$rid]))?>" data-value="<?=h($rtc)?>" onclick="toggleColorPalette(this)"><span class="color-dot" style="background:<?=h($rtc)?>"></span></button>
                <?php else: ?><span class="blank-note">วว/ดด/ปปปป</span><?php endif; ?>
              </div>
            <?php endforeach; ?>
            </div>
          </td>
          <td class="time-cell">
            <div class="stack">
            <?php foreach($grows as $r): $rid=(int)($r['id']??0); $rt=$r['inspection_time']??''; $rtc=$r['date_color']??'#ffffff'; if(!preg_match('/^#[0-9a-fA-F]{6}$/',$rtc))$rtc='#ffffff'; ?>
              <?php if($rid): ?><input class="cell-edit time-edit" style="background:<?=h($rtc)?>;border-color:#cbd5e1" value="<?=h($rt)?>" data-last-saved="<?=h($rt)?>" data-group-ids='<?=h(json_encode([$rid]))?>' onchange="saveGroupDateTimeFromTime(this)"><?php endif; ?>
            <?php endforeach; ?>
            </div>
          </td>
          <td class="area <?=$dateClass?>">
            <div class="stack">
            <?php foreach($grows as $r): $rid=(int)($r['id']??0); ?>
              <input class="cell-edit area-edit" value="<?=h($r['area_5s']??'')?>" data-id="<?=$rid?>" data-field="area_5s" oninput="updateUnitsForArea(this)" onchange="saveCell(this)" <?=!$rid?'disabled':''?>>
            <?php endforeach; ?>
            </div>
          </td>
          <td class="unit <?=$dateClass?>"><div class="stack unit-stack">
            <?php foreach($grows as $r): $rid=(int)($r['id']??0); ?><span id="units-<?=$rid?>"><?=unit_count($r['area_5s']??'')?></span><?php endforeach; ?>
          </div></td>
          <td>
            <div class="stack">
            <?php foreach($grows as $r): $rid=(int)($r['id']??0); $rs=trim((string)($r['status']??'รอตรวจ')); if(!in_array($rs,['รอตรวจ','เลื่อนการตรวจ','ตรวจบางส่วน','ตรวจแล้ว'],true))$rs='รอตรวจ'; $sc=$rs==='ตรวจแล้ว'?'done':($rs==='ตรวจบางส่วน'?'partial':($rs==='เลื่อนการตรวจ'?'postpone':'wait')); ?>
              <?php if($rid): ?>
                <select class="status-select <?=$sc?>" data-group-ids='<?=h(json_encode([$rid]))?>' onchange="saveGroupStatus(this)">
                  <option value="รอตรวจ" <?=$rs==='รอตรวจ'?'selected':''?>>ยังไม่ได้ตรวจ</option>
                  <option value="เลื่อนการตรวจ" <?=$rs==='เลื่อนการตรวจ'?'selected':''?>>เลื่อนการตรวจ</option>
                  <option value="ตรวจบางส่วน" <?=$rs==='ตรวจบางส่วน'?'selected':''?>>กำลังตรวจ</option>
                  <option value="ตรวจแล้ว" <?=$rs==='ตรวจแล้ว'?'selected':''?>>ตรวจแล้ว</option>
                </select>
              <?php endif; ?>
            <?php endforeach; ?>
            </div>
          </td>
          <td class="no-print action"><div class="stack action-stack">
            <?php foreach($grows as $r): $rid=(int)($r['id']??0); ?>
              <?php if($rid): ?>
                <span class="action-line">
                  <button type="button" class="iconbtn add" title="เพิ่มแผนกให้ทีมนี้" onclick="insertDepartment(<?=$rid?>)">+</button>
                  <button class="iconbtn save" title="บันทึกแผนก/พื้นที่" onclick="saveRow(<?=$rid?>,this)"><?=five_s_icon('check')?></button>
                  <button class="iconbtn edit" title="แก้ไขรายการ" onclick='editRow(<?=json_encode($r,JSON_UNESCAPED_UNICODE)?>)'><?=five_s_icon('edit')?></button>
                  <form method="post" action="Page/admin/schedule_manage.php?month=<?=$month?>&year=<?=$year?>&team=<?=$activeTeam?>" style="display:inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$rid?>"><button class="iconbtn trash" title="ลบรายการนี้" onclick="return confirm('ลบรายการนี้?')"><svg class="ico" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></button></form>
                </span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div class="v16-footer">
      <div class="sm-legend"><span class="lg done">ตรวจแล้ว</span><span class="lg partial">กำลังตรวจ</span><span class="lg wait">ยังไม่ได้ตรวจ</span><span class="lg postpone">เลื่อนการตรวจ</span></div>
      <div class="sm-total">รวมหน่วยพื้นที่ 5 ส. <b><?=$totalUnits?></b> หน่วย</div>
    </div>
  </div>
</div>

<div id="modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 no-print"><form method="post" action="Page/admin/schedule_manage.php?month=<?=$month?>&year=<?=$year?>&team=<?=$activeTeam?>" class="bg-white rounded-2xl p-6 w-[95%] max-w-2xl"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id"><h2 class="text-2xl font-black mb-4">เพิ่ม / แก้ไขข้อมูลทีมตรวจ</h2><div class="grid md:grid-cols-2 gap-3">
<div><label>ชุดทีม</label><select name="team" id="f_team" class="w-full border rounded-lg p-2"><option value="A">Team A</option><option value="B">Team B</option></select></div>
<div><label>ชื่อ-สกุล (ทีมตรวจ)</label><input name="auditor_name" id="f_name" class="w-full border rounded-lg p-2"></div>
<div><label>แผนก</label><select name="department" id="f_dept" class="w-full border rounded-lg p-2"><option value="">เลือกแผนก</option><?php foreach($departmentOptions as $depOpt): ?><option value="<?=h($depOpt)?>"><?=h($depOpt)?></option><?php endforeach; ?></select></div>
<div><label>วันที่ตรวจ</label><input name="inspection_date" id="f_date" type="date" class="w-full border rounded-lg p-2"></div>
<div><label>เวลา</label><input name="inspection_time" id="f_time" class="w-full border rounded-lg p-2" value="16:00 - 17:00"></div>
<div><label>หน่วยพื้นที่ 5 ส.</label><input name="area_5s" id="f_area" class="w-full border rounded-lg p-2"></div>
<div><label>จำนวนหน่วย (คำนวณอัตโนมัติ)</label><input id="f_units" type="number" readonly class="w-full border rounded-lg p-2 bg-slate-100 font-bold"><div class="multi-note">เช่น 12,13,17 = 3 หน่วย</div></div>
<div><label>หมายเหตุ</label><input name="note" id="f_note" class="w-full border rounded-lg p-2"></div>
</div><div class="flex justify-end gap-2 mt-5"><button type="button" onclick="closeModal()" class="px-4 py-2 border rounded-lg">ยกเลิก</button><button class="v16-btn orange">บันทึก</button></div></form></div>

<script src="assets/vendor/html2canvas.min.js"></script>
<script>
function postQuick(data){const fd=new FormData();Object.keys(data).forEach(k=>fd.append(k,typeof data[k]==='object'?JSON.stringify(data[k]):(data[k]??'')));return fetch('Page/admin/schedule_manage.php?month=<?=$month?>&year=<?=$year?>&team=<?=$activeTeam?>',{method:'POST',body:fd,credentials:'same-origin'});}
function addNewTeam(){postQuick({action:'insert_row',team:'<?=$activeTeam?>',after_id:0,clone_person:0,ajax:'1'}).then(r=>r.json()).then(x=>{if(x.ok)location.reload();else throw new Error();}).catch(()=>alert('เพิ่มทีมไม่สำเร็จ'));}
function insertDepartment(id){
  const btn=document.querySelector('.iconbtn.add[onclick*="insertDepartment('+id+')"]');
  if(btn)btn.disabled=true;
  postQuick({
    action:'insert_row',
    team:'<?=$activeTeam?>',
    after_id:id,
    clone_person:1,
    ajax:'1'
  })
  .then(async r=>{
    const raw=await r.text();
    let x;
    try{x=JSON.parse(raw)}catch(e){throw new Error(raw.replace(/<[^>]*>/g,' ').trim().slice(0,500)||'PHP ไม่ส่ง JSON')}
    if(!x.ok)throw new Error(x.message||'เพิ่มช่องไม่สำเร็จ');
    location.reload();
  })
  .catch(e=>{
    if(btn)btn.disabled=false;
    alert(e.message||'เพิ่มช่องไม่สำเร็จ');
  });
}
function saveGroupName(el){const ids=JSON.parse(el.dataset.groupIds||'[]');postQuick({action:'quick_group_name',ids:ids,value:el.value}).then(r=>r.json()).then(x=>{if(!x.ok)throw new Error();el.style.boxShadow='inset 0 0 0 2px #16a34a';setTimeout(()=>el.style.boxShadow='',800)}).catch(()=>alert('บันทึกชื่อทีมไม่สำเร็จ'));}
function applyStatusClass(el){el.classList.remove('wait','postpone','partial','done'); if(el.value==='ตรวจแล้ว')el.classList.add('done'); else if(el.value==='ตรวจบางส่วน')el.classList.add('partial'); else if(el.value==='เลื่อนการตรวจ')el.classList.add('postpone'); else el.classList.add('wait');}
function saveGroupStatus(el){const ids=JSON.parse(el.dataset.groupIds||'[]');postQuick({action:'quick_group_status',ids:ids,value:el.value}).then(r=>r.json()).then(x=>{if(!x.ok)throw new Error();applyStatusClass(el);el.style.boxShadow='inset 0 0 0 2px #16a34a';setTimeout(()=>el.style.boxShadow='',800)}).catch(()=>alert('อัปเดตสถานะไม่สำเร็จ'));}
let activeColorButton=null;
function getSmallColorPalette(){
 const old=document.getElementById('smallColorPalette');
 if(old)return old;
 const p=document.createElement('div');
 p.id='smallColorPalette';
 p.className='small-color-palette';
 ['#ffffff','#fff3cd','#d9f0fb','#dcf6d9','#f9dff0','#eadff7'].forEach(c=>{
   const b=document.createElement('button');
   b.type='button'; b.dataset.color=c; b.style.background=c; b.title=c;
   b.onclick=function(e){e.stopPropagation();applySmallColor(c);};
   p.appendChild(b);
 });
 document.body.appendChild(p);
 return p;
}
function toggleColorPalette(el){
 const p=getSmallColorPalette();
 if(activeColorButton===el && p.classList.contains('show')){
   p.classList.remove('show'); activeColorButton=null; return;
 }
 activeColorButton=el; p.classList.add('show');
 const r=el.getBoundingClientRect(), pw=190, ph=48;
 let left=r.left, top=r.bottom+5;
 if(left+pw>innerWidth-6)left=Math.max(6,innerWidth-pw-6);
 if(top+ph>innerHeight-6)top=Math.max(6,r.top-ph-5);
 p.style.left=left+'px'; p.style.top=top+'px';
}
async function applySmallColor(color){
 const el=activeColorButton, p=document.getElementById('smallColorPalette');
 if(!el)return;
 el.dataset.value=color;
 const dot=el.querySelector('.color-dot'); if(dot)dot.style.background=color;
 if(p){p.classList.remove('show');activeColorButton=null;}
 if(el.dataset.colorField==='date_color'){
   previewDateColor(el);
   const ids=JSON.parse(el.dataset.groupIds||'[]');
   try{
     const x=await postQuick({action:'quick_group_color',ids:ids,field:'date_color',value:color}).then(r=>r.json());
     if(!x.ok)throw new Error(x.message||'');
     el.style.boxShadow='inset 0 0 0 2px #16a34a';
     setTimeout(()=>el.style.boxShadow='',900);
   }catch(e){alert('บันทึกสีวันที่ไม่สำเร็จ');}
 }else if(el.dataset.colorField==='department_color'){
   const id=el.dataset.id, row=el.closest('.color-tools');
   const input=row?.querySelector('[data-field="department"]');
   if(input)input.style.setProperty('background',color,'important');
   try{
     const x=await postQuick({action:'quick_color',id:id,field:'department_color',value:color}).then(r=>r.json());
     if(!x.ok)throw new Error(x.message||'');
     el.style.boxShadow='inset 0 0 0 2px #16a34a';
     setTimeout(()=>el.style.boxShadow='',900);
   }catch(e){alert('บันทึกสีแผนกไม่สำเร็จ');}
 }
}
function previewDateColor(el){
 const value=el.dataset.value||'#ffffff', ids=el.dataset.groupIds||'[]';
 const tool=el.closest('.color-tools');
 if(tool)tool.style.setProperty('background',value,'important');
 document.querySelectorAll('.row-date,.time-edit').forEach(c=>{
   if(c.dataset.groupIds===ids)c.style.setProperty('background',value,'important');
 });
}
function quickDateColor(el){return applySmallColor(el.dataset.value||'#ffffff');}
function quickDeptColor(el){return applySmallColor(el.dataset.value||'#ffffff');}
document.addEventListener('click',function(e){
 const p=document.getElementById('smallColorPalette');
 if(p && activeColorButton && !p.contains(e.target) && !e.target.closest('.color-swatch-btn')){
   p.classList.remove('show');activeColorButton=null;
 }
});
async function saveGroupDateTime(dateEl){
 const ids=JSON.parse(dateEl.dataset.groupIds||'[]');
 const oldValue=dateEl.dataset.lastSaved??dateEl.defaultValue??'';
 const row=dateEl.closest('tr');
 const timeEl=Array.from(row?.querySelectorAll('.time-edit')||[]).find(el=>el.dataset.groupIds===dateEl.dataset.groupIds);
 dateEl.disabled=true;
 try{
   const response=await postQuick({action:'quick_group_date_time',ids:ids,inspection_date:dateEl.value,inspection_time:timeEl?timeEl.value:''});
   const x=await response.json();
   if(!response.ok || !x.ok)throw new Error(x.message||'บันทึกวันที่ไม่สำเร็จ');
   dateEl.dataset.lastSaved=dateEl.value;
   dateEl.style.boxShadow='inset 0 0 0 2px #16a34a';
   setTimeout(()=>dateEl.style.boxShadow='',1000);
 }catch(e){dateEl.value=oldValue;alert(e.message||'บันทึกวันที่ไม่สำเร็จ');}
 finally{dateEl.disabled=false;}
}
async function saveGroupDateTimeFromTime(timeEl){
 const ids=JSON.parse(timeEl.dataset.groupIds||'[]');
 const row=timeEl.closest('tr');
 const dateEl=Array.from(row?.querySelectorAll('.row-date')||[]).find(el=>el.dataset.groupIds===timeEl.dataset.groupIds);
 const oldValue=timeEl.dataset.lastSaved??timeEl.defaultValue??'';
 timeEl.disabled=true;
 try{
   const response=await postQuick({action:'quick_group_date_time',ids:ids,inspection_date:dateEl?dateEl.value:'',inspection_time:timeEl.value});
   const x=await response.json();
   if(!response.ok || !x.ok)throw new Error(x.message||'บันทึกเวลาไม่สำเร็จ');
   timeEl.dataset.lastSaved=timeEl.value;
   timeEl.style.boxShadow='inset 0 0 0 2px #16a34a';
   setTimeout(()=>timeEl.style.boxShadow='',1000);
 }catch(e){timeEl.value=oldValue;alert(e.message||'บันทึกเวลาไม่สำเร็จ');}
 finally{timeEl.disabled=false;}
}
function calcUnits(v){return String(v||'').split(',').map(x=>x.trim()).filter(Boolean).length;}
function updateUnitsForArea(el){const id=el?.dataset?.id;if(!id)return;const u=document.getElementById('units-'+id);if(u)u.textContent=calcUnits(el.value);}
function saveDepartmentAndRecalc(el){saveCell(el); const row=el.closest('tr'); const area=row?.querySelector('[data-field=\"area_5s\"]'); if(area) updateUnitsForArea(area);}
function saveCell(el){const id=el.dataset.id;if(!id)return;postQuick({action:'quick_field',id:id,field:el.dataset.field,value:el.value}).then(r=>r.json()).then(x=>{if(!x.ok)throw new Error();if(el.dataset.field==='area_5s'){const u=document.getElementById('units-'+id);if(u)u.textContent=x.units??0;}el.style.boxShadow='inset 0 0 0 2px #16a34a';setTimeout(()=>el.style.boxShadow='',800)}).catch(()=>alert('บันทึกข้อมูลไม่สำเร็จ'));}
function saveRow(id,btn){const dept=document.querySelector(`[data-id="${id}"][data-field="department"]`)?.value||'';const area=document.querySelector(`[data-id="${id}"][data-field="area_5s"]`)?.value||'';postQuick({action:'save_row',id:id,department:dept,area_5s:area,note:''}).then(r=>r.json()).then(x=>{if(!x.ok)throw new Error();const u=document.getElementById('units-'+id);if(u)u.textContent=x.units;btn.textContent='✓';setTimeout(()=>btn.textContent='💾',900)}).catch(()=>alert('บันทึกแถวไม่สำเร็จ'));}
async function saveAllRows(){
 const btn=[...document.querySelectorAll('button')].find(b=>b.textContent.includes('บันทึกทั้งหมด'));
 if(btn){btn.disabled=true;btn.dataset.oldText=btn.textContent;btn.textContent='กำลังบันทึก…';}
 const rowMap=new Map();
 const getRow=id=>{id=String(id||'');if(!id)return null;if(!rowMap.has(id))rowMap.set(id,{id:id,department:'',inspection_date:'',inspection_time:'',area_5s:'',status:'รอตรวจ'});return rowMap.get(id);};
 document.querySelectorAll('.department-select[data-id]').forEach(el=>{const r=getRow(el.dataset.id);if(r)r.department=el.value;});
 document.querySelectorAll('.row-date[data-group-ids]').forEach(el=>{try{JSON.parse(el.dataset.groupIds||'[]').forEach(id=>{const r=getRow(id);if(r)r.inspection_date=el.value;});}catch(e){}});
 document.querySelectorAll('.time-edit[data-group-ids]').forEach(el=>{try{JSON.parse(el.dataset.groupIds||'[]').forEach(id=>{const r=getRow(id);if(r)r.inspection_time=el.value;});}catch(e){}});
 document.querySelectorAll('.area-edit[data-id]').forEach(el=>{const r=getRow(el.dataset.id);if(r)r.area_5s=el.value;});
 document.querySelectorAll('.status-select[data-group-ids]').forEach(el=>{try{JSON.parse(el.dataset.groupIds||'[]').forEach(id=>{const r=getRow(id);if(r)r.status=el.value;});}catch(e){}});
 const names=[];document.querySelectorAll('.name-input[data-group-ids]').forEach(el=>{try{names.push({ids:JSON.parse(el.dataset.groupIds||'[]'),value:el.value});}catch(e){}});
 try{
   const response=await postQuick({action:'save_all_rows',rows:[...rowMap.values()],names:names});
   const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.message||'บันทึกไม่สำเร็จ');
   document.querySelectorAll('.row-date,.time-edit,.department-select,.area-edit,.status-select,.name-input').forEach(el=>{el.dataset.lastSaved=el.value;el.style.boxShadow='inset 0 0 0 2px #16a34a';});
   alert('บันทึกข้อมูลทั้งหมดเรียบร้อยแล้ว');
 }catch(e){alert('บันทึกไม่สำเร็จ: '+(e.message||'กรุณาลองอีกครั้ง'));}
 finally{if(btn){btn.disabled=false;btn.textContent=btn.dataset.oldText||'💾 บันทึกทั้งหมด';}}
}
document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('[data-field=\"area_5s\"]').forEach(updateUnitsForArea);document.querySelectorAll('[data-field=\"department_color\"]').forEach(el=>{const row=el.closest('.color-tools');const target=row?.querySelector('[data-field=\"department\"]');if(target)target.style.setProperty('background',el.value||'#ffffff','important');});});
function openModal(){document.getElementById('modal').classList.remove('hidden');document.getElementById('modal').classList.add('flex');document.getElementById('f_id').value='';['f_name','f_dept','f_date','f_time','f_area','f_units','f_note'].forEach(id=>document.getElementById(id).value='');document.getElementById('f_time').value='16:00 - 17:00';document.getElementById('f_team').value='<?=$activeTeam?>';}
function closeModal(){document.getElementById('modal').classList.add('hidden');document.getElementById('modal').classList.remove('flex')}
function editRow(r){openModal();[['f_id','id'],['f_team','team'],['f_name','auditor_name'],['f_dept','department'],['f_date','inspection_date'],['f_time','inspection_time'],['f_area','area_5s'],['f_units','unit_count'],['f_note','note']].forEach(([a,b])=>document.getElementById(a).value=r[b]??'');}

</script>
<div id="department-manager-modal" class="fixed inset-0 hidden items-center justify-center" style="z-index:9999;background:rgba(15,23,42,.55);padding:20px">
  <div style="background:#fff;border-radius:18px;width:min(560px,96vw);max-height:85vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <div style="padding:16px 20px;background:#ff6b00;color:#fff;font-weight:900;font-size:20px;display:flex;justify-content:space-between;align-items:center">
      <span>⚙️ จัดการแผนก</span><button type="button" onclick="closeDepartmentManager()" style="background:transparent;border:0;color:#fff;font-size:24px">×</button>
    </div>
    <div style="padding:18px">
      <div style="display:flex;gap:8px;margin-bottom:14px">
        <input id="new-department-name" type="text" placeholder="ชื่อแผนกใหม่" style="flex:1;border:1px solid #cbd5e1;border-radius:8px;padding:9px 11px;font-weight:700">
        <button type="button" onclick="addDepartmentMaster()" style="background:#16a34a;color:#fff;border:0;border-radius:8px;padding:9px 14px;font-weight:900">＋ เพิ่ม</button>
      </div>
      <div id="department-manager-list" style="display:grid;gap:7px"></div>
      <div style="margin-top:14px;color:#64748b;font-size:13px">การเพิ่ม/ลบจะบันทึกลงฐานข้อมูลทันที และใช้กับช่องเลือกแผนกทุกแถว</div>
    </div>
  </div>
</div>

<script>

function openDepartmentManager(){
  const m=document.getElementById('department-manager-modal'); if(!m)return;
  m.classList.remove('hidden'); m.classList.add('flex'); loadDepartmentManager();
}
function closeDepartmentManager(){
  const m=document.getElementById('department-manager-modal'); if(!m)return;
  m.classList.add('hidden'); m.classList.remove('flex');
}
async function loadDepartmentManager(){
  const box=document.getElementById('department-manager-list'); if(!box)return;
  box.innerHTML='<div style="padding:12px;text-align:center;color:#64748b">กำลังโหลด...</div>';
  try{
    const r=await fetch('Page/auditor/department_list.php?ts='+Date.now(),{cache:'no-store'}); const x=await r.json();
    if(!x.ok)throw new Error(x.error||'โหลดรายการไม่สำเร็จ');
    box.innerHTML='';
    (x.items||[]).forEach(d=>{
      const row=document.createElement('div');
      row.style.cssText='display:flex;align-items:center;justify-content:space-between;gap:8px;border:1px solid #e2e8f0;border-radius:8px;padding:7px 10px';
      const name=document.createElement('span'); name.textContent=d.name; name.style.fontWeight='800';
      const b=document.createElement('button'); b.type='button'; b.textContent='ลบ'; b.style.cssText='background:#ef4444;color:#fff;border:0;border-radius:7px;padding:5px 10px;font-weight:800';
      b.onclick=()=>deleteDepartmentMaster(d.id,d.name);
      row.append(name,b); box.append(row);
    });
  }catch(e){box.innerHTML='<div style="color:#dc2626;padding:10px">'+(e.message||e)+'</div>';}
}
async function addDepartmentMaster(){
  const el=document.getElementById('new-department-name'); const name=(el.value||'').trim();
  if(!name){alert('กรุณากรอกชื่อแผนก');return;}
  try{
    const fd=new FormData(); fd.append('action','department_add'); fd.append('name',name);
    const x=await fetch('Page/admin/schedule_manage.php?month=<?=$month?>&year=<?=$year?>&team=<?=$activeTeam?>',{method:'POST',body:fd}).then(r=>r.json());
    if(!x.ok)throw new Error(x.message||'เพิ่มแผนกไม่สำเร็จ');
    el.value=''; await loadDepartmentManager(); await refreshDepartmentSelects();
  }catch(e){alert(e.message||e);}
}
async function deleteDepartmentMaster(id,name){
  if(!confirm('ลบแผนก "'+name+'" ออกจากรายการเลือกหรือไม่?\\nข้อมูลเก่าที่บันทึกไว้จะไม่ถูกลบ'))return;
  try{
    const fd=new FormData(); fd.append('action','department_delete'); fd.append('id',id);
    const x=await fetch('Page/admin/schedule_manage.php?month=<?=$month?>&year=<?=$year?>&team=<?=$activeTeam?>',{method:'POST',body:fd}).then(r=>r.json());
    if(!x.ok)throw new Error('ลบแผนกไม่สำเร็จ');
    await loadDepartmentManager(); await refreshDepartmentSelects();
  }catch(e){alert(e.message||e);}
}
async function refreshDepartmentSelects(){
  const x=await fetch('Page/auditor/department_list.php?ts='+Date.now(),{cache:'no-store'}).then(r=>r.json());
  if(!x.ok)return;
  document.querySelectorAll('select.department-select').forEach(sel=>{
    const current=sel.value;
    sel.innerHTML='<option value="">เลือกแผนก</option>';
    (x.departments||[]).forEach(d=>{
      const o=document.createElement('option'); o.value=d; o.textContent=d; if(d===current)o.selected=true; sel.appendChild(o);
    });
  });
}
</script>
<script>

/* V70: ตัวเลขสรุปด้านบนอัปเดตทันทีเมื่อเลือกแผนก / วันที่ / สถานะ (นับเฉพาะแถวที่มีแผนก) */
function recalcPlanStats(){
  let n=0,dated=0,st={'ตรวจแล้ว':0,'ตรวจบางส่วน':0,'รอตรวจ':0,'เลื่อนการตรวจ':0};
  const idsOf=el=>{try{return JSON.parse(el.dataset.groupIds||'[]').map(Number);}catch(e){return[];}};
  const dateBy={},statBy={};
  document.querySelectorAll('input.row-date').forEach(el=>idsOf(el).forEach(id=>dateBy[id]=el.value));
  document.querySelectorAll('select.status-select').forEach(el=>idsOf(el).forEach(id=>statBy[id]=el.value));
  document.querySelectorAll('select.department-select[data-id]').forEach(sel=>{
    const id=+sel.dataset.id; if(!id||!sel.value) return; n++;
    if((dateBy[id]||'').trim()) dated++;
    const v=statBy[id]||'รอตรวจ'; if(v in st) st[v]++; else st['รอตรวจ']++;
  });
  const set=(id,v)=>{const e=document.getElementById(id);if(e)e.textContent=v;};
  set('stDated',dated);set('stDatedOf','/ '+n+' แผนก');set('stDone',st['ตรวจแล้ว']);set('stPart',st['ตรวจบางส่วน']);set('stWait',st['รอตรวจ']);
  const pct=n?Math.round(st['ตรวจแล้ว']/n*100):0;set('stPct',pct+'%');const bar=document.getElementById('stBar');if(bar)bar.style.width=pct+'%';
}
document.addEventListener('change',e=>{if(e.target.matches&&e.target.matches('input.row-date,select.status-select,select.department-select'))setTimeout(recalcPlanStats,50);});
document.addEventListener('input',e=>{if(e.target.matches&&e.target.matches('input.row-date'))setTimeout(recalcPlanStats,50);});
/* V67: รูปส่ง LINE ใช้หน้าพิมพ์แผนตรวจ (Page/public/schedule_print.php) เป็นมาสเตอร์ — ได้ภาพเดียวกับที่พิมพ์ทุกประการ */
function copyLineImage(){FiveSCopy.copyFrom(buildPrintPoster,'แผนตรวจ5ส-<?=$months[$month]?>-<?=$year?>-ทีม<?=h($activeTeam)?>.png');}
async function buildPrintPoster(){
  const f=document.createElement('iframe');
  f.setAttribute('aria-hidden','true');
  f.style.cssText='position:fixed;left:-30000px;top:0;width:1800px;height:1400px;border:0;visibility:hidden';
  f.src='Page/public/schedule_print.php?month=<?=$month?>&year=<?=$year?>&team=<?=h($activeTeam)?>&_='+Date.now();
  document.body.appendChild(f);
  try{
    await new Promise((res,rej)=>{f.onload=res;setTimeout(()=>rej(new Error('โหลดหน้าพิมพ์ไม่สำเร็จ')),20000);});
    const w=f.contentWindow,d=w.document;
    if(d.fonts&&d.fonts.ready){try{await d.fonts.ready;}catch(e){}}
    await new Promise(r=>setTimeout(r,250));
    if(typeof w.buildPosterCanvas!=='function') throw new Error('ไม่พบหน้าพิมพ์แผนตรวจ');
    return await w.buildPosterCanvas();
  }finally{setTimeout(()=>f.remove(),200);}
}
async function buildLineCanvas(){
 const btn=document.querySelector('[onclick="copyLineImage()"]');
 const oldText=btn?btn.innerHTML:'';
 let clone=null;
 try{
  if(typeof html2canvas==='undefined') throw new Error('ยังโหลดตัวสร้างรูปไม่สำเร็จ');
  if(btn){btn.disabled=true;btn.innerHTML='⏳ กำลังจัดรูปสำหรับ LINE...';}
  const source=document.getElementById('poster');
  if(!source) throw new Error('ไม่พบตารางแผนตรวจ');
  // เก็บค่าจากหน้าจอจริงก่อน clone เพราะ cloneNode() ของ select/input
  // อาจย้อนกลับไปใช้ค่า option/attribute เดิมแทนค่าที่ผู้ใช้เลือกไว้ล่าสุด
  const sourceControls = Array.from(source.querySelectorAll('input,select,textarea'));
  const sourceStatusValues = Array.from(source.querySelectorAll('.status-select')).map(el => el.value || 'รอตรวจ');

  clone=source.cloneNode(true); clone.id='line-poster-export';

  // เพิ่มหัวภาพสำหรับ LINE ให้เห็นเดือน/ปีและทีมที่ตรวจอย่างชัดเจน
  const lineHeading=document.createElement('div');
  lineHeading.id='line-poster-month-team-heading';
  lineHeading.style.cssText='box-sizing:border-box;width:100%;padding:18px 22px 16px;margin:0;background:linear-gradient(120deg,#ff7a00,#ff4d00 58%,#ff9800);color:#fff;text-align:center;font-family:Tahoma,Arial,sans-serif;border-bottom:4px solid #ffd166;';
  lineHeading.innerHTML=
    '<div style="font-size:28px;font-weight:900;line-height:1.35">แผนการตรวจ 5 ส. ประจำเดือน <?=h($months[$month]??'')?> พ.ศ. <?=h((string)$year)?></div>'+
    '<div style="font-size:22px;font-weight:900;line-height:1.5;margin-top:5px">ทีมตรวจประจำเดือน: ทีม <?=$activeTeam?></div>';
  clone.insertBefore(lineHeading,clone.firstChild);


  // คืนค่าที่กรอก/เลือกจริงจากหน้าจอให้ clone ก่อนสร้างรูป
  // โดยจับคู่ตามลำดับ DOM เดียวกัน
  const cloneControls = Array.from(clone.querySelectorAll('input,select,textarea'));
  sourceControls.forEach((src,i)=>{
    const dst=cloneControls[i];
    if(!dst) return;
    if(src.tagName==='SELECT'){
      dst.value=src.value;
      const opt=Array.from(dst.options||[]).find(o=>o.value===src.value);
      if(opt) opt.selected=true;
    }else{
      dst.value=src.value;
    }
  });

  Array.from(clone.querySelectorAll('.status-select')).forEach((el,i)=>{
    if(sourceStatusValues[i] !== undefined){
      el.value = sourceStatusValues[i];
      el.setAttribute('data-export-status', sourceStatusValues[i]);
    }
  });

  // ให้ภาพ LINE ใช้สีแผนกที่บันทึกไว้จริงจากปุ่มเลือกสีของแต่ละแถว
  // ต้องทำก่อนลบปุ่มเลือกสีออกจาก clone
  clone.querySelectorAll('.department-select').forEach(el=>{
    const row=el.closest('tr');
    const swatch=row ? row.querySelector('[data-color-field="department_color"]') : null;
    const savedColor=swatch ? (swatch.getAttribute('data-value')||'') : '';
    if(/^#[0-9a-fA-F]{6}$/.test(savedColor)){
      el.style.setProperty('background-color',savedColor,'important');
      el.style.setProperty('background-image','none','important');
    }
  });
  // ภาพส่ง LINE แสดงชื่อเดือน/ปีและทีมที่กำลังตรวจเพียงจุดเดียว
  clone.querySelectorAll('.cycle-note,.only-print.team-pill').forEach(el=>el.remove());

  // ตัดเฉพาะส่วนควบคุมออกก่อนแปลง input/select เป็นข้อความ
  // เก็บชื่อแผนกและสีที่ตั้งไว้แล้วใน element สำหรับส่งออก
  clone.querySelectorAll('.no-print,.action,.color-select,.color-save,.color-swatch,.color-hex').forEach(el=>el.remove());

  // แปลงสถานะก่อน เพื่อรักษาค่าที่เลือกจริงจากหน้าจอ
  clone.querySelectorAll('.status-select').forEach(el=>{
    const value=el.value||'รอตรวจ';
    const span=document.createElement('span');
    const bg=value==='ตรวจแล้ว'?'#dcfce7':(value==='เลื่อนการตรวจ'?'#ffe4e6':'#ffedd5');
    const fg=value==='ตรวจแล้ว'?'#166534':(value==='เลื่อนการตรวจ'?'#be123c':'#9a3412');
    span.textContent=value;
    span.style.cssText=`display:flex;align-items:center;justify-content:center;min-height:30px;padding:5px 8px;background:${bg};border:2px solid ${fg};border-radius:999px;color:${fg};font-weight:1000;font-size:15px;box-sizing:border-box;width:100%;`;
    el.replaceWith(span);
  });

  // แปลงช่องข้อมูลเป็นข้อความ โดยคงสีพื้นหลังจริงของช่องไว้
  clone.querySelectorAll('input,select,textarea').forEach(el=>{
    const cs=getComputedStyle(el);
    let text='';
    if(el.tagName==='SELECT') text=el.value||'';
    else text=el.value||el.placeholder||'';
    if(el.type==='date' && text){ const m=text.match(/^(\d{4})-(\d{2})-(\d{2})$/); if(m) text=`${m[3]}/${m[2]}/${m[1]}`; }
    const span=document.createElement('span');
    span.textContent=text;
    span.style.cssText=`display:flex;align-items:center;justify-content:center;min-height:30px;padding:5px 8px;background:${cs.backgroundColor};border:1px solid #d7dee8;border-radius:6px;color:#173b70;font-weight:900;font-size:15px;line-height:1.25;box-sizing:border-box;width:100%;`;
    el.replaceWith(span);
  });

  clone.style.cssText='position:absolute;left:-100000px;top:0;width:1400px;max-width:none;background:#fff;border:0;border-radius:16px;box-shadow:none;padding:0;margin:0;';
  clone.querySelector('.schedule-top')?.style.setProperty('padding','16px 22px');
  clone.querySelector('.schedule-top .left')?.style.setProperty('font-size','26px');
  clone.querySelector('.schedule-top .team-pill')?.style.setProperty('font-size','30px');
  clone.querySelector('.schedule-top .team-pill')?.style.setProperty('padding','11px 30px');
  clone.querySelectorAll('.schedule-top .right').forEach(e=>e.style.fontSize='15px');
  clone.querySelectorAll('.v16table th').forEach(e=>{e.style.fontSize='15px';e.style.padding='10px 7px';});
  clone.querySelectorAll('.v16table td').forEach(e=>{e.style.fontSize='14px';e.style.padding='7px 6px';});
  clone.querySelectorAll('.v16table .name-input').forEach(e=>e.style.fontSize='14px');
  clone.querySelectorAll('.stack').forEach(e=>e.style.gap='4px');
  document.body.appendChild(clone);
  await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));
  const canvas=await FiveSCopy.render(clone,{scale:2,backgroundColor:'#fff',useCORS:true,logging:false,windowWidth:1440,scrollX:0,scrollY:0});
  clone.remove(); clone=null;
  return canvas;
 }catch(e){
  if(clone) clone.remove();
  console.error(e);
  throw e;
 }finally{ if(btn){btn.disabled=false;btn.innerHTML=oldText;} }
}
</script>

<script>
/* ตารางแผนตรวจ: ปรับความกว้างคอลัมน์/ความสูงแถวได้ด้วยการลาก และจำค่าบนเครื่องนี้ */
(function(){
 const table=document.querySelector('.v16table');
 if(!table) return;
 const storageKey='5s_schedule_table_sizes_v1';
 let saved={};
 try{ saved=JSON.parse(localStorage.getItem(storageKey)||'{}')||{}; }catch(e){}
 const cols=table.querySelectorAll('colgroup col');
 const ths=table.querySelectorAll('thead th');
 function applyColWidths(){
   if(saved.cols && cols.length){
     saved.cols.forEach((w,i)=>{if(cols[i] && Number(w)>2) cols[i].style.width=w+'%';});
   }
 }
 applyColWidths();
 ths.forEach((th,i)=>{
   if(i>=cols.length-1) return;
   const grip=document.createElement('span');
   grip.className='col-resize-grip'; grip.title='ลากเพื่อปรับความกว้างคอลัมน์';
   th.appendChild(grip);
   grip.addEventListener('pointerdown',function(e){
     if(e.button!==0) return;
     e.preventDefault(); e.stopPropagation();
     const startX=e.clientX, startW=th.getBoundingClientRect().width;
     const tableW=table.getBoundingClientRect().width;
     grip.classList.add('dragging'); grip.setPointerCapture(e.pointerId);
     function move(ev){
       const next=Math.max(45,startW+(ev.clientX-startX));
       const pct=Math.max(3,Math.min(60,next/tableW*100));
       cols[i].style.width=pct+'%';
     }
     function end(){
       grip.classList.remove('dragging');
       saved.cols=Array.from(cols).map(c=>parseFloat(c.style.width)||0);
       try{localStorage.setItem(storageKey,JSON.stringify(saved));}catch(e){}
       grip.removeEventListener('pointermove',move); grip.removeEventListener('pointerup',end); grip.removeEventListener('pointercancel',end);
     }
     grip.addEventListener('pointermove',move); grip.addEventListener('pointerup',end); grip.addEventListener('pointercancel',end);
   });
 });
 const rows=table.querySelectorAll('tbody tr');
 rows.forEach((tr,i)=>{
   const grip=document.createElement('span');
   grip.className='row-resize-grip'; grip.title='ลากเพื่อปรับความสูงแถว';
   tr.style.position='relative'; tr.appendChild(grip);
   const heights=saved.rows||[];
   if(heights[i] && Number(heights[i])>20) tr.style.height=heights[i]+'px';
   grip.addEventListener('pointerdown',function(e){
     if(e.button!==0)return;
     e.preventDefault(); e.stopPropagation();
     const startY=e.clientY, startH=tr.getBoundingClientRect().height;
     grip.classList.add('dragging'); grip.setPointerCapture(e.pointerId);
     function move(ev){tr.style.height=Math.max(34,startH+(ev.clientY-startY))+'px';}
     function end(){
       grip.classList.remove('dragging');
       saved.rows=Array.from(rows).map(r=>Math.round(r.getBoundingClientRect().height));
       try{localStorage.setItem(storageKey,JSON.stringify(saved));}catch(e){}
       grip.removeEventListener('pointermove',move);grip.removeEventListener('pointerup',end);grip.removeEventListener('pointercancel',end);
     }
     grip.addEventListener('pointermove',move);grip.addEventListener('pointerup',end);grip.addEventListener('pointercancel',end);
   });
 });
})();
</script>

<?php require FIVE_S_ROOT.'/includes/footer.php';?>
