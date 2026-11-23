<?php
/**
 * V80 — URL / path helpers for the role-based folder layout
 *   index.php (root) • Page/public/ • Page/auditor/ • Page/admin/ • includes/ • assets/ • db/
 * FIVE_S_BASE : URL path of the application root (e.g. /5S_V33_final_area_auto/)
 *               works on XAMPP and on shared hosting (any sub-folder).
 * Every page prints <base href="FIVE_S_BASE"> so links like "Page/admin/areas.php"
 * and "assets/..." resolve from the application root on every page.
 */
if (!defined('FIVE_S_ROOT')) define('FIVE_S_ROOT', dirname(__DIR__));
if (!defined('FIVE_S_BASE')) {
    $__root = str_replace('\\', '/', (string)realpath(FIVE_S_ROOT));
    $__file = str_replace('\\', '/', (string)realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $__relDir = '';
    if ($__root !== '' && $__file !== '' && stripos($__file, $__root) === 0) {
        $__relDir = trim(substr(dirname($__file), strlen($__root)), '/');
    }
    $__scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
    if ($__relDir !== '' && substr($__scriptDir, -strlen($__relDir)) === $__relDir) {
        $__scriptDir = substr($__scriptDir, 0, -strlen($__relDir));
    }
    $__base = '/' . trim($__scriptDir, '/') . '/';
    if ($__base === '//') $__base = '/';
    define('FIVE_S_BASE', $__base);
    /* Pages live in Page/<area>/ (admin, auditor, public) — area = folder under Page/ */
    $__parts = $__relDir === '' ? [] : explode('/', $__relDir);
    if ($__parts && strcasecmp($__parts[0], 'Page') === 0) array_shift($__parts);
    define('FIVE_S_AREA', $__parts ? strtolower($__parts[0]) : 'root');
    define('FIVE_S_SELF', ($__relDir === '' ? '' : $__relDir . '/') . basename($__file ?: 'index.php'));
}
if (!function_exists('five_s_self')) {
    /** URL of the current page (for "?query" and "#anchor" links). */
    function five_s_self(): string { return FIVE_S_BASE . FIVE_S_SELF; }
}
if (!function_exists('five_s_url')) {
    function five_s_url(string $rel = ''): string { return FIVE_S_BASE . ltrim($rel, '/'); }
}
