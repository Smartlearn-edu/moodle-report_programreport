<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Main controller for report_programreport.
 *
 * @package     report_programreport
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use report_programreport\program_helper;
use report_programreport\matrix_builder;
use report_programreport\exporter;
use report_programreport\event\report_viewed;

require_login();

$systemcontext = \context_system::instance();
require_capability('report/programreport:view', $systemcontext);

$PAGE->set_context($systemcontext);
$PAGE->set_url('/report/programreport/index.php');

$programkey = optional_param('program', '', PARAM_ALPHANUMEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$statusfilter = optional_param('status', 'all', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

// Remember and restore last selected program preference.
if (empty($programkey)) {
    $lastprogram = get_user_preferences('report_programreport_lastprogram', '');
    if (!empty($lastprogram)) {
        $programkey = $lastprogram;
    }
}

// Fetch all available programs.
$availableprograms = program_helper::get_all_accessible_programs();

// If still empty and only one program exists, automatically select it.
if (empty($programkey) && count($availableprograms) === 1) {
    $firstprog = reset($availableprograms);
    $programkey = $firstprog->key;
}

// Persist current program choice if valid.
if (!empty($programkey) && isset($availableprograms[$programkey])) {
    set_user_preference('report_programreport_lastprogram', $programkey);
}

// Build matrix data.
$builder = new matrix_builder($programkey, $page, $perpage, $search, $statusfilter);

// Handle export actions immediately before rendering layout.
if (!empty($action) && !empty($programkey)) {
    $event = report_viewed::create([
        'context' => $systemcontext,
        'other' => ['programkey' => $programkey, 'action' => $action],
    ]);
    $event->trigger();

    switch ($action) {
        case 'export_excel':
        case 'exportexcel':
            exporter::export_excel($builder);
            break;
        case 'export_csv':
        case 'exportcsv':
            exporter::export_csv($builder);
            break;
        case 'export_pdf':
        case 'exportpdf':
            exporter::export_pdf($builder);
            break;
    }
}

// Standard Web Page Setup.
$baseurl = new moodle_url('/report/programreport/index.php', [
    'program' => $programkey,
    'perpage' => $perpage,
    'search' => $search,
    'status' => $statusfilter,
]);

$PAGE->set_url($baseurl);
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('pluginname', 'report_programreport'));
$PAGE->set_heading(get_string('pluginname', 'report_programreport'));
$PAGE->set_pagelayout('report');

// Trigger view event.
$event = report_viewed::create([
    'context' => $systemcontext,
    'other' => ['programkey' => $programkey],
]);
$event->trigger();

// Prepare template data.
$programsoptions = [];
foreach ($availableprograms as $pkey => $pobj) {
    $programsoptions[] = [
        'key' => $pkey,
        'fullname' => $pobj->fullname,
        'is_selected' => ($pkey === $programkey),
    ];
}

$programinfo = $builder->get_program_info();
$kpis = $builder->get_kpi_metrics();
$courses = $builder->get_courses();
$pagedstudents = $builder->get_paged_student_rows();
$coursestats = $builder->get_course_stats();
$pagination = $builder->get_pagination($baseurl);

// URLs for export buttons.
$exportexcelurl = new moodle_url($baseurl, ['action' => 'export_excel']);
$exportcsvurl = new moodle_url($baseurl, ['action' => 'export_csv']);
$exportpdfurl = new moodle_url($baseurl, ['action' => 'export_pdf']);
$reseturl = new moodle_url('/report/programreport/index.php', ['program' => $programkey]);

$templatedata = [
    'pluginname' => get_string('pluginname', 'report_programreport'),
    'has_programs' => !empty($availableprograms),
    'programs_options' => $programsoptions,
    'has_selected_program' => !empty($programkey) && ($programinfo !== null),
    'selected_program_key' => $programkey,
    'selected_program_name' => $programinfo ? $programinfo->fullname : '',
    'search_query' => $search,
    'status_filter' => $statusfilter,
    'status_all_selected' => ($statusfilter === 'all'),
    'status_completed_selected' => ($statusfilter === 'completed'),
    'status_inprogress_selected' => ($statusfilter === 'inprogress'),
    'status_notstarted_selected' => ($statusfilter === 'notstarted'),
    'perpage_25_selected' => ($perpage === 25),
    'perpage_50_selected' => ($perpage === 50),
    'perpage_100_selected' => ($perpage === 100),
    'perpage_all_selected' => ($perpage === 0),
    'reset_url' => $reseturl->out(false),
    'export_excel_url' => $exportexcelurl->out(false),
    'export_csv_url' => $exportcsvurl->out(false),
    'export_pdf_url' => $exportpdfurl->out(false),
    'kpis' => $kpis,
    'courses' => $courses,
    'has_courses' => !empty($courses),
    'students' => $pagedstudents,
    'has_students' => !empty($pagedstudents),
    'course_stats' => $coursestats,
    'pagination' => $pagination,
    'logo_html' => exporter::get_report_logo_html(180, 50),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('report_programreport/index', $templatedata);
echo $OUTPUT->footer();
