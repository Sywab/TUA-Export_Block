<?php
class block_library_export extends block_base {
    
    public function init() {
        $this->title = get_string('pluginname', 'block_library_export');
    }

    public function get_content() {
        if ($this->content !== null) return $this->content;

        global $CFG;
        $this->content = new stdClass();

        $url = $CFG->wwwroot . '/blocks/library_export/selection.php';

        $html = '<div class="p-3 text-center">';
        $html .= '<p class="text-muted small mb-3">Filter, view, and export library access logs by date, category, and cohort.</p>';
        $html .= '<a href="' . $url . '" class="btn btn-primary w-100 font-weight-bold"><i class="fa fa-calendar mr-2"></i>Open Dashboard</a>';
        $html .= '</div>';

        $this->content->text = $html;

        return $this->content;
    }
}