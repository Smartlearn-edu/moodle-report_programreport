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
 * Administration settings for report_programreport.
 *
 * @package     report_programreport
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // 1. Branding & Header Logo Settings.
    $settings->add(new admin_setting_heading(
        'report_programreport/brandingheading',
        get_string('brandingsettings', 'report_programreport'),
        get_string('brandingsettingsdesc', 'report_programreport')
    ));

    $settings->add(new admin_setting_configstoredfile(
        'report_programreport/report_logo',
        get_string('reportlogo', 'report_programreport'),
        get_string('reportlogodesc', 'report_programreport'),
        'report_logo',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['.jpg', '.jpeg', '.png', '.gif', '.svg']]
    ));

    // 2. Color Palette Settings.
    $settings->add(new admin_setting_heading(
        'report_programreport/colorheading',
        get_string('colorsettings', 'report_programreport'),
        get_string('colorsettingsdesc', 'report_programreport')
    ));

    $settings->add(new admin_setting_configcolourpicker(
        'report_programreport/header_primary_color',
        get_string('headerprimarycolor', 'report_programreport'),
        get_string('headerprimarycolordesc', 'report_programreport'),
        '#6f42c1'
    ));

    $settings->add(new admin_setting_configcolourpicker(
        'report_programreport/header_secondary_color',
        get_string('headersecondarycolor', 'report_programreport'),
        get_string('headersecondarycolordesc', 'report_programreport'),
        '#8e44ad'
    ));

    // Grade Performance Colors.
    $settings->add(new admin_setting_configcolourpicker(
        'report_programreport/grade_excellent_color',
        get_string('gradeexcellentcolor', 'report_programreport'),
        get_string('gradeexcellentcolordesc', 'report_programreport'),
        '#28a745'
    ));

    $settings->add(new admin_setting_configcolourpicker(
        'report_programreport/grade_good_color',
        get_string('gradegoodcolor', 'report_programreport'),
        get_string('gradegoodcolordesc', 'report_programreport'),
        '#17a2b8'
    ));

    $settings->add(new admin_setting_configcolourpicker(
        'report_programreport/grade_pass_color',
        get_string('gradepasscolor', 'report_programreport'),
        get_string('gradepasscolordesc', 'report_programreport'),
        '#ffc107'
    ));

    $settings->add(new admin_setting_configcolourpicker(
        'report_programreport/grade_fail_color',
        get_string('gradefailcolor', 'report_programreport'),
        get_string('gradefailcolordesc', 'report_programreport'),
        '#dc3545'
    ));
}

// Register external report page under Site administration -> Reports.
$ADMIN->add('reports', new admin_externalpage(
    'report_programreport',
    get_string('pluginname', 'report_programreport'),
    new moodle_url('/report/programreport/index.php'),
    'report/programreport:view'
));
