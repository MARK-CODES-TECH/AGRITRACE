<?php
/**
 * AgriTrace+ — Report Export Engine
 * ---------------------------------------------------------------------------
 * Replaces the old plain-CSV export_helper.php. Works on XAMPP and on free
 * hosting: it needs NO Composer, NO PhpSpreadsheet and NO ZipArchive.
 *
 *   ?export=farmers            -> styled Excel workbook (.xlsx)  [default]
 *   ?export=farmers&format=csv -> clean UTF-8 CSV (opens correctly in Excel)
 *   ?export=farmers&format=pdf -> styled PDF report
 *
 * The Excel file has two sheets:
 *   Summary : banner, KPI tiles and native Excel charts (+ the chart data)
 *   Data    : formatted table (frozen header, filters, colour-coded status,
 *             totals row, correct column widths, real dates, text phones)
 *
 * Public API
 *   agxReport($type, $rows, $by)                       one report (admin.php)
 *   agxSend($format, $summary, $datasets, $by, $base)  multi-sheet (Official Portal)
 *   agx_package($format, $summary, $datasets, $by)     returns [data, mime, ext]
 *   agx_dataset($type, $rows, $title, $sub)            dataset from a definition
 *   agx_summary_for($type, $rows, $title, $sub)        KPIs + charts for a definition
 *   agx_count_by / agx_sum_by / agx_top_n              chart-data helpers
 *   agx_now_text()                                     "04 Nov 2026, 02:48 PM" (Manila time)
 */

/* ============================================================================
   1. PALETTE / SMALL HELPERS
   ========================================================================== */

function agx_c($k) {
    static $c = array(
        'dark' => '022C22', 'forest' => '064E3B', 'emerald' => '059669', 'green' => '10B981',
        'mint' => 'D1FAE5', 'soft' => 'F0FDF4', 'line' => 'D6E4DD', 'ink' => '0F172A',
        'muted' => '64748B', 'zebra' => 'F6FBF8', 'white' => 'FFFFFF', 'amber' => 'F59E0B',
        'red' => 'DC2626', 'blue' => '2563EB', 'violet' => '7C3AED', 'gray' => '94A3B8',
    );
    return isset($c[$k]) ? $c[$k] : '000000';
}

/** [fill, font] colours for a status word */
function agx_status_style($v) {
    static $m = array(
        'Active' => array('D1FAE5', '065F46'), 'Approved' => array('D1FAE5', '065F46'),
        'Healthy' => array('D1FAE5', '065F46'), 'Resolved' => array('D1FAE5', '065F46'),
        'Complete' => array('D1FAE5', '065F46'), 'Recovered' => array('CCFBF1', '0F766E'),
        'Low' => array('DCFCE7', '166534'),
        'Pending' => array('FEF3C7', '92400E'), 'Partial' => array('FEF3C7', '92400E'),
        'Medium' => array('FEF3C7', '92400E'),
        'In Progress' => array('DBEAFE', '1E40AF'), 'Reviewed' => array('DBEAFE', '1E40AF'),
        'Inactive' => array('FEE2E2', '991B1B'), 'Rejected' => array('FEE2E2', '991B1B'),
        'Sick' => array('FEE2E2', '991B1B'), 'None' => array('FEE2E2', '991B1B'),
        'High' => array('FFEDD5', '9A3412'), 'Critical' => array('FECACA', '7F1D1D'),
        'Deceased' => array('E2E8F0', '334155'), 'Closed' => array('E2E8F0', '334155'),
        'Public' => array('EDE9FE', '5B21B6'),
    );
    return isset($m[$v]) ? $m[$v] : array('E2E8F0', '334155');
}

/** solid colour used for a status inside charts */
function agx_status_color($v) {
    static $m = array(
        'Active' => '10B981', 'Approved' => '10B981', 'Healthy' => '10B981', 'Resolved' => '10B981',
        'Complete' => '10B981', 'Recovered' => '14B8A6', 'Low' => '84CC16',
        'Pending' => 'F59E0B', 'Partial' => 'F59E0B', 'Medium' => 'F59E0B',
        'In Progress' => '3B82F6', 'Reviewed' => '3B82F6',
        'Inactive' => 'EF4444', 'Rejected' => 'EF4444', 'Sick' => 'EF4444', 'None' => 'EF4444',
        'High' => 'F97316', 'Critical' => 'B91C1C', 'Deceased' => '64748B', 'Closed' => '94A3B8',
        'Public' => '8B5CF6',
    );
    return isset($m[$v]) ? $m[$v] : '94A3B8';
}

function agx_palette() {
    return array('10B981', '3B82F6', 'F59E0B', '8B5CF6', 'EC4899', 'F97316', '14B8A6', 'EF4444', '64748B');
}

function agx_xml($s) {
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$s);
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

function agx_num($v) {
    if (is_int($v)) { return (string)$v; }
    $v = (float)$v;
    if (is_nan($v) || is_infinite($v)) { return '0'; }
    $s = sprintf('%.12F', $v);
    if (strpos($s, '.') !== false) { $s = rtrim(rtrim($s, '0'), '.'); }
    return ($s === '' || $s === '-') ? '0' : $s;
}

function agx_col($i) {
    $s = '';
    $i = (int)$i;
    while ($i >= 0) {
        $s = chr(65 + ($i % 26)) . $s;
        $i = (int)floor($i / 26) - 1;
    }
    return $s;
}

function agx_ref($r, $c, $abs = false) {
    return $abs ? '$' . agx_col($c) . '$' . ($r + 1) : agx_col($c) . ($r + 1);
}

function agx_range($sheet, $r1, $c1, $r2, $c2) {
    return "'" . str_replace("'", "''", $sheet) . "'!" . agx_ref($r1, $c1, true) . ':' . agx_ref($r2, $c2, true);
}

function agx_now() {
    return new DateTime('now', new DateTimeZone('Asia/Manila'));
}

function agx_now_text() {
    return agx_now()->format('d M Y, h:i A');
}

/** MySQL datetime string -> Excel serial number (wall-clock, no timezone shift) */
function agx_excel_date($v) {
    if ($v === null || $v === '' || strpos((string)$v, '0000-00-00') === 0) { return null; }
    $d = date_create((string)$v, new DateTimeZone('UTC'));
    if (!$d) { return null; }
    return $d->getTimestamp() / 86400 + 25569;
}

function agx_pad($prefix, $n, $w = 4) {
    return $prefix . '-' . str_pad((string)(int)$n, $w, '0', STR_PAD_LEFT);
}

function agx_name($r) {
    $f = isset($r['firstName']) ? trim((string)$r['firstName']) : '';
    $l = isset($r['lastName']) ? trim((string)$r['lastName']) : '';
    return trim($f . ' ' . $l);
}

/** closure: text field with default */
function agx_g($k, $d = '') {
    return function ($r) use ($k, $d) {
        return (isset($r[$k]) && $r[$k] !== null && $r[$k] !== '') ? $r[$k] : $d;
    };
}

/** closure: numeric field (0 when missing) */
function agx_gn($k) {
    return function ($r) use ($k) {
        return (isset($r[$k]) && is_numeric($r[$k])) ? $r[$k] + 0 : 0;
    };
}

function agx_cs($h, $w, $t, $f, $x = array()) {
    return array_merge(array('h' => $h, 'w' => $w, 't' => $t, 'f' => $f), $x);
}

/* ---- aggregation helpers (return list of [label, value]) ---- */

function agx_count_by($rows, $fn, $order = null) {
    $d = array();
    if ($order) { foreach ($order as $o) { $d[(string)$o] = 0; } }
    foreach ($rows as $r) {
        $k = call_user_func($fn, $r);
        if ($k === null || $k === '') { $k = 'Unknown'; }
        $k = (string)$k;
        if (!isset($d[$k])) { $d[$k] = 0; }
        $d[$k]++;
    }
    $out = array();
    foreach ($d as $k => $v) {
        if ($v > 0 || !$order) { $out[] = array((string)$k, $v); }
    }
    return $out;
}

function agx_sum_by($rows, $kf, $vf, $limit = 0) {
    $d = array();
    foreach ($rows as $r) {
        $k = call_user_func($kf, $r);
        if ($k === null || $k === '') { $k = 'Unknown'; }
        $k = (string)$k;
        if (!isset($d[$k])) { $d[$k] = 0; }
        $d[$k] += call_user_func($vf, $r);
    }
    $out = array();
    foreach ($d as $k => $v) { if ($v > 0) { $out[] = array((string)$k, $v); } }
    usort($out, function ($a, $b) { return $b[1] <=> $a[1]; });
    return $limit > 0 ? array_slice($out, 0, $limit) : $out;
}

function agx_top_n($rows, $kf, $vf, $n = 8) {
    $out = array();
    foreach ($rows as $r) {
        $v = call_user_func($vf, $r);
        if ($v > 0) { $out[] = array((string)call_user_func($kf, $r), $v); }
    }
    usort($out, function ($a, $b) { return $b[1] <=> $a[1]; });
    return array_slice($out, 0, $n);
}

function agx_month_counts($rows, $key) {
    $out = agx_count_by($rows, function ($r) use ($key) {
        return (isset($r[$key]) && strlen((string)$r[$key]) >= 7) ? substr((string)$r[$key], 0, 7) : null;
    });
    usort($out, function ($a, $b) { return strcmp($a[0], $b[0]); });
    return array_slice($out, -10);
}

function agx_sum_key($rows, $key) {
    $t = 0;
    foreach ($rows as $r) { if (isset($r[$key]) && is_numeric($r[$key])) { $t += $r[$key] + 0; } }
    return $t;
}

function agx_count_where($rows, $key, $values) {
    $n = 0;
    foreach ($rows as $r) { if (isset($r[$key]) && in_array($r[$key], $values, true)) { $n++; } }
    return $n;
}

/* ============================================================================
   2. REPORT DEFINITIONS  (columns, KPI tiles, charts)
   col types: id | text | phone | status | int | dec | dec6 | date
   ========================================================================== */

function agx_def($type) {
    $name = function ($r) { return agx_name($r); };
    $muni = function ($r) {
        if (!empty($r['municipality'])) { return $r['municipality']; }
        if (!empty($r['pet_municipality'])) { return $r['pet_municipality']; }
        return '1st District';
    };
    $g = agx_c('green');

    switch ($type) {

        case 'farmers':
            return array(
                'title' => 'Farmers Roster',
                'sub' => 'Registered farmer accounts in the Camarines Sur 1st District',
                'sheet' => 'Farmers', 'file' => 'farmers_roster',
                'cols' => array(
                    agx_cs('Farmer ID', 12, 'id', function ($r) { return agx_pad('F', isset($r['id']) ? $r['id'] : 0); }),
                    agx_cs('Full Name', 26, 'text', $name),
                    agx_cs('Email Address', 34, 'text', agx_g('email')),
                    agx_cs('Mobile Number', 18, 'phone', agx_g('mobile')),
                    agx_cs('Account Status', 16, 'status', agx_g('status')),
                    agx_cs('Registered Farms', 12, 'int', agx_gn('farms'), array('sum' => true)),
                    agx_cs('Livestock Heads', 12, 'int', agx_gn('livestock'), array('sum' => true)),
                    agx_cs('Date Registered', 21, 'date', agx_g('createdAt', null)),
                ),
                'kpis' => array(
                    array('TOTAL FARMERS', function ($R) { return count($R); }, agx_c('emerald'), 'registered accounts'),
                    array('ACTIVE', function ($R) { return agx_count_where($R, 'status', array('Active')); }, $g, 'can log in'),
                    array('PENDING', function ($R) { return agx_count_where($R, 'status', array('Pending')); }, agx_c('amber'), 'awaiting approval'),
                    array('INACTIVE', function ($R) { return agx_count_where($R, 'status', array('Inactive')); }, agx_c('red'), 'suspended / rejected'),
                    array('FARMS', function ($R) { return agx_sum_key($R, 'farms'); }, agx_c('blue'), 'registered holdings'),
                    array('LIVESTOCK', function ($R) { return agx_sum_key($R, 'livestock'); }, agx_c('violet'), 'total heads'),
                ),
                'charts' => array(
                    array('Account Status', 'doughnut', function ($R) { return agx_count_by($R, agx_g('status'), array('Active', 'Pending', 'Inactive')); }, 'status', array('Status', 'Farmers')),
                    array('Farms per Farmer (Top 8)', 'bar', function ($R) use ($name) { return agx_top_n($R, $name, agx_gn('farms'), 8); }, agx_c('emerald'), array('Farmer', 'Farms')),
                    array('Livestock Heads per Farmer (Top 8)', 'column', function ($R) use ($name) { return agx_top_n($R, $name, agx_gn('livestock'), 8); }, '3B82F6', array('Farmer', 'Heads')),
                    array('Registrations by Month', 'column', function ($R) { return agx_month_counts($R, 'createdAt'); }, 'F59E0B', array('Month', 'Farmers')),
                ),
            );

        case 'farms':
            return array(
                'title' => 'Farm Holdings',
                'sub' => 'Registered farm holdings and approval status',
                'sheet' => 'Farms', 'file' => 'farm_holdings',
                'cols' => array(
                    agx_cs('Farm ID', 11, 'id', function ($r) { return agx_pad('FM', isset($r['id']) ? $r['id'] : 0); }),
                    agx_cs('Farm Name', 26, 'text', agx_g('name')),
                    agx_cs('Owner', 24, 'text', function ($r) { $n = agx_name($r); return $n !== '' ? $n : (isset($r['ownerName']) ? $r['ownerName'] : ''); }),
                    agx_cs('Owner Email', 30, 'text', agx_g('email'), array('pdf' => false)),
                    agx_cs('Municipality', 16, 'text', $muni),
                    agx_cs('Address', 38, 'text', agx_g('address'), array('wrap' => true, 'pdf' => false)),
                    agx_cs('Farm Type', 12, 'text', agx_g('type')),
                    agx_cs('App Type', 12, 'text', agx_g('application_type', 'New')),
                    agx_cs('Ownership', 12, 'text', agx_g('ownership', 'Private')),
                    agx_cs('Area (ha)', 10, 'dec', agx_gn('area'), array('sum' => true)),
                    agx_cs('Status', 14, 'status', agx_g('status')),
                    agx_cs('Livestock Records', 11, 'int', agx_gn('livestock_records'), array('pdf' => false)),
                    agx_cs('Total Livestock', 12, 'int', agx_gn('total_livestock'), array('sum' => true)),
                    agx_cs('Livestock Types', 24, 'text', agx_g('livestock_types'), array('pdf' => false)),
                    agx_cs('Latitude', 12, 'dec6', agx_g('latitude', null), array('pdf' => false)),
                    agx_cs('Longitude', 12, 'dec6', agx_g('longitude', null), array('pdf' => false)),
                    agx_cs('Date Registered', 21, 'date', agx_g('createdAt', null), array('pdf' => false)),
                    agx_cs('Last Updated', 21, 'date', agx_g('updatedAt', null), array('pdf' => false)),
                ),
                'kpis' => array(
                    array('TOTAL FARMS', function ($R) { return count($R); }, agx_c('emerald'), 'holdings on record'),
                    array('APPROVED', function ($R) { return agx_count_where($R, 'status', array('Approved')); }, $g, 'verified farms'),
                    array('PENDING', function ($R) { return agx_count_where($R, 'status', array('Pending')); }, agx_c('amber'), 'awaiting review'),
                    array('REJECTED', function ($R) { return agx_count_where($R, 'status', array('Rejected')); }, agx_c('red'), 'not approved'),
                    array('TOTAL AREA (HA)', function ($R) { return round(agx_sum_key($R, 'area'), 1); }, agx_c('blue'), 'declared farm area'),
                    array('LIVESTOCK', function ($R) { return agx_sum_key($R, 'total_livestock'); }, agx_c('violet'), 'heads on farms'),
                ),
                'charts' => array(
                    array('Farm Approval Status', 'doughnut', function ($R) { return agx_count_by($R, agx_g('status'), array('Approved', 'Pending', 'Rejected')); }, 'status', array('Status', 'Farms')),
                    array('Farms per Municipality', 'column', function ($R) use ($muni) { return agx_count_by($R, $muni); }, agx_c('emerald'), array('Municipality', 'Farms')),
                    array('Livestock Heads per Municipality', 'column', function ($R) use ($muni) { return agx_sum_by($R, $muni, agx_gn('total_livestock')); }, '3B82F6', array('Municipality', 'Heads')),
                    array('Farm Types', 'pie', function ($R) { return agx_count_by($R, agx_g('type')); }, 'palette', array('Farm Type', 'Farms')),
                ),
            );

        case 'livestock':
            return array(
                'title' => 'Livestock Inventory',
                'sub' => 'Head counts, health and vaccination status across registered farms',
                'sheet' => 'Livestock', 'file' => 'livestock_inventory',
                'cols' => array(
                    agx_cs('Record ID', 11, 'id', function ($r) { return agx_pad('LS', isset($r['id']) ? $r['id'] : 0); }),
                    agx_cs('Tag ID', 16, 'text', agx_g('tagId')),
                    agx_cs('Species', 12, 'text', agx_g('type')),
                    agx_cs('Breed', 16, 'text', agx_g('breed')),
                    agx_cs('Farm', 24, 'text', agx_g('farm_name')),
                    agx_cs('Owner', 22, 'text', $name),
                    agx_cs('Municipality', 16, 'text', $muni),
                    agx_cs('Head Count', 11, 'int', agx_gn('qty'), array('sum' => true)),
                    agx_cs('Age (months)', 11, 'int', agx_gn('age'), array('pdf' => false)),
                    agx_cs('Weight (kg)', 11, 'dec', agx_gn('weight'), array('pdf' => false)),
                    agx_cs('Health Status', 14, 'status', agx_g('healthStatus')),
                    agx_cs('Vaccination', 14, 'status', agx_g('vaccinationStatus')),
                    agx_cs('Notes', 34, 'text', agx_g('notes'), array('wrap' => true, 'pdf' => false)),
                    agx_cs('Date Registered', 21, 'date', agx_g('createdAt', null), array('pdf' => false)),
                    agx_cs('Last Updated', 21, 'date', agx_g('updatedAt', null), array('pdf' => false)),
                ),
                'kpis' => array(
                    array('RECORDS', function ($R) { return count($R); }, agx_c('emerald'), 'livestock entries'),
                    array('TOTAL HEADS', function ($R) { return agx_sum_key($R, 'qty'); }, agx_c('blue'), 'animals registered'),
                    array('HEALTHY', function ($R) {
                        $tot = agx_sum_key($R, 'qty'); $ok = 0;
                        foreach ($R as $r) { if (isset($r['healthStatus']) && in_array($r['healthStatus'], array('Healthy', 'Recovered'), true)) { $ok += (isset($r['qty']) ? (int)$r['qty'] : 0); } }
                        return $tot > 0 ? round($ok / $tot * 100) . '%' : '0%';
                    }, $g, 'healthy or recovered'),
                    array('SICK HEADS', function ($R) {
                        $n = 0;
                        foreach ($R as $r) { if (isset($r['healthStatus']) && $r['healthStatus'] === 'Sick') { $n += (isset($r['qty']) ? (int)$r['qty'] : 0); } }
                        return $n;
                    }, agx_c('red'), 'need attention'),
                    array('VACCINATED', function ($R) {
                        $tot = agx_sum_key($R, 'qty'); $ok = 0;
                        foreach ($R as $r) { if (isset($r['vaccinationStatus']) && $r['vaccinationStatus'] === 'Complete') { $ok += (isset($r['qty']) ? (int)$r['qty'] : 0); } }
                        return $tot > 0 ? round($ok / $tot * 100) . '%' : '0%';
                    }, agx_c('amber'), 'fully vaccinated'),
                    array('FARMS COVERED', function ($R) {
                        $s = array();
                        foreach ($R as $r) { if (!empty($r['farm_name'])) { $s[$r['farm_name']] = 1; } }
                        return count($s);
                    }, agx_c('violet'), 'with livestock'),
                ),
                'charts' => array(
                    array('Heads by Species', 'doughnut', function ($R) { return agx_sum_by($R, agx_g('type'), agx_gn('qty')); }, 'palette', array('Species', 'Heads')),
                    array('Health Status (heads)', 'column', function ($R) {
                        $o = array();
                        foreach (array('Healthy', 'Recovered', 'Sick', 'Deceased') as $s) { $o[$s] = 0; }
                        foreach ($R as $r) { $k = isset($r['healthStatus']) ? $r['healthStatus'] : 'Unknown'; if (!isset($o[$k])) { $o[$k] = 0; } $o[$k] += (isset($r['qty']) ? (int)$r['qty'] : 0); }
                        $out = array(); foreach ($o as $k => $v) { if ($v > 0) { $out[] = array((string)$k, $v); } }
                        return $out;
                    }, 'status', array('Health', 'Heads')),
                    array('Vaccination Status (heads)', 'column', function ($R) {
                        $o = array('Complete' => 0, 'Partial' => 0, 'None' => 0);
                        foreach ($R as $r) { $k = isset($r['vaccinationStatus']) ? $r['vaccinationStatus'] : 'None'; if (!isset($o[$k])) { $o[$k] = 0; } $o[$k] += (isset($r['qty']) ? (int)$r['qty'] : 0); }
                        $out = array(); foreach ($o as $k => $v) { if ($v > 0) { $out[] = array((string)$k, $v); } }
                        return $out;
                    }, 'status', array('Vaccination', 'Heads')),
                    array('Heads per Municipality', 'bar', function ($R) use ($muni) { return agx_sum_by($R, $muni, agx_gn('qty')); }, agx_c('emerald'), array('Municipality', 'Heads')),
                ),
            );

        case 'incidents':
            return array(
                'title' => 'Incidents & Public Reports',
                'sub' => 'Farm incidents and citizen reports across the district',
                'sheet' => 'Incidents', 'file' => 'incidents_reports',
                'cols' => array(
                    agx_cs('Reference', 12, 'id', function ($r) {
                        $pub = (isset($r['source_table']) && $r['source_table'] === 'public_reports');
                        return agx_pad($pub ? 'PR' : 'INC', isset($r['id']) ? $r['id'] : 0);
                    }),
                    agx_cs('Source', 16, 'text', agx_g('source', 'Farmer Incident')),
                    agx_cs('Title', 28, 'text', agx_g('title'), array('wrap' => true)),
                    agx_cs('Type', 11, 'text', agx_g('type')),
                    agx_cs('Description', 44, 'text', agx_g('description'), array('wrap' => true, 'pdf' => false)),
                    agx_cs('Priority', 12, 'status', agx_g('priority')),
                    agx_cs('Status', 13, 'status', agx_g('status')),
                    agx_cs('Municipality', 16, 'text', $muni),
                    agx_cs('Farm', 22, 'text', function ($r) { return !empty($r['farm_name']) ? $r['farm_name'] : 'Public area'; }, array('pdf' => false)),
                    agx_cs('Reporter', 20, 'text', agx_g('reporter'), array('pdf' => false)),
                    agx_cs('Contact', 18, 'phone', function ($r) {
                        $p = isset($r['contactPhone']) ? trim((string)$r['contactPhone']) : '';
                        return $p !== '' ? $p : (isset($r['contactEmail']) ? (string)$r['contactEmail'] : '');
                    }, array('pdf' => false)),
                    agx_cs('Latitude', 12, 'dec6', agx_g('latitude', null), array('pdf' => false)),
                    agx_cs('Longitude', 12, 'dec6', agx_g('longitude', null), array('pdf' => false)),
                    agx_cs('Date Reported', 21, 'date', agx_g('createdAt', null)),
                ),
                'kpis' => array(
                    array('TOTAL REPORTS', function ($R) { return count($R); }, agx_c('emerald'), 'incidents + public'),
                    array('ACTIVE', function ($R) { return agx_count_where($R, 'status', array('Pending', 'In Progress', 'Reviewed')); }, agx_c('amber'), 'need action'),
                    array('RESOLVED / CLOSED', function ($R) { return agx_count_where($R, 'status', array('Resolved', 'Closed')); }, $g, 'completed'),
                    array('HIGH / CRITICAL', function ($R) { return agx_count_where($R, 'priority', array('High', 'Critical')); }, agx_c('red'), 'urgent'),
                    array('FARM INCIDENTS', function ($R) {
                        $n = 0; foreach ($R as $r) { if (!(isset($r['source_table']) && $r['source_table'] === 'public_reports')) { $n++; } } return $n;
                    }, agx_c('blue'), 'reported by farmers'),
                    array('PUBLIC REPORTS', function ($R) {
                        $n = 0; foreach ($R as $r) { if (isset($r['source_table']) && $r['source_table'] === 'public_reports') { $n++; } } return $n;
                    }, agx_c('violet'), 'reported by citizens'),
                ),
                'charts' => array(
                    array('Report Status', 'doughnut', function ($R) { return agx_count_by($R, agx_g('status'), array('Pending', 'In Progress', 'Reviewed', 'Resolved', 'Closed')); }, 'status', array('Status', 'Reports')),
                    array('Priority Level', 'column', function ($R) { return agx_count_by($R, agx_g('priority'), array('Low', 'Medium', 'High', 'Critical', 'Public')); }, 'status', array('Priority', 'Reports')),
                    array('Incident Type', 'column', function ($R) { return agx_count_by($R, agx_g('type')); }, 'F59E0B', array('Type', 'Reports')),
                    array('Reports per Municipality', 'bar', function ($R) use ($muni) { return agx_count_by($R, $muni); }, agx_c('emerald'), array('Municipality', 'Reports')),
                ),
            );

        case 'public_reports':
            return array(
                'title' => 'Public Reports',
                'sub' => 'Citizen-submitted sick / dead / stray animal reports',
                'sheet' => 'PublicReports', 'file' => 'public_reports',
                'cols' => array(
                    agx_cs('Reference', 12, 'id', function ($r) { return agx_pad('PR', isset($r['id']) ? $r['id'] : 0); }),
                    agx_cs('Type', 14, 'text', function ($r) {
                        if (isset($r['reportType']) && $r['reportType'] === 'Others' && !empty($r['otherType'])) { return $r['otherType']; }
                        return isset($r['reportType']) ? $r['reportType'] : '';
                    }),
                    agx_cs('Description', 52, 'text', agx_g('description'), array('wrap' => true)),
                    agx_cs('Contact Phone', 18, 'phone', agx_g('contactPhone')),
                    agx_cs('Contact Email', 28, 'text', agx_g('contactEmail'), array('pdf' => false)),
                    agx_cs('Status', 13, 'status', agx_g('status')),
                    agx_cs('Latitude', 12, 'dec6', agx_g('latitude', null), array('pdf' => false)),
                    agx_cs('Longitude', 12, 'dec6', agx_g('longitude', null), array('pdf' => false)),
                    agx_cs('Date Reported', 21, 'date', agx_g('createdAt', null)),
                    agx_cs('Reviewed On', 21, 'date', agx_g('reviewedAt', null), array('pdf' => false)),
                ),
                'kpis' => array(
                    array('TOTAL REPORTS', function ($R) { return count($R); }, agx_c('emerald'), 'citizen reports'),
                    array('PENDING', function ($R) { return agx_count_where($R, 'status', array('Pending')); }, agx_c('amber'), 'not yet reviewed'),
                    array('REVIEWED', function ($R) { return agx_count_where($R, 'status', array('Reviewed')); }, agx_c('blue'), 'under review'),
                    array('RESOLVED', function ($R) { return agx_count_where($R, 'status', array('Resolved')); }, $g, 'handled'),
                    array('CLOSED', function ($R) { return agx_count_where($R, 'status', array('Closed')); }, agx_c('gray'), 'archived'),
                    array('WITH GPS', function ($R) {
                        $n = 0; foreach ($R as $r) { if (!empty($r['latitude']) && !empty($r['longitude'])) { $n++; } } return $n;
                    }, agx_c('violet'), 'mapped location'),
                ),
                'charts' => array(
                    array('Report Status', 'doughnut', function ($R) { return agx_count_by($R, agx_g('status'), array('Pending', 'Reviewed', 'Resolved', 'Closed')); }, 'status', array('Status', 'Reports')),
                    array('Report Type', 'column', function ($R) { return agx_count_by($R, agx_g('reportType')); }, 'F59E0B', array('Type', 'Reports')),
                    array('Reports by Month', 'column', function ($R) { return agx_month_counts($R, 'createdAt'); }, '3B82F6', array('Month', 'Reports')),
                ),
            );
    }
    return null;
}

/** Build the summary (title, KPI tiles, chart data) for a definition + rows */
function agx_summary_for($type, $rows, $title = null, $sub = null) {
    $def = agx_def($type);
    if (!$def) { return array('title' => 'Report', 'sub' => '', 'kpis' => array(), 'charts' => array()); }
    $s = array('title' => $title !== null ? $title : $def['title'], 'sub' => $sub !== null ? $sub : $def['sub'], 'kpis' => array(), 'charts' => array());
    foreach ($def['kpis'] as $k) {
        $s['kpis'][] = array($k[0], call_user_func($k[1], $rows), $k[2], $k[3]);
    }
    foreach ($def['charts'] as $c) {
        $s['charts'][] = array('title' => $c[0], 'type' => $c[1], 'data' => call_user_func($c[2], $rows), 'colors' => $c[3], 'head' => $c[4]);
    }
    return $s;
}

function agx_dataset($type, $rows, $title = null, $sub = null, $sheet = null) {
    $def = agx_def($type);
    return array(
        'title' => $title !== null ? $title : $def['title'],
        'sub' => $sub !== null ? $sub : $def['sub'],
        'sheet' => $sheet !== null ? $sheet : $def['sheet'],
        'cols' => $def['cols'],
        'rows' => $rows,
    );
}

/* value helpers used by CSV / PDF */

function agx_raw($col, $row) {
    return call_user_func($col['f'], $row);
}

function agx_disp($col, $row) {
    $v = agx_raw($col, $row);
    $t = $col['t'];
    if ($v === null || $v === '') { return ''; }
    if ($t === 'int') { return number_format((float)$v, 0); }
    if ($t === 'dec') { return number_format((float)$v, 2); }
    if ($t === 'dec6') { return number_format((float)$v, 6, '.', ''); }
    if ($t === 'date') { $ts = strtotime((string)$v); return $ts ? date('d-M-Y h:i A', $ts) : (string)$v; }
    return (string)$v;
}

function agx_csv_val($col, $row) {
    $v = agx_raw($col, $row);
    $t = $col['t'];
    if ($v === null || $v === '') { return ''; }
    if ($t === 'int') { return (string)(0 + $v); }
    if ($t === 'dec') { return sprintf('%.2f', (float)$v); }
    if ($t === 'dec6') { return sprintf('%.6f', (float)$v); }
    if ($t === 'phone') { return '="' . str_replace('"', '""', (string)$v) . '"'; }
    if ($t === 'date') { $ts = strtotime((string)$v); return '="' . ($ts ? date('Y-m-d H:i', $ts) : (string)$v) . '"'; }
    $s = (string)$v;
    if (preg_match('/^[=+\-@\t\r]/', $s)) { $s = "'" . $s; }
    return $s;
}

/* ============================================================================
   3. MINIMAL ZIP WRITER (pure PHP)
   ========================================================================== */

class AgxZip {
    private $files = array();

    public function add($name, $data) {
        $this->files[] = array($name, $data);
    }

    public function build() {
        $dt = getdate();
        $time = (($dt['hours'] << 11) | ($dt['minutes'] << 5) | ($dt['seconds'] >> 1)) & 0xFFFF;
        $date = ((($dt['year'] - 1980) << 9) | ($dt['mon'] << 5) | $dt['mday']) & 0xFFFF;
        $out = '';
        $cd = '';
        $count = 0;
        foreach ($this->files as $f) {
            $name = $f[0];
            $data = $f[1];
            $crc = crc32($data);
            $size = strlen($data);
            $method = 0;
            $comp = $data;
            if (function_exists('gzdeflate')) {
                $z = gzdeflate($data, 6);
                if ($z !== false && strlen($z) < $size) { $comp = $z; $method = 8; }
            }
            $csize = strlen($comp);
            $offset = strlen($out);
            $out .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $time, $date, $crc, $csize, $size, strlen($name), 0) . $name . $comp;
            $cd .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $method, $time, $date, $crc, $csize, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
            $count++;
        }
        $cdOffset = strlen($out);
        return $out . $cd . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($cd), $cdOffset, 0);
    }
}

/* ============================================================================
   4. XLSX ENGINE
   ========================================================================== */

class AgxStyles {
    private $fonts = array();
    private $fills = array();
    private $borders = array();
    private $fmts = array();
    private $xfs = array();
    private $map = array();

    public function __construct() {
        $this->fonts[] = '<font><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/></font>';
        $this->fills[] = '<fill><patternFill patternType="none"/></fill>';
        $this->fills[] = '<fill><patternFill patternType="gray125"/></fill>';
        $this->borders[] = '<border><left/><right/><top/><bottom/><diagonal/></border>';
        $this->xfs[] = '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>';
    }

    private function pick(&$list, $xml) {
        $i = array_search($xml, $list, true);
        if ($i === false) { $list[] = $xml; $i = count($list) - 1; }
        return $i;
    }

    private function fontId($o) {
        $x = '<font>';
        if (!empty($o['b'])) { $x .= '<b/>'; }
        if (!empty($o['i'])) { $x .= '<i/>'; }
        $x .= '<sz val="' . (isset($o['sz']) ? $o['sz'] : 10) . '"/>';
        $x .= '<color rgb="FF' . (isset($o['color']) ? $o['color'] : '0F172A') . '"/>';
        $x .= '<name val="Calibri"/><family val="2"/></font>';
        return $this->pick($this->fonts, $x);
    }

    private function fillId($rgb) {
        $x = '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $rgb . '"/><bgColor indexed="64"/></patternFill></fill>';
        return $this->pick($this->fills, $x);
    }

    private function borderId($b) {
        $sides = array('l' => 'left', 'r' => 'right', 't' => 'top', 'b' => 'bottom');
        $x = '<border>';
        foreach ($sides as $k => $tag) {
            $s = isset($b[$k]) ? $b[$k] : (isset($b['all']) ? $b['all'] : null);
            if ($s) {
                $x .= '<' . $tag . ' style="' . $s[0] . '"><color rgb="FF' . $s[1] . '"/></' . $tag . '>';
            } else {
                $x .= '<' . $tag . '/>';
            }
        }
        $x .= '<diagonal/></border>';
        return $this->pick($this->borders, $x);
    }

    private function fmtId($code) {
        if ($code === null || $code === '' || $code === 'General') { return 0; }
        $builtin = array('0' => 1, '0.00' => 2, '#,##0' => 3, '#,##0.00' => 4, '0%' => 9, '0.00%' => 10);
        if (isset($builtin[$code])) { return $builtin[$code]; }
        if (!isset($this->fmts[$code])) { $this->fmts[$code] = 164 + count($this->fmts); }
        return $this->fmts[$code];
    }

    public function id($s) {
        $key = json_encode($s);
        if (isset($this->map[$key])) { return $this->map[$key]; }
        $f = isset($s['font']) ? $this->fontId($s['font']) : 0;
        $fill = isset($s['fill']) ? $this->fillId($s['fill']) : 0;
        $b = isset($s['border']) ? $this->borderId($s['border']) : 0;
        $n = isset($s['fmt']) ? $this->fmtId($s['fmt']) : 0;
        $al = '';
        if (isset($s['h'])) { $al .= ' horizontal="' . $s['h'] . '"'; }
        if (isset($s['v'])) { $al .= ' vertical="' . $s['v'] . '"'; }
        if (!empty($s['wrap'])) { $al .= ' wrapText="1"'; }
        if (!empty($s['indent'])) { $al .= ' indent="' . (int)$s['indent'] . '"'; }
        $xf = '<xf numFmtId="' . $n . '" fontId="' . $f . '" fillId="' . $fill . '" borderId="' . $b . '" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"';
        $xf .= ($al !== '') ? ' applyAlignment="1"><alignment' . $al . '/></xf>' : '/>';
        $this->xfs[] = $xf;
        $idx = count($this->xfs) - 1;
        $this->map[$key] = $idx;
        return $idx;
    }

    public function xml() {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $x .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if (count($this->fmts)) {
            $x .= '<numFmts count="' . count($this->fmts) . '">';
            foreach ($this->fmts as $code => $id) {
                $x .= '<numFmt numFmtId="' . $id . '" formatCode="' . agx_xml($code) . '"/>';
            }
            $x .= '</numFmts>';
        }
        $x .= '<fonts count="' . count($this->fonts) . '">' . implode('', $this->fonts) . '</fonts>';
        $x .= '<fills count="' . count($this->fills) . '">' . implode('', $this->fills) . '</fills>';
        $x .= '<borders count="' . count($this->borders) . '">' . implode('', $this->borders) . '</borders>';
        $x .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $x .= '<cellXfs count="' . count($this->xfs) . '">' . implode('', $this->xfs) . '</cellXfs>';
        $x .= '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><dxfs count="0"/></styleSheet>';
        return $x;
    }
}

class AgxSheet {
    public $name;
    public $selected = false;
    public $freeze = 0;
    public $filter = null;
    public $tab = null;
    public $printRow = null;
    public $footer = false;
    public $fitHeight = 0;
    public $charts = array();
    private $wb;
    private $cells = array();
    private $heights = array();
    private $widths = array();
    private $merges = array();

    public function __construct($wb, $name) {
        $this->wb = $wb;
        $this->name = $name;
    }

    public function str($r, $c, $t, $s = 0) { $this->cells[$r][$c] = array('s', $this->wb->sst($t), $s, null); }
    public function num($r, $c, $v, $s = 0) { $this->cells[$r][$c] = array('n', $v, $s, null); }
    public function blank($r, $c, $s = 0) { $this->cells[$r][$c] = array('b', null, $s, null); }
    public function formula($r, $c, $f, $cached, $s = 0) { $this->cells[$r][$c] = array('f', $f, $s, $cached); }
    public function height($r, $h) { $this->heights[$r] = $h; }
    public function width($c1, $c2, $w) { $this->widths[] = array($c1, $c2, $w); }

    public function merge($r1, $c1, $r2, $c2, $v, $s = 0) {
        for ($r = $r1; $r <= $r2; $r++) {
            for ($c = $c1; $c <= $c2; $c++) { $this->blank($r, $c, $s); }
        }
        if (is_int($v) || is_float($v)) { $this->num($r1, $c1, $v, $s); } else { $this->str($r1, $c1, (string)$v, $s); }
        if ($r1 != $r2 || $c1 != $c2) { $this->merges[] = agx_ref($r1, $c1) . ':' . agx_ref($r2, $c2); }
    }

    private function cellXml($r, $c, $cell) {
        $ref = agx_ref($r, $c);
        $s = $cell[2] ? ' s="' . $cell[2] . '"' : '';
        if ($cell[0] === 's') { return '<c r="' . $ref . '"' . $s . ' t="s"><v>' . $cell[1] . '</v></c>'; }
        if ($cell[0] === 'n') { return '<c r="' . $ref . '"' . $s . '><v>' . agx_num($cell[1]) . '</v></c>'; }
        if ($cell[0] === 'f') { return '<c r="' . $ref . '"' . $s . '><f>' . agx_xml($cell[1]) . '</f><v>' . agx_num($cell[3]) . '</v></c>'; }
        return '<c r="' . $ref . '"' . $s . '/>';
    }

    public function xml($hasDrawing) {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $x .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $x .= '<sheetPr>' . ($this->tab ? '<tabColor rgb="FF' . $this->tab . '"/>' : '') . '<pageSetUpPr fitToPage="1"/></sheetPr>';
        $x .= '<sheetViews><sheetView showGridLines="0"' . ($this->selected ? ' tabSelected="1"' : '') . ' workbookViewId="0">';
        if ($this->freeze > 0) {
            $tl = 'A' . ($this->freeze + 1);
            $x .= '<pane ySplit="' . $this->freeze . '" topLeftCell="' . $tl . '" activePane="bottomLeft" state="frozen"/>';
            $x .= '<selection pane="bottomLeft" activeCell="' . $tl . '" sqref="' . $tl . '"/>';
        }
        $x .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/>';

        if (count($this->widths)) {
            $w = $this->widths;
            usort($w, function ($a, $b) { return $a[0] - $b[0]; });
            $x .= '<cols>';
            foreach ($w as $c) {
                $x .= '<col min="' . ($c[0] + 1) . '" max="' . ($c[1] + 1) . '" width="' . agx_num($c[2] + 0.7109375) . '" customWidth="1"/>';
            }
            $x .= '</cols>';
        }

        $x .= '<sheetData>';
        $rows = array_unique(array_merge(array_keys($this->cells), array_keys($this->heights)));
        sort($rows);
        foreach ($rows as $r) {
            $x .= '<row r="' . ($r + 1) . '"';
            if (isset($this->heights[$r])) { $x .= ' ht="' . agx_num($this->heights[$r]) . '" customHeight="1"'; }
            if (empty($this->cells[$r])) { $x .= '/>'; continue; }
            $x .= '>';
            $cs = $this->cells[$r];
            ksort($cs);
            foreach ($cs as $c => $cell) { $x .= $this->cellXml($r, $c, $cell); }
            $x .= '</row>';
        }
        $x .= '</sheetData>';

        if ($this->filter) { $x .= '<autoFilter ref="' . $this->filter . '"/>'; }
        if (count($this->merges)) {
            $x .= '<mergeCells count="' . count($this->merges) . '">';
            foreach ($this->merges as $m) { $x .= '<mergeCell ref="' . $m . '"/>'; }
            $x .= '</mergeCells>';
        }
        $x .= '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>';
        $x .= '<pageSetup paperSize="9" orientation="landscape"' . ($this->fitHeight === 0 ? ' fitToHeight="0"' : '') . '/>';
        if ($this->footer) {
            $x .= '<headerFooter><oddFooter>&amp;LAgriTrace+ Report&amp;CPage &amp;P of &amp;N&amp;R&amp;D</oddFooter></headerFooter>';
        }
        if ($hasDrawing) { $x .= '<drawing r:id="rId1"/>'; }
        return $x . '</worksheet>';
    }
}

class AgxXlsx {
    public $styles;
    private $sheets = array();
    private $sst = array();
    private $sstMap = array();
    private $sstCount = 0;

    public function __construct() {
        $this->styles = new AgxStyles();
    }

    public function st($spec) { return $this->styles->id($spec); }

    public function sst($t) {
        $t = (string)$t;
        $this->sstCount++;
        if (!isset($this->sstMap[$t])) {
            $this->sstMap[$t] = count($this->sst);
            $this->sst[] = $t;
        }
        return $this->sstMap[$t];
    }

    public function addSheet($name) {
        $s = new AgxSheet($this, $name);
        $this->sheets[] = $s;
        return $s;
    }

    private function sstXml() {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $x .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $this->sstCount . '" uniqueCount="' . count($this->sst) . '">';
        foreach ($this->sst as $t) { $x .= '<si><t xml:space="preserve">' . agx_xml($t) . '</t></si>'; }
        return $x . '</sst>';
    }

    public function build() {
        $zip = new AgxZip();
        $n = count($this->sheets);
        $hdr = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $relNs = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $relT = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
        $ct = $hdr . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $ct .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>';
        $ct .= '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
        $ct .= '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
        $ct .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $ct .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $ct .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';

        $wbSheets = '';
        $wbRels = '';
        $names = '';
        $parts = array();
        $dn = 0;
        $cn = 0;

        for ($i = 0; $i < $n; $i++) {
            $sh = $this->sheets[$i];
            $sn = $i + 1;
            $hasCharts = count($sh->charts) > 0;
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $sn . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $wbSheets .= '<sheet name="' . agx_xml($sh->name) . '" sheetId="' . $sn . '" r:id="rId' . $sn . '"/>';
            $wbRels .= '<Relationship Id="rId' . $sn . '" Type="' . $relT . 'worksheet" Target="worksheets/sheet' . $sn . '.xml"/>';
            $qn = "'" . str_replace("'", "''", $sh->name) . "'";
            if ($sh->filter) {
                $a = explode(':', $sh->filter);
                $abs = array();
                foreach ($a as $cell) { preg_match('/^([A-Z]+)(\d+)$/', $cell, $mm); $abs[] = '$' . $mm[1] . '$' . $mm[2]; }
                $names .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">' . agx_xml($qn . '!' . implode(':', $abs)) . '</definedName>';
            }
            if ($sh->printRow !== null) {
                $names .= '<definedName name="_xlnm.Print_Titles" localSheetId="' . $i . '">' . agx_xml($qn . '!$' . ($sh->printRow + 1) . ':$' . ($sh->printRow + 1)) . '</definedName>';
            }
            $zip_parts_sheet = $sh->xml($hasCharts);
            $parts['xl/worksheets/sheet' . $sn . '.xml'] = $zip_parts_sheet;

            if ($hasCharts) {
                $dn++;
                $parts['xl/worksheets/_rels/sheet' . $sn . '.xml.rels'] = $hdr . '<Relationships xmlns="' . $relNs . '"><Relationship Id="rId1" Type="' . $relT . 'drawing" Target="../drawings/drawing' . $dn . '.xml"/></Relationships>';
                $ct .= '<Override PartName="/xl/drawings/drawing' . $dn . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
                $dx = $hdr . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">';
                $dr = $hdr . '<Relationships xmlns="' . $relNs . '">';
                foreach ($sh->charts as $k => $ch) {
                    $cn++;
                    $fr = $ch['from'];
                    $to = $ch['to'];
                    $dx .= '<xdr:twoCellAnchor>';
                    $dx .= '<xdr:from><xdr:col>' . $fr[0] . '</xdr:col><xdr:colOff>' . $fr[1] . '</xdr:colOff><xdr:row>' . $fr[2] . '</xdr:row><xdr:rowOff>' . $fr[3] . '</xdr:rowOff></xdr:from>';
                    $dx .= '<xdr:to><xdr:col>' . $to[0] . '</xdr:col><xdr:colOff>' . $to[1] . '</xdr:colOff><xdr:row>' . $to[2] . '</xdr:row><xdr:rowOff>' . $to[3] . '</xdr:rowOff></xdr:to>';
                    $dx .= '<xdr:graphicFrame macro=""><xdr:nvGraphicFramePr><xdr:cNvPr id="' . ($k + 2) . '" name="Chart ' . ($k + 1) . '"/><xdr:cNvGraphicFramePr/></xdr:nvGraphicFramePr>';
                    $dx .= '<xdr:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></xdr:xfrm>';
                    $dx .= '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="rId' . ($k + 1) . '"/></a:graphicData></a:graphic>';
                    $dx .= '</xdr:graphicFrame><xdr:clientData/></xdr:twoCellAnchor>';
                    $dr .= '<Relationship Id="rId' . ($k + 1) . '" Type="' . $relT . 'chart" Target="../charts/chart' . $cn . '.xml"/>';
                    $ct .= '<Override PartName="/xl/charts/chart' . $cn . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>';
                    $parts['xl/charts/chart' . $cn . '.xml'] = agx_chart_xml($ch, $cn);
                }
                $parts['xl/drawings/drawing' . $dn . '.xml'] = $dx . '</xdr:wsDr>';
                $parts['xl/drawings/_rels/drawing' . $dn . '.xml.rels'] = $dr . '</Relationships>';
            }
        }
        $ct .= '</Types>';

        $wbRels .= '<Relationship Id="rId' . ($n + 1) . '" Type="' . $relT . 'styles" Target="styles.xml"/>';
        $wbRels .= '<Relationship Id="rId' . ($n + 2) . '" Type="' . $relT . 'sharedStrings" Target="sharedStrings.xml"/>';

        $wbx = $hdr . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $wbx .= '<workbookPr/><bookViews><workbookView xWindow="240" yWindow="15" windowWidth="16095" windowHeight="9660" activeTab="0"/></bookViews>';
        $wbx .= '<sheets>' . $wbSheets . '</sheets>';
        if ($names !== '') { $wbx .= '<definedNames>' . $names . '</definedNames>'; }
        $wbx .= '<calcPr calcId="124519" fullCalcOnLoad="1"/></workbook>';

        $iso = gmdate('Y-m-d\TH:i:s\Z');
        $zip->add('[Content_Types].xml', $ct);
        $zip->add('_rels/.rels', $hdr . '<Relationships xmlns="' . $relNs . '"><Relationship Id="rId1" Type="' . $relT . 'officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="' . $relT . 'extended-properties" Target="docProps/app.xml"/></Relationships>');
        $zip->add('docProps/core.xml', $hdr . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>AgriTrace+ Report</dc:title><dc:creator>AgriTrace+</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . $iso . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $iso . '</dcterms:modified></cp:coreProperties>');
        $zip->add('docProps/app.xml', $hdr . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Microsoft Excel</Application></Properties>');
        $zip->add('xl/workbook.xml', $wbx);
        $zip->add('xl/_rels/workbook.xml.rels', $hdr . '<Relationships xmlns="' . $relNs . '">' . $wbRels . '</Relationships>');
        $zip->add('xl/styles.xml', $this->styles->xml());
        $zip->add('xl/sharedStrings.xml', $this->sstXml());
        foreach ($parts as $path => $data) { $zip->add($path, $data); }
        return $zip->build();
    }
}

/* ---- chart XML ---------------------------------------------------------- */

function agx_txpr($sz, $color, $bold = false) {
    return '<c:txPr><a:bodyPr/><a:lstStyle/><a:p><a:pPr><a:defRPr sz="' . $sz . '"' . ($bold ? ' b="1"' : '') . ' baseline="0"><a:solidFill><a:srgbClr val="' . $color . '"/></a:solidFill></a:defRPr></a:pPr><a:endParaRPr lang="en-US"/></a:p></c:txPr>';
}

function agx_chart_xml($ch, $n) {
    $t = $ch['type'];
    $ax1 = 50010000 + $n * 10 + 1;
    $ax2 = $ax1 + 1;
    $labels = $ch['labels'];
    $values = $ch['values'];
    $cnt = count($labels);
    $perPoint = ($t === 'doughnut' || $t === 'pie' || $ch['perPoint']);

    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $x .= '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><c:lang val="en-US"/><c:chart>';
    $x .= '<c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:pPr><a:defRPr sz="1200" b="1" baseline="0"><a:solidFill><a:srgbClr val="' . agx_c('forest') . '"/></a:solidFill></a:defRPr></a:pPr>';
    $x .= '<a:r><a:rPr lang="en-US" sz="1200" b="1" baseline="0"><a:solidFill><a:srgbClr val="' . agx_c('forest') . '"/></a:solidFill></a:rPr><a:t>' . agx_xml($ch['title']) . '</a:t></a:r></a:p></c:rich></c:tx><c:layout/></c:title>';
    $x .= '<c:plotArea><c:layout/>';

    $ser = '<c:ser><c:idx val="0"/><c:order val="0"/>';
    $ser .= '<c:tx><c:strRef><c:f>' . agx_xml($ch['nameRef']) . '</c:f><c:strCache><c:ptCount val="1"/><c:pt idx="0"><c:v>' . agx_xml($ch['seriesName']) . '</c:v></c:pt></c:strCache></c:strRef></c:tx>';
    if ($t !== 'doughnut' && $t !== 'pie') {
        $ser .= '<c:spPr><a:solidFill><a:srgbClr val="' . $ch['colors'][0] . '"/></a:solidFill></c:spPr><c:invertIfNegative val="0"/>';
    }
    if ($perPoint) {
        for ($i = 0; $i < $cnt; $i++) {
            $ser .= '<c:dPt><c:idx val="' . $i . '"/>' . (($t === 'doughnut' || $t === 'pie') ? '' : '<c:invertIfNegative val="0"/>');
            $ser .= '<c:spPr><a:solidFill><a:srgbClr val="' . $ch['colors'][$i] . '"/></a:solidFill></c:spPr></c:dPt>';
        }
    }
    if ($t === 'doughnut' || $t === 'pie') {
        $ser .= '<c:dLbls>' . agx_txpr(900, 'FFFFFF', true) . '<c:showLegendKey val="0"/><c:showVal val="0"/><c:showCatName val="0"/><c:showSerName val="0"/><c:showPercent val="1"/><c:showBubbleSize val="0"/></c:dLbls>';
    } else {
        $ser .= '<c:dLbls>' . agx_txpr(900, '334155', true) . '<c:dLblPos val="outEnd"/><c:showLegendKey val="0"/><c:showVal val="1"/><c:showCatName val="0"/><c:showSerName val="0"/><c:showPercent val="0"/><c:showBubbleSize val="0"/></c:dLbls>';
    }
    $ser .= '<c:cat><c:strRef><c:f>' . agx_xml($ch['catRef']) . '</c:f><c:strCache><c:ptCount val="' . $cnt . '"/>';
    for ($i = 0; $i < $cnt; $i++) { $ser .= '<c:pt idx="' . $i . '"><c:v>' . agx_xml($labels[$i]) . '</c:v></c:pt>'; }
    $ser .= '</c:strCache></c:strRef></c:cat>';
    $ser .= '<c:val><c:numRef><c:f>' . agx_xml($ch['valRef']) . '</c:f><c:numCache><c:formatCode>General</c:formatCode><c:ptCount val="' . $cnt . '"/>';
    for ($i = 0; $i < $cnt; $i++) { $ser .= '<c:pt idx="' . $i . '"><c:v>' . agx_num($values[$i]) . '</c:v></c:pt>'; }
    $ser .= '</c:numCache></c:numRef></c:val></c:ser>';

    if ($t === 'doughnut') {
        $x .= '<c:doughnutChart><c:varyColors val="1"/>' . $ser . '<c:firstSliceAng val="0"/><c:holeSize val="58"/></c:doughnutChart>';
    } elseif ($t === 'pie') {
        $x .= '<c:pieChart><c:varyColors val="1"/>' . $ser . '<c:firstSliceAng val="0"/></c:pieChart>';
    } else {
        $horizontal = ($t === 'bar');
        $x .= '<c:barChart><c:barDir val="' . ($horizontal ? 'bar' : 'col') . '"/><c:grouping val="clustered"/><c:varyColors val="0"/>' . $ser;
        $x .= '<c:gapWidth val="60"/><c:axId val="' . $ax1 . '"/><c:axId val="' . $ax2 . '"/></c:barChart>';
        $x .= '<c:catAx><c:axId val="' . $ax1 . '"/><c:scaling><c:orientation val="' . ($horizontal ? 'maxMin' : 'minMax') . '"/></c:scaling><c:delete val="0"/><c:axPos val="' . ($horizontal ? 'l' : 'b') . '"/>';
        $x .= '<c:numFmt formatCode="General" sourceLinked="1"/><c:majorTickMark val="none"/><c:minorTickMark val="none"/><c:tickLblPos val="nextTo"/>';
        $x .= '<c:spPr><a:ln><a:solidFill><a:srgbClr val="CBD5E1"/></a:solidFill></a:ln></c:spPr>' . agx_txpr(900, '475569');
        $x .= '<c:crossAx val="' . $ax2 . '"/><c:crosses val="autoZero"/><c:auto val="1"/><c:lblAlgn val="ctr"/><c:lblOffset val="100"/><c:noMultiLvlLbl val="0"/></c:catAx>';
        $x .= '<c:valAx><c:axId val="' . $ax2 . '"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="' . ($horizontal ? 'b' : 'l') . '"/>';
        $x .= '<c:majorGridlines><c:spPr><a:ln><a:solidFill><a:srgbClr val="E2E8F0"/></a:solidFill></a:ln></c:spPr></c:majorGridlines>';
        $x .= '<c:numFmt formatCode="General" sourceLinked="1"/><c:majorTickMark val="none"/><c:minorTickMark val="none"/><c:tickLblPos val="nextTo"/>';
        $x .= '<c:spPr><a:ln><a:noFill/></a:ln></c:spPr>' . agx_txpr(900, '64748B');
        $x .= '<c:crossAx val="' . $ax1 . '"/><c:crosses val="' . ($horizontal ? 'max' : 'autoZero') . '"/><c:crossBetween val="between"/></c:valAx>';
    }
    $x .= '</c:plotArea>';
    if ($t === 'doughnut' || $t === 'pie') {
        $x .= '<c:legend><c:legendPos val="r"/><c:layout/>' . agx_txpr(900, '475569') . '</c:legend>';
    }
    $x .= '<c:plotVisOnly val="1"/><c:dispBlanksAs val="gap"/></c:chart>';
    $x .= '<c:spPr><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill><a:ln><a:solidFill><a:srgbClr val="E2E8F0"/></a:solidFill></a:ln></c:spPr>';
    return $x . '</c:chartSpace>';
}

/* ---- Summary sheet ------------------------------------------------------ */

function agx_xlsx_summary($wb, $sum, $by, $now) {
    $S = $wb->addSheet('Summary');
    $S->tab = agx_c('green');
    $S->selected = true;
    $S->fitHeight = 1;
    $S->width(0, 0, 2);
    $S->width(1, 12, 13);
    $S->width(13, 13, 2);

    // banner
    $band = $wb->st(array('fill' => agx_c('dark')));
    $stripe = $wb->st(array('fill' => agx_c('green')));
    for ($r = 0; $r < 3; $r++) { for ($c = 0; $c < 14; $c++) { $S->blank($r, $c, $band); } }
    for ($c = 0; $c < 14; $c++) { $S->blank(3, $c, $stripe); }
    $S->height(0, 10); $S->height(1, 38); $S->height(2, 22); $S->height(3, 4);
    $S->merge(1, 1, 1, 12, strtoupper($sum['title']) . '  •  AgriTrace+', $wb->st(array(
        'font' => array('b' => 1, 'sz' => 20, 'color' => 'FFFFFF'), 'fill' => agx_c('dark'), 'v' => 'center', 'h' => 'left')));
    $S->merge(2, 1, 2, 12, $sum['sub'] . '   |   Generated ' . $now . ' by ' . $by, $wb->st(array(
        'font' => array('sz' => 10, 'color' => 'A7F3D0'), 'fill' => agx_c('dark'), 'v' => 'center', 'h' => 'left')));
    $S->height(4, 10);

    // KPI tiles (up to 6, two columns each)
    $S->height(5, 22); $S->height(6, 40); $S->height(7, 20); $S->height(8, 10);
    $line = agx_c('line');
    $kpis = array_slice($sum['kpis'], 0, 6);
    foreach ($kpis as $i => $k) {
        $c0 = 1 + $i * 2;
        $col = $k[2];
        $S->merge(5, $c0, 5, $c0 + 1, $k[0], $wb->st(array(
            'font' => array('b' => 1, 'sz' => 9, 'color' => agx_c('muted')), 'fill' => agx_c('soft'),
            'border' => array('l' => array('thin', $line), 'r' => array('thin', $line), 't' => array('thick', $col)),
            'h' => 'center', 'v' => 'bottom')));
        $isFloat = is_float($k[1]);
        $S->merge(6, $c0, 6, $c0 + 1, $k[1], $wb->st(array(
            'font' => array('b' => 1, 'sz' => 26, 'color' => $col), 'fill' => agx_c('soft'),
            'border' => array('l' => array('thin', $line), 'r' => array('thin', $line)),
            'h' => 'center', 'v' => 'center', 'fmt' => $isFloat ? '#,##0.0' : '#,##0')));
        $S->merge(7, $c0, 7, $c0 + 1, $k[3], $wb->st(array(
            'font' => array('i' => 1, 'sz' => 9, 'color' => agx_c('muted')), 'fill' => agx_c('soft'),
            'border' => array('l' => array('thin', $line), 'r' => array('thin', $line), 'b' => array('thin', $line)),
            'h' => 'center', 'v' => 'top')));
    }

    // keep only charts that have data (max 4)
    $charts = array();
    foreach ($sum['charts'] as $ch) {
        if (!empty($ch['data'])) { $ch['data'] = array_slice($ch['data'], 0, 10); $charts[] = $ch; }
        if (count($charts) >= 4) { break; }
    }
    $nc = count($charts);
    if ($nc === 0) { return; }

    $blocks = (int)ceil($nc / 2);
    $hdrRow = 9 + $blocks * 19;       // "CHART DATA" caption row
    $tblTop = $hdrRow + 1;
    $S->merge($hdrRow, 1, $hdrRow, 12, 'CHART DATA', $wb->st(array(
        'font' => array('b' => 1, 'sz' => 11, 'color' => agx_c('forest')),
        'border' => array('b' => array('medium', agx_c('green'))), 'v' => 'center')));

    $th = $wb->st(array('font' => array('b' => 1, 'sz' => 10, 'color' => 'FFFFFF'), 'fill' => agx_c('forest'), 'h' => 'left', 'v' => 'center', 'indent' => 1));
    $thr = $wb->st(array('font' => array('b' => 1, 'sz' => 10, 'color' => 'FFFFFF'), 'fill' => agx_c('forest'), 'h' => 'center', 'v' => 'center'));
    $td = $wb->st(array('font' => array('sz' => 10), 'v' => 'center', 'h' => 'left', 'indent' => 1, 'border' => array('b' => array('thin', $line))));
    $tdn = $wb->st(array('font' => array('b' => 1, 'sz' => 10), 'v' => 'center', 'h' => 'center', 'fmt' => '#,##0', 'border' => array('b' => array('thin', $line))));

    $colW = 962025;   // EMU width of a 13.7-char column
    foreach ($charts as $i => $ch) {
        $col0 = ($i % 2 === 0) ? 1 : 7;
        $row0 = $tblTop + (int)floor($i / 2) * 13;
        $S->merge($row0, $col0, $row0, $col0 + 1, $ch['head'][0], $th);
        $S->str($row0, $col0 + 2, $ch['head'][1], $thr);
        $labels = array(); $values = array();
        foreach ($ch['data'] as $j => $d) {
            $S->merge($row0 + 1 + $j, $col0, $row0 + 1 + $j, $col0 + 1, (string)$d[0], $td);
            $S->num($row0 + 1 + $j, $col0 + 2, $d[1], $tdn);
            $labels[] = (string)$d[0];
            $values[] = $d[1];
        }
        $k = count($labels);

        // point colours
        $pal = agx_palette();
        $colors = array();
        $perPoint = false;
        foreach ($labels as $j => $lab) {
            if ($ch['colors'] === 'status') { $colors[] = agx_status_color($lab); $perPoint = true; }
            elseif ($ch['colors'] === 'palette') { $colors[] = $pal[$j % count($pal)]; $perPoint = true; }
            else { $colors[] = $ch['colors']; }
        }
        if ($ch['type'] === 'doughnut' || $ch['type'] === 'pie') { $perPoint = true; }
        if (!$perPoint) { $colors = array($ch['colors']); }
        elseif ($ch['type'] !== 'doughnut' && $ch['type'] !== 'pie') { array_unshift($colors, $colors[0]); }
        if ($perPoint && ($ch['type'] === 'doughnut' || $ch['type'] === 'pie') === false) {
            // bar/column with per-point colours: series colour = first, dPt colours follow
            $series = array_shift($colors);
            $pc = $colors;
            $colors = $pc;
            array_unshift($colors, $series);
        }

        $baseRow = 9 + (int)floor($i / 2) * 19;
        $fromCol = $col0;
        if ($col0 === 1) {
            $from = array(1, 0, $baseRow, 0);
            $to = array(6, $colW - 70000, $baseRow + 18, 0);
        } else {
            $from = array(7, 70000, $baseRow, 0);
            $to = array(13, 0, $baseRow + 18, 0);
        }
        $S->charts[] = array(
            'type' => $ch['type'], 'title' => $ch['title'], 'labels' => $labels, 'values' => $values,
            'colors' => $colors, 'perPoint' => $perPoint,
            'nameRef' => agx_range('Summary', $row0, $col0 + 2, $row0, $col0 + 2),
            'catRef' => agx_range('Summary', $row0 + 1, $col0, $row0 + $k, $col0),
            'valRef' => agx_range('Summary', $row0 + 1, $col0 + 2, $row0 + $k, $col0 + 2),
            'seriesName' => $ch['head'][1], 'from' => $from, 'to' => $to,
        );
    }
}

/* ---- Data sheet --------------------------------------------------------- */

function agx_xlsx_data($wb, $ds, $by, $now) {
    $cols = $ds['cols'];
    $rows = $ds['rows'];
    $n = count($cols);
    $D = $wb->addSheet($ds['sheet']);
    $D->tab = agx_c('emerald');
    $D->freeze = 4;
    $D->printRow = 3;
    $D->footer = true;
    foreach ($cols as $i => $c) { $D->width($i, $i, $c['w']); }
    $D->height(0, 34); $D->height(1, 22); $D->height(2, 8); $D->height(3, 32);

    $D->merge(0, 0, 0, $n - 1, $ds['title'], $wb->st(array(
        'font' => array('b' => 1, 'sz' => 18, 'color' => 'FFFFFF'), 'fill' => agx_c('dark'), 'v' => 'center', 'h' => 'left', 'indent' => 1)));
    $D->merge(1, 0, 1, $n - 1, 'Generated ' . $now . '  •  By ' . $by . '  •  ' . count($rows) . ' record(s)  •  Camarines Sur 1st District', $wb->st(array(
        'font' => array('i' => 1, 'sz' => 10, 'color' => agx_c('forest')), 'fill' => agx_c('mint'), 'v' => 'center', 'h' => 'left', 'indent' => 1)));

    $hs = $wb->st(array('font' => array('b' => 1, 'sz' => 11, 'color' => 'FFFFFF'), 'fill' => agx_c('emerald'),
        'border' => array('all' => array('thin', agx_c('forest'))), 'h' => 'center', 'v' => 'center', 'wrap' => 1));
    foreach ($cols as $i => $c) { $D->str(3, $i, $c['h'], $hs); }

    $line = agx_c('line');
    $r0 = 4;
    if (count($rows) === 0) {
        $D->height($r0, 26);
        $D->merge($r0, 0, $r0, $n - 1, 'No records found.', $wb->st(array(
            'font' => array('i' => 1, 'sz' => 11, 'color' => agx_c('muted')), 'h' => 'center', 'v' => 'center',
            'border' => array('all' => array('thin', $line)))));
        return;
    }

    $sums = array();
    $ri = 0;
    foreach ($rows as $row) {
        $r = $r0 + $ri;
        $z = ($ri % 2 === 1);
        $lines = 1;
        foreach ($cols as $ci => $c) {
            $t = $c['t'];
            $v = agx_raw($c, $row);
            $base = array('font' => array('sz' => 10), 'v' => 'center', 'border' => array('all' => array('thin', $line)));
            if ($z) { $base['fill'] = agx_c('zebra'); }

            if ($t === 'status') {
                $sv = (string)$v;
                $ss = agx_status_style($sv);
                $base['font'] = array('b' => 1, 'sz' => 10, 'color' => $ss[1]);
                $base['fill'] = $ss[0];
                $base['h'] = 'center';
                $D->str($r, $ci, $sv, $wb->st($base));
            } elseif ($t === 'int' || $t === 'dec') {
                $base['h'] = 'right';
                $base['fmt'] = ($t === 'int') ? '#,##0' : '#,##0.00';
                $num = ($v === null || $v === '') ? 0 : $v + 0;
                $D->num($r, $ci, $num, $wb->st($base));
                if (!empty($c['sum'])) { $sums[$ci] = (isset($sums[$ci]) ? $sums[$ci] : 0) + $num; }
            } elseif ($t === 'dec6') {
                $base['h'] = 'center';
                if ($v === null || $v === '') { $D->blank($r, $ci, $wb->st($base)); }
                else { $base['fmt'] = '0.000000'; $D->num($r, $ci, (float)$v, $wb->st($base)); }
            } elseif ($t === 'date') {
                $base['h'] = 'center';
                $ser = agx_excel_date($v);
                if ($ser === null) { $D->blank($r, $ci, $wb->st($base)); }
                else { $base['fmt'] = 'dd-mmm-yyyy hh:mm AM/PM'; $D->num($r, $ci, $ser, $wb->st($base)); }
            } elseif ($t === 'id' || $t === 'phone') {
                $base['h'] = 'center';
                $D->str($r, $ci, (string)$v, $wb->st($base));
            } else {
                $base['h'] = 'left';
                $base['indent'] = 1;
                if (!empty($c['wrap'])) {
                    $base['wrap'] = 1;
                    $len = strlen((string)$v);
                    $est = (int)ceil($len / max(8, $c['w'] * 1.05));
                    if ($est > $lines) { $lines = $est; }
                }
                $D->str($r, $ci, (string)$v, $wb->st($base));
            }
        }
        $D->height($r, $lines > 1 ? min(150, 15 * $lines + 6) : 21);
        $ri++;
    }
    $last = $r0 + count($rows) - 1;

    // totals row
    $has = false;
    foreach ($cols as $c) { if (!empty($c['sum'])) { $has = true; } }
    $D->filter = 'A4:' . agx_col($n - 1) . ($last + 1);
    if ($has) {
        $tr = $last + 1;
        $D->height($tr, 24);
        $tf = $wb->st(array('font' => array('b' => 1, 'sz' => 11, 'color' => agx_c('forest')), 'fill' => agx_c('mint'),
            'border' => array('t' => array('medium', agx_c('emerald'))), 'v' => 'center', 'h' => 'left', 'indent' => 1));
        foreach ($cols as $ci => $c) {
            if ($ci === 0) { $D->str($tr, $ci, 'TOTAL', $tf); }
            elseif (!empty($c['sum'])) {
                $L = agx_col($ci);
                $D->formula($tr, $ci, 'SUM(' . $L . '5:' . $L . ($last + 1) . ')', isset($sums[$ci]) ? $sums[$ci] : 0, $wb->st(array(
                    'font' => array('b' => 1, 'sz' => 11, 'color' => agx_c('forest')), 'fill' => agx_c('mint'),
                    'border' => array('t' => array('medium', agx_c('emerald'))), 'v' => 'center', 'h' => 'right',
                    'fmt' => ($c['t'] === 'dec') ? '#,##0.00' : '#,##0')));
            } else {
                $D->blank($tr, $ci, $tf);
            }
        }
    }
}

function agx_build_xlsx($summary, $datasets, $by) {
    $wb = new AgxXlsx();
    $now = agx_now_text();
    agx_xlsx_summary($wb, $summary, $by, $now);
    foreach ($datasets as $ds) { agx_xlsx_data($wb, $ds, $by, $now); }
    return $wb->build();
}

/* ============================================================================
   5. CSV
   ========================================================================== */

function agx_build_csv($datasets, $by, $summary = null) {
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel shows accents / symbols correctly

    // ── 1. EXECUTIVE REPORT BANNER & METADATA (When summary is present) ──────
    if ($summary && !empty($summary['title'])) {
        fputcsv($fh, array('AGRITRACE+ - ' . mb_strtoupper($summary['title'])), ',', '"', '\\');
        if (!empty($summary['sub'])) {
            fputcsv($fh, array($summary['sub']), ',', '"', '\\');
        }
        fputcsv($fh, array('Generated On', agx_now_text(), 'Generated By', $by), ',', '"', '\\');

        $totalRecords = 0;
        foreach ($datasets as $ds) { $totalRecords += count($ds['rows']); }
        fputcsv($fh, array('Total Records', $totalRecords, 'Jurisdiction', 'District I (Cabusao, Del Gallego, Lupi, Ragay, Sipocot)'), ',', '"', '\\');
        fwrite($fh, "\r\n");

        // ── 2. EXECUTIVE KPI SUMMARY BLOCK ───────────────────────────────────
        if (!empty($summary['kpis'])) {
            fputcsv($fh, array('--- EXECUTIVE KPI SUMMARY ---'), ',', '"', '\\');
            fputcsv($fh, array('Metric', 'Value', 'Context / Description'), ',', '"', '\\');
            foreach ($summary['kpis'] as $kpi) {
                $lbl = is_array($kpi) ? ($kpi[0] ?? '') : '';
                $val = is_array($kpi) ? ($kpi[1] ?? '') : '';
                $sub = is_array($kpi) ? ($kpi[3] ?? '') : '';
                fputcsv($fh, array($lbl, $val, $sub), ',', '"', '\\');
            }
            fwrite($fh, "\r\n");
        }

        // ── 3. ANALYTICAL DISTRIBUTIONS & CHART GRAPH DATA ───────────────────
        if (!empty($summary['charts'])) {
            fputcsv($fh, array('--- ANALYTICAL DISTRIBUTIONS AND GRAPH DATA ---'), ',', '"', '\\');
            foreach ($summary['charts'] as $chart) {
                $chartTitle = $chart['title'] ?? 'Chart';
                $chartType  = strtoupper($chart['type'] ?? 'BAR');
                $headers    = !empty($chart['headers']) ? $chart['headers'] : array('Category', 'Count');

                fputcsv($fh, array("Chart: {$chartTitle} [{$chartType} Graph]"), ',', '"', '\\');
                fputcsv($fh, $headers, ',', '"', '\\');
                if (!empty($chart['data'])) {
                    foreach ($chart['data'] as $point) {
                        fputcsv($fh, array($point[0] ?? '', $point[1] ?? 0), ',', '"', '\\');
                    }
                }
                fwrite($fh, "\r\n");
            }
        }
    }

    // ── 4. ITEMIZED DATASET TABLE(S) ──────────────────────────────────────────
    $multi = count($datasets) > 1;
    foreach ($datasets as $ds) {
        if ($summary) {
            fputcsv($fh, array('--- DETAILED ' . mb_strtoupper($ds['title']) . ' RECORDS (' . count($ds['rows']) . ') ---'), ',', '"', '\\');
        } elseif ($multi) {
            fputcsv($fh, array('AgriTrace+ - ' . $ds['title']), ',', '"', '\\');
            fputcsv($fh, array('Generated ' . agx_now_text() . ' by ' . $by . ' (' . count($ds['rows']) . ' record(s))'), ',', '"', '\\');
            fwrite($fh, "\r\n");
        }

        $head = array();
        foreach ($ds['cols'] as $c) { $head[] = $c['h']; }
        fputcsv($fh, $head, ',', '"', '\\');

        foreach ($ds['rows'] as $row) {
            $line = array();
            foreach ($ds['cols'] as $c) { $line[] = agx_csv_val($c, $row); }
            fputcsv($fh, $line, ',', '"', '\\');
        }
        if ($multi || $summary) { fwrite($fh, "\r\n"); }
    }

    rewind($fh);
    $out = stream_get_contents($fh);
    fclose($fh);
    return str_replace("\n", "\r\n", str_replace("\r\n", "\n", $out));
}

/* ============================================================================
   6. PDF (pure PHP, A4 landscape)
   ========================================================================== */

function agx_pdf_widths() {
    static $w = null;
    if ($w === null) {
        $w = array_map('intval', explode(',',
            '278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,' .
            '556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,' .
            '1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,' .
            '667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,' .
            '333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,' .
            '556,556,333,500,278,556,500,722,500,500,500,334,260,334,584'));
    }
    return $w;
}

function agx_pdf_enc($s) {
    $s = (string)$s;
    $o = false;
    if (function_exists('iconv')) { $o = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s); }
    if ($o === false && function_exists('mb_convert_encoding')) { $o = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8'); }
    if ($o === false || $o === null) { $o = preg_replace('/[^\x20-\x7E]/', '?', $s); }
    return preg_replace('/[\x00-\x08\x0A-\x1F]/', ' ', $o);
}

function agx_pdf_w($enc, $size, $bold) {
    $W = agx_pdf_widths();
    $t = 0;
    $len = strlen($enc);
    for ($i = 0; $i < $len; $i++) {
        $o = ord($enc[$i]);
        $t += ($o >= 32 && $o <= 126) ? $W[$o - 32] : 556;
    }
    return $t / 1000 * $size * ($bold ? 1.06 : 1.0);
}

function agx_pdf_fit($enc, $maxW, $size, $bold) {
    if ($maxW <= 0 || agx_pdf_w($enc, $size, $bold) <= $maxW) { return $enc; }
    while (strlen($enc) > 1 && agx_pdf_w($enc . '...', $size, $bold) > $maxW) { $enc = substr($enc, 0, -1); }
    return $enc . '...';
}

class AgxPdf {
    public $W = 842.0;
    public $H = 595.0;
    private $pages = array();
    private $cur = -1;

    public function addPage() {
        $this->pages[] = '';
        $this->cur = count($this->pages) - 1;
    }

    private function rgb($hex) {
        $hex = ltrim($hex, '#');
        return sprintf('%.3F %.3F %.3F', hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255);
    }

    public function rect($x, $y, $w, $h, $fill = null, $stroke = null, $lw = 0.6) {
        $op = 'q ';
        if ($fill !== null) { $op .= $this->rgb($fill) . ' rg '; }
        if ($stroke !== null) { $op .= $this->rgb($stroke) . ' RG ' . sprintf('%.2F', $lw) . ' w '; }
        $paint = ($fill !== null && $stroke !== null) ? 'B' : ($fill !== null ? 'f' : 'S');
        $this->pages[$this->cur] .= $op . sprintf('%.2F %.2F %.2F %.2F re ', $x, $this->H - $y - $h, $w, $h) . $paint . " Q\n";
    }

    public function text($x, $y, $s, $size = 9, $bold = false, $color = '0F172A', $align = 'L', $maxW = 0) {
        $e = agx_pdf_fit(agx_pdf_enc($s), $maxW, $size, $bold);
        $w = agx_pdf_w($e, $size, $bold);
        if ($align === 'C') { $x -= $w / 2; } elseif ($align === 'R') { $x -= $w; }
        $esc = str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $e);
        $this->pages[$this->cur] .= sprintf('q %s rg BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET Q' . "\n", $this->rgb($color), $bold ? 'F2' : 'F1', $size, $x, $this->H - $y, $esc);
    }

    public function output($footerLeft) {
        $n = count($this->pages);
        for ($i = 0; $i < $n; $i++) {
            $this->cur = $i;
            $this->text(30, 584, $footerLeft, 7, false, '94A3B8');
            $this->text(812, 584, 'Page ' . ($i + 1) . ' of ' . $n, 7, false, '94A3B8', 'R');
        }
        $objs = array();
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = '';
        for ($i = 0; $i < $n; $i++) { $kids .= (5 + $i * 2) . ' 0 R '; }
        $objs[2] = '<< /Type /Pages /Kids [ ' . $kids . '] /Count ' . $n . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        for ($i = 0; $i < $n; $i++) {
            $pg = 5 + $i * 2;
            $ct = $pg + 1;
            $objs[$pg] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $ct . ' 0 R >>';
            $objs[$ct] = '<< /Length ' . strlen($this->pages[$i]) . " >>\nstream\n" . $this->pages[$i] . 'endstream';
        }
        $out = "%PDF-1.4\n";
        $offs = array();
        $total = count($objs);
        for ($k = 1; $k <= $total; $k++) {
            $offs[$k] = strlen($out);
            $out .= $k . " 0 obj\n" . $objs[$k] . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . ($total + 1) . "\n0000000000 65535 f \n";
        for ($k = 1; $k <= $total; $k++) { $out .= sprintf("%010d 00000 n \n", $offs[$k]); }
        $out .= "trailer\n<< /Size " . ($total + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $out;
    }
}

function agx_pdf_chart($pdf, $x, $y, $w, $h, $ch) {
    $pdf->rect($x, $y, $w, $h, 'FFFFFF', 'E2E8F0');
    $pdf->text($x + 12, $y + 20, $ch['title'], 10, true, agx_c('forest'), 'L', $w - 24);
    $data = array_slice($ch['data'], 0, 8);
    $max = 1; $total = 0;
    foreach ($data as $d) { if ($d[1] > $max) { $max = $d[1]; } $total += $d[1]; }
    $n = count($data);
    $rowH = min(21, ($h - 44) / max(1, $n));
    $labW = 112;
    $barX = $x + 12 + $labW;
    $barMax = $w - 24 - $labW - 62;
    $pal = agx_palette();
    $pct = ($ch['type'] === 'doughnut' || $ch['type'] === 'pie');
    foreach ($data as $i => $d) {
        $ry = $y + 34 + $i * $rowH;
        if ($ch['colors'] === 'status') { $col = agx_status_color($d[0]); }
        elseif ($ch['colors'] === 'palette') { $col = $pal[$i % count($pal)]; }
        else { $col = $ch['colors']; }
        $pdf->text($x + 12, $ry + $rowH * 0.66, (string)$d[0], 8, false, '334155', 'L', $labW - 6);
        $bw = max(2, $barMax * $d[1] / $max);
        $pdf->rect($barX, $ry + $rowH * 0.16, $bw, $rowH * 0.6, $col);
        $label = number_format((float)$d[1], 0);
        if ($pct && $total > 0) { $label .= ' (' . round($d[1] / $total * 100) . '%)'; }
        $pdf->text($barX + $bw + 5, $ry + $rowH * 0.66, $label, 8, true, '0F172A');
    }
}

function agx_pdf_head($pdf, $title, $now) {
    $pdf->rect(0, 0, 842, 46, agx_c('dark'));
    $pdf->rect(0, 46, 842, 3, agx_c('green'));
    $pdf->text(30, 31, $title, 15, true, 'FFFFFF', 'L', 560);
    $pdf->text(812, 31, 'AgriTrace+  |  ' . $now, 8, false, 'A7F3D0', 'R');
}

function agx_build_pdf($sum, $datasets, $by) {
    $pdf = new AgxPdf();
    $now = agx_now_text();
    $pdf->addPage();

    // ---- summary page
    $pdf->rect(0, 0, 842, 74, agx_c('dark'));
    $pdf->rect(0, 74, 842, 4, agx_c('green'));
    $pdf->text(30, 38, strtoupper($sum['title']), 20, true, 'FFFFFF', 'L', 540);
    $pdf->text(30, 58, $sum['sub'], 9, false, 'A7F3D0', 'L', 540);
    $pdf->text(812, 38, 'AgriTrace+', 13, true, '6EE7B7', 'R');
    $pdf->text(812, 58, 'Generated ' . $now . ' by ' . $by, 8, false, 'A7F3D0', 'R', 260);

    $kpis = array_slice($sum['kpis'], 0, 6);
    $nk = count($kpis);
    if ($nk > 0) {
        $tw = (782 - 12 * ($nk - 1)) / $nk;
        foreach ($kpis as $i => $k) {
            $x = 30 + $i * ($tw + 12);
            $pdf->rect($x, 92, $tw, 66, agx_c('soft'), agx_c('line'));
            $pdf->rect($x, 92, $tw, 3, $k[2]);
            $pdf->text($x + $tw / 2, 112, $k[0], 7, true, agx_c('muted'), 'C', $tw - 8);
            $val = is_numeric($k[1]) ? number_format((float)$k[1], is_float($k[1]) ? 1 : 0) : (string)$k[1];
            $pdf->text($x + $tw / 2, 138, $val, 20, true, $k[2], 'C', $tw - 8);
            $pdf->text($x + $tw / 2, 151, $k[3], 7, false, agx_c('muted'), 'C', $tw - 8);
        }
    }
    $charts = array();
    foreach ($sum['charts'] as $ch) { if (!empty($ch['data'])) { $charts[] = $ch; } if (count($charts) >= 4) { break; } }
    foreach ($charts as $i => $ch) {
        $x = ($i % 2 === 0) ? 30 : 421;
        $y = 172 + (int)floor($i / 2) * 200;
        agx_pdf_chart($pdf, $x, $y, 391, 190, $ch);
    }

    // ---- data pages
    foreach ($datasets as $ds) {
        $cols = array();
        foreach ($ds['cols'] as $c) { if (!isset($c['pdf']) || $c['pdf']) { $cols[] = $c; } }
        if (count($cols) === 0) { continue; }
        $totW = 0;
        foreach ($cols as $c) { $totW += $c['w']; }
        $widths = array();
        foreach ($cols as $c) { $widths[] = 782 * $c['w'] / $totW; }

        $drawHead = function ($y) use ($pdf, $cols, $widths) {
            $pdf->rect(30, $y, 782, 20, agx_c('emerald'));
            $x = 30;
            foreach ($cols as $i => $c) {
                $pdf->text($x + $widths[$i] / 2, $y + 13.5, $c['h'], 7.5, true, 'FFFFFF', 'C', $widths[$i] - 4);
                $x += $widths[$i];
            }
            return $y + 20;
        };

        $pdf->addPage();
        agx_pdf_head($pdf, $ds['title'] . '  (' . count($ds['rows']) . ' records)', $now);
        $y = $drawHead(62);
        $shown = 0;
        $max = 500;
        foreach ($ds['rows'] as $ri => $row) {
            if ($shown >= $max) { break; }
            if ($y > 548) {
                $pdf->addPage();
                agx_pdf_head($pdf, $ds['title'] . ' (continued)', $now);
                $y = $drawHead(62);
            }
            $rh = 17;
            if ($ri % 2 === 1) { $pdf->rect(30, $y, 782, $rh, agx_c('zebra')); }
            $x = 30;
            foreach ($cols as $i => $c) {
                $t = $c['t'];
                $txt = agx_disp($c, $row);
                $wd = $widths[$i];
                if ($t === 'status' && $txt !== '') {
                    $ss = agx_status_style($txt);
                    $pdf->rect($x + 3, $y + 2, $wd - 6, $rh - 4, $ss[0]);
                    $pdf->text($x + $wd / 2, $y + 11.5, $txt, 7, true, $ss[1], 'C', $wd - 8);
                } elseif ($t === 'int' || $t === 'dec') {
                    $pdf->text($x + $wd - 5, $y + 11.5, $txt, 7.5, false, '0F172A', 'R', $wd - 8);
                } elseif ($t === 'id' || $t === 'phone' || $t === 'date') {
                    $pdf->text($x + $wd / 2, $y + 11.5, $txt, 7.5, false, '0F172A', 'C', $wd - 6);
                } else {
                    $pdf->text($x + 5, $y + 11.5, $txt, 7.5, false, '0F172A', 'L', $wd - 8);
                }
                $x += $wd;
            }
            $pdf->rect(30, $y + $rh, 782, 0.01, null, 'E2E8F0', 0.3);
            $y += $rh;
            $shown++;
        }
        if (count($ds['rows']) > $max) {
            $pdf->text(30, $y + 14, 'Showing the first ' . $max . ' of ' . count($ds['rows']) . ' records. Use the Excel export for the full list.', 8, false, agx_c('muted'));
        }
    }
    return $pdf->output('AgriTrace+ | Camarines Sur 1st District | Confidential');
}

/* ============================================================================
   7. DELIVERY
   ========================================================================== */

function agx_norm_format($f) {
    $f = strtolower(trim((string)$f));
    if ($f === 'csv') { return 'csv'; }
    if ($f === 'pdf') { return 'pdf'; }
    return 'xlsx';   // xlsx | excel | xls | anything else
}

/** returns array(data, mime, extension) — throws on failure */
function agx_package($format, $summary, $datasets, $by) {
    $f = agx_norm_format($format);
    if ($f === 'csv') { return array(agx_build_csv($datasets, $by, $summary), 'text/csv; charset=utf-8', 'csv'); }
    if ($f === 'pdf') { return array(agx_build_pdf($summary, $datasets, $by), 'application/pdf', 'pdf'); }
    return array(agx_build_xlsx($summary, $datasets, $by), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx');
}

function agxSend($format, $summary, $datasets, $by, $fileBase) {
    @ini_set('display_errors', '0');
    @set_time_limit(120);
    try {
        $pkg = agx_package($format, $summary, $datasets, $by);
    } catch (Throwable $e) {
        error_log('AgriTrace export failed: ' . $e->getMessage());
        while (ob_get_level() > 0) { @ob_end_clean(); }
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Export failed: ' . $e->getMessage();
        exit;
    }
    while (ob_get_level() > 0) { @ob_end_clean(); }
    $name = $fileBase . '_' . agx_now()->format('Ymd_His') . '.' . $pkg[2];
    header('Content-Type: ' . $pkg[1]);
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($pkg[0]));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    echo $pkg[0];
    exit;
}

/**
 * One-call export used by admin.php:
 *   agxReport('farmers', $rows, $adminName);
 * Format comes from ?format=xlsx|csv|pdf (default xlsx).
 */
function agxReport($type, $rows, $by = 'Administrator') {
    $def = agx_def($type);
    if (!$def) {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Unknown report type.';
        exit;
    }
    $format = isset($_GET['format']) ? $_GET['format'] : 'xlsx';
    agxSend($format, agx_summary_for($type, $rows), array(agx_dataset($type, $rows)), $by, 'agritrace_' . $def['file']);
}


