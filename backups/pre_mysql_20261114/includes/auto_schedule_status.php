<?php
/**
 * Synchronize monthly inspection-plan status from saved 5S audit records.
 * The audit/audit-score tables are the source of truth; this never edits scores.
 */
if (!function_exists('five_s_plan_normalize')) {
    function five_s_plan_normalize(string $value): string {
        $value = trim($value);
        $value = preg_replace('/^[\x{0E00}-\x{0E4E}\s]+/u', '', $value) ?? $value;
        $value = mb_strtoupper($value, 'UTF-8');
        return preg_replace('/[^A-Z0-9ก-๙]/u', '', $value) ?? $value;
    }
}

if (!function_exists('five_s_plan_group_aliases')) {
    function five_s_plan_group_aliases(string $department): array {
        $key = five_s_plan_normalize($department);
        $map = [
            'EXT2400'       => ['2400'],
            '2400'          => ['2400'],
            'EXT2000'       => ['2000', 'OFFICEEXT2000'],
            '2000'          => ['2000', 'OFFICEEXT2000'],
            'EXT8801'       => ['8801'],
            '8801'          => ['8801'],
            'EXT8802'       => ['8802'],
            '8802'          => ['8802'],
            'DIEPC'         => ['DIEPC'],
            'DIEDESIGN'     => ['DIEDESIGN'],
            'NEWMODEL'      => ['NEWMODEL'],
            'NEWMEDEL'      => ['NEWMODEL'],
            'QA'            => ['QMS', 'QA'],
            'BILLETSCARP'   => ['BILLETSCARP', 'PURCHASING'],
            'RDHUB'         => ['RD', 'HUB'],
            'ACCBUD'        => ['ACC'],
            'FIN'           => ['FINANCE', 'FIN'],
            'AO'            => ['AOAE', 'AO'],
            'PDC2'          => ['PDC2', 'PDC'],
            'MNTE'          => ['MNTE', 'MNT'],
            'MIS'           => ['MISEXT880', 'MIS'],
            'PDC1'          => ['PDC1'],
            'PPT1'          => ['PPT1'],
            'PPT2'          => ['PPT2'],
            'PURCHASING'    => ['PURCHASING'],
            'CX'            => ['CX'],
            'WHL'           => ['WHL'],
            'ANO'           => ['ANO'],
            'DIEMAKING'     => ['DIEMAKING'],
            'PLANNING'      => ['PLANNING'],
            'IT'            => ['IT'],
            'HRD'           => ['HRD'],
            'GA'            => ['GA'],
        ];
        return $map[$key] ?? [$key];
    }
}

if (!function_exists('five_s_sync_after_save')) {
    /** Refresh plan status for the month of each date, right after audit data changed. The save itself stays successful if this fails. */
    function five_s_sync_after_save(PDO $p, string ...$dates): void {
        $done = [];
        foreach ($dates as $date) {
            $ts = strtotime($date);
            if ($ts === false) continue;
            $ym = date('Y-n', $ts);
            if (isset($done[$ym])) continue;
            $done[$ym] = true;
            try { sync_monthly_audit_plan_statuses($p, (int)date('n', $ts), (int)date('Y', $ts) + 543); }
            catch (Throwable $e) { error_log('5S plan status sync failed for '.$ym.': '.$e->getMessage()); }
        }
    }
}

if (!function_exists('sync_monthly_audit_plan_statuses')) {
    /** @return array{updated:int,done:int,partial:int,unstarted:int} */
    function sync_monthly_audit_plan_statuses(PDO $p, int $month, int $buddhistYear): array {
        $result = ['updated'=>0, 'done'=>0, 'partial'=>0, 'unstarted'=>0];
        if ($month < 1 || $month > 12 || $buddhistYear < 2400) return $result;

        $year = $buddhistYear >= 2400 ? $buddhistYear - 543 : $buddhistYear;
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        $plansQ = $p->prepare("SELECT id,department,area_5s,status FROM audit_plans
            WHERE month=? AND year=? AND active=1 AND TRIM(COALESCE(area_5s,''))<>''");
        $plansQ->execute([$month, $buddhistYear]);
        $plans = $plansQ->fetchAll(PDO::FETCH_ASSOC);
        if (!$plans) return $result;

        $allUnits = [];
        $planUnits = [];
        foreach ($plans as $plan) {
            $units = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)$plan['area_5s'])), static fn($v) => $v !== '')));
            $aliases = array_values(array_unique(array_map('five_s_plan_normalize', five_s_plan_group_aliases((string)$plan['department']))));
            $planUnits[(int)$plan['id']] = ['units'=>$units, 'aliases'=>$aliases];
            foreach ($units as $unit) $allUnits[$unit] = true;
        }
        if (!$allUnits) return $result;

        $marks = implode(',', array_fill(0, count($allUnits), '?'));
        $areaQ = $p->prepare("SELECT id,department,group_name,unit_no FROM areas WHERE TRIM(unit_no) IN ({$marks})");
        $areaQ->execute(array_keys($allUnits));
        $areasByUnit = [];
        foreach ($areaQ->fetchAll(PDO::FETCH_ASSOC) as $area) {
            $unit = trim((string)$area['unit_no']);
            $group = five_s_plan_normalize((string)$area['group_name']);
            if (!isset($areasByUnit[$unit])) $areasByUnit[$unit] = [];
            $areasByUnit[$unit][] = $area + ['normalized_group'=>$group];
        }

        $updateQ = $p->prepare("UPDATE audit_plans SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND COALESCE(status,'')<>?");
        foreach ($plans as $plan) {
            $pid = (int)$plan['id'];
            $meta = $planUnits[$pid] ?? ['units'=>[], 'aliases'=>[]];
            $units = $meta['units'];
            $aliases = $meta['aliases'];
            if (!$units || !$aliases) continue;

            $saved = 0;
            $done = 0;
            $mapped = 0;
            foreach ($units as $unit) {
                $candidates = [];
                foreach (($areasByUnit[$unit] ?? []) as $area) {
                    if (in_array($area['normalized_group'], $aliases, true)) $candidates[] = $area;
                }
                if (!$candidates) continue;
                $mapped++;
                $state = 'none';
                foreach ($candidates as $area) {
                    $s = five_s_unit_state($p, $area, $start, $end);
                    if ($s === 'done') { $state = 'done'; break; }
                    if ($s === 'partial') $state = 'partial';
                }
                if ($state !== 'none') $saved++;
                if ($state === 'done') $done++;
            }

            if ($saved === 0) {
                // Nothing recorded: never show done/in-progress. A postponed plan stays postponed.
                $newStatus = in_array(trim((string)($plan['status'] ?? '')), ['ตรวจแล้ว','ตรวจบางส่วน'], true) ? 'รอตรวจ' : null;
            } elseif ($mapped === count($units) && $done === count($units)) {
                // Every planned unit exists in Master and has every required item answered (N/A counts as answered).
                $newStatus = 'ตรวจแล้ว';
            } else {
                $newStatus = 'ตรวจบางส่วน';
            }

            if ($newStatus === null) continue;
            if ($newStatus === 'ตรวจแล้ว') $result['done']++;
            elseif ($newStatus === 'ตรวจบางส่วน') $result['partial']++;
            else $result['unstarted']++;

            if (trim((string)($plan['status'] ?? '')) !== $newStatus) {
                $updateQ->execute([$newStatus, $pid, $newStatus]);
                if ($updateQ->rowCount() > 0) $result['updated']++;
            }
        }
        return $result;
    }
}
