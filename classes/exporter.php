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
 * Exporter engine for report_programreport.
 *
 * Supports Excel (.xlsx), CSV, and Landscape PDF exports.
 *
 * @package     report_programreport
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_programreport;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use core_text;

/**
 * Exporter class for matrix report data.
 */
class exporter {

    /**
     * Export matrix report to Excel (.xlsx) file.
     *
     * @param matrix_builder $builder
     * @return void
     */
    public static function export_excel(matrix_builder $builder): void {
        global $CFG, $SITE;

        require_once($CFG->libdir . '/excellib.class.php');

        $proginfo = $builder->get_program_info();
        $progname = $proginfo ? $proginfo->fullname : get_string('pluginname', 'report_programreport');
        $safeprogname = clean_filename(str_replace(' ', '_', $progname)) ?: 'program';
        $filename = 'program_report_' . $safeprogname . '_' . date('Ymd_His') . '.xlsx';

        $workbook = new \MoodleExcelWorkbook('-');
        $workbook->send($filename);

        $sheetname = get_string('pluginname', 'report_programreport');
        $sheetname = strtr(trim($sheetname, "'"), '[]*/\?:', '       ');
        $sheetname = core_text::substr($sheetname, 0, 31) ?: 'Program Report';
        $worksheet = $workbook->add_worksheet($sheetname);

        $colors = matrix_builder::get_color_settings();
        $primary = $colors['header_primary'] ?? '#6f42c1';

        // Excel cell formatting styles.
        $fmtTitle = $workbook->add_format([
            'bold' => 1,
            'size' => 14,
            'color' => $primary,
        ]);
        $fmtSub = $workbook->add_format([
            'size' => 9,
            'color' => '#64748b',
            'italic' => 1,
        ]);
        $fmtHeader = $workbook->add_format([
            'bold' => 1,
            'size' => 10,
            'color' => '#ffffff',
            'bg_color' => $primary,
            'border' => 1,
            'v_align' => 'center',
            'h_align' => 'center',
            'text_wrap' => 1,
        ]);
        $fmtHeaderLeft = $workbook->add_format([
            'bold' => 1,
            'size' => 10,
            'color' => '#ffffff',
            'bg_color' => $primary,
            'border' => 1,
            'v_align' => 'center',
            'h_align' => 'left',
        ]);
        $fmtCellLeft = $workbook->add_format([
            'border' => 1,
            'v_align' => 'center',
            'h_align' => 'left',
        ]);
        $fmtCellCenter = $workbook->add_format([
            'border' => 1,
            'v_align' => 'center',
            'h_align' => 'center',
        ]);
        $fmtCellBoldCenter = $workbook->add_format([
            'bold' => 1,
            'border' => 1,
            'v_align' => 'center',
            'h_align' => 'center',
        ]);
        $fmtSummaryLabel = $workbook->add_format([
            'bold' => 1,
            'bg_color' => '#e2e8f0',
            'border' => 1,
            'v_align' => 'center',
            'h_align' => 'left',
        ]);
        $fmtSummaryCenter = $workbook->add_format([
            'bold' => 1,
            'bg_color' => '#e2e8f0',
            'border' => 1,
            'v_align' => 'center',
            'h_align' => 'center',
        ]);

        $kpis = $builder->get_kpi_metrics();
        $courses = $builder->get_courses();
        $rows = $builder->get_filtered_student_rows();
        $stats = $builder->get_course_stats();

        // 1. Report Title & Metadata Banner.
        $worksheet->write_string(0, 0, $SITE->fullname . ' - ' . get_string('pluginname', 'report_programreport') . ': ' . $progname, $fmtTitle);
        $metatxt = 'Generated: ' . userdate(time(), get_string('strftimedatetime', 'langconfig')) .
                   ' | Students: ' . ($kpis['total_students'] ?? 0) .
                   ' | Courses: ' . ($kpis['total_courses'] ?? 0) .
                   ' | Completion Rate: ' . ($kpis['completion_rate_formatted'] ?? '0%') .
                   ' | Cohort Average: ' . ($kpis['cohort_average_formatted'] ?? '-');
        $worksheet->write_string(1, 0, $metatxt, $fmtSub);

        // 2. Table Column Headers.
        $rowidx = 3;
        $colidx = 0;

        $worksheet->write_string($rowidx, $colidx++, get_string('student', 'report_programreport'), $fmtHeaderLeft);
        $worksheet->write_string($rowidx, $colidx++, get_string('idnumber', 'report_programreport'), $fmtHeader);
        $worksheet->write_string($rowidx, $colidx++, get_string('email'), $fmtHeaderLeft);

        foreach ($courses as $c) {
            $worksheet->write_string($rowidx, $colidx++, $c->fullname, $fmtHeader);
        }

        $worksheet->write_string($rowidx, $colidx++, get_string('completedcourses', 'report_programreport'), $fmtHeader);
        $worksheet->write_string($rowidx, $colidx++, get_string('progavg', 'report_programreport'), $fmtHeader);
        $worksheet->write_string($rowidx, $colidx++, get_string('progstatus', 'report_programreport'), $fmtHeader);

        // 3. Student Data Rows.
        $rowidx++;
        foreach ($rows as $r) {
            $colidx = 0;
            $worksheet->write_string($rowidx, $colidx++, $r->fullname, $fmtCellLeft);
            $worksheet->write_string($rowidx, $colidx++, $r->idnumber ?: '-', $fmtCellCenter);
            $worksheet->write_string($rowidx, $colidx++, $r->email ?: '-', $fmtCellLeft);

            foreach ($r->courses as $c) {
                $cellval = $c->percentage_formatted;
                $worksheet->write_string($rowidx, $colidx++, $cellval, $fmtCellCenter);
            }

            $worksheet->write_string($rowidx, $colidx++, $r->completed_ratio, $fmtCellCenter);
            $worksheet->write_string($rowidx, $colidx++, $r->prog_avg_formatted, $fmtCellBoldCenter);
            $worksheet->write_string($rowidx, $colidx++, $r->prog_status_label, $fmtCellCenter);
            $rowidx++;
        }

        // 4. Bottom Summary Rows.
        // Row A: Course Averages.
        $colidx = 0;
        $worksheet->write_string($rowidx, $colidx++, get_string('courseaverages', 'report_programreport'), $fmtSummaryLabel);
        $worksheet->write_string($rowidx, $colidx++, '', $fmtSummaryCenter);
        $worksheet->write_string($rowidx, $colidx++, '', $fmtSummaryCenter);
        foreach ($stats as $st) {
            $worksheet->write_string($rowidx, $colidx++, $st->average_formatted, $fmtSummaryCenter);
        }
        $worksheet->write_string($rowidx, $colidx++, '-', $fmtSummaryCenter);
        $worksheet->write_string($rowidx, $colidx++, $kpis['cohort_average_formatted'] ?? '-', $fmtSummaryCenter);
        $worksheet->write_string($rowidx, $colidx++, '-', $fmtSummaryCenter);
        $rowidx++;

        // Row B: Pass Rates.
        $colidx = 0;
        $worksheet->write_string($rowidx, $colidx++, get_string('passrate', 'report_programreport'), $fmtSummaryLabel);
        $worksheet->write_string($rowidx, $colidx++, '', $fmtSummaryCenter);
        $worksheet->write_string($rowidx, $colidx++, '', $fmtSummaryCenter);
        foreach ($stats as $st) {
            $worksheet->write_string($rowidx, $colidx++, $st->pass_rate_formatted, $fmtSummaryCenter);
        }
        $worksheet->write_string($rowidx, $colidx++, '-', $fmtSummaryCenter);
        $worksheet->write_string($rowidx, $colidx++, '-', $fmtSummaryCenter);
        $worksheet->write_string($rowidx, $colidx++, '-', $fmtSummaryCenter);
        $rowidx++;

        // Row C: Completed Count.
        $colidx = 0;
        $worksheet->write_string($rowidx, $colidx++, get_string('completedcount', 'report_programreport'), $fmtSummaryLabel);
        $worksheet->write_string($rowidx, $colidx++, '', $fmtSummaryCenter);
        $worksheet->write_string($rowidx, $colidx++, '', $fmtSummaryCenter);
        foreach ($stats as $st) {
            $worksheet->write_string($rowidx, $colidx++, (string)$st->completed_count, $fmtSummaryCenter);
        }
        $worksheet->write_string($rowidx, $colidx++, '-', $fmtSummaryCenter);
        $worksheet->write_string($rowidx, $colidx++, '-', $fmtSummaryCenter);
        $worksheet->write_string($rowidx, $colidx++, '-', $fmtSummaryCenter);

        while (ob_get_level()) {
            ob_end_clean();
        }

        $workbook->close();
        exit;
    }

    /**
     * Export matrix report to CSV stream.
     *
     * @param matrix_builder $builder
     * @return void
     */
    public static function export_csv(matrix_builder $builder): void {
        global $SITE;

        $proginfo = $builder->get_program_info();
        $progname = $proginfo ? $proginfo->fullname : get_string('pluginname', 'report_programreport');
        $safeprogname = clean_filename(str_replace(' ', '_', $progname)) ?: 'program';
        $filename = 'program_report_' . $safeprogname . '_' . date('Ymd_His') . '.csv';

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output UTF-8 Byte Order Mark (BOM) for correct Arabic / Unicode encoding in Excel.
        echo "\xEF\xBB\xBF";

        $fp = fopen('php://output', 'w');

        $kpis = $builder->get_kpi_metrics();
        $courses = $builder->get_courses();
        $rows = $builder->get_filtered_student_rows();
        $stats = $builder->get_course_stats();

        // 1. Title and Metadata.
        fputcsv($fp, [$SITE->fullname . ' - ' . get_string('pluginname', 'report_programreport') . ': ' . $progname]);
        fputcsv($fp, [
            'Generated: ' . userdate(time(), get_string('strftimedatetime', 'langconfig')),
            'Students: ' . ($kpis['total_students'] ?? 0),
            'Courses: ' . ($kpis['total_courses'] ?? 0),
            'Completion Rate: ' . ($kpis['completion_rate_formatted'] ?? '0%'),
            'Cohort Average: ' . ($kpis['cohort_average_formatted'] ?? '-'),
        ]);
        fputcsv($fp, []); // Empty line.

        // 2. Header Row.
        $header = [
            get_string('student', 'report_programreport'),
            get_string('idnumber', 'report_programreport'),
            get_string('email'),
        ];
        foreach ($courses as $c) {
            $header[] = $c->fullname;
        }
        $header[] = get_string('completedcourses', 'report_programreport');
        $header[] = get_string('progavg', 'report_programreport');
        $header[] = get_string('progstatus', 'report_programreport');
        fputcsv($fp, $header);

        // 3. Student Rows.
        foreach ($rows as $r) {
            $rowdata = [
                $r->fullname,
                $r->idnumber ?: '-',
                $r->email ?: '-',
            ];
            foreach ($r->courses as $c) {
                $rowdata[] = $c->percentage_formatted;
            }
            $rowdata[] = $r->completed_ratio;
            $rowdata[] = $r->prog_avg_formatted;
            $rowdata[] = $r->prog_status_label;
            fputcsv($fp, $rowdata);
        }

        // 4. Summary Rows.
        $avgrow = [get_string('courseaverages', 'report_programreport'), '', ''];
        foreach ($stats as $st) {
            $avgrow[] = $st->average_formatted;
        }
        $avgrow[] = '-';
        $avgrow[] = $kpis['cohort_average_formatted'] ?? '-';
        $avgrow[] = '-';
        fputcsv($fp, $avgrow);

        $passrow = [get_string('passrate', 'report_programreport'), '', ''];
        foreach ($stats as $st) {
            $passrow[] = $st->pass_rate_formatted;
        }
        $passrow[] = '-';
        $passrow[] = '-';
        $passrow[] = '-';
        fputcsv($fp, $passrow);

        $comprow = [get_string('completedcount', 'report_programreport'), '', ''];
        foreach ($stats as $st) {
            $comprow[] = (string)$st->completed_count;
        }
        $comprow[] = '-';
        $comprow[] = '-';
        $comprow[] = '-';
        fputcsv($fp, $comprow);

        fclose($fp);
        exit;
    }

    /**
     * Export matrix report to Landscape PDF.
     *
     * @param matrix_builder $builder
     * @return void
     */
    public static function export_pdf(matrix_builder $builder): void {
        global $CFG, $SITE;

        require_once($CFG->libdir . '/pdflib.php');

        $proginfo = $builder->get_program_info();
        $progname = $proginfo ? $proginfo->fullname : get_string('pluginname', 'report_programreport');
        $safeprogname = clean_filename(str_replace(' ', '_', $progname)) ?: 'program';
        $filename = 'program_report_' . $safeprogname . '_' . date('Ymd_His') . '.pdf';

        $kpis = $builder->get_kpi_metrics();
        $courses = $builder->get_courses();
        $rows = $builder->get_filtered_student_rows();
        $stats = $builder->get_course_stats();
        $numcourses = count($courses);

        $colors = matrix_builder::get_color_settings();
        $primary = $colors['header_primary'] ?? '#6f42c1';

        // Orientation: Landscape ('L'), unit: mm, format: A4.
        $pdf = new \pdf('L', 'mm', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetTitle($progname . ' - ' . get_string('pluginname', 'report_programreport'));
        $pdf->SetAuthor($SITE->fullname);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();

        // Calculate dynamic font size based on course count to ensure graceful fit on landscape page.
        $fontsize = 8.5;
        if ($numcourses > 12) {
            $fontsize = 6.5;
        } else if ($numcourses > 7) {
            $fontsize = 7.5;
        }

        $style = '<style>
            table.meta-table { width: 100%; border-bottom: 2px solid ' . $primary . '; margin-bottom: 10px; }
            h1 { color: ' . $primary . '; font-size: 14pt; margin: 0; }
            .kpi-box { background-color: #f8fafc; border: 1px solid #cbd5e1; padding: 4px 8px; font-size: 8pt; margin-bottom: 8px; }
            table.matrix { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: ' . $fontsize . 'pt; }
            table.matrix th { background-color: ' . $primary . '; color: #ffffff; font-weight: bold; border: 1px solid #4a5568; padding: 4px; text-align: center; }
            table.matrix th.th-student { text-align: left; }
            table.matrix td { border: 1px solid #cbd5e1; padding: 3px 4px; text-align: center; color: #1e293b; }
            table.matrix td.td-student { text-align: left; font-weight: bold; }
            table.matrix tr.summary-row td { background-color: #e2e8f0; font-weight: bold; }
            .badge-completed { color: #155724; font-weight: bold; }
            .badge-passed { color: #0c5460; font-weight: bold; }
            .badge-failed { color: #721c24; font-weight: bold; }
            .badge-inprogress { color: #856404; }
            .badge-notenrolled { color: #6c757d; }
        </style>';

        $logohtml = self::get_report_logo_html(150, 40);

        // Header and metadata table.
        $metahtml = '<table class="meta-table"><tr>';
        $metahtml .= '<td style="width: 70%;">';
        $metahtml .= '<h1>' . s($progname) . '</h1>';
        $metahtml .= '<p style="font-size: 9pt; color: #475569; margin: 2px 0 0 0;"><strong>' . format_string($SITE->fullname) . '</strong> &bull; ' . get_string('pluginname', 'report_programreport') . '</p>';
        $metahtml .= '<p style="font-size: 8pt; color: #64748b; margin: 2px 0 0 0;">' . userdate(time(), get_string('strftimedatetime', 'langconfig')) . '</p>';
        $metahtml .= '</td>';
        $metahtml .= '<td style="width: 30%; text-align: right;">';
        if (!empty($logohtml)) {
            $metahtml .= $logohtml;
        }
        $metahtml .= '</td>';
        $metahtml .= '</tr></table>';

        // KPI summary bar.
        $kpihtml = '<table class="kpi-box" style="width: 100%;"><tr>';
        $kpihtml .= '<td style="width: 25%;"><strong>' . get_string('kpi_totalstudents', 'report_programreport') . ':</strong> ' . ($kpis['total_students'] ?? 0) . '</td>';
        $kpihtml .= '<td style="width: 25%;"><strong>' . get_string('kpi_totalcourses', 'report_programreport') . ':</strong> ' . ($kpis['total_courses'] ?? 0) . '</td>';
        $kpihtml .= '<td style="width: 25%;"><strong>' . get_string('kpi_completionrate', 'report_programreport') . ':</strong> ' . ($kpis['completion_rate_formatted'] ?? '0%') . '</td>';
        $kpihtml .= '<td style="width: 25%;"><strong>' . get_string('kpi_cohortaverage', 'report_programreport') . ':</strong> ' . ($kpis['cohort_average_formatted'] ?? '-') . '</td>';
        $kpihtml .= '</tr></table>';

        // Matrix table.
        $tablehtml = '<table class="matrix" cellpadding="3" cellspacing="0">';
        $tablehtml .= '<thead><tr>';
        $tablehtml .= '<th class="th-student">' . get_string('student', 'report_programreport') . '</th>';
        $tablehtml .= '<th>' . get_string('idnumber', 'report_programreport') . '</th>';
        foreach ($courses as $c) {
            $tablehtml .= '<th>' . s($c->shortname ?: $c->fullname) . '</th>';
        }
        $tablehtml .= '<th>' . get_string('completedcourses', 'report_programreport') . '</th>';
        $tablehtml .= '<th>' . get_string('progavg', 'report_programreport') . '</th>';
        $tablehtml .= '<th>' . get_string('progstatus', 'report_programreport') . '</th>';
        $tablehtml .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $tablehtml .= '<tr>';
            $tablehtml .= '<td class="td-student">' . s($r->fullname) . '</td>';
            $tablehtml .= '<td>' . s($r->idnumber ?: '-') . '</td>';
            foreach ($r->courses as $c) {
                $cls = 'badge-' . $c->status;
                $tablehtml .= '<td class="' . $cls . '">' . s($c->percentage_formatted) . '</td>';
            }
            $tablehtml .= '<td>' . s($r->completed_ratio) . '</td>';
            $tablehtml .= '<td><strong>' . s($r->prog_avg_formatted) . '</strong></td>';
            $tablehtml .= '<td>' . s($r->prog_status_label) . '</td>';
            $tablehtml .= '</tr>';
        }

        // Summary footer rows.
        // Course averages.
        $tablehtml .= '<tr class="summary-row">';
        $tablehtml .= '<td class="td-student">' . get_string('courseaverages', 'report_programreport') . '</td>';
        $tablehtml .= '<td>-</td>';
        foreach ($stats as $st) {
            $tablehtml .= '<td>' . s($st->average_formatted) . '</td>';
        }
        $tablehtml .= '<td>-</td>';
        $tablehtml .= '<td>' . s($kpis['cohort_average_formatted'] ?? '-') . '</td>';
        $tablehtml .= '<td>-</td>';
        $tablehtml .= '</tr>';

        // Pass rates.
        $tablehtml .= '<tr class="summary-row">';
        $tablehtml .= '<td class="td-student">' . get_string('passrate', 'report_programreport') . '</td>';
        $tablehtml .= '<td>-</td>';
        foreach ($stats as $st) {
            $tablehtml .= '<td>' . s($st->pass_rate_formatted) . '</td>';
        }
        $tablehtml .= '<td>-</td>';
        $tablehtml .= '<td>-</td>';
        $tablehtml .= '<td>-</td>';
        $tablehtml .= '</tr>';

        $tablehtml .= '</tbody></table>';

        $pdf->writeHTML($style . $metahtml . $kpihtml . $tablehtml);

        while (ob_get_level()) {
            ob_end_clean();
        }
        $pdf->Output($filename, 'D');
        exit;
    }

    /**
     * Retrieve base64-encoded logo image HTML for PDF and web exports.
     *
     * @param int $maxwidth
     * @param int $maxheight
     * @return string
     */
    public static function get_report_logo_html(int $maxwidth = 160, int $maxheight = 48): string {
        global $CFG, $SITE;

        try {
            $fs = get_file_storage();
            $syscontext = \context_system::instance();
            $targetfile = null;

            // 1. Custom report logo in report_programreport.
            $files = $fs->get_area_files($syscontext->id, 'report_programreport', 'report_logo', 0, 'timemodified DESC', false);
            if (!empty($files)) {
                $targetfile = reset($files);
            }

            // 2. Custom report logo in report_studentgrades (if shared).
            if (!$targetfile) {
                $files = $fs->get_area_files($syscontext->id, 'report_studentgrades', 'report_logo', 0, 'timemodified DESC', false);
                if (!empty($files)) {
                    $targetfile = reset($files);
                }
            }

            // 3. Fallback to core Moodle logo.
            if (!$targetfile) {
                $files = $fs->get_area_files($syscontext->id, 'core_admin', 'logo', 0, 'timemodified DESC', false);
                if (!empty($files)) {
                    $targetfile = reset($files);
                }
            }

            // 4. Fallback to core Moodle compact logo.
            if (!$targetfile) {
                $files = $fs->get_area_files($syscontext->id, 'core_admin', 'logocompact', 0, 'timemodified DESC', false);
                if (!empty($files)) {
                    $targetfile = reset($files);
                }
            }

            if ($targetfile && !$targetfile->is_directory()) {
                $content = $targetfile->get_content();
                $mimetype = $targetfile->get_mimetype();
                $b64 = 'data:' . $mimetype . ';base64,' . base64_encode($content);

                $width = $maxwidth;
                $height = $maxheight;

                $size = @getimagesizefromstring($content);
                if ($size && !empty($size[0]) && !empty($size[1])) {
                    $origw = $size[0];
                    $origh = $size[1];
                    $ratio = min($maxwidth / $origw, $maxheight / $origh, 1.0);
                    $width = (int)round($origw * $ratio);
                    $height = (int)round($origh * $ratio);
                }

                return '<div style="margin-bottom: 5px;"><img src="' . $b64 . '" width="' . $width . '" height="' . $height . '" alt="' . s($SITE->fullname) . '"></div>';
            }
        } catch (\Throwable $e) {
            // Fail safely without disrupting export.
        }

        return '';
    }
}
