<?php
// Path: blocks/library_export/ajax_export.php

require_once('../../config.php');
require_login();

// Give PHP unlimited time and memory since we are streaming a massive CSV
@set_time_limit(0);
@ini_set('memory_limit', '-1');

global $DB;

// =========================================================================
// 1. CAPTURE SMART FILTERS (Works perfectly with POST payloads)
// =========================================================================
$mode = optional_param('mode', 'range', PARAM_ALPHANUM);
$categoryids = optional_param_array('categoryids', [], PARAM_INT);
$courseids = optional_param_array('courseids', [], PARAM_INT);

$params = []; 
$base_where_sql = ''; 

if ($mode === 'range') {
    $raw_start = optional_param('start', 0, PARAM_INT);
    $raw_end = optional_param('end', 0, PARAM_INT);
    if (empty($raw_start) || empty($raw_end)) {
        die('Missing start or end date.');
    }
    $base_where_sql = "(l.timecreated >= :start AND l.timecreated <= :end)";
    $params['start'] = $raw_start;
    $params['end'] = $raw_end + 86399;
} else if ($mode === 'multiple') {
    $raw_dates = optional_param('dates', '', PARAM_SEQUENCE); 
    if (empty($raw_dates)) {
        die('Dates were not sent.');
    }
    $date_array = array_map('intval', explode(',', $raw_dates));
    $or_conditions = [];
    foreach ($date_array as $index => $start) {
        $or_conditions[] = "(l.timecreated >= :start{$index} AND l.timecreated <= :end{$index})";
        $params["start{$index}"] = $start;
        $params["end{$index}"] = $start + 86399;
    }
    $base_where_sql = "(" . implode(' OR ', $or_conditions) . ")";
}

$final_where_sql = $base_where_sql;
if (!empty($categoryids) || !empty($courseids)) {
    $filter_conditions = [];
    if (!empty($categoryids)) {
        list($in_sql_cat, $in_params_cat) = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
        $filter_conditions[] = "c.category $in_sql_cat";
        $params = array_merge($params, $in_params_cat);
    }
    if (!empty($courseids)) {
        list($in_sql_crs, $in_params_crs) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'crs');
        $filter_conditions[] = "l.courseid $in_sql_crs";
        $params = array_merge($params, $in_params_crs);
    }
    $final_where_sql .= " AND (" . implode(' OR ', $filter_conditions) . ")";
}

// =========================================================================
// 2. SET UP DIRECT BROWSER CSV DOWNLOAD
// =========================================================================
// Clear out any stray spaces or HTML that might break the file
if (ob_get_length()) { ob_clean(); }

$filename = "Library_Access_Logs_" . date('Ymd_Hi') . ".csv";

// Tell the browser a CSV file is coming instantly
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open a direct pipe to the browser's download manager
$output = fopen('php://output', 'w');

// Add a UTF-8 BOM marker so Microsoft Excel formats the text correctly
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Write the Column Headers (All data in a single sheet)
fputcsv($output, ['Log ID', 'Username', 'Full Name', 'Email', 'College/Category', 'Role/Cohort', 'Course Name', 'Action', 'Date', 'Time', 'IP Address']);

// =========================================================================
// 3. PRE-FETCH COHORTS IN PHP (Prevents MySQL Database Lockups)
// =========================================================================
$cohort_sql = "SELECT cm.userid, ch.name AS cohortname
               FROM {cohort_members} cm
               JOIN {cohort} ch ON cm.cohortid = ch.id";
$cohort_rs = $DB->get_recordset_sql($cohort_sql);
$user_cohorts = [];
if ($cohort_rs->valid()) {
    foreach ($cohort_rs as $rec) {
        if (!isset($user_cohorts[$rec->userid]) || $rec->cohortname > $user_cohorts[$rec->userid]) {
            $user_cohorts[$rec->userid] = $rec->cohortname;
        }
    }
}
$cohort_rs->close();

// =========================================================================
// 4. STREAM DATA ROW BY ROW
// =========================================================================
$sql = "SELECT l.id AS logid,
               u.id AS userid,
               u.username,
               CONCAT(u.firstname, ' ', u.lastname) AS fullname,
               u.email,
               cc.name AS categoryname,
               c.fullname AS coursename,
               l.timecreated,
               l.ip,
               l.eventname
        FROM {logstore_standard_log} l
        JOIN {user} u ON l.userid = u.id
        LEFT JOIN {course} c ON l.courseid = c.id
        LEFT JOIN {course_categories} cc ON cc.id = c.category
        WHERE u.deleted = 0 AND $final_where_sql
        ORDER BY l.timecreated DESC";

// get_recordset_sql acts like a conveyor belt, drastically reducing memory usage!
$rs = $DB->get_recordset_sql($sql, $params);

if ($rs->valid()) {
    foreach ($rs as $entry) {
        // Look up cohort instantly from our PHP dictionary
        $cohort = isset($user_cohorts[$entry->userid]) ? $user_cohorts[$entry->userid] : 'None';
        
        $row = [
            $entry->logid,
            $entry->username,
            $entry->fullname,
            $entry->email,
            $entry->categoryname ?: 'System Dashboard', // Differentiates the college directly in the column!
            $cohort,
            $entry->coursename ?: 'N/A',
            str_replace('\\', ' ', $entry->eventname),
            date('Y-m-d', $entry->timecreated),
            date('H:i:s', $entry->timecreated),
            $entry->ip
        ];
        
        // Push this row instantly to the downloaded file
        fputcsv($output, $row);
    }
}

// Clean up and close connection
$rs->close();
fclose($output);
exit;