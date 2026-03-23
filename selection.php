<?php
// Path: blocks/library_export/selection.php

require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG;

$url = new moodle_url('/blocks/library_export/selection.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Library Logs - Date Selection');
$PAGE->set_heading('Library Access Logs Dashboard');
require_login();

echo $OUTPUT->header();

// 1. Inject UI Libraries
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';
echo '<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/nouislider/dist/nouislider.min.css">';
echo '<script src="https://cdn.jsdelivr.net/npm/nouislider/dist/nouislider.min.js"></script>';

// 2. Custom CSS
echo '<style>
    .selection-card { max-width: 600px; margin: 0 auto; }
    #month-slider { height: 10px; width: 90%; margin: 10px auto 45px auto; }
    #month-slider .noUi-pips-horizontal { padding: 10px 0 0 0; }
    #month-slider .noUi-value-horizontal { font-size: 12px; font-weight: bold; color: #1a5a30; cursor: pointer; transform: translate(-50%, 50%); }
    #month-slider .noUi-marker-horizontal { display: none; } 
    .noUi-connect { background: #1a5a30; } 
    
    #month-checkboxes { width: 100%; margin-bottom: 20px; }
    .month-cb-label { font-size: 13px; font-weight: bold; color: #1a5a30; cursor: pointer; display: flex; flex-direction: column; align-items: center; margin-bottom: 0; }
    .month-cb-label input { margin-bottom: 5px; cursor: pointer; transform: scale(1.2); }
</style>';

// 3. UI Layout
echo '<div class="container mt-4">';
echo '<div class="card shadow selection-card">';
echo '<div class="card-body p-4">';

echo '<h4 class="text-center mb-4 text-secondary">Select Date Range</h4>';

// Selection Mode
echo '<div class="mb-3 text-center border-bottom pb-3">';
echo '<span class="font-weight-bold mr-3">Mode:</span>';
echo '<input type="radio" id="mode-range" name="cal_mode" value="range" checked> <label for="mode-range" class="mr-4" style="cursor:pointer;">Date Range</label> ';
echo '<input type="radio" id="mode-multiple" name="cal_mode" value="multiple"> <label for="mode-multiple" style="cursor:pointer;">Specific Dates</label>';
echo '</div>';

// Enable Monthly Tool Toggle
echo '<div id="month-toggle-container" class="mb-4 text-center">';
echo '<input type="checkbox" id="toggle-month-slider" style="transform: scale(1.2); margin-right: 8px; cursor: pointer;">';
echo '<label for="toggle-month-slider" class="font-weight-bold text-info" style="cursor: pointer; font-size: 1.1em;">Enable Quick Month Selection</label>';
echo '</div>';

// Year Dropdown
$current_year = date('Y');
$years_html = '';
for ($i = $current_year + 1; $i >= $current_year - 4; $i--) {
    $selected = ($i == $current_year) ? 'selected' : '';
    $years_html .= '<option value="'.$i.'" '.$selected.'>'.$i.'</option>';
}

// Swapping Panel (Slider vs Checkboxes)
echo '<div id="month-panel" class="w-100 bg-light p-3 rounded mb-4 border" style="display: none; flex-direction: column; align-items: center;">';
echo '<select id="quick-year" class="form-control form-control-sm font-weight-bold mb-4" style="width: 150px; text-align:center; font-size: 1.1em;">'.$years_html.'</select>';

// Slider
echo '<div id="month-slider" style="display: none;"></div>'; 

// Checkboxes
echo '<div id="month-checkboxes" class="justify-content-between px-3" style="display: none; width: 100%;">';
$months_short = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
foreach ($months_short as $idx => $m) {
    echo '<label class="month-cb-label">';
    echo '<input type="checkbox" class="month-cb" value="'.$idx.'">'.$m;
    echo '</label>';
}
echo '</div>';

echo '</div>'; // End Panel

// Calendar Container
echo '<div class="d-flex justify-content-center mb-4" id="calendar-wrapper">';
echo '<input type="text" id="lib-date-range" class="form-control" style="display:none;">';
echo '</div>';

// Submit Buttons
echo '<div class="row mt-2">';
echo '<div class="col-6"><button id="btn-display-list" class="btn btn-primary w-100 font-weight-bold py-2"><i class="fa fa-table mr-2"></i> Display List</button></div>';
echo '<div class="col-6"><button id="btn-export-excel" class="btn btn-success w-100 font-weight-bold py-2"><i class="fa fa-file-excel-o mr-2"></i> Export CSV</button></div>';
echo '</div>';

echo '</div></div>'; // End Card
echo '<div class="text-center mt-3"><a href="'.$CFG->wwwroot.'/my" class="btn btn-link text-muted">← Back to Dashboard</a></div>';
echo '</div>'; // End Container

// 4. SCRIPT LOGIC
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let fp;
    const inputField = document.getElementById('lib-date-range');
    const monthPanel = document.getElementById('month-panel');
    const quickYearSelect = document.getElementById('quick-year');
    const monthSlider = document.getElementById('month-slider');
    const monthCheckboxes = document.getElementById('month-checkboxes');
    const monthCbs = document.querySelectorAll('.month-cb');
    const toggleMonthSlider = document.getElementById('toggle-month-slider');
    const modeRange = document.getElementById('mode-range');
    const modeMultiple = document.getElementById('mode-multiple');

    function initCalendar(modeStr) {
        if (fp) { fp.destroy(); } 
        fp = flatpickr(inputField, {
            mode: modeStr,
            inline: true,
            dateFormat: 'Y-m-d'
        });
    }
    initCalendar('range');

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    noUiSlider.create(monthSlider, {
        start: [0, 11], 
        connect: true,
        step: 1,
        range: { 'min': 0, 'max': 11 },
        pips: { mode: 'steps', density: 12, format: { to: function (value) { return months[Math.round(value)]; } } }
    });

    function updateCalendarFromSlider() {
        if (!fp || fp.config.mode !== 'range' || !toggleMonthSlider.checked) return;
        const year = parseInt(quickYearSelect.value);
        const values = monthSlider.noUiSlider.get();
        const startMonth = Math.round(parseFloat(values[0]));
        const endMonth = Math.round(parseFloat(values[1]));
        
        const start = new Date(year, startMonth, 1);
        const end = new Date(year, endMonth + 1, 0); 
        fp.setDate([start, end]);
        fp.jumpToDate(start); 
    }
    monthSlider.noUiSlider.on('change', updateCalendarFromSlider);

    monthCbs.forEach(cb => {
        cb.addEventListener('change', function() {
            if (!fp || fp.config.mode !== 'multiple') return;
            const year = parseInt(quickYearSelect.value);
            const monthIdx = parseInt(this.value);
            const isChecked = this.checked;

            let currentDates = fp.selectedDates || [];

            if (isChecked) {
                let daysInMonth = new Date(year, monthIdx + 1, 0).getDate();
                for (let d = 1; d <= daysInMonth; d++) {
                    let newDate = new Date(year, monthIdx, d);
                    if (!currentDates.some(cd => cd.getTime() === newDate.getTime())) {
                        currentDates.push(newDate);
                    }
                }
            } else {
                currentDates = currentDates.filter(cd => !(cd.getFullYear() === year && cd.getMonth() === monthIdx));
            }
            fp.setDate(currentDates);
            fp.jumpToDate(new Date(year, monthIdx, 1));
        });
    });

    function updateVisibility() {
        if (toggleMonthSlider.checked) {
            monthPanel.style.display = 'flex';
            if (modeRange.checked) {
                monthSlider.style.display = 'block';
                monthCheckboxes.style.display = 'none';
                updateCalendarFromSlider(); 
            } else {
                monthSlider.style.display = 'none';
                monthCheckboxes.style.display = 'flex';
            }
        } else {
            monthPanel.style.display = 'none';
        }
    }

    modeRange.addEventListener('change', function() {
        if(this.checked) { initCalendar('range'); updateVisibility(); }
    });
    
    modeMultiple.addEventListener('change', function() {
        if(this.checked) { 
            initCalendar('multiple'); 
            monthCbs.forEach(cb => cb.checked = false);
            updateVisibility(); 
        }
    });

    quickYearSelect.addEventListener('change', function() {
        if (modeRange.checked) {
            updateCalendarFromSlider();
        } else {
            monthCbs.forEach(cb => cb.checked = false);
            fp.setDate([]); 
            fp.jumpToDate(new Date(parseInt(this.value), 0, 1));
        }
    });

    toggleMonthSlider.addEventListener('change', updateVisibility);

    function processAction(e, targetFile) {
        e.preventDefault();
        const selectedDates = fp.selectedDates;
        const mode = document.querySelector('input[name="cal_mode"]:checked').value;

        if (selectedDates.length === 0) {
            alert('Please select at least one date.');
            return;
        }

        if (mode === 'range' && selectedDates.length !== 2) {
            alert('For Range mode, please select a start and end date.');
            return;
        }

        const form = document.createElement('form');
        form.method = (targetFile === 'ajax_export.php') ? 'POST' : 'GET';
        form.action = '<?php echo $CFG->wwwroot; ?>/blocks/library_export/' + targetFile;
        form.style.display = 'none';

        function addHiddenInput(name, value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }

        addHiddenInput('mode', mode);

        if (mode === 'range') {
            const startTs = Math.floor(selectedDates[0].getTime() / 1000);
            const endTs = Math.floor(selectedDates[1].getTime() / 1000);
            addHiddenInput('start', startTs);
            addHiddenInput('end', endTs);
        } else {
            const timestamps = selectedDates.map(d => Math.floor(d.getTime() / 1000));
            addHiddenInput('dates', timestamps.join(','));
        }

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    document.getElementById('btn-display-list').onclick = function(e) { processAction(e, 'display.php'); };
    document.getElementById('btn-export-excel').onclick = function(e) { processAction(e, 'ajax_export.php'); };
});
</script>

<?php
echo $OUTPUT->footer();
?>