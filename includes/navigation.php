<?php
/**
 * 5S SMART | ALUMET — single source of truth for the menu (V60)
 * Sidebar, breadcrumb, section tabs, Ctrl+K search and the
 * "previous / next step" links are all generated from this list,
 * so every page is linked to every other page automatically.
 */
if (!function_exists('five_s_nav')) {
    /** V80: menu filtered by the signed-in role (guest sees public pages only). */
    function five_s_nav(): array {
        $out = [];
        foreach (five_s_nav_all() as $sec) {
            $sec['items'] = array_values(array_filter($sec['items'], fn($it) => five_s_can(five_s_need_for($it['href']))));
            if ($sec['items']) $out[] = $sec;
        }
        return $out;
    }
    function five_s_nav_all(): array {
        return [
            ['key'=>'home','title'=>'ภาพรวม (Overview)','items'=>[
                ['href'=>'index.php','label'=>'ภาพรวมระบบ (Dashboard)','short'=>'Dashboard','icon'=>'home','page'=>'index.php','desc'=>'คะแนนล่าสุด กราฟ และสิ่งที่ต้องทำ'],
                ['href'=>'Page/public/graph_overview.php','label'=>'ภาพรวมคะแนนทุกกลุ่ม','short'=>'ทุกกลุ่ม','icon'=>'grid','page'=>'graph_overview.php','desc'=>'Production + Office ในหน้าเดียว'],
                ['href'=>'Page/admin/setup_check.php','label'=>'ตรวจความพร้อมระบบ','short'=>'ความพร้อมระบบ','icon'=>'shield','page'=>'setup_check.php','desc'=>'ตรวจฐานข้อมูล สิทธิ์ไฟล์ และงานอัตโนมัติ'],
            ]],
            ['key'=>'prod','title'=>'1. ตรวจ 5 ส. — Production','items'=>[
                ['href'=>'Page/auditor/production_score.php','label'=>'บันทึกคะแนน Production','short'=>'บันทึกคะแนน','icon'=>'edit','page'=>'production_score.php','alias'=>['score.php','print.php'],'desc'=>'กรอกคะแนนตรวจพื้นที่ผลิต'],
                ['href'=>'Page/admin/production_criteria.php','label'=>'หัวข้อตรวจ Production','short'=>'หัวข้อตรวจ','icon'=>'list','page'=>'production_criteria.php','alias'=>['criteria.php'],'desc'=>'เกณฑ์และคะแนนเต็มแต่ละข้อ'],
                ['href'=>'Page/public/reports.php?department=Production','label'=>'กราฟและ Ranking Production','short'=>'กราฟ & Ranking','icon'=>'chart','page'=>'reports.php','dept'=>'Production','desc'=>'อันดับคะแนนหน่วย Production'],
                ['href'=>'Page/public/graph_print.php?department=Production','label'=>'พิมพ์กราฟ Production','short'=>'พิมพ์กราฟ','icon'=>'printer','page'=>'graph_print.php','dept'=>'Production','blank'=>true,'desc'=>'กราฟพร้อมพิมพ์ A4/A3'],
            ]],
            ['key'=>'office','title'=>'2. ตรวจ 5 ส. — Office','items'=>[
                ['href'=>'Page/auditor/office_score.php','label'=>'บันทึกคะแนน Office','short'=>'บันทึกคะแนน','icon'=>'edit','page'=>'office_score.php','alias'=>['print_office.php'],'desc'=>'กรอกคะแนนตรวจสำนักงาน'],
                ['href'=>'Page/admin/office_criteria.php','label'=>'หัวข้อตรวจ Office','short'=>'หัวข้อตรวจ','icon'=>'list','page'=>'office_criteria.php','desc'=>'เกณฑ์ Office'],
                ['href'=>'Page/public/office_reports.php','label'=>'กราฟและ Ranking Office','short'=>'กราฟ & Ranking','icon'=>'chart','page'=>'office_reports.php','alias_dept'=>['reports.php'=>'Office'],'desc'=>'อันดับคะแนนหน่วย Office'],
                ['href'=>'Page/public/graph_print.php?department=Office','label'=>'พิมพ์กราฟ Office','short'=>'พิมพ์กราฟ','icon'=>'printer','page'=>'graph_print.php','dept'=>'Office','blank'=>true,'desc'=>'กราฟพร้อมพิมพ์ A4/A3'],
            ]],
            ['key'=>'plan','title'=>'3. แผนตรวจและเอกสาร','items'=>[
                ['href'=>'Page/admin/schedule_manage.php','label'=>'จัดตารางแผนตรวจ','short'=>'แผนตรวจ','icon'=>'calendar','page'=>'schedule_manage.php','badge'=>'plan','desc'=>'สถานะตรวจอัปเดตอัตโนมัติจากคะแนน'],
                ['href'=>'Page/public/schedule_print.php','label'=>'พิมพ์แผนตรวจ','short'=>'พิมพ์แผน','icon'=>'printer','page'=>'schedule_print.php','blank'=>true,'desc'=>'แผนตรวจ A4/A3'],
                ['href'=>'Page/public/schedule_signoff.php','label'=>'แบบลงชื่อ Auditor','short'=>'ลงชื่อ Auditor','icon'=>'pen','page'=>'schedule_signoff.php','blank'=>true,'desc'=>'ใบลงชื่อผู้ตรวจประจำเดือน'],
                ['href'=>'Page/public/inspection_form.php','label'=>'พิมพ์ใบตรวจ 5 ส.','short'=>'ใบตรวจ','icon'=>'file','page'=>'inspection_form.php','alias'=>['inspection_form_print.php'],'desc'=>'ใบตรวจเปล่า / ตามหน่วย'],
                ['href'=>'Page/public/completion_summary.php','label'=>'สรุปผลการตรวจ (ความครบถ้วน)','short'=>'สรุปผลการตรวจ','icon'=>'check','page'=>'completion_summary.php','desc'=>'หน่วยที่ตรวจแล้ว/ยังไม่ตรวจ'],
                ['href'=>'Page/public/period_report.php','label'=>'รายงานรายเดือน / รายปี','short'=>'รายงานรายงวด','icon'=>'report','page'=>'period_report.php','desc'=>'สรุปผลตามช่วงเวลา'],
                ['href'=>'Page/public/org_3month_summary.php','label'=>'สรุปองค์กร / เปรียบเทียบ 3 เดือน','short'=>'สรุป 3 เดือน','icon'=>'trend','page'=>'org_3month_summary.php','desc'=>'บอร์ดสรุปผลองค์กร'],
                ['href'=>'Page/public/board_title.php','label'=>'ป้ายชื่อบอร์ด 5 ส.','short'=>'ป้ายบอร์ด','icon'=>'board','page'=>'board_title.php','desc'=>'ป้ายหัวบอร์ดประจำเดือน'],
            ]],
            ['key'=>'tag','title'=>'4. TAG 5 ส.','items'=>[
                ['href'=>'Page/public/tag_dashboard.php','label'=>'Dashboard สถานะ TAG','short'=>'TAG Dashboard','icon'=>'chart','page'=>'tag_dashboard.php','desc'=>'% ปิด TAG รายแผนก'],
                ['href'=>'Page/auditor/tag_manage.php','label'=>'รับ TAG / ติดตาม / ปิด TAG','short'=>'จัดการ TAG','icon'=>'tag','page'=>'tag_manage.php','badge'=>'tag','desc'=>'เปิด ติดตาม และปิด TAG'],
                ['href'=>'Page/public/tag_print.php','label'=>'พิมพ์สรุป TAG','short'=>'สรุป TAG','icon'=>'printer','page'=>'tag_print.php','blank'=>true,'desc'=>'สรุปการปิด TAG'],
                ['href'=>'Page/public/tag_blank_form_print.php','label'=>'พิมพ์แบบฟอร์ม TAG เปล่า','short'=>'ฟอร์มเปล่า','icon'=>'file','page'=>'tag_blank_form_print.php','blank'=>true,'desc'=>'ฟอร์ม TAG สำหรับเขียนมือ'],
                ['href'=>'Page/public/tag_form_print.php','label'=>'พิมพ์แบบฟอร์ม TAG จากข้อมูล','short'=>'ฟอร์มจากข้อมูล','icon'=>'printer','page'=>'tag_form_print.php','desc'=>'ฟอร์ม TAG ที่ดึงข้อมูลอัตโนมัติ'],
            ]],
            ['key'=>'master','title'=>'5. ข้อมูลพื้นฐานและผู้ตรวจ','items'=>[
                ['href'=>'Page/admin/master_data.php','label'=>'จัดการแผนก / กลุ่ม / หน่วย','short'=>'แผนก/กลุ่ม/หน่วย','icon'=>'building','page'=>'master_data.php','desc'=>'Master data ของหน่วยตรวจ'],
                ['href'=>'Page/admin/areas.php','label'=>'จัดการพื้นที่ / หน่วย','short'=>'พื้นที่/หน่วย','icon'=>'map','page'=>'areas.php','desc'=>'รายการพื้นที่ 5 ส.'],
                ['href'=>'Page/auditor/history.php','label'=>'ประวัติผู้ตรวจ / ผลตรวจ','short'=>'ประวัติผลตรวจ','icon'=>'users','page'=>'history.php','desc'=>'ใบตรวจย้อนหลังทั้งหมด'],
                ['href'=>'Page/public/auditor_12month.php','label'=>'ประวัติผู้ตรวจ 12 เดือน','short'=>'ผู้ตรวจ 12 เดือน','icon'=>'history','page'=>'auditor_12month.php','desc'=>'สถิติการเข้าตรวจรายเดือน'],
                ['href'=>'Page/admin/verify.php','label'=>'ตรวจสอบความถูกต้องของข้อมูล','short'=>'ตรวจสอบข้อมูล','icon'=>'search','page'=>'verify.php','desc'=>'ตรวจหาข้อมูลผิดปกติ'],
                ['href'=>'Page/admin/settings.php','label'=>'ตั้งค่าเป้าหมาย / ระบบอัตโนมัติ','short'=>'ตั้งค่า','icon'=>'target','page'=>'settings.php','desc'=>'เป้าหมายคะแนน และงานอัตโนมัติ'],
            ]],
            ['key'=>'data','title'=>'6. นำเข้า / ส่งออก / สำรองข้อมูล','items'=>[
                ['href'=>'Page/auditor/import.php','label'=>'นำเข้าผลตรวจจากไฟล์','short'=>'นำเข้า','icon'=>'upload','page'=>'import.php','alias'=>['unit_scores.php'],'desc'=>'นำเข้าคะแนนจากไฟล์สแกน/Excel'],
                ['href'=>'Page/admin/calibrate.php','label'=>'ตรวจพิกัดแบบฟอร์ม (OCR)','short'=>'พิกัดฟอร์ม','icon'=>'crosshair','page'=>'calibrate.php','desc'=>'ตรวจกรอบ Template ก่อนนำเข้า'],
                ['href'=>'Page/public/legacy.php','label'=>'ข้อมูล Excel เดิม','short'=>'Excel เดิม','icon'=>'book','page'=>'legacy.php','desc'=>'ข้อมูลย้อนหลังจาก Excel'],
                ['href'=>'Page/admin/export.php','label'=>'ส่งออก Excel','short'=>'ส่งออก','icon'=>'download','page'=>'export.php','desc'=>'ดาวน์โหลดคะแนนเป็น Excel'],
                ['href'=>'Page/admin/backup.php','label'=>'สำรอง / กู้คืนข้อมูล','short'=>'Backup','icon'=>'database','page'=>'backup.php','desc'=>'Backup อัตโนมัติรายวัน + กู้คืน'],
                ['href'=>'Page/admin/users.php','label'=>'ผู้ใช้งานและสิทธิ์','short'=>'ผู้ใช้งาน','icon'=>'users','page'=>'users.php','desc'=>'เพิ่ม/แก้ไขผู้ใช้ กำหนดสิทธิ์ Admin / Auditor / ดูอย่างเดียว รีเซ็ตรหัสผ่าน'],
            ]],
        ];
    }
}

if (!function_exists('five_s_nav_is_active')) {
    function five_s_nav_is_active(array $it): bool {
        $page = basename($_SERVER['PHP_SELF'] ?? '');
        $dept = (string)($_GET['department'] ?? '');
        if (!empty($it['alias_dept'])) {
            foreach ($it['alias_dept'] as $pg => $d) if ($page === $pg && $dept === $d) return true;
        }
        if (in_array($page, $it['alias'] ?? [], true)) return true;
        if ($page !== ($it['page'] ?? '')) return false;
        if (!empty($it['dept'])) {
            if ($dept === '') return $it['dept'] === 'Production';
            return $dept === $it['dept'];
        }
        return true;
    }
}

if (!function_exists('five_s_nav_current')) {
    /** @return array{section:?array,item:?array,prev:?array,next:?array} */
    function five_s_nav_current(): array {
        $flat = [];
        $found = ['section'=>null, 'item'=>null, 'prev'=>null, 'next'=>null];
        foreach (five_s_nav() as $sec) foreach ($sec['items'] as $it) {
            if (empty($it['blank']) && ($it['page'] ?? '') !== 'export.php') $flat[] = $it;
            if ($found['item'] === null && five_s_nav_is_active($it)) { $found['section'] = $sec; $found['item'] = $it; }
        }
        if ($found['item']) {
            foreach ($flat as $i => $it) {
                if ($it['href'] === $found['item']['href']) {
                    $found['prev'] = $flat[$i-1] ?? null;
                    $found['next'] = $flat[$i+1] ?? null;
                    break;
                }
            }
        }
        return $found;
    }
}

if (!function_exists('five_s_nav_json')) {
    function five_s_nav_json(): string {
        $rows = [];
        foreach (five_s_nav() as $sec) foreach ($sec['items'] as $it) {
            $rows[] = ['t'=>$it['label'], 's'=>$sec['title'], 'h'=>$it['href'], 'd'=>$it['desc'] ?? '', 'b'=>!empty($it['blank'])];
        }
        return json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    }
}

if (!function_exists('five_s_icon')) {
    /** Inline line icons (stroke = currentColor), no external icon font needed. */
    function five_s_icon(string $name): string {
        static $p = [
            'home'=>'<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
            'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            'shield'=>'<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
            'edit'=>'<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
            'list'=>'<path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
            'chart'=>'<path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6" rx=".5"/><rect x="12" y="8" width="3" height="10" rx=".5"/><rect x="17" y="5" width="3" height="13" rx=".5"/>',
            'printer'=>'<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/>',
            'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
            'pen'=>'<path d="M3 21h6"/><path d="M14.5 4.5l5 5L9 20H4v-5z"/>',
            'file'=>'<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
            'check'=>'<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
            'report'=>'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h5"/>',
            'trend'=>'<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
            'board'=>'<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20l4-4 4 4"/>',
            'tag'=>'<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
            'building'=>'<rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M9 7h.01M15 7h.01M9 11h.01M15 11h.01M9 15h.01M15 15h.01M10 21v-3h4v3"/>',
            'map'=>'<path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/>',
            'users'=>'<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6"/>',
            'history'=>'<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
            'search'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'target'=>'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
            'upload'=>'<path d="M12 15V3M7 8l5-5 5 5"/><path d="M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/>',
            'download'=>'<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/>',
            'crosshair'=>'<circle cx="12" cy="12" r="8"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
            'book'=>'<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v17H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 19.5V4.5"/>',
            'database'=>'<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
            'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
            'bell'=>'<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
            'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'external'=>'<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
            'arrow-left'=>'<path d="M19 12H5M11 6l-6 6 6 6"/>',
            'arrow-right'=>'<path d="M5 12h14M13 6l6 6-6 6"/>',
            'bolt'=>'<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        ];
        $d = $p[$name] ?? $p['file'];
        return '<svg class="ico" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$d.'</svg>';
    }
}
