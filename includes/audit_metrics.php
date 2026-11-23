<?php
/**
 * Shared definitions of "inspection status" and "unit score".
 * Every page (save forms, plan sync, Dashboard, completion summary, graphs) reads these,
 * so one unit can never show two different states. Nothing here edits score data.
 */
require_once __DIR__.'/auto_schedule_status.php';

if (!function_exists('five_s_begin_write')) {
    /** Start a transaction that holds the write lock from the first statement, so two simultaneous saves run one after the other. */
    function five_s_begin_write(PDO $p): void {
        $p->beginTransaction();
        /* MySQL/InnoDB: lock one known row so concurrent saves queue up (SQLite took the DB write lock here). */
        $p->query("SELECT `value` FROM app_settings WHERE `key`='target_pct' FOR UPDATE")->fetchAll();
    }

    /** @return string empty when the date is a valid Christian-era Y-m-d */
    function five_s_audit_date_error(string $date): string {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return 'วันที่ตรวจไม่ถูกต้อง';
        if ((int)$m[1] < 2000 || (int)$m[1] > 2100) return 'กรุณาระบุปีเป็น ค.ศ. (เช่น 2026) — ระบบไม่รับปี พ.ศ. ในช่องวันที่';
        return '';
    }

    /** True when the submitted form contains at least one score or N/A mark. */
    function five_s_post_has_answers(array $criteria): bool {
        foreach ($criteria as $c) {
            $id = $c['id'];
            if (!empty($_POST['na'][$id])) return true;
            for ($i = 1; $i <= 3; $i++) if (($_POST['score'.$i][$id] ?? '') !== '') return true;
        }
        return false;
    }

    /** Stored plan status -> wording shown on screen. Stored values stay unchanged. */
    function five_s_plan_status_label(string $status): string {
        return ['รอตรวจ' => 'ยังไม่ได้ตรวจ', 'ตรวจบางส่วน' => 'กำลังตรวจ'][trim($status)] ?? trim($status);
    }

    /** Answered = has a score, or is marked N/A. @return array{required:int,answered:int,complete:bool} */
    function five_s_audit_progress(PDO $p, int $auditId, bool $isOffice): array {
        $score = $isOffice ? 'office_scores' : 'scores';
        $crit  = $isOffice ? 'office_criteria' : 'criteria';
        $q = $p->prepare("SELECT COUNT(*) required, COALESCE(SUM(CASE WHEN s.id IS NOT NULL AND (
                (s.score1 IS NOT NULL AND TRIM(CAST(s.score1 AS CHAR))<>'') OR
                (s.score2 IS NOT NULL AND TRIM(CAST(s.score2 AS CHAR))<>'') OR
                (s.score3 IS NOT NULL AND TRIM(CAST(s.score3 AS CHAR))<>'') OR
                UPPER(TRIM(COALESCE(s.note,''))) LIKE 'N/A:%') THEN 1 ELSE 0 END),0) answered
            FROM {$crit} c LEFT JOIN {$score} s ON s.criterion_id=c.id AND s.audit_id=? WHERE c.active=1");
        $q->execute([$auditId]);
        $r = $q->fetch(PDO::FETCH_ASSOC);
        return ['required'=>(int)$r['required'], 'answered'=>(int)$r['answered'], 'complete'=>(int)$r['required'] > 0 && (int)$r['answered'] >= (int)$r['required']];
    }

    /** Latest audit of one area in a period. @return string none|partial|done */
    function five_s_unit_state(PDO $p, array $area, string $start, string $end): string {
        $q = $p->prepare("SELECT id FROM audits WHERE area_id=? AND audit_date BETWEEN ? AND ? ORDER BY audit_date DESC,id DESC LIMIT 1");
        $q->execute([(int)$area['id'], $start, $end]);
        $auditId = (int)$q->fetchColumn();
        if ($auditId < 1) return 'none';
        $g = five_s_audit_progress($p, $auditId, trim((string)$area['department']) === 'Office');
        return $g['complete'] ? 'done' : ($g['answered'] > 0 ? 'partial' : 'none');
    }

    /**
     * One row per Master unit (duplicates of the same group+unit merged), scored from the latest audit that has
     * scores in the period. Source used by Dashboard, the overview and the printed graphs.
     * @return array{all:array,rows:array,missing:array,total:int,scored:int,avg:?float}
     */
    function five_s_month_unit_scores(PDO $p, string $dept, string $start, string $end): array {
        $summaryFn = $dept === 'Office' ? 'office_audit_summary' : 'production_audit_summary';
        $units = [];
        $q = $p->prepare("SELECT id,group_name,unit_no,area_name,active FROM areas WHERE department=? ORDER BY active DESC, group_name COLLATE utf8mb4_general_ci, CAST(unit_no AS SIGNED), unit_no");
        $q->execute([$dept]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $group = trim((string)display_area_group((string)$a['group_name']));
            if ($group === '') $group = trim((string)$a['group_name']);
            if ($group === '') $group = 'ไม่ระบุกลุ่ม';
            $unit = trim((string)$a['unit_no']);
            $key = $group.'|'.$unit;
            if (!isset($units[$key])) {
                if ((int)$a['active'] !== 1) continue; // inactive-only records are not part of the Master
                $label = $unit !== '' ? $group.' • หน่วย '.$unit : (trim((string)$a['area_name']) ?: $group);
                $units[$key] = ['group'=>$group, 'unit'=>$unit, 'label'=>$label, 'ids'=>[]];
            }
            $units[$key]['ids'][] = (int)$a['id'];
        }
        $q = $p->prepare("SELECT au.id,au.area_id,au.audit_date FROM audits au JOIN areas a ON a.id=au.area_id
                          WHERE a.department=? AND au.audit_date BETWEEN ? AND ? ORDER BY au.audit_date DESC,au.id DESC");
        $q->execute([$dept, $start, $end]);
        $byArea = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $au) $byArea[(int)$au['area_id']][] = $au;
        $all = []; $rows = []; $missing = [];
        foreach ($units as $u) {
            $best = null;
            foreach ($u['ids'] as $aid) foreach (($byArea[$aid] ?? []) as $au) {
                $s = $summaryFn($p, (int)$au['id']);
                if (($s['max'] ?? 0) > 0) {
                    if ($best === null || $au['audit_date'] > $best['date']) $best = ['pct'=>round((float)$s['pct'],2), 'date'=>$au['audit_date'], 'audit_id'=>(int)$au['id']];
                    break; // audits are newest first; older ones of this area cannot win
                }
            }
            $row = $u + ['pct'=>$best['pct'] ?? null, 'date'=>$best['date'] ?? null, 'audit_id'=>$best['audit_id'] ?? null];
            $all[] = $row;
            if ($best) $rows[] = $row; else $missing[] = $u['label'];
        }
        $avg = $rows ? round(array_sum(array_column($rows,'pct'))/count($rows),2) : null;
        return ['all'=>$all, 'rows'=>$rows, 'missing'=>$missing, 'total'=>count($units), 'scored'=>count($rows), 'avg'=>$avg];
    }

    /** Warn when a unit has no plan in the month of the chosen audit date. @return ?array{month:string,suggest:?string} */
    function five_s_plan_month_hint(PDO $p, array $area, string $date): ?array {
        if (five_s_audit_date_error($date) !== '') return null;
        $group = five_s_plan_normalize((string)($area['group_name'] ?? ''));
        $unit = trim((string)($area['unit_no'] ?? ''));
        $find = function (int $m, int $be) use ($p, $group, $unit) {
            $q = $p->prepare("SELECT department,area_5s,inspection_date FROM audit_plans WHERE active=1 AND month=? AND year=? AND TRIM(area_5s)<>''");
            $q->execute([$m, $be]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $pl) {
                $units = array_map('trim', explode(',', (string)$pl['area_5s']));
                $aliases = array_map('five_s_plan_normalize', five_s_plan_group_aliases((string)$pl['department']));
                if (in_array($unit, $units, true) && in_array($group, $aliases, true)) return ['date'=>trim((string)$pl['inspection_date'])];
            }
            return null;
        };
        $d = new DateTimeImmutable($date.' 12:00:00');
        if ($find((int)$d->format('n'), (int)$d->format('Y') + 543) !== null) return null;
        $suggest = null;
        foreach ([$d->modify('first day of last month'), $d->modify('first day of next month')] as $o) {
            $hit = $find((int)$o->format('n'), (int)$o->format('Y') + 543);
            if ($hit !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hit['date'])) { $suggest = $hit['date']; break; }
        }
        return ['month'=>five_s_thai_months()[(int)$d->format('n')].' '.((int)$d->format('Y') + 543), 'suggest'=>$suggest];
    }

    /** Team responsible for a unit in the month of a date, with that team's auditors for that month. @return ?array{team:string,names:array} */
    function five_s_team_roster_for_area(PDO $p, array $area, string $date): ?array {
        if (five_s_audit_date_error($date) !== '') return null;
        $ts = strtotime($date);
        $month = (int)date('n', $ts); $be = (int)date('Y', $ts) + 543;
        $group = five_s_plan_normalize((string)($area['group_name'] ?? ''));
        $unit = trim((string)($area['unit_no'] ?? ''));
        $q = $p->prepare("SELECT team,department,area_5s FROM audit_plans WHERE active=1 AND month=? AND year=? AND TRIM(area_5s)<>'' ORDER BY team,sort_no,id");
        $q->execute([$month, $be]);
        $team = null;
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $pl) {
            $aliases = array_map('five_s_plan_normalize', five_s_plan_group_aliases((string)$pl['department']));
            if (in_array($unit, array_map('trim', explode(',', (string)$pl['area_5s'])), true) && in_array($group, $aliases, true)) { $team = (string)$pl['team']; break; }
        }
        if ($team === null) return null;
        $n = $p->prepare("SELECT auditor_name FROM audit_plans WHERE active=1 AND month=? AND year=? AND team=? AND TRIM(auditor_name)<>'' ORDER BY sort_no,id");
        $n->execute([$month, $be, $team]);
        $names = [];
        foreach ($n->fetchAll(PDO::FETCH_COLUMN) as $name) { $x = preg_replace('/\s+/u', ' ', trim((string)$name)); if (!in_array($x, $names, true)) $names[] = $x; }
        return ['team'=>$team, 'names'=>$names];
    }

    /** Monthly unit-level completeness used by the summary page and the Dashboard. */
    function build_completion(PDO $p, string $dept, string $start, string $end, int $expectedCriteria): array {
        // Use active Master areas plus inactive areas that actually have an audit in this month.
        $areasQ = $p->prepare("SELECT DISTINCT ar.id,ar.group_name,ar.unit_no,ar.area_name
            FROM areas ar LEFT JOIN audits ax ON ax.area_id=ar.id AND ax.audit_date BETWEEN ? AND ?
            WHERE TRIM(ar.department)=? AND (ar.active=1 OR ax.id IS NOT NULL)
            ORDER BY ar.group_name, CAST(ar.unit_no AS SIGNED), ar.unit_no");
        $areasQ->execute([$start, $end, $dept]);
        $groups = []; $areaMap = [];
        foreach ($areasQ as $r) {
            $g = display_area_group((string)$r['group_name']); if ($g === '') $g = (string)$r['group_name'];
            $u = (string)$r['unit_no'];
            if (!isset($groups[$g])) $groups[$g] = ['expected_units'=>[], 'area_ids'=>[], 'names'=>[]];
            $groups[$g]['expected_units'][$u] = 1;
            $groups[$g]['area_ids'][(int)$r['id']] = 1;
            $groups[$g]['names'][] = $r['area_name'];
            $areaMap[(int)$r['id']] = ['group'=>$g, 'unit'=>$u];
        }
        /* Department comes from the linked Master area, not the denormalized audits.department. */
        $aq = $p->prepare("SELECT a.id,a.area_id,a.audit_date,a.auditor FROM audits a JOIN areas ar ON ar.id=a.area_id
                           WHERE TRIM(ar.department)=? AND a.audit_date BETWEEN ? AND ? ORDER BY a.audit_date DESC,a.id DESC");
        $aq->execute([$dept, $start, $end]);
        $latestAuditByArea = [];
        foreach ($aq as $r) {
            $areaId = (int)$r['area_id'];
            if (isset($areaMap[$areaId]) && !isset($latestAuditByArea[$areaId])) $latestAuditByArea[$areaId] = $r;
        }
        $auditState = [];
        foreach ($latestAuditByArea as $ar) {
            $g = five_s_audit_progress($p, (int)$ar['id'], $dept === 'Office');
            $auditState[(int)$ar['id']] = ['complete'=>$g['complete'], 'covered'=>$g['answered'], 'answered'=>$g['answered'] > 0,
                'audit_date'=>$ar['audit_date'], 'auditor'=>(string)($ar['auditor'] ?? ''), 'expected'=>$expectedCriteria];
        }
        $out = []; $completeGroups = $partialGroups = $noneGroups = 0;
        $expectedUnits = $recordedUnits = $completeUnits = $partialUnits = $missingUnits = 0;
        $auditTotal = count($latestAuditByArea); $scoreComplete = 0; $scoreAudits = count($latestAuditByArea);
        foreach ($groups as $g => $info) {
            $exp = array_keys($info['expected_units']); sort($exp, SORT_NATURAL);
            $complete = []; $partial = []; $missing = []; $recorded = []; $auditDetails = [];
            foreach ($exp as $u) {
                // Duplicated Master records of one unit: read whichever record was audited most recently.
                $foundAreaId = null; $foundDate = '';
                foreach ($info['area_ids'] as $areaId => $dummy) {
                    if (($areaMap[$areaId]['unit'] ?? '') !== (string)$u) continue;
                    if ($foundAreaId === null) $foundAreaId = $areaId;
                    $d = (string)($latestAuditByArea[$areaId]['audit_date'] ?? '');
                    if ($d !== '' && $d > $foundDate) { $foundDate = $d; $foundAreaId = $areaId; }
                }
                if ($foundAreaId === null || !isset($latestAuditByArea[$foundAreaId])) { $missing[] = $u; continue; }
                $state = $auditState[(int)$latestAuditByArea[$foundAreaId]['id']] ?? null;
                if (!$state || !$state['answered']) { $missing[] = $u; continue; }
                $recorded[] = $u; $recordedUnits++;
                $auditDetails[(string)$u] = ['date'=>(string)$state['audit_date'], 'auditor'=>(string)$state['auditor'], 'covered'=>(int)$state['covered'], 'expected'=>(int)$state['expected']];
                if ($state['complete']) { $complete[] = $u; $completeUnits++; } else { $partial[] = $u; $partialUnits++; }
            }
            $expectedUnits += count($exp); $missingUnits += count($missing);
            $nComplete = count($complete); $nOutstanding = count($partial) + count($missing);
            if (count($recorded) === 0 && count($exp) > 0) { $status = 'ยังไม่ได้ตรวจ'; $statusClass = 'bad'; $noneGroups++; }
            elseif ($nOutstanding === 0) { $status = 'ตรวจแล้ว'; $statusClass = 'ok'; $completeGroups++; }
            else { $status = 'กำลังตรวจ'; $statusClass = 'warn'; $partialGroups++; }
            $completeAuditsForGroup = 0;
            foreach ($info['area_ids'] as $areaId => $dummy) {
                if (!isset($latestAuditByArea[$areaId])) continue;
                $state = $auditState[$latestAuditByArea[$areaId]['id']] ?? null;
                if ($state && $state['complete']) $completeAuditsForGroup++;
            }
            $scoreComplete += $completeAuditsForGroup;
            $out[] = ['group'=>$g, 'expected'=>$exp, 'recorded'=>$recorded, 'audit_details'=>$auditDetails, 'actual'=>$complete, 'partial'=>$partial, 'missing'=>$missing,
                'audits'=>$nComplete + count($partial), 'score_complete'=>$completeAuditsForGroup, 'score_audits'=>$nComplete + count($partial), 'status'=>$status, 'statusClass'=>$statusClass];
        }
        return ['dept'=>$dept, 'groups'=>$out, 'group_total'=>count($groups), 'complete_groups'=>$completeGroups, 'partial_groups'=>$partialGroups, 'none_groups'=>$noneGroups,
            'expected_units'=>$expectedUnits, 'recorded_units'=>$recordedUnits, 'audited_units'=>$completeUnits, 'partial_units'=>$partialUnits, 'missing_units'=>$missingUnits,
            'unit_pct'=>$expectedUnits ? round($completeUnits/$expectedUnits*100,1) : 0, 'audit_total'=>$auditTotal, 'score_complete'=>$scoreComplete, 'score_audits'=>$scoreAudits,
            'score_pct'=>$scoreAudits ? round($scoreComplete/$scoreAudits*100,1) : 0];
    }
}
