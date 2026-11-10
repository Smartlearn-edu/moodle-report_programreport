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
 * Matrix builder and batch calculation engine for report_programreport.
 *
 * Implements high-performance batch SQL preloading to eliminate N x M queries.
 *
 * @package     report_programreport
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_programreport;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use moodle_url;

/**
 * Matrix builder and reporting engine.
 */
class matrix_builder {

    /** @var string Program composite key */
    protected string $programkey;

    /** @var stdClass|null Program details */
    protected ?stdClass $programinfo = null;

    /** @var int Current page index (0-based) */
    protected int $page = 0;

    /** @var int Items per page (0 = all) */
    protected int $perpage = 50;

    /** @var string Search query */
    protected string $search = '';

    /** @var string Status filter: all, completed, inprogress, notstarted */
    protected string $statusfilter = 'all';

    /** @var array Array of integer course IDs */
    protected array $courseids = [];

    /** @var array Array of course stdClass records in sequence */
    protected array $courses = [];

    /** @var array Preloaded grade items indexed by courseid */
    protected array $gradeitems = [];

    /** @var array Preloaded grades indexed by userid and courseid */
    protected array $studentgrades = [];

    /** @var array Preloaded course completions indexed by userid and courseid */
    protected array $completions = [];

    /** @var array Preloaded course enrolments indexed by userid and courseid */
    protected array $enrolments = [];

    /** @var array All evaluated student matrix records */
    protected array $allstudentrows = [];

    /** @var array Filtered student matrix records */
    protected array $filteredstudentrows = [];

    /** @var array Per-course aggregate statistics */
    protected array $coursestats = [];

    /** @var array Top KPI card metrics */
    protected array $kpimetrics = [];

    /** @var bool Whether data has been calculated */
    protected bool $iscalculated = false;

    /**
     * Constructor.
     *
     * @param string $programkey Composite program key (e.g. 'programs_1', 'muprog_2').
     * @param int $page Page index (0-based).
     * @param int $perpage Students per page (0 for all).
     * @param string $search Search query for name, email or ID.
     * @param string $statusfilter Status filter (all, completed, inprogress, notstarted).
     */
    public function __construct(
        string $programkey,
        int $page = 0,
        int $perpage = 50,
        string $search = '',
        string $statusfilter = 'all'
    ) {
        $this->programkey = trim($programkey);
        $this->page = max(0, $page);
        $this->perpage = max(0, $perpage);
        $this->search = trim($search);
        $this->statusfilter = in_array($statusfilter, ['all', 'completed', 'inprogress', 'notstarted'], true)
            ? $statusfilter
            : 'all';
    }

    /**
     * Get theme and badge colors from plugin configuration.
     *
     * @return array
     */
    public static function get_color_settings(): array {
        return [
            'header_primary' => get_config('report_programreport', 'header_primary_color') ?: '#6f42c1',
            'header_secondary' => get_config('report_programreport', 'header_secondary_color') ?: '#8e44ad',
            'grade_excellent' => get_config('report_programreport', 'grade_excellent_color') ?: '#28a745',
            'grade_good' => get_config('report_programreport', 'grade_good_color') ?: '#17a2b8',
            'grade_pass' => get_config('report_programreport', 'grade_pass_color') ?: '#ffc107',
            'grade_fail' => get_config('report_programreport', 'grade_fail_color') ?: '#dc3545',
        ];
    }

    /**
     * Execute the batch preloading queries and compute in-memory calculations.
     *
     * @return self
     */
    public function calculate(): self {
        global $DB;

        if ($this->iscalculated) {
            return $this;
        }

        $this->iscalculated = true;

        if (empty($this->programkey)) {
            $this->init_empty_state();
            return $this;
        }

        $this->programinfo = program_helper::get_program_info($this->programkey);
        if (!$this->programinfo) {
            $this->init_empty_state();
            return $this;
        }

        $this->courseids = program_helper::get_program_courses($this->programkey);
        if (empty($this->courseids)) {
            $this->init_empty_state();
            return $this;
        }

        $this->courses = program_helper::get_courses_in_sequence($this->courseids);
        $users = program_helper::get_program_users($this->programkey, $this->search);

        if (empty($users)) {
            $this->init_empty_state();
            return $this;
        }

        $userids = array_map(fn($u) => (int)$u->id, array_values($users));

        // -------------------------------------------------------------
        // Batch 1: Preload Course Grade Items for all Program Courses
        // -------------------------------------------------------------
        [$cInSql, $cInParams] = $DB->get_in_or_equal($this->courseids, SQL_PARAMS_NAMED, 'bcid');
        $itemsql = "SELECT id, courseid, grademin, grademax, gradepass
                      FROM {grade_items}
                     WHERE courseid $cInSql AND itemtype = 'course'";
        $gradeitemrecords = $DB->get_records_sql($itemsql, $cInParams);
        $this->gradeitems = [];
        foreach ($gradeitemrecords as $gi) {
            $this->gradeitems[(int)$gi->courseid] = $gi;
        }

        // -------------------------------------------------------------
        // Batch 2: Preload All Final Grades for all Students in these Courses
        // -------------------------------------------------------------
        [$uInSql, $uInParams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'buid');
        $params = array_merge($cInParams, $uInParams);

        $gradesql = "SELECT g.id, g.itemid, g.userid, g.finalgrade, g.rawgrademin, g.rawgrademax,
                            i.courseid, i.gradepass, i.grademin, i.grademax
                       FROM {grade_grades} g
                       JOIN {grade_items} i ON i.id = g.itemid
                      WHERE g.userid $uInSql AND i.courseid $cInSql AND i.itemtype = 'course'";
        $graderecords = $DB->get_records_sql($gradesql, $params);
        $this->studentgrades = [];
        foreach ($graderecords as $gr) {
            $uid = (int)$gr->userid;
            $cid = (int)$gr->courseid;
            $this->studentgrades[$uid][$cid] = $gr;
        }
        unset($graderecords);

        // -------------------------------------------------------------
        // Batch 3: Preload Course Completions
        // -------------------------------------------------------------
        $compsql = "SELECT id, userid, course, timecompleted
                      FROM {course_completions}
                     WHERE userid $uInSql AND course $cInSql AND timecompleted IS NOT NULL AND timecompleted > 0";
        $comprecords = $DB->get_records_sql($compsql, $params);
        $this->completions = [];
        foreach ($comprecords as $cr) {
            $uid = (int)$cr->userid;
            $cid = (int)$cr->course;
            $this->completions[$uid][$cid] = (int)$cr->timecompleted;
        }
        unset($comprecords);

        // -------------------------------------------------------------
        // Batch 4: Preload Active Course Enrolments
        // -------------------------------------------------------------
        $concat = $DB->sql_concat('ue.userid', "'-'", 'e.courseid');
        $enrolsql = "SELECT DISTINCT $concat AS ukey, ue.userid, e.courseid
                       FROM {user_enrolments} ue
                       JOIN {enrol} e ON e.id = ue.enrolid
                      WHERE ue.userid $uInSql AND e.courseid $cInSql AND ue.status = 0 AND e.status = 0";
        $enrolrecords = $DB->get_records_sql($enrolsql, $params);
        $this->enrolments = [];
        foreach ($enrolrecords as $er) {
            $uid = (int)$er->userid;
            $cid = (int)$er->courseid;
            $this->enrolments[$uid][$cid] = true;
        }
        unset($enrolrecords);

        // -------------------------------------------------------------
        // Assemble Student Matrix Rows & Filter by Status
        // -------------------------------------------------------------
        $this->assemble_student_rows($users);

        // -------------------------------------------------------------
        // Compute Cohort KPIs & Course Column Aggregate Stats
        // -------------------------------------------------------------
        $this->compute_aggregates();

        return $this;
    }

    /**
     * Assemble in-memory matrix rows for all students.
     *
     * @param array $users
     */
    protected function assemble_student_rows(array $users): void {
        global $CFG;

        $hasstudentgradesplugin = file_exists($CFG->dirroot . '/report/studentgrades/index.php');
        $colors = self::get_color_settings();
        $totalcourserequired = count($this->courseids);

        $allrows = [];
        $filteredrows = [];

        foreach ($users as $user) {
            $uid = (int)$user->id;
            $userfullname = fullname($user);

            // Student Report Link: prefers report_studentgrades filtered to this program if installed.
            if ($hasstudentgradesplugin) {
                $studenturl = new moodle_url('/report/studentgrades/index.php', [
                    'userid' => $uid,
                    'program' => $this->programkey,
                ]);
            } else {
                $studenturl = new moodle_url('/user/profile.php', ['id' => $uid]);
            }

            $cells = [];
            $completedcount = 0;
            $percentagesum = 0.0;
            $gradedcoursescount = 0;
            $hasanyenrolment = false;

            foreach ($this->courses as $course) {
                $cid = (int)$course->id;
                $iscompleted = isset($this->completions[$uid][$cid]);
                $isenrolled = isset($this->enrolments[$uid][$cid]);
                $gradeobj = $this->studentgrades[$uid][$cid] ?? null;

                if ($isenrolled) {
                    $hasanyenrolment = true;
                }

                $status = 'not_enrolled';
                $badgeclass = 'badge-notenrolled';
                $badgecolor = '#6c757d';
                $percentage = null;
                $percentageformatted = '-';
                $rawgradeformatted = '-';
                $rawmaxformatted = '100.00';
                $passmarkformatted = '-';
                $hasgrade = false;
                $completeddate = '';

                if ($iscompleted) {
                    $completedcount++;
                    $completedtime = $this->completions[$uid][$cid];
                    $completeddate = userdate($completedtime, get_string('strftimedatetimeshort', 'langconfig'));
                }

                if ($gradeobj !== null && $gradeobj->finalgrade !== null) {
                    $hasgrade = true;
                    $finalgrade = (float)$gradeobj->finalgrade;
                    $gmin = (float)($gradeobj->grademin ?? $gradeobj->rawgrademin ?? 0.0);
                    $gmax = (float)($gradeobj->grademax ?? $gradeobj->rawgrademax ?? 100.0);
                    $range = $gmax - $gmin;

                    if ($range > 0) {
                        $pct = (($finalgrade - $gmin) / $range) * 100.0;
                    } else {
                        $pct = $finalgrade;
                    }
                    $pct = max(0.0, min(100.0, $pct));
                    $percentage = round($pct, 1);
                    $percentageformatted = number_format($percentage, 1) . '%';
                    $percentagesum += $percentage;
                    $gradedcoursescount++;

                    $rawgradeformatted = format_float($finalgrade, 2);
                    $rawmaxformatted = format_float($gmax, 2);
                    $passmark = (float)($gradeobj->gradepass ?? 0.0);
                    $passmarkformatted = $passmark > 0 ? format_float($passmark, 2) : '-';

                    // Determine pass / fail status.
                    if ($iscompleted) {
                        $status = 'completed';
                        $badgeclass = 'badge-completed';
                        $badgecolor = $colors['grade_excellent'];
                    } else if ($passmark > 0 && $finalgrade >= $passmark) {
                        $status = 'passed';
                        $badgeclass = 'badge-passed';
                        $badgecolor = $colors['grade_good'];
                    } else if ($passmark > 0 && $finalgrade < $passmark) {
                        $status = 'failed';
                        $badgeclass = 'badge-failed';
                        $badgecolor = $colors['grade_fail'];
                    } else {
                        $status = 'in_progress';
                        $badgeclass = 'badge-inprogress';
                        $badgecolor = $colors['grade_pass'];
                    }
                } else if ($iscompleted) {
                    $status = 'completed';
                    $badgeclass = 'badge-completed';
                    $badgecolor = $colors['grade_excellent'];
                    $percentageformatted = '100%';
                } else if ($isenrolled) {
                    $status = 'in_progress';
                    $badgeclass = 'badge-inprogress';
                    $badgecolor = $colors['grade_pass'];
                    $percentageformatted = get_string('status_inprogress', 'report_programreport');
                } else {
                    $status = 'not_enrolled';
                    $badgeclass = 'badge-notenrolled';
                    $badgecolor = '#adb5bd';
                    $percentageformatted = '-';
                }

                $graderurl = new moodle_url('/grade/report/user/index.php', [
                    'id' => $cid,
                    'userid' => $uid,
                ]);

                // Tooltip info string.
                $a = (object)[
                    'rawgrade' => $rawgradeformatted,
                    'rawgrademax' => $rawmaxformatted,
                    'gradepass' => $passmarkformatted,
                ];
                $tooltip = get_string('gradeitemdetails', 'report_programreport', $a);
                if (!empty($completeddate)) {
                    $tooltip .= ' | ' . get_string('completedon', 'report_programreport', $completeddate);
                }

                $cells[] = (object)[
                    'courseid' => $cid,
                    'coursename' => $course->fullname,
                    'status' => $status,
                    'status_label' => get_string('status_' . $status, 'report_programreport'),
                    'badge_class' => $badgeclass,
                    'badge_color' => $badgecolor,
                    'percentage' => $percentage,
                    'percentage_formatted' => $percentageformatted,
                    'raw_grade' => $rawgradeformatted,
                    'raw_max' => $rawmaxformatted,
                    'pass_mark' => $passmarkformatted,
                    'has_grade' => $hasgrade,
                    'is_completed' => $iscompleted,
                    'completed_date' => $completeddate,
                    'tooltip' => $tooltip,
                    'grader_url' => $graderurl->out(false),
                ];
            }

            // Student Program Summary.
            $progavg = $gradedcoursescount > 0 ? round($percentagesum / $gradedcoursescount, 1) : null;
            $progavgformatted = $progavg !== null ? number_format($progavg, 1) . '%' : '-';

            // Student Program Status.
            if ($totalcourserequired > 0 && $completedcount === $totalcourserequired) {
                $progstatus = 'completed';
                $progstatuslabel = get_string('status_completed', 'report_programreport');
                $progstatusbadge = 'badge-completed';
                $progstatuscolor = $colors['grade_excellent'];
            } else if ($completedcount > 0 || $gradedcoursescount > 0 || $hasanyenrolment) {
                $progstatus = 'inprogress';
                $progstatuslabel = get_string('status_inprogress', 'report_programreport');
                $progstatusbadge = 'badge-inprogress';
                $progstatuscolor = $colors['grade_pass'];
            } else {
                $progstatus = 'notstarted';
                $progstatuslabel = get_string('status_notstarted', 'report_programreport');
                $progstatusbadge = 'badge-notstarted';
                $progstatuscolor = '#6c757d';
            }

            $row = (object)[
                'userid' => $uid,
                'fullname' => $userfullname,
                'idnumber' => $user->idnumber ?? '',
                'email' => $user->email ?? '',
                'student_url' => $studenturl->out(false),
                'courses' => $cells,
                'completed_count' => $completedcount,
                'total_courses_count' => $totalcourserequired,
                'completed_ratio' => "{$completedcount} / {$totalcourserequired}",
                'prog_avg' => $progavg,
                'prog_avg_formatted' => $progavgformatted,
                'prog_status' => $progstatus,
                'prog_status_label' => $progstatuslabel,
                'prog_status_badge' => $progstatusbadge,
                'prog_status_color' => $progstatuscolor,
            ];

            $allrows[] = $row;

            // Apply Status Filter.
            if ($this->statusfilter === 'all' || $this->statusfilter === $progstatus) {
                $filteredrows[] = $row;
            }
        }

        $this->allstudentrows = $allrows;
        $this->filteredstudentrows = $filteredrows;
    }

    /**
     * Compute cohort KPIs and column aggregate stats across the filtered student population.
     */
    protected function compute_aggregates(): void {
        $totalfiltered = count($this->filteredstudentrows);
        $totalcourses = count($this->courses);

        if ($totalfiltered === 0 || $totalcourses === 0) {
            $this->kpimetrics = [
                'total_students' => 0,
                'total_courses' => $totalcourses,
                'completion_rate_formatted' => '0.0%',
                'cohort_average_formatted' => '-',
            ];
            $this->coursestats = [];
            return;
        }

        $completedstudents = 0;
        $totalgradesum = 0.0;
        $totalgradecount = 0;

        // Initialize per-course stats accumulators.
        $columntotals = [];
        foreach ($this->courses as $course) {
            $cid = (int)$course->id;
            $columntotals[$cid] = [
                'courseid' => $cid,
                'coursename' => $course->fullname,
                'scores_sum' => 0.0,
                'scores_count' => 0,
                'passed_count' => 0,
                'completed_count' => 0,
            ];
        }

        foreach ($this->filteredstudentrows as $row) {
            if ($row->prog_status === 'completed') {
                $completedstudents++;
            }

            if ($row->prog_avg !== null) {
                $totalgradesum += $row->prog_avg;
                $totalgradecount++;
            }

            foreach ($row->courses as $c) {
                $cid = $c->courseid;
                if (!isset($columntotals[$cid])) {
                    continue;
                }

                if ($c->is_completed) {
                    $columntotals[$cid]['completed_count']++;
                }

                if ($c->percentage !== null) {
                    $columntotals[$cid]['scores_sum'] += $c->percentage;
                    $columntotals[$cid]['scores_count']++;
                    if ($c->status === 'passed' || $c->status === 'completed' || $c->percentage >= 50.0) {
                        $columntotals[$cid]['passed_count']++;
                    }
                }
            }
        }

        $completionrate = round(($completedstudents / $totalfiltered) * 100.0, 1);
        $cohortavg = $totalgradecount > 0 ? round($totalgradesum / $totalgradecount, 1) : null;

        $this->kpimetrics = [
            'total_students' => $totalfiltered,
            'total_courses' => $totalcourses,
            'completion_rate_formatted' => number_format($completionrate, 1) . '%',
            'cohort_average_formatted' => $cohortavg !== null ? number_format($cohortavg, 1) . '%' : '-',
        ];

        // Format column summary statistics for matrix footer.
        $this->coursestats = [];
        foreach ($columntotals as $cid => $stat) {
            $scount = $stat['scores_count'];
            $cavg = $scount > 0 ? round($stat['scores_sum'] / $scount, 1) : null;
            $passrate = $scount > 0 ? round(($stat['passed_count'] / $scount) * 100.0, 1) : null;

            $this->coursestats[] = (object)[
                'courseid' => $cid,
                'coursename' => $stat['coursename'],
                'average_formatted' => $cavg !== null ? number_format($cavg, 1) . '%' : '-',
                'pass_rate_formatted' => $passrate !== null ? number_format($passrate, 1) . '%' : '-',
                'completed_count' => $stat['completed_count'],
            ];
        }
    }

    /**
     * Initialize empty state when no program or courses are selected.
     */
    protected function init_empty_state(): void {
        $this->courses = [];
        $this->allstudentrows = [];
        $this->filteredstudentrows = [];
        $this->coursestats = [];
        $this->kpimetrics = [
            'total_students' => 0,
            'total_courses' => 0,
            'completion_rate_formatted' => '0.0%',
            'cohort_average_formatted' => '-',
        ];
    }

    /**
     * Get program info object.
     *
     * @return stdClass|null
     */
    public function get_program_info(): ?stdClass {
        $this->calculate();
        return $this->programinfo;
    }

    /**
     * Get curriculum courses in sequence.
     *
     * @return array
     */
    public function get_courses(): array {
        $this->calculate();
        return $this->courses;
    }

    /**
     * Get all filtered student rows (for exports or pagination).
     *
     * @return array
     */
    public function get_filtered_student_rows(): array {
        $this->calculate();
        return $this->filteredstudentrows;
    }

    /**
     * Get paged slice of student rows for web display.
     *
     * @return array
     */
    public function get_paged_student_rows(): array {
        $this->calculate();
        if ($this->perpage <= 0) {
            return $this->filteredstudentrows;
        }
        $offset = $this->page * $this->perpage;
        return array_slice($this->filteredstudentrows, $offset, $this->perpage);
    }

    /**
     * Get KPI cards data.
     *
     * @return array
     */
    public function get_kpi_metrics(): array {
        $this->calculate();
        return $this->kpimetrics;
    }

    /**
     * Get course column footer statistics.
     *
     * @return array
     */
    public function get_course_stats(): array {
        $this->calculate();
        return $this->coursestats;
    }

    /**
     * Get pagination metadata and page link objects.
     *
     * @param moodle_url $baseurl
     * @return stdClass
     */
    public function get_pagination(moodle_url $baseurl): stdClass {
        $this->calculate();
        $total = count($this->filteredstudentrows);
        $perpage = $this->perpage;
        $currentpage = $this->page;

        if ($perpage <= 0 || $total <= $perpage) {
            return (object)[
                'has_pages' => false,
                'total_records' => $total,
                'page' => 0,
                'total_pages' => 1,
                'pages' => [],
            ];
        }

        $totalpages = (int)ceil($total / $perpage);
        $pages = [];

        // Build window of visible page numbers.
        $start = max(0, $currentpage - 3);
        $end = min($totalpages - 1, $currentpage + 3);

        for ($p = $start; $p <= $end; $p++) {
            $url = new moodle_url($baseurl, ['page' => $p]);
            $pages[] = [
                'number' => $p + 1,
                'page' => $p,
                'url' => $url->out(false),
                'is_active' => ($p === $currentpage),
            ];
        }

        $prevurl = null;
        if ($currentpage > 0) {
            $u = new moodle_url($baseurl, ['page' => $currentpage - 1]);
            $prevurl = $u->out(false);
        }

        $nexturl = null;
        if ($currentpage < $totalpages - 1) {
            $u = new moodle_url($baseurl, ['page' => $currentpage + 1]);
            $nexturl = $u->out(false);
        }

        $firsturl = null;
        if ($currentpage > 0) {
            $u = new moodle_url($baseurl, ['page' => 0]);
            $firsturl = $u->out(false);
        }

        $lasturl = null;
        if ($currentpage < $totalpages - 1) {
            $u = new moodle_url($baseurl, ['page' => $totalpages - 1]);
            $lasturl = $u->out(false);
        }

        $from = ($currentpage * $perpage) + 1;
        $to = min($total, ($currentpage + 1) * $perpage);

        return (object)[
            'has_pages' => true,
            'total_records' => $total,
            'page' => $currentpage,
            'total_pages' => $totalpages,
            'showing_text' => "Showing {$from}–{$to} of {$total} students",
            'first_url' => $firsturl,
            'prev_url' => $prevurl,
            'next_url' => $nexturl,
            'last_url' => $lasturl,
            'pages' => $pages,
        ];
    }
}
