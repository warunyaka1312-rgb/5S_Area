<?php
/**
 * 5S SMART | ALUMET — Automation engine (V60)
 * ------------------------------------------------------------------
 * Runs quietly on every page that loads includes/header.php.
 *  1) Daily auto-backup of the MySQL database to backups/auto_daily_YYYYMMDD.sql (keeps the latest 30 days)
 *  2) Auto-sync of inspection-plan status (current + previous month)
 *     from the scores that were actually saved — never edits scores
 *  3) Live alert counters for the top bar / dashboard action center
 * Every step is wrapped in try/catch so automation can never stop a page.
 */
require_once __DIR__.'/config.php';

/* ค่าเหล่านี้ตั้งได้ที่ .env (APP_VERSION / AUTO_BACKUP_KEEP / AUTO_SYNC_MINUTES) */
if (!defined('FIVE_S_APP_VERSION')) define('FIVE_S_APP_VERSION', five_s_env_str('APP_VERSION', 'V71'));
if (!defined('FIVE_S_AUTO_BACKUP_KEEP')) define('FIVE_S_AUTO_BACKUP_KEEP', max(1, five_s_env_int('AUTO_BACKUP_KEEP', 30)));
if (!defined('FIVE_S_AUTO_SYNC_MINUTES')) define('FIVE_S_AUTO_SYNC_MINUTES', max(1, five_s_env_int('AUTO_SYNC_MINUTES', 10)));

if (!function_exists('five_s_backup_dir')) {
    function five_s_backup_dir(): string { return dirname(__DIR__).'/backups'; }
}

if (!function_exists('five_s_auto_backup')) {
    /** Create one database snapshot per day. Returns the snapshot file name or ''. */
    function five_s_auto_backup(): string {
        try {
            $dir = five_s_backup_dir();
            if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return '';
            $name = 'auto_daily_'.date('Ymd').'.sql';
            $file = $dir.'/'.$name;
            if (is_file($file) && filesize($file) > 0) return $name;

            // MySQL: full .sql dump (structure + data) written by PHP — no mysqldump needed.
            if (!five_s_backup_to_file($file)) return '';

            // Retention: keep only the newest N automatic daily snapshots (old .sqlite snapshots stay untouched).
            $all = glob($dir.'/auto_daily_*.sql') ?: [];
            rsort($all);
            foreach (array_slice($all, FIVE_S_AUTO_BACKUP_KEEP) as $old) @unlink($old);
            save_app_setting('auto_backup_last', date('Y-m-d H:i:s'));
            return $name;
        } catch (Throwable $e) { return ''; }
    }
}

if (!function_exists('five_s_backup_to_file')) {
    /** Dump the whole MySQL database to a .sql file. Returns true on success. */
    function five_s_backup_to_file(string $file): bool {
        try {
            return five_s_mysql_dump_to_file(db(), $file);
        } catch (Throwable $e) {
            if (is_file($file)) @unlink($file);
            return false;
        }
    }
}

if (!function_exists('five_s_auto_backup_list')) {
    /** @return array<int,array{name:string,size:int,time:int}> newest first */
    function five_s_auto_backup_list(): array {
        $out = [];
        foreach (glob(five_s_backup_dir().'/*.{sql,sqlite,json}', GLOB_BRACE) ?: [] as $f) {
            $out[] = ['name'=>basename($f), 'size'=>(int)@filesize($f), 'time'=>(int)@filemtime($f)];
        }
        usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }
}

if (!function_exists('five_s_auto_sync_plans')) {
    /** Sync plan status for the current and previous month, throttled. */
    function five_s_auto_sync_plans(bool $force = false): array {
        $res = ['ran'=>false, 'updated'=>0];
        try {
            $last = strtotime(app_setting('auto_sync_at', '1970-01-01 00:00:00')) ?: 0;
            if (!$force && (time() - $last) < FIVE_S_AUTO_SYNC_MINUTES * 60) return $res;
            require_once __DIR__.'/auto_schedule_status.php';
            $p = db();
            $now = new DateTimeImmutable('first day of this month');
            foreach ([$now, $now->modify('-1 month')] as $d) {
                $r = sync_monthly_audit_plan_statuses($p, (int)$d->format('n'), (int)$d->format('Y') + 543);
                $res['updated'] += (int)($r['updated'] ?? 0);
            }
            $res['ran'] = true;
            save_app_setting('auto_sync_at', date('Y-m-d H:i:s'));
            if ($res['updated'] > 0) save_app_setting('auto_sync_last_change', date('Y-m-d H:i:s').'|'.$res['updated']);
        } catch (Throwable $e) { /* never block a page */ }
        return $res;
    }
}

if (!function_exists('five_s_alerts')) {
    /** Live counters used by the top bar bell and the dashboard action center. */
    function five_s_alerts(): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        $a = [
            'tag_open'=>0, 'tag_overdue'=>0, 'tag_total'=>0,
            'plan_total'=>0, 'plan_done'=>0, 'plan_partial'=>0, 'plan_wait'=>0, 'plan_today'=>0, 'plan_next'=>null,
            'latest_audit'=>'', 'audits_month'=>0,
            'backup_last'=>'', 'sync_at'=>'',
            'month'=>(int)date('n'), 'year_be'=>(int)date('Y') + 543, 'plan_fallback'=>false,
        ];
        try {
            $p = db();
            $today = date('Y-m-d');
            $t = $p->query("SELECT COUNT(*) total,
                    SUM(CASE WHEN status<>'CLOSED' THEN 1 ELSE 0 END) open_count,
                    SUM(CASE WHEN status<>'CLOSED' AND TRIM(due_date)<>'' AND due_date<".$p->quote($today)." THEN 1 ELSE 0 END) overdue
                    FROM tags")->fetch();
            $a['tag_total'] = (int)($t['total'] ?? 0);
            $a['tag_open'] = (int)($t['open_count'] ?? 0);
            $a['tag_overdue'] = (int)($t['overdue'] ?? 0);
        } catch (Throwable $e) {}
        try {
            $p = db();
            // If the current month has no plan yet, show the latest planned month automatically.
            $cnt = $p->prepare("SELECT COUNT(*) FROM audit_plans WHERE active=1 AND month=? AND year=?");
            $cnt->execute([$a['month'], $a['year_be']]);
            if ((int)$cnt->fetchColumn() === 0) {
                $lp = $p->prepare("SELECT year,month FROM audit_plans WHERE active=1 AND (year*100+month)<=? ORDER BY year DESC,month DESC LIMIT 1");
                $lp->execute([$a['year_be']*100 + $a['month']]);
                if ($r = $lp->fetch()) { $a['month'] = (int)$r['month']; $a['year_be'] = (int)$r['year']; $a['plan_fallback'] = true; }
            }
            // Same rule as the plan page: a row without a department is an empty slot, not a pending inspection.
            $q = $p->prepare("SELECT status, inspection_date FROM audit_plans WHERE active=1 AND month=? AND year=? AND TRIM(department)<>''");
            $q->execute([$a['month'], $a['year_be']]);
            $today = date('Y-m-d');
            foreach ($q as $r) {
                $a['plan_total']++;
                $s = trim((string)$r['status']);
                if ($s === 'ตรวจแล้ว') $a['plan_done']++;
                elseif ($s === 'ตรวจบางส่วน') $a['plan_partial']++;
                else $a['plan_wait']++;
                $d = five_s_plan_date_iso((string)$r['inspection_date']);
                if ($d === $today && $s !== 'ตรวจแล้ว') $a['plan_today']++;
                if ($d !== '' && $d >= $today && $s !== 'ตรวจแล้ว' && ($a['plan_next'] === null || $d < $a['plan_next'])) $a['plan_next'] = $d;
            }
        } catch (Throwable $e) {}
        try {
            $p = db();
            $a['latest_audit'] = (string)$p->query("SELECT MAX(audit_date) FROM audits")->fetchColumn();
            $q = $p->prepare("SELECT COUNT(*) FROM audits WHERE audit_date BETWEEN ? AND ?");
            $q->execute([date('Y-m-01'), date('Y-m-t')]);
            $a['audits_month'] = (int)$q->fetchColumn();
            $a['backup_last'] = app_setting('auto_backup_last', '');
            $a['sync_at'] = app_setting('auto_sync_at', '');
        } catch (Throwable $e) {}
        $mTh = [1=>'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
        $a['plan_label'] = ($mTh[$a['month']] ?? '').' '.$a['year_be'];
        return $cache = $a;
    }
}

if (!function_exists('five_s_plan_date_iso')) {
    /** Plan dates may be stored as 2026-10-15, 15/10/2569 or 15/10/2026. */
    function five_s_plan_date_iso(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) {
            $y = (int)$m[1]; if ($y > 2400) $y -= 543;
            return sprintf('%04d-%02d-%02d', $y, (int)$m[2], (int)$m[3]);
        }
        if (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2,4})/', $v, $m)) {
            $y = (int)$m[3]; if ($y < 100) $y += 2000; if ($y > 2400) $y -= 543;
            return sprintf('%04d-%02d-%02d', $y, (int)$m[2], (int)$m[1]);
        }
        return '';
    }
}

if (!function_exists('five_s_run_automation')) {
    function five_s_run_automation(): void {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            if (!extension_loaded('pdo_mysql')) return;
            if (app_setting('automation_enabled', '1') !== '1') return;
            if (app_setting('auto_backup_enabled', '1') === '1') five_s_auto_backup();
            if (app_setting('auto_sync_enabled', '1') === '1') five_s_auto_sync_plans();
        } catch (Throwable $e) { /* never block a page */ }
    }
}
