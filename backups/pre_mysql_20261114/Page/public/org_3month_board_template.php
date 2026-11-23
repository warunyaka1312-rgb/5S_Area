<?php
/* V60: this file is a template included by Page/public/org_3month_summary.php — redirect direct visits. */
if (!isset($liveStats) && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { header('Location: '.FIVE_S_BASE.'Page/public/org_3month_summary.php'); exit; }
$boardMode = in_array(($boardMode ?? 'organization'), ['organization','Production','Office'], true) ? $boardMode : 'organization';
$isOrgBoard = $boardMode === 'organization';
$boardDept = $boardMode === 'organization' ? null : $boardMode;
$boardTarget = $boardDept ? target_pct($boardDept) : null;
$boardDeptField = $boardDept === 'Production' ? 'prod' : 'office';
$boardLatestStat = $liveStats[$boardDept ?? 'all'];
$boardLatestAvg = $boardLatestStat['avg'];
$boardMasterCount = $boardDept ? (int)($activeByDept[$boardDept] ?? 0) : (int)$activeTotal;
$boardScoredCount = (int)$boardLatestStat['n'];
$boardMissingCount = (int)$boardLatestStat['missing'];
$boardPassCount = (int)$boardLatestStat['pass'];
$boardBelowCount = (int)$boardLatestStat['below'];
$boardCoveragePct = $boardMasterCount > 0 ? round($boardScoredCount / $boardMasterCount * 100, 1) : 0;
$__scope = $isOrgBoard ? 'all' : $boardDept;
$boardJulyAvg = $org3Months[0]['stats'][$__scope]['avg'];
$boardAugAvg = $org3Months[1]['stats'][$__scope]['avg'];
$boardChange = ($boardJulyAvg !== null && $boardLatestAvg !== null) ? round($boardLatestAvg - $boardJulyAvg, 2) : null;
$boardTrend = [];
foreach ($org3Months as $mo) {
    $st = $mo['stats'][$__scope];
    $boardTrend[] = ['label'=>$mo['label'],'short'=>$mo['short'],'tag'=>$mo['tag'],'source'=>$mo['source'],'hist'=>!$mo['live'],
        'score'=>$st['avg'],'count'=>$st['n'],'master'=>$st['master'],'coverage'=>$st['master']>0?round($st['n']/$st['master']*100,1):0,'be'=>$mo['be']];
}

$boardAllCurrentRows = [];
$boardMissingRows = [];
foreach ($unitRows as $unitRow) {
    if (empty($unitRow['inActiveMaster'])) continue;
    if ($boardDept && $unitRow['department'] !== $boardDept) continue;
    $score = $unitRow['liveScore'] ?? null;
    $target = (float)($unitRow['liveTarget'] ?? ($unitRow['department'] === 'Office' ? 87 : 85));
    $label = trim((string)$unitRow['group_name']) . ' - หน่วย ' . trim((string)$unitRow['unit_no']);
    $item = [
        'department'=>(string)$unitRow['department'],
        'label'=>$label,
        'score'=>$score === null ? null : (float)$score,
        'target'=>$target,
        'delta'=>$score === null ? null : round((float)$score - $target, 2),
    ];
    if ($score === null) {
        $boardMissingRows[] = $item;
    } else {
        $boardAllCurrentRows[] = $item;
    }
}
usort($boardAllCurrentRows, function($a,$b){
    $cmp = $a['score'] <=> $b['score'];
    if ($cmp !== 0) return $cmp;
    return strnatcasecmp($a['label'],$b['label']);
});
usort($boardMissingRows, function($a,$b){
    $cmp = strcmp($a['department'],$b['department']);
    return $cmp !== 0 ? $cmp : strnatcasecmp($a['label'],$b['label']);
});
$boardBelowRows = array_values(array_filter($boardAllCurrentRows, fn($r)=>$r['score'] < $r['target']));
$boardPassedRows = array_values(array_filter($boardAllCurrentRows, fn($r)=>$r['score'] >= $r['target']));
$boardPriorityLimit = $isOrgBoard ? 6 : 10;
$boardPriorities = array_slice($boardBelowRows, 0, $boardPriorityLimit);
$boardExtraBelow = max(0, count($boardBelowRows) - count($boardPriorities));
$boardMissingShown = array_slice($boardMissingRows, 0, $isOrgBoard ? 8 : 12);
$boardExtraMissing = max(0, count($boardMissingRows) - count($boardMissingShown));
$boardDeptStats = [];
foreach (['Production','Office'] as $dep) {
    $boardDeptStats[$dep] = $liveStats[$dep];
    $boardDeptStats[$dep]['master'] = (int)($activeByDept[$dep] ?? 0);
    $boardDeptStats[$dep]['coverage'] = $boardDeptStats[$dep]['master'] > 0 ? round($boardDeptStats[$dep]['n'] / $boardDeptStats[$dep]['master'] * 100, 1) : 0;
    $boardDeptStats[$dep]['target'] = target_pct($dep);
    $boardDeptStats[$dep]['month_jul'] = $org3Months[0]['stats'][$dep]['avg'];
    $boardDeptStats[$dep]['month_aug'] = $org3Months[1]['stats'][$dep]['avg'];
}
$boardTitle = $isOrgBoard ? 'สรุปภาพรวมองค์กร 5 ส.' : ('สรุปผลการประเมิน 5 ส. — ' . $boardDept);
$boardEnglishTitle = $isOrgBoard ? 'ORGANIZATION OVERVIEW' : ($boardDept === 'Production' ? 'PRODUCTION PERFORMANCE' : 'OFFICE PERFORMANCE');
$boardPeriod = $periodFull;
$boardMonthRows = [];
foreach ($org3Months as $mo) {
    $boardMonthRows[] = ['label'=>$mo['label'],'short'=>$mo['short'],'source'=>$mo['source'],
        'org'=>$mo['stats']['all']['avg'],'production'=>$mo['stats']['Production']['avg'],'office'=>$mo['stats']['Office']['avg'],
        'orgN'=>$mo['stats']['all']['n'],'prodN'=>$mo['stats']['Production']['n'],'officeN'=>$mo['stats']['Office']['n']];
}
$boardFmt = fn($v, $digits=2) => $v === null ? '—' : number_format((float)$v, $digits) . '%';
$boardModeQuery = $boardMode;
$boardDateGenerated = date('d/m/').(date('Y')+543);

/* ===== V61 board summary: plain-language takeaways for the notice board ===== */
$boardPrevAvg = $boardAugAvg;
$boardMoM = ($boardPrevAvg !== null && $boardLatestAvg !== null) ? round($boardLatestAvg - (float)$boardPrevAvg, 2) : null;
$boardPassPct = $boardScoredCount > 0 ? round($boardPassCount / $boardScoredCount * 100, 1) : 0;
$boardLowest = $boardBelowRows[0] ?? ($boardAllCurrentRows[0] ?? null);
$boardHighest = $boardAllCurrentRows ? $boardAllCurrentRows[count($boardAllCurrentRows)-1] : null;
$boardScopeName = $isOrgBoard ? 'ทั้งองค์กร' : $boardDept;
$boardStatusOk = $isOrgBoard
    ? ($boardScoredCount > 0 && $boardPassPct >= 70)
    : ($boardLatestAvg !== null && $boardLatestAvg >= (float)$boardTarget);
$boardStatusText = $isOrgBoard
    ? ($boardStatusOk ? 'ภาพรวมอยู่ในเกณฑ์ดี' : 'ต้องเร่งปรับปรุง')
    : ($boardStatusOk ? 'ผ่านเป้าหมาย' : 'ต่ำกว่าเป้าหมาย');
$boardTakeaways = [];
if ($boardLatestAvg !== null) {
    $t = 'คะแนนเฉลี่ยเดือน'.$mLast['label'].' <b>'.number_format($boardLatestAvg,2).'%</b>';
    if ($boardMoM !== null) $t .= $boardMoM >= 0 ? ' เพิ่มขึ้น <b class="up">+'.number_format($boardMoM,2).' จุด</b> จาก '.$mPrev['short'] : ' ลดลง <b class="down">'.number_format($boardMoM,2).' จุด</b> จาก '.$mPrev['short'];
    $boardTakeaways[] = ['ok'=>($boardMoM ?? 0) >= 0, 'html'=>$t];
}
$boardTakeaways[] = ['ok'=>$boardPassPct >= 70, 'html'=>'ผ่านเป้าหมาย <b>'.number_format($boardPassCount).' จาก '.number_format($boardScoredCount).' หน่วย</b> ('.number_format($boardPassPct,1).'%)'];
if ($isOrgBoard) {
    $pA = $boardDeptStats['Production']['avg']; $oA = $boardDeptStats['Office']['avg'];
    if ($pA !== null && $oA !== null) {
        $boardTakeaways[] = ['ok'=>$pA >= $boardDeptStats['Production']['target'] && $oA >= $boardDeptStats['Office']['target'],
          'html'=>'Production <b>'.number_format($pA,2).'%</b> (เป้า '.number_format($boardDeptStats['Production']['target'],0).'%) • Office <b>'.number_format($oA,2).'%</b> (เป้า '.number_format($boardDeptStats['Office']['target'],0).'%)'];
    }
}
if ($boardBelowCount > 0 && $boardLowest) {
    $boardTakeaways[] = ['ok'=>false, 'html'=>'ต่ำกว่าเป้าหมาย <b class="down">'.number_format($boardBelowCount).' หน่วย</b> — ต่ำสุด '.h($boardLowest['label']).' <b>'.number_format((float)$boardLowest['score'],2).'%</b>'];
} else {
    $boardTakeaways[] = ['ok'=>true, 'html'=>'ทุกหน่วยที่ตรวจแล้ว <b>ผ่านเป้าหมาย</b>'];
}
if ($boardMissingCount > 0) {
    $boardTakeaways[] = ['ok'=>false, 'html'=>'ยังไม่มีคะแนน <b class="down">'.number_format($boardMissingCount).' หน่วย</b> — ต้องตรวจให้ครบทุกหน่วยในเดือนถัดไป'];
}
if ($boardHighest && !$isOrgBoard) {
    $boardTakeaways[] = ['ok'=>true, 'html'=>'คะแนนสูงสุด '.h($boardHighest['label']).' <b class="up">'.number_format((float)$boardHighest['score'],2).'%</b>'];
}
/* Trend chart scale: 60–100% with labelled gridlines so month-to-month change is readable. */
$boardAxisMin = 60; $boardAxisMax = 100;
$boardBarH = function($v) use ($boardAxisMin, $boardAxisMax) {
    if ($v === null) return 0;
    return max(2, min(100, ((float)$v - $boardAxisMin) / ($boardAxisMax - $boardAxisMin) * 100));
};
$boardPriorities = array_slice($boardBelowRows, 0, $isOrgBoard ? 6 : 8);
$boardExtraBelow = max(0, count($boardBelowRows) - count($boardPriorities));
$boardMissingShown = array_slice($boardMissingRows, 0, 6);
$boardExtraMissing = max(0, count($boardMissingRows) - count($boardMissingShown));
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8"><base href="<?=htmlspecialchars(FIVE_S_BASE,ENT_QUOTES)?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=h($boardTitle)?> | 5S SMART • ALUMET</title>
<link rel="icon" type="image/png" href="assets/alumet-mark.png">
<script src="assets/vendor/html2canvas.min.js"></script>
<script src="assets/copy-line.js?v=64"></script>
<link rel="stylesheet" href="assets/vendor/fonts.css">
<style>
/* ===== V61 Board poster — international, high-contrast, print-first (A3/A4 landscape) ===== */
:root{--navy:#0E1A2F;--navy2:#1B2B47;--brand:#F26A21;--amber:#F7A21B;--blue:#1D4ED8;--green:#15803D;--green-bg:#E8F7EE;--red:#C0262D;--red-bg:#FDECEC;--amber-bg:#FFF4E2;--ink:#0F172A;--ink2:#334155;--muted:#5B6B82;--line:#DDE3EC;--soft:#F5F7FA;--bg:#E9EDF3;
 --font:"IBM Plex Sans Thai","Inter",Tahoma,Arial,sans-serif;--num:"Inter","IBM Plex Sans Thai",Arial,sans-serif}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);font-family:var(--font);color:var(--ink);-webkit-font-smoothing:antialiased}
/* toolbar (screen only) */
.board-toolbar{position:sticky;top:0;z-index:20;background:var(--navy);color:#fff;padding:10px 18px;display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;box-shadow:0 4px 16px #0f172a33}
.toolbar-left,.toolbar-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.board-tabs{display:flex;gap:4px;background:#ffffff14;padding:4px;border-radius:10px}
.board-tabs a{padding:8px 14px;border-radius:7px;color:#cbd5e1;text-decoration:none;font-weight:600;font-size:14px}
.board-tabs a:hover{color:#fff;background:#ffffff14}.board-tabs a.active{background:#fff;color:var(--navy)}
.tool-btn{display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 14px;border-radius:9px;border:1px solid #ffffff33;background:transparent;color:#fff;font:600 14px var(--font);text-decoration:none;cursor:pointer}
.tool-btn:hover{background:#ffffff14}.tool-btn.primary{background:var(--brand);border-color:var(--brand)}.tool-btn.primary:hover{background:#DB5512}
.tool-btn.green{background:#06C755;border-color:#06C755}
.page-note{max-width:1600px;margin:12px auto 0;padding:0 14px;font-size:13px;color:var(--muted)}
.poster-wrap{padding:14px 14px 40px;overflow-x:auto}
/* poster canvas: fixed 1600 × 1131 px = ISO landscape ratio (A3/A4) */
.poster{width:1600px;min-height:1131px;margin:0 auto;background:#fff;color:var(--ink);padding:30px 44px 20px;display:flex;flex-direction:column;gap:16px;box-shadow:0 10px 40px #0f172a1f;position:relative}
.poster::before{content:"";position:absolute;left:0;right:0;top:0;height:10px;background:linear-gradient(90deg,var(--amber),var(--brand) 40%,var(--navy) 100%)}
/* header */
.p-head{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:28px;padding-bottom:14px;border-bottom:2px solid var(--line)}
.p-brand{display:flex;align-items:center;gap:18px}
.p-brand img{height:54px;width:auto;display:block}
.p-brand .sep{width:2px;height:48px;background:var(--line)}
.p-brand .app{font:800 26px/1 var(--num);color:var(--navy);letter-spacing:-.3px}.p-brand .app span{color:var(--brand)}
.p-brand .app small{display:block;font:600 12px var(--font);color:var(--muted);letter-spacing:.8px;margin-top:6px}
.p-title{text-align:center}
.p-title h1{margin:0;font-size:42px;white-space:nowrap;line-height:1.15;font-weight:700;color:var(--navy);letter-spacing:-.3px}
.p-title .en{margin-top:4px;font:700 15px var(--num);letter-spacing:3px;color:var(--brand);text-transform:uppercase}
.p-period{text-align:right;border-left:2px solid var(--line);padding-left:28px}
.p-period small{display:block;font-size:14px;font-weight:600;color:var(--muted)}
.p-period strong{display:block;font:800 30px/1.2 var(--num);color:var(--navy);white-space:nowrap}
.p-period span{display:block;font-size:13px;color:var(--muted);margin-top:2px}
/* KPI row */
.p-kpis{display:grid;grid-template-columns:420px repeat(4,1fr);gap:16px}
.kpi-main{background:var(--navy);color:#fff;border-radius:18px;padding:18px 24px;display:flex;flex-direction:column;justify-content:center;position:relative;overflow:hidden}
.kpi-main::after{content:"";position:absolute;right:-40px;bottom:-60px;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,#F26A2140,transparent 70%)}
.kpi-main .lbl{font-size:17px;font-weight:600;color:#C7D2E3}
.kpi-main .val{font:800 84px/1 var(--num);letter-spacing:-3px;margin:6px 0 10px;color:#fff}
.kpi-main .val sup{font-size:44px;letter-spacing:0;margin-left:4px;color:var(--amber);vertical-align:top;position:relative;top:10px}
.kpi-main .row{display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1}
.chip{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:999px;font-size:14px;font-weight:700;white-space:nowrap}
.chip.ok{background:#22C55E26;color:#86EFAC}.chip.bad{background:#EF444430;color:#FCA5A5}.chip.neu{background:#ffffff1f;color:#E2E8F0}
.kpi{border:1px solid var(--line);border-radius:18px;padding:16px 18px 14px;display:flex;flex-direction:column;background:#fff;position:relative;overflow:hidden}
.kpi::before{content:"";position:absolute;left:0;top:0;bottom:0;width:6px;background:var(--c,#1D4ED8)}
.kpi .lbl{font-size:18px;font-weight:700;color:var(--ink2);line-height:1.3}
.kpi .lbl small{display:block;font:600 11.5px var(--num);letter-spacing:1.2px;color:var(--muted);margin-top:2px}
.kpi .val{font:800 60px/1 var(--num);color:var(--c,#0F172A);margin:auto 0 6px;padding-top:8px;letter-spacing:-1.5px}
.kpi .val small{font-size:30px;color:var(--muted);font-weight:700;letter-spacing:0}
.kpi .note{font-size:15px;color:var(--muted);font-weight:500}
.kpi .bar{height:8px;border-radius:9px;background:#EDF1F6;margin-top:10px;overflow:hidden}.kpi .bar i{display:block;height:100%;background:var(--c,#1D4ED8);border-radius:9px}
/* middle row: trend chart + takeaways */
.p-mid{display:grid;grid-template-columns:1.12fr .88fr;gap:16px}
.panel{border:1px solid var(--line);border-radius:18px;padding:14px 20px;background:#fff;display:flex;flex-direction:column}
.panel-h{display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:8px}
.panel-h h2{margin:0;font-size:24px;font-weight:700;color:var(--navy);display:flex;align-items:center;gap:10px}
.panel-h h2::before{content:"";width:6px;height:24px;border-radius:4px;background:var(--brand)}
.panel-h small{font:700 12px var(--num);letter-spacing:1.5px;color:var(--muted);text-transform:uppercase}
.chart{position:relative;height:225px;margin:30px 0 0 44px;border-left:2px solid #CBD5E1;border-bottom:2px solid #CBD5E1}
.chart .grid{position:absolute;left:0;right:0;border-top:1px dashed #DCE3EC}
.chart .grid span{position:absolute;left:-46px;top:-10px;width:38px;text-align:right;font:600 13px var(--num);color:var(--muted)}
.chart .tline{position:absolute;left:0;right:0;border-top:3px solid var(--red);z-index:2}
.chart .tline span{position:absolute;left:8px;top:-24px;background:var(--red);color:#fff;font:700 12px var(--num);padding:2px 8px;border-radius:5px}
.chart .groups{position:absolute;inset:0;display:grid;grid-template-columns:repeat(3,1fr);z-index:1}
.chart .grp{display:flex;align-items:flex-end;justify-content:center;gap:12px;padding:0 18px}
.chart .bar{position:relative;flex:1;max-width:78px;border-radius:8px 8px 0 0;background:var(--c)}
.chart .bar b{position:absolute;top:-28px;left:50%;transform:translateX(-50%);font:800 17px var(--num);color:var(--ink);white-space:nowrap}
.chart-x{display:grid;grid-template-columns:repeat(3,1fr);margin-left:44px;padding-top:6px}
.chart-x div{text-align:center;font-size:18px;font-weight:700;color:var(--navy)}
.chart-x div small{display:block;font-size:12.5px;font-weight:500;color:var(--muted)}
.legend{display:flex;gap:18px;justify-content:center;margin-top:6px;font-size:14px;font-weight:600;color:var(--ink2);flex-wrap:wrap}
.legend span{display:inline-flex;align-items:center;gap:7px}.legend i{width:14px;height:14px;border-radius:4px;background:var(--c);display:inline-block}
/* takeaways */
.takeaways{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;flex:1;justify-content:space-between}
.takeaways li{display:flex;gap:12px;align-items:flex-start;padding:10px 14px;border-radius:12px;background:var(--soft);font-size:18.5px;line-height:1.45;color:var(--ink)}
.takeaways li .ic{flex:0 0 32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font:800 17px var(--num);color:#fff;margin-top:1px}
.takeaways li.ok .ic{background:var(--green)}.takeaways li.bad .ic{background:var(--red)}
.takeaways li.bad{background:#FFF6F6}.takeaways li.ok{background:#F3FBF6}
.takeaways b{font-weight:700}.takeaways b.up{color:var(--green)}.takeaways b.down{color:var(--red)}
.status-banner{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;padding:12px 16px;border-radius:12px;font-size:19px;font-weight:700}
.status-banner.ok{background:var(--green);color:#fff}.status-banner.bad{background:var(--red);color:#fff}
.status-banner small{font-size:14px;font-weight:500;opacity:.9}
/* bottom row */
.p-bottom{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.tbl{width:100%;border-collapse:separate;border-spacing:0;font-size:17px}
.tbl th{background:var(--navy);color:#fff;font-weight:600;font-size:14.5px;padding:10px 12px;text-align:center}
.tbl th:first-child{border-radius:10px 0 0 0;text-align:left}.tbl th:last-child{border-radius:0 10px 0 0}
.tbl td{padding:9px 12px;border-bottom:1px solid var(--line);text-align:center;font-family:var(--num);font-weight:600}
.tbl td:first-child{text-align:left;font-family:var(--font);font-weight:700;color:var(--navy)}
.tbl td.pass{color:var(--green);font-weight:800}.tbl td.fail{color:var(--red);font-weight:800}
.tbl td .pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:14px;font-weight:700}
.pill.ok{background:var(--green-bg);color:var(--green)}.pill.bad{background:var(--red-bg);color:var(--red)}
.rule{margin-top:8px;font-size:14px;color:var(--muted);line-height:1.5}
.prio{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.prio.one{grid-template-columns:1fr}
.prio-row{display:grid;grid-template-columns:36px 1fr auto;gap:10px;align-items:center;padding:7px 12px;border:1px solid #F3D2D2;background:#FFF8F8;border-radius:10px}
.prio-row .no{width:34px;height:34px;border-radius:9px;background:var(--red);color:#fff;display:flex;align-items:center;justify-content:center;font:800 15px var(--num)}
.prio-row .nm{font-size:16.5px;font-weight:700;color:var(--ink);line-height:1.3}
.prio-row .nm small{display:block;font-size:13px;font-weight:500;color:var(--muted)}
.prio-row .sc{text-align:right;font:800 20px/1.1 var(--num);color:var(--red)}
.prio-row .sc small{display:block;font:600 12.5px var(--font);color:var(--muted)}
.good-box{padding:18px;border-radius:12px;background:var(--green-bg);color:var(--green);font-size:19px;font-weight:700;text-align:center}
.missing{margin-top:8px;padding:9px 12px;border-radius:12px;background:var(--amber-bg);border:1px solid #F8D9A6}
.missing .t{font-size:16px;font-weight:700;color:#92400E;margin-bottom:6px}
.missing .chips{display:flex;flex-wrap:wrap;gap:6px}
.missing .chips span{background:#fff;border:1px solid #F3D19B;border-radius:999px;padding:4px 11px;font-size:14px;font-weight:600;color:#7C2D12}
.more{margin-top:8px;font-size:14px;color:var(--muted)}
.cov{display:flex;flex-direction:column;gap:10px}
.cov-line .t{display:flex;justify-content:space-between;font-size:17px;font-weight:600;margin-bottom:6px}
.cov-line .t b{font-family:var(--num)}
.cov-track{height:16px;border-radius:9px;background:#EDF1F6;overflow:hidden}.cov-track i{display:block;height:100%;border-radius:9px;background:var(--c)}
/* footer */
.p-foot{margin-top:auto;display:flex;align-items:center;justify-content:space-between;gap:20px;padding-top:10px;border-top:2px solid var(--line);font-size:13.5px;color:var(--muted)}
.p-foot .l{display:flex;align-items:center;gap:12px}.p-foot img{height:24px;width:auto}
.p-foot b{color:var(--ink2)}
.p-foot .tag{font:800 13px var(--num);letter-spacing:2px;color:var(--brand);white-space:nowrap}
.feedback{display:none;position:fixed;right:18px;bottom:18px;z-index:50;background:var(--navy);color:#fff;border-radius:10px;padding:12px 16px;font-size:14px;font-weight:600;box-shadow:0 8px 28px #0f172a35}.feedback.show{display:block}
@media print{
 @page{size:A4 landscape;margin:0}
 body{background:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
 .board-toolbar,.page-note,.feedback{display:none!important}
 .poster-wrap{padding:0;overflow:visible}
 .poster{box-shadow:none;margin:0 auto;zoom:.6;min-height:0}
 html,body{height:auto}
}
</style>
</head>
<body>
<div class="board-toolbar no-print">
  <div class="toolbar-left">
    <a href="index.php" class="toolbar-brand" style="display:inline-flex;align-items:center;gap:10px;text-decoration:none;color:inherit;background:#fff;border-radius:8px;padding:5px 10px"><img src="assets/alumet-logo.png" alt="ALUMET" style="height:24px;width:auto"><span style="color:#0E1A2F">5S <span style="color:#F26A21">SMART</span></span></a>
    <nav class="board-tabs" aria-label="เลือกรายงาน">
      <a href="Page/public/org_3month_summary.php?board=organization&end=<?=h($endKey)?>" class="<?=$boardMode==='organization'?'active':''?>">ภาพรวมองค์กร</a>
      <a href="Page/public/org_3month_summary.php?board=Production&end=<?=h($endKey)?>" class="<?=$boardMode==='Production'?'active':''?>">Production</a>
      <a href="Page/public/org_3month_summary.php?board=Office&end=<?=h($endKey)?>" class="<?=$boardMode==='Office'?'active':''?>">Office</a>
    </nav>
  </div>
  <div class="toolbar-actions">
    <button class="tool-btn primary" onclick="printPoster('A3')">🖨 พิมพ์ A3</button>
    <button class="tool-btn" onclick="printPoster('A4')">📄 พิมพ์ A4</button>
    <button class="tool-btn green" onclick="copyPoster()">📋 คัดลอกรูปส่ง LINE</button>
    <a class="tool-btn orange" href="Page/public/org_3month_summary.php?end=<?=h($endKey)?>">↩ กลับหน้าตาราง</a>
  </div>
</div>
<div class="page-note">เลือกรายงาน แล้วกด “พิมพ์ A3/A4” (แนวนอน 1 หน้า) หรือ “คัดลอกรูปส่ง LINE” แล้วกด Ctrl+V ในแชท LINE • ขนาดตัวอักษรออกแบบให้อ่านได้จากระยะ 1–2 เมตรบนบอร์ด</div>
<div class="poster-wrap">
  <article id="reportPoster" class="poster">
    <header class="p-head">
      <div class="p-brand"><img src="assets/alumet-logo.png" alt="ALUMET"><span class="sep"></span><div class="app">5S <span>SMART</span><small>CLEAN TODAY • BETTER TOMORROW</small></div></div>
      <div class="p-title"><h1><?=$isOrgBoard?'สรุปผล 5 ส. ภาพรวมองค์กร':'สรุปผล 5 ส. — '.h($boardDept)?></h1><div class="en">5S Performance Report • <?=$isOrgBoard?'ORGANIZATION OVERVIEW':($boardDept==='Production'?'PRODUCTION PERFORMANCE':'OFFICE PERFORMANCE')?></div></div>
      <div class="p-period"><small>รอบรายงาน</small><strong><?=h($periodShort)?></strong><span>ข้อมูล ณ <?=h($boardDateGenerated)?></span></div>
    </header>

    <section class="p-kpis">
      <div class="kpi-main">
        <div class="lbl">คะแนนเฉลี่ย เดือน<?=h($mLast['label'])?> <?=$mLast['be']?> • <?=h($boardScopeName)?></div>
        <div class="val"><?=$boardLatestAvg===null?'—':number_format($boardLatestAvg,2)?><sup>%</sup></div>
        <div class="row">
          <span class="chip <?=$boardStatusOk?'ok':'bad'?>"><?=$boardStatusOk?'✓':'!'?> <?=h($boardStatusText)?></span>
          <?php if(!$isOrgBoard):?><span class="chip neu">เป้า <?=number_format((float)$boardTarget,0)?>%</span><?php endif;?>
          <?php if($boardMoM!==null):?><span class="chip <?=$boardMoM>=0?'ok':'bad'?>"><?=$boardMoM>=0?'▲ +':'▼ '?><?=number_format($boardMoM,2)?> จาก <?=h($mPrev['short'])?></span><?php endif;?>
        </div>
      </div>
      <div class="kpi" style="--c:#1D4ED8"><div class="lbl">หน่วยที่มีคะแนน<small>AUDITED UNITS</small></div><div class="val"><?=number_format($boardScoredCount)?><small> / <?=number_format($boardMasterCount)?></small></div><div class="note">ครอบคลุม <?=number_format($boardCoveragePct,1)?>% ของหน่วยทั้งหมด</div><div class="bar"><i style="width:<?=max(0,min(100,$boardCoveragePct))?>%"></i></div></div>
      <div class="kpi" style="--c:#15803D"><div class="lbl">ผ่านเป้าหมาย<small>MEETING TARGET</small></div><div class="val"><?=number_format($boardPassCount)?></div><div class="note"><?=number_format($boardPassPct,1)?>% ของหน่วยที่ตรวจ</div><div class="bar"><i style="width:<?=max(0,min(100,$boardPassPct))?>%"></i></div></div>
      <div class="kpi" style="--c:#C0262D"><div class="lbl">ต่ำกว่าเป้าหมาย<small>BELOW TARGET</small></div><div class="val"><?=number_format($boardBelowCount)?></div><div class="note">ต้องติดตามการปรับปรุง</div><div class="bar"><i style="width:<?=$boardScoredCount>0?min(100,$boardBelowCount/$boardScoredCount*100):0?>%"></i></div></div>
      <div class="kpi" style="--c:#C2410C"><div class="lbl">ยังไม่มีคะแนน<small>NOT YET AUDITED</small></div><div class="val"><?=number_format($boardMissingCount)?></div><div class="note">ต้องตรวจให้ครบ</div><div class="bar"><i style="width:<?=$boardMasterCount>0?min(100,$boardMissingCount/$boardMasterCount*100):0?>%"></i></div></div>
    </section>

    <section class="p-mid">
      <div class="panel">
        <div class="panel-h"><h2>แนวโน้มคะแนนเฉลี่ย 3 เดือน</h2><small>Monthly trend</small></div>
        <div class="chart">
          <?php foreach([100,90,80,70,60] as $g):?><div class="grid" style="top:<?=($boardAxisMax-$g)/($boardAxisMax-$boardAxisMin)*100?>%"><span><?=$g?>%</span></div><?php endforeach;?>
          <?php if(!$isOrgBoard && $boardTarget):?><div class="tline" style="top:<?=100-$boardBarH($boardTarget)?>%"><span>เป้าหมาย <?=number_format((float)$boardTarget,0)?>%</span></div><?php endif;?>
          <div class="groups">
          <?php foreach($boardTrend as $i=>$m): $mr=$boardMonthRows[$i]; ?>
            <div class="grp">
              <?php if($isOrgBoard): ?>
                <div class="bar" style="--c:#0E1A2F;height:<?=$boardBarH($mr['org'])?>%"><b><?=$mr['org']===null?'—':number_format((float)$mr['org'],1)?></b></div>
                <div class="bar" style="--c:#F26A21;height:<?=$boardBarH($mr['production'])?>%"><b><?=$mr['production']===null?'—':number_format((float)$mr['production'],1)?></b></div>
                <div class="bar" style="--c:#1D4ED8;height:<?=$boardBarH($mr['office'])?>%"><b><?=$mr['office']===null?'—':number_format((float)$mr['office'],1)?></b></div>
              <?php else: $v=$m['score']; ?>
                <div class="bar" style="--c:<?=$v!==null&&$v>=$boardTarget?'#15803D':'#C0262D'?>;height:<?=$boardBarH($v)?>%;max-width:120px"><b><?=$v===null?'—':number_format((float)$v,2).'%'?></b></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          </div>
        </div>
        <div class="chart-x"><?php foreach($boardTrend as $m):?><div><?=h($m['label'])?><small><?=h($m['source'])?> • <?=number_format($m['count'])?>/<?=number_format($m['master'])?> หน่วย</small></div><?php endforeach;?></div>
        <div class="legend">
          <?php if($isOrgBoard):?><span style="--c:#0E1A2F"><i></i>ภาพรวมองค์กร</span><span style="--c:#F26A21"><i></i>Production (เป้า <?=number_format(target_pct('Production'),0)?>%)</span><span style="--c:#1D4ED8"><i></i>Office (เป้า <?=number_format(target_pct('Office'),0)?>%)</span>
          <?php else:?><span style="--c:#15803D"><i></i>ผ่านเป้าหมาย</span><span style="--c:#C0262D"><i></i>ต่ำกว่าเป้าหมาย</span><?php endif;?>
        </div>
      </div>
      <div class="panel">
        <div class="panel-h"><h2>สรุปผลสำคัญ</h2><small>Key takeaways</small></div>
        <ul class="takeaways">
          <?php foreach(array_slice($boardTakeaways,0,5) as $t):?><li class="<?=$t['ok']?'ok':'bad'?>"><span class="ic"><?=$t['ok']?'✓':'!'?></span><span><?=$t['html']?></span></li><?php endforeach;?>
        </ul>
      </div>
    </section>

    <section class="p-bottom">
      <?php if($isOrgBoard): ?>
      <div class="panel">
        <div class="panel-h"><h2>เปรียบเทียบรายแผนก</h2><small>Department comparison</small></div>
        <table class="tbl"><thead><tr><th>แผนก</th><th><?=h($org3Months[0]['short'])?></th><th><?=h($org3Months[1]['short'])?></th><th><?=h($mLast['short'])?></th><th>เป้าหมาย</th><th>ผ่าน</th><th>ต่ำกว่า</th><th>ผลประเมิน</th></tr></thead><tbody>
        <?php foreach(['Production','Office'] as $dep): $ds=$boardDeptStats[$dep]; $ok=$ds['avg']!==null && $ds['avg']>=$ds['target']; ?>
          <tr><td><?=h($dep)?></td><td><?=$boardFmt($ds['month_jul'])?></td><td><?=$boardFmt($ds['month_aug'])?></td><td style="font-weight:800;color:var(--navy)"><?=$boardFmt($ds['avg'])?></td><td><?=number_format($ds['target'],1)?>%</td><td class="pass"><?=number_format($ds['pass'])?></td><td class="fail"><?=number_format($ds['below'])?></td><td><span class="pill <?=$ok?'ok':'bad'?>"><?=$ok?'ผ่าน':'ไม่ผ่าน'?></span></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <div class="rule">เกณฑ์: หน่วยผ่านเมื่อคะแนนเฉลี่ยไม่น้อยกว่าเป้าหมายของแผนก • หน่วยที่ยังไม่มีคะแนนไม่ถูกนับเป็น 0</div>
        <?php if($boardMissingRows): ?><div class="missing"><div class="t">ยังไม่มีคะแนนเดือน<?=h($mLast['label'])?> <?=number_format(count($boardMissingRows))?> หน่วย</div><div class="chips"><?php foreach($boardMissingShown as $mrow):?><span><?=h($mrow['department'])?> · <?=h($mrow['label'])?></span><?php endforeach;?><?php if($boardExtraMissing>0):?><span>+ อีก <?=number_format($boardExtraMissing)?> หน่วย</span><?php endif;?></div></div><?php endif; ?>
      </div>
      <?php else: ?>
      <div class="panel">
        <div class="panel-h"><h2>ความครอบคลุมการตรวจ</h2><small>Audit coverage</small></div>
        <div class="cov">
          <div class="cov-line"><div class="t"><span>มีคะแนน</span><b><?=number_format($boardScoredCount)?> / <?=number_format($boardMasterCount)?> หน่วย (<?=number_format($boardCoveragePct,1)?>%)</b></div><div class="cov-track"><i style="--c:#1D4ED8;width:<?=max(0,min(100,$boardCoveragePct))?>%"></i></div></div>
          <div class="cov-line"><div class="t"><span>ผ่านเป้าหมาย</span><b><?=number_format($boardPassCount)?> หน่วย</b></div><div class="cov-track"><i style="--c:#15803D;width:<?=$boardMasterCount>0?min(100,$boardPassCount/$boardMasterCount*100):0?>%"></i></div></div>
          <div class="cov-line"><div class="t"><span>ต่ำกว่าเป้าหมาย</span><b><?=number_format($boardBelowCount)?> หน่วย</b></div><div class="cov-track"><i style="--c:#C0262D;width:<?=$boardMasterCount>0?min(100,$boardBelowCount/$boardMasterCount*100):0?>%"></i></div></div>
        </div>
        <?php if($boardMissingRows): ?><div class="missing"><div class="t">ยังไม่มีคะแนน <?=number_format(count($boardMissingRows))?> หน่วย</div><div class="chips"><?php foreach($boardMissingShown as $mrow):?><span><?=h($mrow['label'])?></span><?php endforeach;?><?php if($boardExtraMissing>0):?><span>+ อีก <?=number_format($boardExtraMissing)?> หน่วย</span><?php endif;?></div></div><?php else: ?><div class="good-box" style="margin-top:12px">✓ ทุกหน่วยมีคะแนนในเดือน<?=h($mLast['label'])?></div><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="panel">
        <div class="panel-h"><h2>หน่วยที่ต้องเร่งปรับปรุง</h2><small>Priority actions • lowest first</small></div>
        <?php if($boardPriorities): ?>
        <div class="prio <?=count($boardPriorities)<=4?'one':''?>">
          <?php foreach($boardPriorities as $idx=>$r):?><div class="prio-row"><div class="no"><?=$idx+1?></div><div class="nm"><?=h($r['label'])?><small><?=h($r['department'])?> • เป้า <?=number_format($r['target'],0)?>%</small></div><div class="sc"><?=number_format((float)$r['score'],2)?>%<small>ขาด <?=number_format(abs((float)$r['delta']),2)?> จุด</small></div></div><?php endforeach;?>
        </div>
        <?php if($boardExtraBelow>0):?><div class="more">และยังต่ำกว่าเป้าหมายอีก <?=number_format($boardExtraBelow)?> หน่วย (ดูรายละเอียดในระบบ 5S SMART)</div><?php endif;?>
        <?php else: ?><div class="good-box">✓ ทุกหน่วยที่ตรวจแล้วผ่านเป้าหมาย</div><?php endif; ?>
      </div>
    </section>

    <footer class="p-foot">
      <div class="l"><img src="assets/alumet-logo.png" alt="ALUMET"><span><b>5S SMART Management System</b></span></div>
      <div>ที่มา: <?php foreach($org3Months as $__i=>$__mo):?><?=$__i?' • ':''?><?=h($__mo['short'])?> <?=$__mo['live']?'ระบบ':'Excel'?><?php endforeach;?> • พิมพ์ <?=h($boardDateGenerated)?></div>
      <div class="tag">CLEAN TODAY • BETTER TOMORROW</div>
    </footer>
  </article>
</div>
<div id="boardFeedback" class="feedback" role="status" aria-live="polite"></div>
<script>
const boardName = <?=json_encode($isOrgBoard?'Organization':$boardDept,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const boardThaiName = <?=json_encode($isOrgBoard?'ภาพรวมองค์กร':($boardDept==='Production'?'Production':'Office'),JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
function feedback(msg){const e=document.getElementById('boardFeedback');e.textContent=msg;e.classList.add('show');window.setTimeout(()=>e.classList.remove('show'),4200);}
async function renderPosterCanvas(scale=2){
  if(typeof window.html2canvas!=='function') throw new Error('โหลดเครื่องมือสร้างภาพไม่สำเร็จ กรุณาตรวจอินเทอร์เน็ตแล้วรีเฟรชหน้า');
  if(document.fonts&&document.fonts.ready){try{await document.fonts.ready;}catch(e){}}
  const node=document.getElementById('reportPoster');
  return await FiveSCopy.render(node,{backgroundColor:'#ffffff',scale,useCORS:true,logging:false,allowTaint:false,windowWidth:Math.max(document.documentElement.clientWidth,1700),scrollX:0,scrollY:-window.scrollY});
}
async function downloadPoster(){
  try{feedback('กำลังสร้างภาพความละเอียดสูง…');const canvas=await renderPosterCanvas();const a=document.createElement('a');a.download='5S_'+boardName+'_'+<?=json_encode(str_replace([' ','—'],['','-'],$periodTag))?>+'.png';a.href=canvas.toDataURL('image/png');a.click();feedback('สร้างไฟล์ PNG แล้ว');}
  catch(e){alert(e.message||'สร้างภาพไม่สำเร็จ');}
}
function copyPoster(){
  FiveSCopy.copyFrom(()=>renderPosterCanvas(),'5S_'+boardName+'_'+<?=json_encode(str_replace([' ','—'],['','-'],$periodTag))?>+'.png');
}
async function printPoster(paper){
  const pageSize = paper === 'A3' ? 'A3' : 'A4';
  // Open synchronously from the user's click so Chrome does not block the print window.
  const printWin = window.open('', '_blank');
  if (!printWin) {
    alert('เบราว์เซอร์บล็อกหน้าต่างพิมพ์ กรุณาอนุญาต Pop-ups สำหรับ localhost แล้วลองใหม่');
    return;
  }
  printWin.document.open();
  printWin.document.write('<!doctype html><html lang="th"><head><meta charset="utf-8"><title>กำลังเตรียมรายงาน 5S</title><style>html,body{margin:0;padding:0;background:#fff;font-family:Tahoma,Arial,sans-serif}body{padding:24px;color:#14233b} .wait{font-weight:700;text-align:center;margin-top:20vh}</style></head><body><div class="wait">กำลังเตรียมรายงานให้พิมพ์ในกระดาษ '+pageSize+' หน้าเดียว…</div></body></html>');
  printWin.document.close();
  try {
    feedback('กำลังจัดรายงานให้พอดีกระดาษ '+pageSize+' หน้าเดียว…');
    const canvas = await renderPosterCanvas(2.5);
    const blob = await new Promise((resolve,reject)=>canvas.toBlob(b=>b?resolve(b):reject(new Error('สร้างภาพรายงานไม่สำเร็จ')),'image/png'));
    const imageUrl = URL.createObjectURL(blob);
    printWin.document.open();
    printWin.document.write(`<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>5S SMART | ${pageSize}</title><style>@page{size:${pageSize} landscape;margin:0}*{box-sizing:border-box}html,body{width:100%;height:100%;margin:0!important;padding:0!important;background:#fff;overflow:hidden}.sheet{width:100%;height:100%;display:flex;align-items:center;justify-content:center;overflow:hidden}img{display:block;width:100%;height:100%;object-fit:contain;object-position:center}@media screen{html,body{height:auto;min-height:100%;background:#e9eef5}.sheet{width:min(96vw,1500px);height:auto;aspect-ratio:${pageSize==='A3'?'420/297':'297/210'};margin:2vh auto;background:#fff;box-shadow:0 4px 24px #14233b22}img{width:100%;height:100%;object-fit:contain}}</style></head><body><div class="sheet"><img id="reportImage" src="${imageUrl}" alt="5S SMART report"></div><script>const img=document.getElementById('reportImage');img.onload=()=>setTimeout(()=>{window.focus();window.print();},350);window.onafterprint=()=>{try{window.opener.URL.revokeObjectURL('${imageUrl}')}catch(e){}setTimeout(()=>window.close(),250)};<\/script></body></html>`);
    printWin.document.close();
    feedback('เปิดหน้าพิมพ์ '+pageSize+' แบบหน้าเดียวแล้ว');
  } catch (e) {
    try { printWin.close(); } catch (_) {}
    alert('เตรียมหน้าพิมพ์ไม่สำเร็จ: '+(e && e.message ? e.message : 'กรุณาลองใหม่'));
  }
}
</script>
</body>
</html>
