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
 * English language strings for report_programreport.
 *
 * @package     report_programreport
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Program Report';
$string['programreport:view'] = 'View Program Report';
$string['programreport:viewall'] = 'View all programs in Program Report';
$string['privacy:metadata'] = 'The Program Report plugin does not store any personal data. It generates analytical matrix reports dynamically from existing core course and grade records.';

// General & Navigation.
$string['selectprogram'] = 'Select Program';
$string['selectprogram_help'] = 'Choose an academic program from Open LMS Programs or MuTMS Programs to display the complete student-by-course matrix report.';
$string['noprogramsfound'] = 'No active academic programs found. Please ensure that Open LMS Programs (enrol_programs) or MuTMS Programs (tool_muprog) are installed and configured with programs.';
$string['noprogramselected'] = 'Please select an academic program from the dropdown above to view the matrix report.';
$string['nostudentsinprogram'] = 'No students are currently allocated or enrolled in this program.';
$string['nocoursesinprogram'] = 'No courses have been configured in this program\'s curriculum sequence.';
$string['eventreportviewed'] = 'Program report viewed';

// KPI Metrics.
$string['kpi_totalstudents'] = 'Total Students';
$string['kpi_totalcourses'] = 'Curriculum Courses';
$string['kpi_completionrate'] = 'Program Completion Rate';
$string['kpi_cohortaverage'] = 'Cohort Average';

// Filters & Controls.
$string['search_placeholder'] = 'Search by student name, ID or email...';
$string['filter_all'] = 'All Students';
$string['filter_completed'] = 'Completed Program';
$string['filter_inprogress'] = 'In Progress';
$string['filter_notstarted'] = 'Not Started';
$string['filter_status'] = 'Status';
$string['perpage'] = 'Per page';
$string['all'] = 'All';
$string['filter_button'] = 'Apply Filters';
$string['reset_filters'] = 'Reset';

// Table Columns & Data.
$string['student'] = 'Student';
$string['idnumber'] = 'ID Number';
$string['email'] = 'Email';
$string['completedcourses'] = 'Completed';
$string['progavg'] = 'Program Avg';
$string['progstatus'] = 'Program Status';
$string['courseaverages'] = 'Course Averages';
$string['passrate'] = 'Pass Rate';
$string['completedcount'] = 'Completed Count';

// Status labels & badges.
$string['status_completed'] = 'Completed';
$string['status_inprogress'] = 'In Progress';
$string['status_in_progress'] = 'In Progress';
$string['status_notstarted'] = 'Not Started';
$string['status_not_started'] = 'Not Started';
$string['status_passed'] = 'Passed';
$string['status_failed'] = 'Failed';
$string['status_notenrolled'] = 'Not Enrolled';
$string['status_not_enrolled'] = 'Not Enrolled';

// Exports.
$string['export'] = 'Export';
$string['export_excel'] = 'Export to Excel (.xlsx)';
$string['export_csv'] = 'Export to CSV';
$string['export_pdf'] = 'Export to PDF (Landscape)';

// Tooltips & Links.
$string['viewstudentreport'] = 'View student grade report';
$string['viewcourse'] = 'View course';
$string['gradeitemdetails'] = 'Score: {$a->rawgrade} / {$a->rawgrademax} (Pass: {$a->gradepass})';
$string['completedon'] = 'Completed on {$a}';
$string['sticky_columns_hint'] = 'Scroll horizontally to view all courses. The student column remains pinned.';

// Admin Settings.
$string['brandingsettings'] = 'Branding & Logo Settings';
$string['brandingsettingsdesc'] = 'Upload your organization logo and choose theme colors for report headers, cards, and export files.';
$string['reportlogo'] = 'Report Header Logo';
$string['reportlogodesc'] = 'Upload an image logo (PNG, JPG, SVG) to appear in the web header, Excel banner, and PDF exports.';
$string['colorsettings'] = 'Color Palette Settings';
$string['colorsettingsdesc'] = 'Customize brand and performance indicator colors.';
$string['headerprimarycolor'] = 'Header Primary Color';
$string['headerprimarycolordesc'] = 'Primary brand color for report headers, badges, and export titles.';
$string['headersecondarycolor'] = 'Header Secondary Color';
$string['headersecondarycolordesc'] = 'Secondary gradient accent color.';
$string['gradeexcellentcolor'] = 'Excellent Grade Color (≥ 85%)';
$string['gradeexcellentcolordesc'] = 'Badge color for distinction grade achievements.';
$string['gradegoodcolor'] = 'Good Grade Color (≥ 70%)';
$string['gradegoodcolordesc'] = 'Badge color for above-average grade achievements.';
$string['gradepasscolor'] = 'Pass Grade Color (≥ 50%)';
$string['gradepasscolordesc'] = 'Badge color for passing grade achievements.';
$string['gradefailcolor'] = 'Fail Grade Color (< 50%)';
$string['gradefailcolordesc'] = 'Badge color for failing grade achievements.';
