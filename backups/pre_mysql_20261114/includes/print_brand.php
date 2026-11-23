<?php
/**
 * V60 — shared brand pieces for stand-alone print pages.
 * five_s_print_toolbar(): a screen-only bar (hidden when printing) with the
 * ALUMET logo and links back into the system, so print pages are never dead ends.
 * five_s_print_logo(): the ALUMET logo for the printed document header.
 */
if (!function_exists('five_s_print_toolbar')) {
    function five_s_print_toolbar(string $backHref = 'index.php', string $backLabel = 'กลับ', bool $showPrint = true): string {
        $b = htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8');
        $l = htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8');
        $print = $showPrint ? '<button type="button" class="fs-pt-btn fs-pt-primary" onclick="window.print()">พิมพ์ / Save PDF</button>' : '';
        return <<<HTML
<style>
div.fs-pt.no-print,.fs-pt{position:sticky;top:0;z-index:999;display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:10px 16px;background:#fff;border-bottom:1px solid #E6E9EF;box-shadow:0 2px 10px rgba(15,23,42,.06);font-family:"Inter","IBM Plex Sans Thai",Tahoma,Arial,sans-serif;font-size:13.5px}
.fs-pt::after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:2px;background:linear-gradient(90deg,#F26A21,#F7A21B 35%,transparent 70%)}
.fs-pt img{height:26px;width:auto;display:block}
.fs-pt .fs-pt-sep{width:1px;height:24px;background:#D5DAE3}
.fs-pt .fs-pt-name{font-weight:800;color:#0E1A2F;margin-right:auto}
.fs-pt .fs-pt-name small{display:block;font-weight:500;color:#64748B;font-size:11px}
.fs-pt-btn{display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 14px;border-radius:9px;border:1px solid #D5DAE3;background:#fff;color:#0F172A;font:inherit;font-weight:600;text-decoration:none;cursor:pointer}
.fs-pt-btn:hover{border-color:#F26A21;color:#F26A21}
.fs-pt-primary{background:#F26A21;border-color:#F26A21;color:#fff}.fs-pt-primary:hover{background:#DB5512;color:#fff}
@media print{div.fs-pt.no-print,.fs-pt{display:none!important}}
</style>
<div class="fs-pt no-print">
  <a href="index.php"><img src="assets/alumet-logo.png" alt="ALUMET"></a><span class="fs-pt-sep"></span>
  <span class="fs-pt-name">5S SMART<small>Management System</small></span>
  <a class="fs-pt-btn" href="{$b}">← {$l}</a>
  <a class="fs-pt-btn" href="index.php">หน้าหลัก</a>
  {$print}
</div>
HTML;
    }
}
if (!function_exists('five_s_print_logo')) {
    function five_s_print_logo(int $height = 34, string $style = ''): string {
        return '<img src="assets/alumet-logo.png" alt="ALUMET" style="height:'.$height.'px;width:auto;vertical-align:middle;'.htmlspecialchars($style, ENT_QUOTES, 'UTF-8').'">';
    }
}
if (!function_exists('five_s_favicon_tags')) {
    function five_s_favicon_tags(): string {
        return '<link rel="icon" type="image/png" href="assets/alumet-mark.png"><link rel="apple-touch-icon" href="assets/alumet-mark-180.png">';
    }
}
