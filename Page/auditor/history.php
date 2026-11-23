<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
$title='ผู้ตรวจ / ประวัติ';
$p=db();
$year=(int)($_GET['year']??$_POST['history_year']??date('Y'));
if($year<2000||$year>2100)$year=(int)date('Y');

$months=['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$msg=''; $err='';

/* Save one editable monthly auditor record. Team A/B is intentionally NOT a field here. */
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    $action=$_POST['action']??'';
    if($action==='snapshot'){
      /* = COUNT(*) + GROUP_CONCAT(DISTINCT ...) GROUP BY year,month,name (first-seen order, like SQLite) */
      $q=five_s_group_concat_rows($p->query("SELECT year,month,TRIM(auditor_name) auditor_name,
          1 inspection_count,inspection_date inspection_dates,department departments
          FROM audit_plans WHERE active=1 AND TRIM(auditor_name)<>'' ORDER BY id"),
          ['year','month','auditor_name'],['inspection_count'],['inspection_dates','departments']);
      $up=$p->prepare("INSERT INTO auditor_monthly_history_unified(history_year,history_month,auditor_name,inspection_count,inspection_dates,departments,updated_at)
          VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP)
          ON DUPLICATE KEY UPDATE
          inspection_count=VALUES(inspection_count),inspection_dates=VALUES(inspection_dates),
          departments=VALUES(departments),updated_at=CURRENT_TIMESTAMP");
      foreach($q as $r)$up->execute([(int)$r['year'],(int)$r['month'],(string)$r['auditor_name'],(int)$r['inspection_count'],(string)($r['inspection_dates']??''),(string)($r['departments']??'')]);
      header('Location: '.FIVE_S_BASE.'Page/auditor/history.php?year='.$year.'&saved=1'); exit;
    }
    if($action==='save_row'){
      $id=(int)($_POST['id']??0); $m=(int)($_POST['history_month']??0); $name=trim((string)($_POST['auditor_name']??''));
      $count=max(0,(int)($_POST['inspection_count']??0)); $dates=trim((string)($_POST['inspection_dates']??'')); $deps=trim((string)($_POST['departments']??''));
      if($m<1||$m>12||$name==='') throw new RuntimeException('กรุณาเลือกชื่อผู้ตรวจและเดือนให้ครบ');
      $p->beginTransaction();
      try {
        if($id){
          $q=$p->prepare('SELECT id FROM auditor_monthly_history_unified WHERE id=? AND history_year=?');
          $q->execute([$id,$year]);
          if($q->fetchColumn()===false) throw new RuntimeException('ไม่พบข้อมูลที่ต้องการแก้ไข');
          $q=$p->prepare('SELECT id FROM auditor_monthly_history_unified WHERE history_year=? AND history_month=? AND auditor_name=? AND id<>?');
          $q->execute([$year,$m,$name,$id]); if($q->fetchColumn()!==false) throw new RuntimeException('ผู้ตรวจคนนี้มีข้อมูลในเดือนนี้แล้ว');
          $st=$p->prepare('UPDATE auditor_monthly_history_unified SET history_month=?,auditor_name=?,inspection_count=?,inspection_dates=?,departments=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND history_year=?');
          $st->execute([$m,$name,$count,$dates,$deps,$id,$year]);
          if($st->rowCount()===0) throw new RuntimeException('ไม่สามารถบันทึกการแก้ไขได้');
        }else{
          $st=$p->prepare('INSERT INTO auditor_monthly_history_unified(history_year,history_month,auditor_name,inspection_count,inspection_dates,departments,updated_at) VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP)');
          $st->execute([$year,$m,$name,$count,$dates,$deps]);
        }
        $p->commit();
      } catch(Throwable $e) {
        if($p->inTransaction()) $p->rollBack();
        throw $e;
      }
      header('Location: '.FIVE_S_BASE.'Page/auditor/history.php?year='.rawurlencode((string)$year).'&saved=1'); exit;
    }
    if($action==='update_audit_date'){
      $isAjax = (($_POST['ajax']??'') === '1');
      $id=(int)($_POST['audit_id']??0);
      $newDate=trim((string)($_POST['new_audit_date']??''));
      if(!$id) throw new RuntimeException('ไม่พบรหัสรายการตรวจ');
      if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$newDate)) throw new RuntimeException('วันที่ไม่ถูกต้อง');
      $dt=DateTime::createFromFormat('!Y-m-d',$newDate);
      if(!$dt || $dt->format('Y-m-d')!==$newDate) throw new RuntimeException('วันที่ไม่ถูกต้อง');

      try{

        $st=$p->prepare("SELECT id,area_id,auditor,audit_date FROM audits WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $cur=$st->fetch(PDO::FETCH_ASSOC);
        if(!$cur) throw new RuntimeException('ไม่พบรายการตรวจที่ต้องการแก้ไข');

        if((string)$cur['audit_date']===$newDate){
          if($isAjax){
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>true,'message'=>'ไม่มีการเปลี่ยนแปลง'],JSON_UNESCAPED_UNICODE);
            exit;
          }
          header('Location: '.FIVE_S_BASE.'Page/auditor/history.php?year='.rawurlencode((string)$year).'&saved=1'); exit;
        }

        /* Respect UNIQUE(area_id,audit_date,auditor) before UPDATE. */
        $dup=$p->prepare("SELECT id FROM audits WHERE area_id=? AND audit_date=? AND auditor=? AND id<>? LIMIT 1");
        $dup->execute([(int)$cur['area_id'],$newDate,(string)$cur['auditor'],$id]);
        $duplicateId=$dup->fetchColumn();
        if($duplicateId!==false) throw new RuntimeException('วันที่นี้มีรายการตรวจของผู้ตรวจคนเดิมในพื้นที่นี้อยู่แล้ว');

        /* Update the real audit record. No rowCount() check is used. */
        $up=$p->prepare("UPDATE audits SET audit_date=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
        $ok=$up->execute([$newDate,$id]);
        if(!$ok) throw new RuntimeException('ฐานข้อมูลไม่สามารถบันทึกวันที่ได้');

        $check=$p->prepare("SELECT audit_date FROM audits WHERE id=? LIMIT 1");
        $check->execute([$id]);
        $savedDate=$check->fetchColumn();
        if($savedDate===false || (string)$savedDate!==$newDate){
          throw new RuntimeException('ตรวจสอบหลังบันทึกแล้ว วันที่ยังไม่เปลี่ยน');
        }
        five_s_sync_after_save($p,(string)$cur['audit_date'],$newDate);

        if($isAjax){
          header('Content-Type: application/json; charset=utf-8');
          echo json_encode(['ok'=>true,'message'=>'บันทึกวันที่เรียบร้อย','audit_id'=>$id,'audit_date'=>$newDate],JSON_UNESCAPED_UNICODE);
          exit;
        }
        $redirectYear=(int)substr($newDate,0,4);
        header('Location: '.FIVE_S_BASE.'Page/auditor/history.php?year='.rawurlencode((string)$redirectYear).'&saved=1'); exit;
      }catch(Throwable $e){
        if($isAjax){
          http_response_code(400);
          header('Content-Type: application/json; charset=utf-8');
          echo json_encode(['ok'=>false,'message'=>'บันทึกวันที่ไม่สำเร็จ: '.$e->getMessage()],JSON_UNESCAPED_UNICODE);
          exit;
        }
        $err='บันทึกวันที่ไม่สำเร็จ: '.$e->getMessage();
      }
    }
    if($action==='delete_row'){
      $id=(int)($_POST['id']??0); if($id)$p->prepare('DELETE FROM auditor_monthly_history_unified WHERE id=?')->execute([$id]);
      header('Location: '.FIVE_S_BASE.'Page/auditor/history.php?year='.$year.'&saved=1'); exit;
    }
  }catch(Throwable $e){$err=$e->getMessage();}
}

/* Names are selectable and can be edited without Team A/B separation. */
$names=[];
try{
  $q=$p->query("SELECT DISTINCT TRIM(auditor_name) name FROM audit_plans WHERE TRIM(auditor_name)<>'' ORDER BY name COLLATE utf8mb4_general_ci");
  $names=$q->fetchAll(PDO::FETCH_COLUMN);
  $q=$p->query("SELECT DISTINCT TRIM(auditor_name) name FROM auditor_monthly_history_unified WHERE TRIM(auditor_name)<>'' ORDER BY name COLLATE utf8mb4_general_ci");
  $names=array_values(array_unique(array_merge($names,$q->fetchAll(PDO::FETCH_COLUMN)))); sort($names,SORT_NATURAL|SORT_FLAG_CASE);
}catch(Throwable $e){}

$monthly=[];
$q=$p->prepare("SELECT * FROM auditor_monthly_history_unified WHERE history_year=? ORDER BY history_month,auditor_name COLLATE utf8mb4_general_ci");
$q->execute([$year]); foreach($q as $r)$monthly[(int)$r['history_month']][]=$r;

$rows=$p->query("SELECT au.*,a.area_name,
 CASE WHEN au.department='Office' THEN (SELECT COUNT(*) FROM office_scores os WHERE os.audit_id=au.id) ELSE (SELECT COUNT(*) FROM scores s WHERE s.audit_id=au.id) END items
 FROM audits au JOIN areas a ON a.id=au.area_id ORDER BY au.audit_date DESC,a.area_name,au.id DESC")->fetchAll();
require FIVE_S_ROOT.'/includes/header.php';
?>
<style>
.hist-hero{background:linear-gradient(120deg,#ff6b00,#ff4d00 55%,#ff9a00);color:#fff;border-radius:18px;padding:20px 24px;display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:18px}.hist-hero h1{margin:0;font-size:28px;font-weight:950}.hist-hero span{opacity:.9}.hist-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.hist-actions select,.hist-actions button{border:0;border-radius:10px;padding:9px 13px;font-weight:900}.hist-actions select{color:#173b70}.btn{border:0;border-radius:9px;padding:7px 10px;font-weight:900;cursor:pointer}.btn-blue{background:#1677e8;color:#fff}.btn-green{background:#16a34a;color:#fff}.btn-red{background:#ef4444;color:#fff}.btn-gray{background:#e2e8f0;color:#334155}.month-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.month-card{background:#fff;border:1px solid #dbe3ef;border-radius:16px;overflow:hidden;box-shadow:0 5px 18px rgba(15,23,42,.05)}.month-head{background:#f8fafc;padding:11px 13px;font-weight:950;color:#173b70;display:flex;justify-content:space-between}.month-body{padding:10px}.aud-row{border:1px solid #e2e8f0;border-radius:12px;padding:9px;margin-bottom:8px}.aud-row form{display:grid;grid-template-columns:1.4fr .6fr 1fr 1fr auto;gap:6px;align-items:center}.aud-row input,.aud-row select{width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:7px;font-size:12px;font-weight:700;background:#fff}.aud-empty{padding:15px;color:#94a3b8;font-size:13px}.add-row{margin-top:8px}.hint{font-size:12px;color:#64748b;font-weight:700;margin:5px 0 0}.card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:18px;box-shadow:0 5px 18px rgba(15,23,42,.04)}@media(max-width:1100px){.month-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.month-grid{grid-template-columns:1fr}.aud-row form{grid-template-columns:1fr 1fr}.aud-row form .wide{grid-column:1/-1}.hist-hero{display:block}.hist-actions{margin-top:10px}}
</style>
<div class="hist-hero"><div><h1>👥 ผู้ตรวจ / ประวัติ 12 เดือน</h1><span>เก็บรายชื่อผู้ตรวจรายเดือนแบบรวม — <b>ไม่แยกทีม A / B</b></span></div><div class="hist-actions"><form method="get"><select name="year"><option value="<?=$year?>"><?=$year?></option><option value="<?=$year-1?>"><?=$year-1?></option><option value="<?=$year+1?>"><?=$year+1?></option></select><button class="btn-blue">ดูปี</button></form><form method="post"><input type="hidden" name="action" value="snapshot"><input type="hidden" name="history_year" value="<?=$year?>"><button class="btn-green">💾 ดึงข้อมูลจากแผนตรวจ</button></form></div></div>
<?php if(isset($_GET['saved'])): ?><div class="card" style="background:#ecfdf5;color:#166534;font-weight:900;margin-bottom:14px">✓ บันทึกข้อมูลเรียบร้อย</div><?php endif; ?>
<?php if($err): ?><div class="card" style="background:#fee2e2;color:#991b1b;font-weight:900;margin-bottom:14px">⚠ <?=h($err)?></div><?php endif; ?>
<div class="card" style="margin-bottom:18px"><b>วิธีใช้งาน:</b> เลือกชื่อผู้ตรวจจากรายการ → แก้จำนวนครั้ง / วันที่ / แผนกได้ → กด 💾 บันทึก โดยไม่มีช่องแยกทีม A/B</div>
<div class="card" style="margin-top:18px"><div style="font-weight:950;font-size:18px;margin-bottom:10px">📋 ประวัติการตรวจจริง</div><table id="tbl" class="display w-full"><thead><tr><th>วันที่</th><th>ประเภท</th><th>พื้นที่</th><th>ผู้ตรวจ 1</th><th>ผู้ตรวจ 2</th><th>ผู้ตรวจ 3</th><th>รอบ/กะ</th><th>รายการคะแนน</th><th>เปิด</th><th>พิมพ์</th></tr></thead><tbody>
<?php foreach($rows as $r):$office=$r['department']==='Office';$scorePage=$office?'Page/auditor/office_score.php':'Page/auditor/score.php';$printPage=$office?'Page/public/print_office.php':'Page/public/print.php';?><tr><td><form method="post" action="Page/auditor/history.php" class="history-date-form" data-audit-id="<?=h((string)$r['id'])?>"><input type="hidden" name="action" value="update_audit_date"><input type="hidden" name="ajax" value="1"><input type="hidden" name="audit_id" value="<?=h((string)$r['id'])?>"><input type="hidden" name="history_year" value="<?=h((string)$year)?>"><input type="date" name="new_audit_date" value="<?=h($r['audit_date'])?>" title="เปลี่ยนวันที่แล้วระบบบันทึกอัตโนมัติ"><span class="date-save-status" aria-live="polite"></span></form></td><td><?=h($r['department'])?></td><td><?=h($r['area_name'])?></td><td><?=h($r['auditor'])?></td><td><?=h($r['auditor2']??'')?></td><td><?=h($r['auditor3']??'')?></td><td><?=h($r['period'])?></td><td><?=$r['items']?></td><td><a class="text-blue-600 font-bold" href="<?=$scorePage?>?area_id=<?=$r['area_id']?>&date=<?=$r['audit_date']?>&audit_id=<?=$r['id']?>">เปิด</a></td><td><a target="_blank" class="font-bold" href="<?=$printPage?>?id=<?=$r['id']?>">🖨</a></td></tr><?php endforeach;?></tbody></table></div>
<style>
.history-date-form{display:flex;align-items:center;gap:5px}
.history-date-form input[type=date]{width:125px;border:1px solid #cbd5e1;border-radius:7px;padding:5px;font-size:12px;background:#fff}
.date-save-status{font-size:12px;font-weight:900;min-width:18px}
.date-save-status.saving{color:#64748b}.date-save-status.ok{color:#16a34a}.date-save-status.err{color:#dc2626}.history-date-form input[type=date]:disabled{opacity:.7}
</style>
<script>
(function(){
  // ใช้ event delegation เพื่อให้ทำงานได้แม้ DataTables จะ redraw ตาราง
  function saveDate(input){
    if(!input || input.dataset.busy==='1') return;
    const form=input.closest('.history-date-form');
    if(!form) return;
    const status=form.querySelector('.date-save-status');
    const oldValue=input.dataset.savedValue || input.defaultValue || '';
    const newValue=input.value || '';
    if(!newValue || newValue===oldValue) return;

    input.dataset.busy='1';
    if(status){status.textContent='กำลังบันทึก…';status.className='date-save-status saving';}
    input.disabled=true;

    const fd=new FormData();
    fd.set('action','update_audit_date');
    fd.set('ajax','1');
    fd.set('audit_id',form.querySelector('[name="audit_id"]').value);
    fd.set('new_audit_date',newValue);

    fetch('Page/auditor/history_date_save.php?ts='+Date.now(),{
      method:'POST',body:fd,credentials:'same-origin',cache:'no-store',
      headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
    }).then(async function(res){
      const raw=await res.text();
      let data=null;
      try{data=JSON.parse(raw);}catch(e){}
      if(!res.ok || !data || !data.ok){
        const msg=(data&&data.message)?data.message:'เซิร์ฟเวอร์ไม่ตอบ JSON';
        throw new Error(msg);
      }
      if(String(data.audit_date)!==String(newValue)) throw new Error('ระบบยืนยันวันที่ใหม่ไม่ตรงกับวันที่เลือก');
      input.dataset.savedValue=newValue;
      input.defaultValue=newValue;
      // อัปเดตลิงก์ "เปิด" ของแถวเดียวกันให้ใช้วันที่ใหม่ทันที
      const row=input.closest('tr');
      if(row){
        const openLink=row.querySelector('a[href*="Page/auditor/score.php"],a[href*="Page/auditor/office_score.php"]');
        if(openLink){
          const u=new URL(openLink.href,window.location.href);
          u.searchParams.set('date',newValue);
          openLink.href=u.toString();
        }
      }
      if(status){status.textContent='✓ บันทึกแล้ว';status.className='date-save-status ok';}
      setTimeout(function(){if(status){status.textContent='';}},2500);
    }).catch(function(err){
      // fallback: ส่ง POST ปกติไป Page/auditor/history.php ซึ่งมีตัวบันทึกฝั่งเซิร์ฟเวอร์อีกชั้น
      // ช่วยกรณีโฮสต์/Apache บล็อก endpoint AJAX
      if(status){status.textContent='กำลังบันทึก…';status.className='date-save-status saving';}
      input.disabled=false;
      input.dataset.busy='0';
      const fallback=document.createElement('form');
      fallback.method='POST'; fallback.action='Page/auditor/history.php'; fallback.style.display='none';
      [['action','update_audit_date'],['ajax','0'],['audit_id',form.querySelector('[name="audit_id"]').value],['new_audit_date',newValue],['history_year',form.querySelector('[name="history_year"]').value]].forEach(function(pair){
        const h=document.createElement('input');h.type='hidden';h.name=pair[0];h.value=pair[1];fallback.appendChild(h);
      });
      document.body.appendChild(fallback);
      fallback.submit();
    }).finally(function(){
      // ถ้า fallback submit จะเปลี่ยนหน้าอยู่แล้ว
      input.disabled=false;
      input.dataset.busy='0';
    });
  }

  document.addEventListener('change',function(e){
    if(e.target && e.target.matches('.history-date-form input[type="date"]')) saveDate(e.target);
  });
})();
</script>
<?php require FIVE_S_ROOT.'/includes/footer.php';?>
