<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
header('Content-Type: application/json; charset=utf-8');

try{
    if($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception('Method not allowed');
    $payload=json_decode($_POST['payload']??'',true,512,JSON_THROW_ON_ERROR);
    $kind=($payload['kind']??'production')==='office'?'office':'production';
    $date=trim($payload['audit_date']??'');
    $section=trim($payload['section']??'');
    $auditor=trim($payload['auditor']??'');
    $period=trim($payload['period']??'');
    $unitsRaw=$payload['units']??[];
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new Exception('วันที่ตรวจไม่ถูกต้อง');
    if($section==='') throw new Exception('กรุณาเลือกแผนก');
    if($auditor==='') throw new Exception('กรุณากรอกชื่อผู้ตรวจ 1 คน');
    $units=[];
    foreach($unitsRaw as $u){$u=trim((string)$u);if($u!=='')$units[]=$u;}
    $units=array_values(array_unique($units));
    if(!$units) throw new Exception('กรุณากรอกพื้นที่/หน่วยอย่างน้อย 1 รายการ');
    if($kind==='production' && count($units)>6) throw new Exception('แบบ Production มีช่องคะแนนจริง 6 พื้นที่/หน่วยต่อใบ');
    $dateError=five_s_audit_date_error($date);
    if($dateError!=='') throw new Exception($dateError);

    /* Accept only the file types offered by the import page, checked by content as well as by name. */
    $uploadExt='';
    if(isset($_FILES['file']) && $_FILES['file']['error']===UPLOAD_ERR_OK){
        $allowedUpload=['pdf'=>['application/pdf'],'png'=>['image/png'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg']];
        $uploadExt=strtolower(pathinfo((string)$_FILES['file']['name'],PATHINFO_EXTENSION));
        $uploadMime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['file']['tmp_name']);
        if(!isset($allowedUpload[$uploadExt]) || !in_array($uploadMime,$allowedUpload[$uploadExt],true)) throw new Exception('รับเฉพาะไฟล์ PDF, PNG หรือ JPG เท่านั้น');
    }

    $p=db();five_s_begin_write($p);

    /* เก็บประวัติไฟล์ต้นฉบับ */
    $st=$p->prepare("INSERT INTO unit_import_audits(kind,audit_date,department,auditor,period,filename,updated_at)
                     VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP)");
    $filename=basename((string)($payload['filename']??''));
    $st->execute([$kind,$date,$section,$auditor,$period,$filename]);
    $importId=(int)$p->lastInsertId();

    $ui=$p->prepare("INSERT INTO unit_import_units(audit_id,slot,unit_no) VALUES(?,?,?)");
    foreach($units as $i=>$u)$ui->execute([$importId,$i,$u]);

    $criteriaTable=$kind==='office'?'office_criteria':'criteria';
    $scoreTable=$kind==='office'?'office_scores':'scores';

    $cq=$p->query("SELECT id,item_no,max_score FROM {$criteriaTable} WHERE active=1 ORDER BY item_no");
    $criteria=$cq->fetchAll();
    $byItem=[];foreach($criteria as $c)$byItem[(int)$c['id']]=$c;

    $rows=$payload['rows']??[];
    $hist=$p->prepare("INSERT INTO unit_import_scores(audit_id,criterion_id,unit_slot,score,na,note) VALUES(?,?,?,?,?,?)");

    foreach($units as $slot=>$unitNo){
        $areaId=find_or_create_area($p,$kind==='office'?'Office':'Production',$section,$unitNo);
        $area=$p->prepare("SELECT department,group_name FROM areas WHERE id=?");$area->execute([$areaId]);$ar=$area->fetch();
        /* One audit per area per date, the same rule as the score pages. Saved scores are never replaced by an import. */
        $aq=$p->prepare("SELECT id FROM audits WHERE area_id=? AND audit_date=? ORDER BY id ASC LIMIT 1");$aq->execute([$areaId,$date]);$auditId=(int)$aq->fetchColumn();
        if($auditId){
            $has=$p->prepare("SELECT COUNT(*) FROM {$scoreTable} WHERE audit_id=?");$has->execute([$auditId]);
            if((int)$has->fetchColumn()>0) throw new Exception("หน่วย {$unitNo} วันที่ {$date} มีคะแนนบันทึกไว้แล้ว ระบบจึงไม่นำเข้าทับ — ใบนี้ยังไม่ถูกบันทึก");
            $p->prepare("UPDATE audits SET auditor=?,period=?,overall_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$auditor,$period,'นำเข้าจากไฟล์: '.$filename,$auditId]);
        }else{
            $q=$p->prepare("INSERT INTO audits(area_id,audit_date,auditor,period,overall_note,department,group_name,updated_at) VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP)");
            $q->execute([$areaId,$date,$auditor,$period,'นำเข้าจากไฟล์: '.$filename,$ar['department']??($kind==='office'?'Office':'Production'),$ar['group_name']??$section]);
            $auditId=(int)$p->lastInsertId();
        }
        $up=$p->prepare("INSERT INTO {$scoreTable}(audit_id,criterion_id,score1,score2,score3,note) VALUES(?,?,?,?,?,?)");

        foreach($rows as $row){
            $cid=(int)($row['criterion_id']??0);
            if(!isset($byItem[$cid])) continue;
            $scores=$row['scores']??[];
            $raw=$scores[$slot]??null;
            $na=($raw==='—'||$raw==='-'||$raw==='–'||$raw==='N/A'||$raw==='NA');
            if($na || $raw===null || $raw===''){
                $score=null;
            }else{
                if(!is_numeric($raw)) continue;
                $score=(float)$raw;
                $max=(float)$byItem[$cid]['max_score'];
                if($score<0||$score>$max) throw new Exception("ข้อ {$byItem[$cid]['item_no']} หน่วย {$unitNo}: คะแนนเกินคะแนนเต็ม {$max}");
            }
            if($na||$score!==null){
                $hist->execute([$importId,$cid,$slot,$score,$na?1:0,'']);
                $up->execute([$auditId,$cid,$score,null,null,$na?'N/A: ไม่ได้ตรวจ':'']);
            }
        }
    }

    if($uploadExt!==''){
        $dir=FIVE_S_ROOT.'/uploads';
        if(!is_dir($dir))mkdir($dir,0775,true);
        $name=date('Ymd_His').'_'.bin2hex(random_bytes(6)).'.'.$uploadExt;
        if(!move_uploaded_file($_FILES['file']['tmp_name'],$dir.'/'.$name)) throw new Exception('ไม่สามารถเก็บไฟล์ต้นฉบับได้');
    }
    $p->commit();
    require_once FIVE_S_ROOT.'/includes/auto_schedule_status.php';
    try { sync_monthly_audit_plan_statuses($p,(int)date('n',strtotime($date)),(int)date('Y',strtotime($date))+543); } catch(Throwable $syncError) { /* import remains successful if plan status synchronization needs attention */ }
    echo json_encode(['ok'=>true,'url'=>'Page/public/unit_scores.php?id='.$importId],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
    if(isset($p)&&$p instanceof PDO&&$p->inTransaction())$p->rollBack();
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
