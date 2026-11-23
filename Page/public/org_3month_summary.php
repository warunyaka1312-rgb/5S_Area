<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
five_s_session(); // same session as login, so the signed-in user is recognised on this page
$title = 'สรุปภาพรวมองค์กร / เทียบ 3 เดือน';
$p = db();

/* Tables excel_monthly / app_settings are created by db/schema_mysql.sql via db(). */
if (empty($_SESSION['org3_csrf'])) { $_SESSION['org3_csrf'] = bin2hex(random_bytes(24)); }
$csrf = $_SESSION['org3_csrf'];
// เปรียบเทียบเฉพาะ ก.ค.–ก.ย. 2569: ก.ค./ส.ค. จาก Excel และ ก.ย. จากระบบจริง
$months = [
    ['label'=>'กรกฎาคม','key'=>'jul','month_no'=>7],
    ['label'=>'สิงหาคม','key'=>'aug','month_no'=>8],
];
$year = 2569;
$flash = $_SESSION['org3_flash'] ?? null;
unset($_SESSION['org3_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_history') {
    try {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('รหัสยืนยันหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่');
        }
        $sourcePath = FIVE_S_ROOT.'/data/history_comparison_3months_2569.json';
        if (!is_file($sourcePath)) { throw new RuntimeException('ไม่พบไฟล์ข้อมูลย้อนหลังที่แนบมากับโปรแกรม'); }
        $sourceRows = json_decode((string)file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($sourceRows) || count($sourceRows) < 1) { throw new RuntimeException('ไฟล์ข้อมูลย้อนหลังไม่มีรายการข้อมูล'); }

        $areaRows = $p->query("SELECT id,department,group_name,unit_no FROM areas WHERE active=1 ORDER BY id")->fetchAll();
        $byUnit = [];
        foreach ($areaRows as $area) {
            $unitKey = trim((string)$area['unit_no']);
            $byUnit[$unitKey] ??= [];
            $byUnit[$unitKey][] = $area;
        }
        $mapped = [];
        $unmapped = [];
        foreach ($sourceRows as $sourceRow) {
            $unit = trim((string)($sourceRow['unit'] ?? ''));
            $candidates = $byUnit[$unit] ?? [];
            if (count($candidates) !== 1) {
                $unmapped[] = [
                    'group'=>(string)($sourceRow['group'] ?? ''),
                    'unit'=>$unit,
                    'reason'=>count($candidates) === 0 ? 'ไม่มีหน่วยที่เปิดใช้งานใน Master' : 'เลขหน่วยซ้ำใน Master',
                    'zero'=>((float)($sourceRow['jun'] ?? 0) === 0.0 && (float)($sourceRow['jul'] ?? 0) === 0.0 && (float)($sourceRow['aug'] ?? 0) === 0.0),
                ];
                continue;
            }
            $area = $candidates[0];
            $canonicalGroup = function_exists('display_area_group') ? display_area_group($area['group_name']) : trim((string)$area['group_name']);
            $mapped[] = [
                'department'=>(string)$area['department'],
                'group_name'=>$canonicalGroup,
                'unit_no'=>trim((string)$area['unit_no']),
                'target'=>(float)($sourceRow['target'] ?? ($area['department'] === 'Office' ? 87 : 85)),
                'scores'=>[
                    'กรกฎาคม'=>(float)($sourceRow['jul'] ?? 0),
                    'สิงหาคม'=>(float)($sourceRow['aug'] ?? 0),
                ],
            ];
        }
        if (count($mapped) === 0) { throw new RuntimeException('จับคู่ข้อมูลย้อนหลังกับ Master ไม่ได้ จึงยังไม่เปลี่ยนฐานข้อมูล'); }

        // Backup only the historical monthly rows that this import will replace.
        $monthNames = array_column($months, 'label');
        $in = implode(',', array_fill(0, count($monthNames), '?'));
        $backupQuery = $p->prepare("SELECT * FROM excel_monthly WHERE source_year=? AND source_month IN ($in) ORDER BY department,group_name,unit_no");
        $backupQuery->execute(array_merge([$year], $monthNames));
        $oldRows = $backupQuery->fetchAll();
        $backupDir = FIVE_S_ROOT.'/backups';
        if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
            throw new RuntimeException('สร้างโฟลเดอร์ backups ไม่ได้ กรุณาตรวจสอบสิทธิ์เขียนไฟล์');
        }
        $backupFile = $backupDir . '/before_3month_import_' . date('Ymd_His') . '.json';
        $backupPayload = [
            'created_at'=>date('c'),
            'source'=>'เทียบ3เดือน.xlsx',
            'scope'=>'excel_monthly เฉพาะ กรกฎาคม-สิงหาคม 2569',
            'rows'=>$oldRows,
        ];
        $backupBytes = @file_put_contents($backupFile, json_encode($backupPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($backupBytes === false) { throw new RuntimeException('สำรองข้อมูลย้อนหลังเดิมไม่ได้ จึงยกเลิกการนำเข้าเพื่อความปลอดภัย'); }

        $p->beginTransaction();
        $delete = $p->prepare("DELETE FROM excel_monthly WHERE source_year=? AND source_month IN ($in)");
        $delete->execute(array_merge([$year], $monthNames));
        $insert = $p->prepare("INSERT INTO excel_monthly(source_month,source_year,department,group_name,unit_no,score,target,actual,section,auditor)
                               VALUES(?,?,?,?,?,?,?,?,?,?)");
        $insertedByMonth = [];
        foreach ($months as $month) {
            $insertedByMonth[$month['label']] = 0;
            foreach ($mapped as $item) {
                $score = $item['scores'][$month['label']];
                $insert->execute([
                    $month['label'], $year, $item['department'], $item['group_name'], $item['unit_no'],
                    $score, $item['target'], $score, $item['department'], ''
                ]);
                $insertedByMonth[$month['label']]++;
            }
        }
        // Verify both historical monthly snapshots before committing.
        $verify = $p->prepare("SELECT COUNT(*) FROM excel_monthly WHERE source_year=? AND source_month=?");
        foreach ($months as $month) {
            $verify->execute([$year, $month['label']]);
            if ((int)$verify->fetchColumn() !== count($mapped)) {
                throw new RuntimeException('ตรวจสอบจำนวนข้อมูลเดือน' . $month['label'] . 'ไม่ผ่าน ยกเลิกการบันทึก');
            }
        }
        $p->commit();
        $_SESSION['org3_flash'] = [
            'type'=>'success',
            'message'=>'นำเข้าข้อมูลย้อนหลัง ก.ค.–ส.ค. เรียบร้อย: ' . count($mapped) . ' หน่วย × 2 เดือน = ' . (count($mapped) * 2) . ' รายการ • เดือน ก.ย. ใช้คะแนนจริงในระบบ',
            'backup'=>basename($backupFile),
            'unmapped'=>$unmapped,
            'importedByMonth'=>$insertedByMonth,
        ];
    } catch (Throwable $e) {
        if ($p->inTransaction()) { $p->rollBack(); }
        $_SESSION['org3_flash'] = ['type'=>'error','message'=>'นำเข้าข้อมูลไม่สำเร็จ: ' . $e->getMessage()];
    }
    header('Location: '.FIVE_S_BASE.'Page/public/org_3month_summary.php');
    exit;
}

/* =====================================================================
   V62: compare the LATEST 3 months automatically.
   Each unit/month uses the real inspection score from the system when one
   exists; otherwise the imported Excel monthly summary (excel_monthly).
   ===================================================================== */
$thFull  = five_s_thai_months();
$thShort = [1=>'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$enShort = [1=>'JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];

/* Months that have any data (live audits or Excel) → options for the period selector. */
$availableMonths = [];
foreach ($p->query("SELECT DISTINCT substr(audit_date,1,7) ym FROM audits WHERE audit_date<>'' ORDER BY ym") as $r) {
    if (preg_match('/^(\d{4})-(\d{2})$/', (string)$r['ym'], $mm)) $availableMonths[sprintf('%04d-%02d',(int)$mm[1],(int)$mm[2])] = true;
}
$thIndex = array_flip($thFull);
foreach ($p->query("SELECT DISTINCT source_year,source_month FROM excel_monthly") as $r) {
    $mn = $thIndex[(string)$r['source_month']] ?? null;
    if ($mn) $availableMonths[sprintf('%04d-%02d',(int)$r['source_year']-543,$mn)] = true;
}
ksort($availableMonths);
[$__ly,$__lm] = five_s_latest_data_month();
$endKey = sprintf('%04d-%02d',$__ly,$__lm);
if (preg_match('/^(\d{4})-(\d{2})$/', (string)($_GET['end'] ?? ''), $mm) && (int)$mm[2]>=1 && (int)$mm[2]<=12) {
    $endKey = sprintf('%04d-%02d',(int)$mm[1],(int)$mm[2]);
}
$endDate = new DateTimeImmutable($endKey.'-01');

/* Active master units */
$masterAreaRows = $p->query("SELECT id,department,group_name,unit_no,area_name FROM areas WHERE active=1 AND department IN ('Production','Office') ORDER BY department,group_name,CAST(unit_no AS SIGNED),unit_no")->fetchAll();
$activeByDept = ['Production'=>0,'Office'=>0];
$masterUnits = [];          // key => meta
$masterByDeptUnit = [];     // dept|unit => [keys]
foreach ($masterAreaRows as $a) {
    $dep = (string)$a['department'];
    $g = trim((string)(function_exists('display_area_group') ? display_area_group($a['group_name']) : $a['group_name']));
    $unit = trim((string)$a['unit_no']);
    $key = $dep.'|'.$g.'|'.$unit;
    /* The same department+group+unit can exist twice in Master (e.g. PURCHASING / Billet&Scarp).
       Treat them as ONE unit and read the score from whichever area record was audited. */
    if (isset($masterUnits[$key])) { $masterUnits[$key]['area_ids'][] = (int)$a['id']; continue; }
    $activeByDept[$dep] = ($activeByDept[$dep] ?? 0) + 1;
    $masterUnits[$key] = ['area_id'=>(int)$a['id'],'area_ids'=>[(int)$a['id']],'department'=>$dep,'group_name'=>$g,'unit_no'=>$unit];
    if (!in_array($key, $masterByDeptUnit[$dep.'|'.$unit] ?? [], true)) $masterByDeptUnit[$dep.'|'.$unit][] = $key;
}
$activeTotal = count($masterUnits);
/* Scores saved on an older, now-inactive area record of the same unit still count for that unit. */
foreach ($p->query("SELECT id,department,group_name,unit_no FROM areas WHERE active=0 AND department IN ('Production','Office')") as $ia) {
    $g = trim((string)(function_exists('display_area_group') ? display_area_group($ia['group_name']) : $ia['group_name']));
    $k = (string)$ia['department'].'|'.$g.'|'.trim((string)$ia['unit_no']);
    if (isset($masterUnits[$k])) $masterUnits[$k]['area_ids'][] = (int)$ia['id'];
}

/* Live scores for one month: latest scored audit per area. */
function org3_live_scores(PDO $p, string $start, string $end): array {
    $q = $p->prepare("SELECT au.id audit_id,au.area_id,a.department FROM audits au JOIN areas a ON a.id=au.area_id
        WHERE a.department IN ('Production','Office') AND au.audit_date BETWEEN ? AND ?
          AND ((a.department='Production' AND EXISTS(SELECT 1 FROM scores s WHERE s.audit_id=au.id AND (s.score1 IS NOT NULL OR s.score2 IS NOT NULL OR s.score3 IS NOT NULL)))
            OR (a.department='Office' AND EXISTS(SELECT 1 FROM office_scores os WHERE os.audit_id=au.id AND (os.score1 IS NOT NULL OR os.score2 IS NOT NULL OR os.score3 IS NOT NULL))))
        ORDER BY a.id,au.audit_date DESC,au.id DESC");
    $q->execute([$start,$end]);
    $out = [];
    foreach ($q->fetchAll() as $r) {
        $aid = (int)$r['area_id'];
        if (isset($out[$aid])) continue;
        $sum = $r['department']==='Office' ? office_audit_summary($p,(int)$r['audit_id']) : production_audit_summary($p,(int)$r['audit_id']);
        if ((float)($sum['max'] ?? 0) > 0) $out[$aid] = round((float)$sum['pct'],2);
    }
    return $out;
}

$org3Months = [];
$unitScores = [];   // key => [i => ['score','target','src']]
for ($i = 2; $i >= 0; $i--) {
    $d = $endDate->modify("-$i month");
    $y = (int)$d->format('Y'); $m = (int)$d->format('n'); $be = $y + 543;
    $idx = 2 - $i;
    $live = org3_live_scores($p, $d->format('Y-m-01'), $d->format('Y-m-t'));
    $ex = $p->prepare("SELECT department,unit_no,score,target FROM excel_monthly WHERE source_year=? AND source_month=?");
    $ex->execute([$be, $thFull[$m]]);
    $excel = [];
    foreach ($ex->fetchAll() as $r) {
        $keys = $masterByDeptUnit[(string)$r['department'].'|'.trim((string)$r['unit_no'])] ?? [];
        if (count($keys) === 1) $excel[$keys[0]] = ['score'=>(float)$r['score'],'target'=>(float)$r['target']];
    }
    $nLive = 0; $nExcel = 0;
    foreach ($masterUnits as $key=>$mu) {
        $tgt = target_pct($mu['department']);
        $liveScore = null;
        foreach ($mu['area_ids'] as $aidX) { if (isset($live[$aidX])) { $liveScore = $live[$aidX]; break; } }
        if ($liveScore !== null) { $unitScores[$key][$idx] = ['score'=>$liveScore,'target'=>$tgt,'src'=>'live']; $nLive++; }
        elseif (isset($excel[$key])) { $unitScores[$key][$idx] = ['score'=>$excel[$key]['score'],'target'=>$excel[$key]['target'] ?: $tgt,'src'=>'excel']; $nExcel++; }
        else { $unitScores[$key][$idx] = ['score'=>null,'target'=>$tgt,'src'=>null]; }
    }
    $source = $nLive && $nExcel ? 'ระบบจริง + Excel' : ($nLive ? 'คะแนนจริงในระบบ' : ($nExcel ? 'คะแนนย้อนหลังจาก Excel' : 'ยังไม่มีข้อมูล'));
    $stats = [];
    foreach (['all','Production','Office'] as $scope) {
        $vals = []; $pass = 0; $below = 0; $master = 0;
        foreach ($masterUnits as $key=>$mu) {
            if ($scope !== 'all' && $mu['department'] !== $scope) continue;
            $master++;
            $u = $unitScores[$key][$idx];
            if ($u['score'] === null) continue;
            $vals[] = $u['score'];
            if ($u['score'] >= $u['target']) $pass++; else $below++;
        }
        $stats[$scope] = ['avg'=>$vals ? round(array_sum($vals)/count($vals),2) : null,'n'=>count($vals),'pass'=>$pass,'below'=>$below,'missing'=>$master-count($vals),'master'=>$master];
    }
    $org3Months[$idx] = ['y'=>$y,'m'=>$m,'be'=>$be,'label'=>$thFull[$m],'short'=>$thShort[$m],'tag'=>$enShort[$m].' '.$y,
        'source'=>$source,'live'=>$nLive>0,'stats'=>$stats];
}
ksort($org3Months);
$mFirst = $org3Months[0]; $mPrev = $org3Months[1]; $mLast = $org3Months[2];
$periodShort = $mFirst['short'].($mFirst['be']!==$mLast['be']?' '.$mFirst['be']:'').' – '.$mLast['short'].' '.$mLast['be'];
$periodFull  = $mFirst['label'].($mFirst['be']!==$mLast['be']?' '.$mFirst['be']:'').' – '.$mLast['label'].' '.$mLast['be'];
$periodTag   = $enShort[$mFirst['m']].' — '.$enShort[$mLast['m']].' '.$mLast['y'];

/* Compatibility variables used by the board template */
$liveStats = $mLast['stats'];
$liveMonthLabel = $mLast['label']; $liveYearThai = $mLast['be'];
$unitRows = [];
foreach ($masterUnits as $key=>$mu) {
    $s = $unitScores[$key];
    $unitRows[] = $mu + ['m'=>[$s[0]['score'],$s[1]['score'],$s[2]['score']],'src'=>[$s[0]['src'],$s[1]['src'],$s[2]['src']],
        'liveScore'=>$s[2]['score'],'liveTarget'=>$s[2]['target'],'inActiveMaster'=>true];
}
$deptOrder = ['Production'=>0,'Office'=>1];
usort($unitRows, function($a,$b) use($deptOrder){
    $d=($deptOrder[$a['department']]??9)<=>($deptOrder[$b['department']]??9); if($d) return $d;
    $g=strcasecmp($a['group_name'],$b['group_name']); return $g?:strnatcasecmp($a['unit_no'],$b['unit_no']);
});
/* Group summary */
$groupSummary = [];
foreach ($unitRows as $u) {
    $k = $u['department'].'|'.$u['group_name'];
    $groupSummary[$k] ??= ['department'=>$u['department'],'group_name'=>$u['group_name'],'units'=>[],'vals'=>[[],[],[]],'pass'=>0,'below'=>0,'missing'=>0];
    $groupSummary[$k]['units'][] = $u['unit_no'];
    foreach ([0,1,2] as $i) if ($u['m'][$i] !== null) $groupSummary[$k]['vals'][$i][] = $u['m'][$i];
    if ($u['m'][2] === null) $groupSummary[$k]['missing']++; elseif ($u['m'][2] >= $u['liveTarget']) $groupSummary[$k]['pass']++; else $groupSummary[$k]['below']++;
}
foreach ($groupSummary as &$g) {
    natsort($g['units']); $g['unitList'] = implode(', ', $g['units']); $g['unitCount'] = count($g['units']);
    foreach ([0,1,2] as $i) $g['avg'][$i] = $g['vals'][$i] ? round(array_sum($g['vals'][$i])/count($g['vals'][$i]),2) : null;
    $g['change'] = ($g['avg'][0] !== null && $g['avg'][2] !== null) ? round($g['avg'][2]-$g['avg'][0],2) : null;
} unset($g);
$groupSummary = array_values($groupSummary);
$orgLatest = $mLast['stats']['all']['avg'];
$overallChange = ($mFirst['stats']['all']['avg'] !== null && $orgLatest !== null) ? round($orgLatest - $mFirst['stats']['all']['avg'],2) : null;
$momChange = ($mPrev['stats']['all']['avg'] !== null && $orgLatest !== null) ? round($orgLatest - $mPrev['stats']['all']['avg'],2) : null;
$lastFlash = $flash;
$fmt = fn($v)=>$v===null?'—':number_format((float)$v,2).'%';
$chg = function($v){ if($v===null) return '<span class="muted">—</span>'; $c=$v>=0?'up':'down'; return '<span class="'.$c.'">'.($v>=0?'▲ +':'▼ ').number_format($v,2).'</span>'; };

if (isset($_GET['board'])) {
    $boardMode = (string)$_GET['board'];
    if (!in_array($boardMode, ['organization','Production','Office'], true)) { $boardMode = 'organization'; }
    require __DIR__.'/org_3month_board_template.php';
    exit;
}
$title = 'สรุปองค์กร / เปรียบเทียบ 3 เดือนล่าสุด';
require FIVE_S_ROOT.'/includes/header.php';
$endQ = 'end='.$endKey;
?>
<style>
.o3{--p:#F26A21;--o:#2563EB;--ok:#16A34A;--bad:#DC2626;--navy:#0E1A2F;--ink:#0F172A;--muted:#64748B;--line:#E4E8EF}
.o3 .muted{color:var(--muted)}.o3 .up{color:var(--ok);font-weight:700}.o3 .down{color:var(--bad);font-weight:700}
.o3-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px 22px;margin-bottom:16px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 18px rgba(15,23,42,.05);position:relative;overflow:hidden}
.o3-head::before{content:"";position:absolute;left:0;top:0;bottom:0;width:5px;background:linear-gradient(180deg,#F7A21B,#F26A21)}
.o3-head h1{margin:0;font-size:24px;font-weight:700;color:var(--navy)}.o3-head h1 span{color:var(--p)}
.o3-head p{margin:4px 0 0;font-size:13.5px;color:var(--muted)}
.o3-tools{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
.fld{display:flex;flex-direction:column;gap:2px}.fld label{font-size:11px;font-weight:600;color:var(--muted);padding-left:2px}
.fld select{height:40px;border:1px solid #D5DBE5;border-radius:10px;padding:0 12px;background:#fff;font-weight:600;font-size:14px;color:var(--ink);min-width:200px}
.btn-x{display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 16px;border-radius:10px;font-weight:600;font-size:14px;text-decoration:none;cursor:pointer;border:1px solid transparent;white-space:nowrap}
.btn-x .ico{width:16px;height:16px}.btn-x.primary{background:var(--p);color:#fff}.btn-x.primary:hover{background:#DB5512}
.btn-x.ghost{background:#fff;border-color:#D5DBE5;color:#1E293B}.btn-x.ghost:hover{border-color:var(--p);color:var(--p)}
.o3-months{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px}
.mcard{background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px 20px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 18px rgba(15,23,42,.05);position:relative;overflow:hidden}
.mcard::before{content:"";position:absolute;left:0;right:0;top:0;height:5px;background:#CBD5E1}
.mcard.c1::before{background:#94A3B8}.mcard.c2::before{background:#60A5FA}.mcard.c3::before{background:linear-gradient(90deg,#F7A21B,#F26A21)}
.mcard.c3{background:linear-gradient(160deg,#0E1A2F,#1B2B47);color:#fff;border-color:#0E1A2F}
.mcard .top{display:flex;justify-content:space-between;align-items:flex-start;gap:8px}
.mcard h3{margin:0;font-size:20px;font-weight:700}.mcard .tag{font:700 12px Inter,sans-serif;letter-spacing:1.2px;color:var(--muted)}
.mcard.c3 .tag{color:#F7A21B}
.mcard .src{display:inline-block;margin-top:4px;font-size:12px;font-weight:600;padding:2px 9px;border-radius:999px;background:#F1F5F9;color:#475569}
.mcard.c3 .src{background:rgba(255,255,255,.12);color:#E2E8F0}
.mcard .big{font:800 44px/1.1 Inter,sans-serif;letter-spacing:-1px;margin:12px 0 4px}
.mcard .big small{font-size:14px;font-weight:600;color:var(--muted);letter-spacing:0;margin-left:6px}.mcard.c3 .big small{color:#CBD5E1}
.mcard .rows{display:grid;gap:7px;margin-top:10px}
.mcard .row{display:grid;grid-template-columns:86px 1fr 64px;gap:10px;align-items:center;font-size:13.5px;font-weight:600}
.mcard .row .t{height:8px;border-radius:9px;background:#EEF1F5;overflow:hidden}.mcard.c3 .row .t{background:rgba(255,255,255,.15)}
.mcard .row .t i{display:block;height:100%;border-radius:9px}
.mcard .row b{text-align:right;font-family:Inter,sans-serif}
.mcard .foot{display:flex;justify-content:space-between;margin-top:12px;padding-top:10px;border-top:1px solid #EEF1F5;font-size:12.5px;color:var(--muted)}
.mcard.c3 .foot{border-color:rgba(255,255,255,.15);color:#CBD5E1}
.o3-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:16px}
.kp{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px;display:flex;gap:14px;align-items:center;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 18px rgba(15,23,42,.04);min-width:0}
.kp .ib{width:48px;height:48px;border-radius:13px;display:flex;align-items:center;justify-content:center;flex:0 0 auto;background:var(--bgc);color:var(--fc)}.kp .ib .ico{width:24px;height:24px}
.kp .lbl{font-size:13px;color:var(--muted);font-weight:600}.kp .val{font:800 28px/1.15 Inter,sans-serif;color:var(--ink)}.kp .sub{font-size:12px;color:var(--muted)}
.o3-grid2{display:grid;grid-template-columns:1.25fr 1fr;gap:16px;margin-bottom:16px}
.card2{background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px 22px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 18px rgba(15,23,42,.05);margin-bottom:16px}
.o3-grid2 .card2{margin-bottom:0}
.card2 h2{margin:0;font-size:19px;font-weight:700;color:var(--navy);display:flex;align-items:center;gap:10px}
.card2 h2::before{content:"";width:5px;height:20px;border-radius:4px;background:var(--p)}
.card2 p.sub{margin:4px 0 12px;font-size:13px;color:var(--muted)}
.chart{position:relative;height:300px}
.tbl{width:100%;border-collapse:separate;border-spacing:0;font-size:14px}
.tbl th{background:var(--navy);color:#fff;font-weight:600;font-size:13px;padding:10px 12px;text-align:center;white-space:nowrap;position:sticky;top:0}
.tbl th:first-child{border-radius:10px 0 0 0;text-align:left}.tbl th:last-child{border-radius:0 10px 0 0}
.tbl th small{display:block;font-weight:400;font-size:11px;color:#CBD5E1}
.tbl td{padding:9px 12px;border-bottom:1px solid #EEF1F5;text-align:center;font-family:Inter,"IBM Plex Sans Thai",sans-serif;font-weight:500}
.tbl td.l{text-align:left;font-family:"IBM Plex Sans Thai",sans-serif}
.tbl tr:hover td{background:#FFF7F0}
.tbl tr.sec td{background:#F1F5F9;font-weight:700;color:var(--navy);text-align:left;font-family:"IBM Plex Sans Thai",sans-serif}
.tbl tr.sec.off td{background:#EAF1FF;color:#1D4ED8}.tbl tr.sec.prod td{background:#FFF1E7;color:#C2410C}
.tbl tr.total td{background:#0E1A2F;color:#fff;font-weight:700}
.tbl .cell{display:inline-block;min-width:74px;padding:3px 8px;border-radius:8px;font-weight:700}
.cell.ok{background:#E9F8EF;color:#15803D}.cell.bad{background:#FDEDED;color:#B91C1C}.cell.na{color:#94A3B8;font-weight:500}
.cell sup{font-size:9px;font-weight:600;color:#94A3B8;margin-left:2px}
.pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12.5px;font-weight:700}
.pill.ok{background:#E9F8EF;color:#15803D}.pill.bad{background:#FDEDED;color:#B91C1C}.pill.na{background:#F1F5F9;color:#64748B}
.unit-list{max-width:260px;font-size:12.5px;color:#475569;text-align:left!important}
.tbl-wrap{overflow:auto;max-height:640px;border-radius:10px}
.o3-filter{display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap}
.o3-filter button{height:34px;padding:0 14px;border-radius:9px;border:1px solid #D5DBE5;background:#fff;font-size:13px;font-weight:600;color:#334155;cursor:pointer}
.o3-filter button.on{background:var(--navy);color:#fff;border-color:var(--navy)}
details.imp{background:#fff;border:1px dashed #D5DBE5;border-radius:14px;padding:12px 16px;margin-bottom:16px}
details.imp summary{cursor:pointer;font-weight:600;color:#334155;font-size:14px}
.msg{border-radius:12px;padding:12px 16px;margin-bottom:16px;font-weight:600}.msg.ok{background:#E9F8EF;color:#166534}.msg.err{background:#FDEDED;color:#991B1B}
@media(max-width:1200px){.o3-months{grid-template-columns:1fr}.o3-grid2{grid-template-columns:1fr}.o3-kpis{grid-template-columns:1fr 1fr}}
@media(max-width:560px){.o3-kpis{grid-template-columns:1fr}.fld select{min-width:0;width:100%}}
@media print{.o3-tools,details.imp,.o3-filter{display:none!important}.tbl-wrap{max-height:none;overflow:visible}.card2,.mcard,.kp{box-shadow:none!important;break-inside:avoid}body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>
<div class="o3">
  <section class="o3-head">
    <div><h1>สรุปองค์กร 5 ส. — <span>เปรียบเทียบ 3 เดือนล่าสุด</span></h1><p><?=h($periodFull)?> • เลือกเดือนล่าสุดที่มีข้อมูลให้อัตโนมัติ • ใช้คะแนนจริงจากใบตรวจในระบบ ถ้าเดือนนั้นไม่มีใบตรวจจึงใช้คะแนนย้อนหลังจาก Excel</p></div>
    <div class="o3-tools no-print">
      <form method="get" class="fld"><label>เดือนล่าสุดของการเปรียบเทียบ</label>
        <select name="end" onchange="this.form.submit()"><?php foreach(array_reverse(array_keys($availableMonths)) as $k): [$yy,$mmn]=array_map('intval',explode('-',$k)); ?><option value="<?=$k?>" <?=$k===$endKey?'selected':''?>><?=$thFull[$mmn]?> <?=$yy+543?></option><?php endforeach; ?></select>
      </form>
      <a class="btn-x primary" href="Page/public/org_3month_summary.php?board=organization&<?=$endQ?>"><?=five_s_icon('board')?> รายงานติดบอร์ด / LINE</a>
      <button class="btn-x ghost" onclick="window.print()"><?=five_s_icon('printer')?> พิมพ์ / PDF</button>
    </div>
  </section>

  <?php if($lastFlash): ?><div class="msg <?=$lastFlash['type']==='success'?'ok':'err'?>"><?=h($lastFlash['message'])?><?php if(!empty($lastFlash['backup'])):?> • สำรองไว้ที่ backups/<?=h($lastFlash['backup'])?><?php endif;?></div><?php endif; ?>

  <div class="o3-months">
  <?php foreach($org3Months as $i=>$mo): $st=$mo['stats']; ?>
    <div class="mcard c<?=$i+1?>">
      <div class="top"><div><h3><?=h($mo['label'])?> <?=$mo['be']?></h3><span class="src"><?=h($mo['source'])?></span></div><div class="tag"><?=h($mo['tag'])?><?=$i===2?' • ล่าสุด':''?></div></div>
      <div class="big"><?=$st['all']['avg']===null?'—':number_format($st['all']['avg'],2).'%'?><small>ภาพรวมองค์กร</small></div>
      <div class="rows">
        <?php foreach(['Production'=>'#F26A21','Office'=>'#3B82F6'] as $dep=>$col): $v=$st[$dep]['avg']; ?>
        <div class="row"><span><?=$dep?></span><div class="t"><i style="width:<?=max(0,min(100,(float)$v))?>%;background:<?=$col?>"></i></div><b><?=$v===null?'—':number_format($v,2).'%'?></b></div>
        <?php endforeach; ?>
      </div>
      <div class="foot"><span>มีคะแนน <?=$st['all']['n']?>/<?=$st['all']['master']?> หน่วย</span><span>ผ่าน <?=$st['all']['pass']?> • ต่ำกว่า <?=$st['all']['below']?></span></div>
    </div>
  <?php endforeach; ?>
  </div>

  <div class="o3-kpis">
    <div class="kp" style="--bgc:#F1F5F9;--fc:#334155"><div class="ib"><?=five_s_icon('building')?></div><div><div class="lbl">หน่วยใน Master</div><div class="val"><?=$activeTotal?></div><div class="sub">Production <?=$activeByDept['Production']?> • Office <?=$activeByDept['Office']?></div></div></div>
    <div class="kp" style="--bgc:#FFF1E7;--fc:#EA580C"><div class="ib"><?=five_s_icon('trend')?></div><div><div class="lbl">เปลี่ยนจากเดือนก่อน (<?=h($mPrev['short'])?> → <?=h($mLast['short'])?>)</div><div class="val"><?=$chg($momChange)?></div><div class="sub">จุด • คะแนนเฉลี่ยองค์กร</div></div></div>
    <div class="kp" style="--bgc:#EEF2FF;--fc:#4F46E5"><div class="ib"><?=five_s_icon('calendar')?></div><div><div class="lbl">เปลี่ยนรอบ 3 เดือน (<?=h($mFirst['short'])?> → <?=h($mLast['short'])?>)</div><div class="val"><?=$chg($overallChange)?></div><div class="sub">จุด • คะแนนเฉลี่ยองค์กร</div></div></div>
    <div class="kp" style="--bgc:#FDEDED;--fc:#DC2626"><div class="ib"><?=five_s_icon('bolt')?></div><div><div class="lbl">ต่ำกว่าเป้า / ยังไม่มีคะแนน (<?=h($mLast['short'])?>)</div><div class="val"><span class="down"><?=$liveStats['all']['below']?></span> <span class="muted" style="font-size:18px">/ <?=$liveStats['all']['missing']?></span></div><div class="sub">หน่วย</div></div></div>
  </div>

  <div class="o3-grid2">
    <section class="card2"><h2>แนวโน้มคะแนนเฉลี่ย 3 เดือน</h2><p class="sub">องค์กร • Production • Office (แกน 60–100%)</p><div class="chart"><canvas id="o3Trend"></canvas></div></section>
    <section class="card2"><h2>หน่วยผ่าน / ต่ำกว่าเป้าหมาย</h2><p class="sub">จำนวนหน่วยที่มีคะแนนในแต่ละเดือน</p><div class="chart"><canvas id="o3Pass"></canvas></div></section>
  </div>

  <section class="card2">
    <h2>สรุปคะแนน 3 เดือน แยกประเภท</h2><p class="sub">เป้าหมาย Production <?=number_format(target_pct('Production'),1)?>% • Office <?=number_format(target_pct('Office'),1)?>%</p>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>ประเภท</th><?php foreach($org3Months as $mo):?><th><?=h($mo['short'])?> <?=$mo['be']?><small><?=h($mo['source'])?></small></th><?php endforeach;?><th>เปลี่ยน<small><?=h($mPrev['short'])?> → <?=h($mLast['short'])?></small></th><th>ผ่านเป้า</th><th>ต่ำกว่าเป้า</th><th>ยังไม่มีคะแนน</th></tr></thead><tbody>
    <?php foreach(['Production','Office','all'] as $dep): $tg=$dep==='all'?null:target_pct($dep); $vals=array_map(fn($mo)=>$mo['stats'][$dep]['avg'],$org3Months); $c=($vals[1]!==null&&$vals[2]!==null)?round($vals[2]-$vals[1],2):null; ?>
      <tr class="<?=$dep==='all'?'total':''?>"><td class="l"><b><?=$dep==='all'?'รวมองค์กร':h($dep)?></b></td>
      <?php foreach($vals as $v):?><td><?php if($dep==='all'):?><?=$fmt($v)?><?php else:?><span class="cell <?=$v===null?'na':($v>=$tg?'ok':'bad')?>"><?=$fmt($v)?></span><?php endif;?></td><?php endforeach;?>
      <td><?=$dep==='all'?($c===null?'—':($c>=0?'+':'').number_format($c,2)):$chg($c)?></td><td><?=$liveStats[$dep]['pass']?></td><td><?=$liveStats[$dep]['below']?></td><td><?=$liveStats[$dep]['missing']?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </section>

  <section class="card2">
    <h2>เปรียบเทียบรายกลุ่ม</h2><p class="sub">ค่าเฉลี่ยของหน่วยในกลุ่ม • สถานะผ่าน/ต่ำกว่าเป้าใช้เดือน <?=h($mLast['label'])?></p>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>กลุ่ม</th><th>หน่วย</th><?php foreach($org3Months as $mo):?><th><?=h($mo['short'])?></th><?php endforeach;?><th>เปลี่ยน 3 เดือน</th><th>ผ่าน / ทั้งหมด</th></tr></thead><tbody>
    <?php $ld=null; foreach($groupSummary as $g): $tg=target_pct($g['department']); if($ld!==$g['department']): $ld=$g['department']; ?><tr class="sec <?=$ld==='Office'?'off':'prod'?>"><td colspan="7"><?=h($ld)?> • เป้าหมาย <?=number_format($tg,0)?>%</td></tr><?php endif; ?>
      <tr><td class="l"><b><?=h($g['group_name'])?></b></td><td class="unit-list"><?=h($g['unitList'])?></td>
      <?php foreach($g['avg'] as $v):?><td><span class="cell <?=$v===null?'na':($v>=$tg?'ok':'bad')?>"><?=$fmt($v)?></span></td><?php endforeach;?>
      <td><?=$chg($g['change'])?></td><td><span class="pill <?=$g['below']?'bad':($g['pass']?'ok':'na')?>"><?=$g['pass']?> / <?=$g['unitCount']?></span></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </section>

  <section class="card2">
    <h2>เปรียบเทียบรายหน่วย (<?=count($unitRows)?> หน่วย)</h2><p class="sub">สีเขียว = ผ่านเป้า • สีแดง = ต่ำกว่าเป้า • <sup style="color:#94A3B8">X</sup> = คะแนนจาก Excel</p>
    <div class="o3-filter no-print"><button class="on" data-f="all">ทั้งหมด</button><button data-f="below">ต่ำกว่าเป้า (<?=h($mLast['short'])?>)</button><button data-f="missing">ยังไม่มีคะแนน</button><button data-f="Production">Production</button><button data-f="Office">Office</button></div>
    <div class="tbl-wrap"><table class="tbl" id="o3Units"><thead><tr><th>กลุ่ม - หน่วย</th><th>ประเภท</th><?php foreach($org3Months as $mo):?><th><?=h($mo['short'])?> <?=$mo['be']?></th><?php endforeach;?><th>เปลี่ยน<small><?=h($mPrev['short'])?> → <?=h($mLast['short'])?></small></th><th>สถานะ <?=h($mLast['short'])?></th></tr></thead><tbody>
    <?php foreach($unitRows as $u): $tg=(float)$u['liveTarget']; $last=$u['m'][2]; $st=$last===null?'missing':($last>=$tg?'pass':'below'); $c=($u['m'][1]!==null&&$last!==null)?round($last-$u['m'][1],2):null; ?>
      <tr data-dep="<?=h($u['department'])?>" data-st="<?=$st?>"><td class="l"><b><?=h($u['group_name'])?></b> • หน่วย <?=h($u['unit_no'])?></td><td><?=h($u['department'])?></td>
      <?php foreach([0,1,2] as $i): $v=$u['m'][$i]; ?><td><span class="cell <?=$v===null?'na':($v>=$tg?'ok':'bad')?>"><?=$fmt($v)?><?=$u['src'][$i]==='excel'?'<sup>X</sup>':''?></span></td><?php endforeach;?>
      <td><?=$chg($c)?></td><td><span class="pill <?=$st==='pass'?'ok':($st==='below'?'bad':'na')?>"><?=$st==='pass'?'ผ่าน':($st==='below'?'ต่ำกว่าเป้า':'ยังไม่มีคะแนน')?></span></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </section>

  <details class="imp no-print"><summary>นำเข้าคะแนนย้อนหลัง ก.ค.–ส.ค. 2569 จาก Excel (ใช้เฉพาะเดือนที่ไม่มีใบตรวจในระบบ)</summary>
    <form method="post" class="mt-3" onsubmit="return confirm('ระบบจะสำรองข้อมูลย้อนหลังเดิม แล้วแทนที่ข้อมูล Excel เฉพาะ ก.ค.–ส.ค. 2569 ดำเนินการหรือไม่?')">
      <input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="import_history">
      <button type="submit" class="btn-x ghost"><?=five_s_icon('upload')?> นำเข้าข้อมูลย้อนหลัง ก.ค.–ส.ค. 2569</button>
      <span class="muted" style="font-size:13px;margin-left:8px">ระบบสำรองข้อมูลเดิมไว้ในโฟลเดอร์ backups ก่อนทุกครั้ง • คะแนนจริงในระบบไม่ถูกแก้ไข</span>
    </form>
  </details>
</div>
<script>
Chart.defaults.font.family='"IBM Plex Sans Thai","Inter",Tahoma,sans-serif';Chart.defaults.color='#475569';
const O3L=<?=json_encode(array_map(fn($mo)=>$mo['short'].' '.$mo['be'],$org3Months),JSON_UNESCAPED_UNICODE)?>;
const O3={all:<?=json_encode(array_map(fn($mo)=>$mo['stats']['all']['avg'],$org3Months))?>,prod:<?=json_encode(array_map(fn($mo)=>$mo['stats']['Production']['avg'],$org3Months))?>,off:<?=json_encode(array_map(fn($mo)=>$mo['stats']['Office']['avg'],$org3Months))?>,pass:<?=json_encode(array_map(fn($mo)=>$mo['stats']['all']['pass'],$org3Months))?>,below:<?=json_encode(array_map(fn($mo)=>$mo['stats']['all']['below'],$org3Months))?>};
const lbl={id:'o3lbl',afterDatasetsDraw(ch){const c=ch.ctx;c.save();c.font='700 12px Inter,sans-serif';c.textAlign='center';ch.data.datasets.forEach((ds,di)=>{const m=ch.getDatasetMeta(di);if(m.hidden)return;m.data.forEach((pt,i)=>{const v=ds.data[i];if(v===null)return;c.fillStyle=ds.borderColor;if(di===0)return;c.fillText(Number(v).toFixed(1),pt.x,pt.y+(di===1?20:-12));});});c.restore();}};
new Chart(document.getElementById('o3Trend'),{type:'line',data:{labels:O3L,datasets:[
 {label:'รวมองค์กร',data:O3.all,borderColor:'#0E1A2F',backgroundColor:'#0E1A2F',borderWidth:3.5,tension:.3,pointRadius:6,pointBackgroundColor:'#fff',pointBorderWidth:3},
 {label:'Production',data:O3.prod,borderColor:'#F26A21',backgroundColor:'#F26A21',borderWidth:3,tension:.3,pointRadius:5,pointBackgroundColor:'#fff',pointBorderWidth:3},
 {label:'Office',data:O3.off,borderColor:'#2563EB',backgroundColor:'#2563EB',borderWidth:3,tension:.3,pointRadius:5,pointBackgroundColor:'#fff',pointBorderWidth:3}]},
 options:{responsive:true,maintainAspectRatio:false,layout:{padding:{top:16}},interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:8,font:{size:13,weight:'600'}}},tooltip:{callbacks:{label:c=>' '+c.dataset.label+': '+(c.raw===null?'ไม่มีข้อมูล':Number(c.raw).toFixed(2)+'%')}}},
 scales:{y:{min:60,max:100,border:{display:false},grid:{color:'rgba(148,163,184,.18)'},ticks:{callback:v=>v+'%'}},x:{grid:{display:false},ticks:{font:{size:13,weight:'600'}}}}},plugins:[lbl]});
new Chart(document.getElementById('o3Pass'),{type:'bar',data:{labels:O3L,datasets:[
 {label:'ผ่านเป้าหมาย',data:O3.pass,backgroundColor:'#16A34A',borderRadius:8,stack:'u',maxBarThickness:70},
 {label:'ต่ำกว่าเป้าหมาย',data:O3.below,backgroundColor:'#DC2626',borderRadius:8,stack:'u',maxBarThickness:70}]},
 options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:8,font:{size:13,weight:'600'}}}},scales:{x:{stacked:true,grid:{display:false},ticks:{font:{size:13,weight:'600'}}},y:{stacked:true,beginAtZero:true,border:{display:false},grid:{color:'rgba(148,163,184,.18)'},ticks:{precision:0}}}},
 plugins:[{id:'o3v',afterDatasetsDraw(ch){const c=ch.ctx;c.save();c.font='700 13px Inter,sans-serif';c.fillStyle='#fff';c.textAlign='center';c.textBaseline='middle';ch.data.datasets.forEach((ds,di)=>ch.getDatasetMeta(di).data.forEach((b,i)=>{const v=ds.data[i];if(v)c.fillText(v,b.x,(b.y+b.base)/2);}));c.restore();}}]});
document.querySelectorAll('.o3-filter button').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('.o3-filter button').forEach(x=>x.classList.remove('on'));b.classList.add('on');const f=b.dataset.f;document.querySelectorAll('#o3Units tbody tr').forEach(tr=>{const ok=f==='all'||(f==='below'&&tr.dataset.st==='below')||(f==='missing'&&tr.dataset.st==='missing')||tr.dataset.dep===f;tr.style.display=ok?'':'none';});}));
</script>
<?php require FIVE_S_ROOT.'/includes/footer.php'; ?>
