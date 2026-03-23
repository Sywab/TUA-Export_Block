<?php
// Path: blocks/library_export/display.php

require_once('../../config.php');

global $DB, $PAGE, $OUTPUT, $USER, $CFG;

$url = new moodle_url('/blocks/library_export/display.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Display Log List');
$PAGE->set_heading('Library Access Logs');
require_login();

$mode = optional_param('mode', 'range', PARAM_ALPHANUM);
$categoryids = optional_param_array('categoryids', [], PARAM_INT);
$courseids = optional_param_array('courseids', [], PARAM_INT);

$params = []; 
$base_where_sql = ''; 

// 1. Process Dates (Only needed for the Course Checkbox UI)
$raw_start = 0;
$raw_end = 0;
$raw_dates = '';

if ($mode === 'range') {
    $raw_start = optional_param('start', 0, PARAM_INT);
    $raw_end = optional_param('end', 0, PARAM_INT);
    if (empty($raw_start) || empty($raw_end)) {
        echo $OUTPUT->header();
        echo html_writer::tag('div', 'Error: Missing start or end date.', ['class' => 'alert alert-danger']);
        echo $OUTPUT->footer();
        die();
    }
    $base_where_sql = "(l.timecreated >= :start AND l.timecreated <= :end)";
    $params['start'] = $raw_start;
    $params['end'] = $raw_end + 86399;
} else if ($mode === 'multiple') {
    $raw_dates = optional_param('dates', '', PARAM_SEQUENCE); 
    if (empty($raw_dates)) {
        echo $OUTPUT->header();
        echo html_writer::tag('div', 'Error: Dates were not sent.', ['class' => 'alert alert-danger']);
        echo $OUTPUT->footer();
        die();
    }
    $date_array = explode(',', $raw_dates);
    $or_conditions = [];
    foreach ($date_array as $index => $ts) {
        $start = (int)$ts;
        $or_conditions[] = "(l.timecreated >= :start{$index} AND l.timecreated <= :end{$index})";
        $params["start{$index}"] = $start;
        $params["end{$index}"] = $start + 86399;
    }
    $base_where_sql = "(" . implode(' OR ', $or_conditions) . ")";
} else {
    die('Invalid mode');
}

// 2. Fetch active courses WITH their Categories
$course_list_sql = "SELECT DISTINCT c.id, c.fullname, COALESCE(cc.id, 0) AS catid, COALESCE(cc.name, 'System & Dashboard') AS catname
                    FROM {logstore_standard_log} l
                    JOIN {course} c ON l.courseid = c.id
                    LEFT JOIN {course_categories} cc ON cc.id = c.category
                    JOIN {user} u ON l.userid = u.id
                    WHERE l.courseid <> 0 AND u.deleted = 0 AND $base_where_sql
                    ORDER BY catname ASC, c.fullname ASC";
$available_courses = $DB->get_records_sql($course_list_sql, $params);

$grouped_data = [];
if ($available_courses) {
    foreach ($available_courses as $c) {
        $catid = $c->catid;
        if (!isset($grouped_data[$catid])) {
            $grouped_data[$catid] = ['name' => $c->catname, 'courses' => []];
        }
        $grouped_data[$catid]['courses'][] = $c;
    }
}

// --- OUTPUT TO SCREEN ---
echo $OUTPUT->header();
echo '<div class="container mt-4">';

// --- CATEGORY & COURSE FILTER PANEL ---
echo '<div class="card shadow-sm mb-4">';
echo '<div class="card-body bg-light">';
echo '<form method="get" action="display.php" id="filterForm">';
echo '<input type="hidden" name="mode" value="' . s($mode) . '">';
if ($mode === 'range') {
    echo '<input type="hidden" name="start" value="' . $raw_start . '">';
    echo '<input type="hidden" name="end" value="' . $raw_end . '">';
} else {
    echo '<input type="hidden" name="dates" value="' . s($raw_dates) . '">';
}

echo '<div class="row">';
echo '<div class="col-md-8">';
echo '<label class="font-weight-bold">Select Categories (Leave empty to show all):</label>';

echo '<div style="max-height: 150px; overflow-y: auto; border: 1px solid #ced4da; padding: 10px; border-radius: 5px; background: #fff;">';
if (!empty($grouped_data)) {
    foreach ($grouped_data as $catid => $group) {
        $has_courses = count($group['courses']) > 0;
        $cat_explicitly_checked = in_array($catid, $categoryids);
        $all_courses_checked = true;
        foreach ($group['courses'] as $c) {
            if (!in_array($c->id, $courseids)) { $all_courses_checked = false; break; }
        }
        $cat_checked = ($cat_explicitly_checked || ($all_courses_checked && $has_courses && !empty($courseids))) ? 'checked' : '';

        echo '<div class="form-check">';
        echo '<input class="form-check-input cat-cb" type="checkbox" name="categoryids[]" value="'.$catid.'" data-catid="'.$catid.'" id="cat_'.$catid.'" '.$cat_checked.'>';
        echo '<label class="form-check-label font-weight-bold" style="cursor:pointer;" for="cat_'.$catid.'">'.$group['name'].'</label>';
        echo '</div>';
    }
} else {
    echo '<div class="text-muted small">No data found for the selected dates.</div>';
}
echo '</div>'; 

echo '<div class="mt-2">';
echo '<button type="button" class="btn btn-sm btn-outline-info font-weight-bold" id="btnAdvancedToggle">Advanced: Show Specific Courses <i class="fa fa-caret-down ml-1"></i></button>';
echo '</div>';

echo '<div id="advancedBox" style="display:none; margin-top: 10px; max-height: 250px; overflow-y: auto; border: 1px solid #ced4da; padding: 15px; border-radius: 5px; background: #e9ecef;">';
if (!empty($grouped_data)) {
    foreach ($grouped_data as $catid => $group) {
        $cat_explicitly_checked = in_array($catid, $categoryids);
        echo '<div class="mb-3">';
        echo '<div class="text-muted small font-weight-bold text-uppercase border-bottom border-secondary mb-2 pb-1">'.$group['name'].'</div>';
        foreach ($group['courses'] as $c) {
            $checked = (in_array($c->id, $courseids) || $cat_explicitly_checked) ? 'checked' : '';
            echo '<div class="form-check ml-3">';
            echo '<input class="form-check-input course-cb cat-child-'.$catid.'" type="checkbox" name="courseids[]" value="'.$c->id.'" id="course_'.$c->id.'" '.$checked.'>';
            echo '<label class="form-check-label" style="cursor:pointer;" for="course_'.$c->id.'">'.$c->fullname.'</label>';
            echo '</div>';
        }
        echo '</div>';
    }
}
echo '</div>'; 
echo '</div>'; // End col-md-8

// Submit Buttons
echo '<div class="col-md-4 d-flex flex-column justify-content-center">';
echo '<button type="submit" id="btnApply" class="btn btn-primary mb-2 w-100 font-weight-bold" disabled>Apply Filter</button>';

// --- FIX: Changed type to "button" so JS can securely construct the export URL ---
echo '<button type="submit" id="btnExport" formmethod="post" formaction="ajax_export.php" class="btn btn-success w-100 font-weight-bold" disabled><i class="fa fa-file-excel-o"></i> Export to CSV</button>';
echo '</div>';
echo '</div></form></div></div>';

// --- LOADING BAR COMPONENT ---
echo '<div id="loading-container" class="text-center my-5">';
echo '<h5 class="text-muted mb-3"><i class="fa fa-spinner fa-spin mr-2"></i> Crunching the numbers... Please wait.</h5>';
echo '<div class="progress" style="height: 25px; border-radius: 10px;">';
echo '<div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>';
echo '</div>';
echo '</div>';

// --- DATA CONTAINERS ---
echo '<div id="data-container" style="display: none;">';

// The wrapper for the Chart.js
echo '<div id="chart-wrapper"></div>';

echo '<h3 class="mb-3">Summary</h3>';
echo '<table class="table table-bordered table-striped shadow-sm">';
echo '<thead class="thead-light"><tr><th>Category Name</th><th>Total Activity Count</th></tr></thead>';
echo '<tbody id="summary-tbody"></tbody></table>';
echo '<hr class="my-5">';

echo '<h3 class="mb-3">Detailed Logs <span id="total-records-badge" class="badge badge-info"></span></h3>';
echo '<div id="pagination-top"></div>';
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover table-sm shadow-sm">';
echo '<thead class="thead-light"><tr><th>Log ID</th><th>Username</th><th>Full Name</th><th>Role/Cohort</th><th>Course Name</th><th>Date</th><th>Time</th><th>IP Address</th></tr></thead>';
echo '<tbody id="logs-tbody"></tbody></table></div>';
echo '<div id="pagination-bottom"></div>';
echo '</div>'; 

echo '<div class="mt-4"><a href="' . $CFG->wwwroot . '/my" class="btn btn-secondary">← Back to Dashboard</a></div>';
echo '</div>'; 
echo $OUTPUT->footer();

?>

<script>
    var moodleDefine = window.define;
    window.define = undefined;
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js/dist/chart.umd.js"></script>

<script>
    window.define = moodleDefine;
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loadingContainer = document.getElementById('loading-container');
    const dataContainer = document.getElementById('data-container');
    const btnApply = document.getElementById('btnApply');
    const btnExport = document.getElementById('btnExport');

    // --- NEW: BULLETPROOF EXPORT LOGIC ---
    // This guarantees the export button sends the EXACT checkboxes you selected
    btnExport.addEventListener('click', function() {
        let exportUrl = 'ajax_export.php?mode=' + encodeURIComponent(document.querySelector('input[name="mode"]').value);
        
        const startInput = document.querySelector('input[name="start"]');
        if (startInput) {
            exportUrl += '&start=' + startInput.value + '&end=' + document.querySelector('input[name="end"]').value;
        } else {
            exportUrl += '&dates=' + encodeURIComponent(document.querySelector('input[name="dates"]').value);
        }
        
        // Grab every checked category
        document.querySelectorAll('.cat-cb:checked').forEach(cb => {
            exportUrl += '&categoryids[]=' + cb.value;
        });
        
        // Grab every checked course
        document.querySelectorAll('.course-cb:checked').forEach(cb => {
            exportUrl += '&courseids[]=' + cb.value;
        });
        
        // Fire the download
        window.location.href = exportUrl;
    });
    // ------------------------------------

    // Toggle Advanced Button
    const btnAdv = document.getElementById('btnAdvancedToggle');
    const advBox = document.getElementById('advancedBox');
    if(btnAdv && advBox) {
        btnAdv.addEventListener('click', function() {
            if(advBox.style.display === 'none') {
                advBox.style.display = 'block';
                btnAdv.innerHTML = 'Hide Specific Courses <i class=\"fa fa-caret-up ml-1\"></i>';
            } else {
                advBox.style.display = 'none';
                btnAdv.innerHTML = 'Advanced: Show Specific Courses <i class=\"fa fa-caret-down ml-1\"></i>';
            }
        });
    }

    // Category & Course Checkbox sync logic
    const catCbs = document.querySelectorAll('.cat-cb');
    const courseCbs = document.querySelectorAll('.course-cb');
    
    catCbs.forEach(function(catCb) {
        catCb.addEventListener('change', function() {
            const catId = this.getAttribute('data-catid');
            document.querySelectorAll('.cat-child-' + catId).forEach(cb => cb.checked = catCb.checked);
        });
    });

    courseCbs.forEach(function(courseCb) {
        courseCb.addEventListener('change', function() {
            let catClass = Array.from(this.classList).find(c => c.startsWith('cat-child-'));
            if(catClass) {
                let catId = catClass.replace('cat-child-', '');
                let allChecked = true;
                document.querySelectorAll('.' + catClass).forEach(sib => { if(!sib.checked) allChecked = false; });
                let parentCatCb = document.querySelector('.cat-cb[data-catid=\"' + catId + '\"]');
                if(parentCatCb) parentCatCb.checked = allChecked;
            }
        });
    });

    // SMART PAYLOAD logic for the "Apply Filter" button
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function() {
            courseCbs.forEach(cb => cb.disabled = false);
            catCbs.forEach(cb => cb.disabled = false);
            let allCatsChecked = true;
            catCbs.forEach(function(catCb) {
                if(catCb.checked) {
                    const catId = catCb.getAttribute('data-catid');
                    document.querySelectorAll('.cat-child-' + catId).forEach(cb => cb.disabled = true);
                } else {
                    allCatsChecked = false;
                }
            });
            if (allCatsChecked && catCbs.length > 0) {
                catCbs.forEach(cb => cb.disabled = true);
                courseCbs.forEach(cb => cb.disabled = true);
            }
        });
    }

    // Fetch the data from ajax_data_engine.php
    function fetchLogData() {
        btnApply.disabled = true;
        btnExport.disabled = true;
        loadingContainer.style.display = 'block';
        dataContainer.style.display = 'none';

        const currentParams = window.location.search;

        fetch('ajax_data_engine.php' + currentParams)
            .then(response => {
                if (!response.ok) throw new Error("Network error");
                return response.json();
            })
            .then(data => {
                if(data.error) throw new Error(data.error);
                
                loadingContainer.style.display = 'none';
                dataContainer.style.display = 'block';
                
                btnApply.disabled = false;
                btnExport.disabled = false;

                renderSummaryTable(data.summary);
                renderLogsTable(data.logs, data.totalcount, data.pagination);
                renderChart(data.chart);
            })
            .catch(error => {
                console.error("Fetch error:", error);
                loadingContainer.style.display = 'block'; 
                dataContainer.style.display = 'none';
                loadingContainer.innerHTML = '<div class="alert alert-danger mt-4"><i class="fa fa-exclamation-triangle mr-2"></i> An error occurred: ' + error.message + '</div>';
                btnApply.disabled = false; 
                btnExport.disabled = false;
            });
    }

    function renderSummaryTable(summaryData) {
        const tbody = document.getElementById('summary-tbody');
        if (!summaryData || summaryData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center">No data found.</td></tr>';
            return;
        }
        let rowsHtml = '';
        summaryData.forEach(row => {
            rowsHtml += `<tr><td>${row.name}</td><td>${row.count}</td></tr>`;
        });
        tbody.innerHTML = rowsHtml;
    }

    function renderLogsTable(logsData, totalCount, paginationHtml) {
        document.getElementById('total-records-badge').innerText = totalCount + ' total records';
        document.getElementById('pagination-top').innerHTML = paginationHtml || '';
        document.getElementById('pagination-bottom').innerHTML = paginationHtml || '';

        const tbody = document.getElementById('logs-tbody');
        if (!logsData || logsData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center">No detailed logs found.</td></tr>';
            return;
        }
        
        let rowsHtml = '';
        logsData.forEach(log => {
            rowsHtml += `<tr>
                <td>${log.logid}</td>
                <td>${log.username}</td>
                <td>${log.fullname}</td>
                <td>${log.cohort}</td>
                <td>${log.course}</td>
                <td>${log.date}</td>
                <td>${log.time}</td>
                <td>${log.ip}</td>
            </tr>`;
        });
        tbody.innerHTML = rowsHtml;
    }

    let activityChartInstance = null;
    function renderChart(chartData) {
        const wrapper = document.getElementById('chart-wrapper');
        
        if (activityChartInstance) {
            activityChartInstance.destroy();
            activityChartInstance = null;
        }

        if (!chartData || !chartData.labels || chartData.labels.length === 0) {
            wrapper.innerHTML = '<div class="alert alert-secondary text-center mt-4">No timeline data available for the selected filters.</div>';
            return;
        }

        wrapper.innerHTML = `
            <h3 class="mb-3">Activity Timeline</h3>
            <div class="card shadow-sm mb-5">
                <div class="card-body" style="height: 400px; position: relative;">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>`;

        const ctx = document.getElementById('activityChart').getContext('2d');
        activityChartInstance = new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: false, title: { display: true, text: 'Month' } },
                    y: { stacked: false, beginAtZero: true, title: { display: true, text: 'Total Activity Count' } }
                },
                plugins: {
                    tooltip: { mode: 'index', intersect: false },
                    legend: { position: 'top' }
                }
            }
        });
    }

    // Trigger the fetch immediately when the page loads
    fetchLogData();
});
</script>