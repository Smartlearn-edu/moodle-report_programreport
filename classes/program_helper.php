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
 * Program integration helper for report_programreport.
 *
 * Supports both Open LMS Programs (enrol_programs) and MuTMS Programs (tool_muprog / enrol_muprog).
 *
 * @package     report_programreport
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_programreport;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Helper class for retrieving programs, sequences, and allocated users.
 */
class program_helper {

    /** @var bool|null Cached existence check for enrol_programs */
    private static $hasenrolprograms = null;

    /** @var bool|null Cached existence check for tool_muprog */
    private static $hasmuprog = null;

    /**
     * Check if enrol_programs plugin tables are available.
     *
     * @return bool
     */
    public static function has_enrol_programs(): bool {
        global $DB;
        if (self::$hasenrolprograms === null) {
            self::$hasenrolprograms = $DB->get_manager()->table_exists('enrol_programs_programs') &&
                $DB->get_manager()->table_exists('enrol_programs_allocations') &&
                $DB->get_manager()->table_exists('enrol_programs_items');
        }
        return self::$hasenrolprograms;
    }

    /**
     * Check if tool_muprog / enrol_muprog plugin tables are available.
     *
     * @return bool
     */
    public static function has_muprog(): bool {
        global $DB;
        if (self::$hasmuprog === null) {
            self::$hasmuprog = $DB->get_manager()->table_exists('tool_muprog_program') &&
                $DB->get_manager()->table_exists('tool_muprog_allocation') &&
                $DB->get_manager()->table_exists('tool_muprog_item');
        }
        return self::$hasmuprog;
    }

    /**
     * Check if any program engine is supported on this site.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return self::has_enrol_programs() || self::has_muprog();
    }

    /**
     * Retrieve all programs accessible to the current viewer for the program selector.
     *
     * @param int $viewerid Viewer user ID (defaults to current logged-in user).
     * @return array Array of program objects keyed by composite key (e.g. 'programs_1', 'muprog_2').
     */
    public static function get_all_accessible_programs(int $viewerid = 0): array {
        global $USER, $DB;

        if ($viewerid <= 0) {
            $viewerid = (int)$USER->id;
        }

        if (!self::is_available()) {
            return [];
        }

        $programs = [];

        $syscontext = \context_system::instance();

        // 1. Open LMS Programs.
        if (self::has_enrol_programs()) {
            $records = $DB->get_records('enrol_programs_programs', ['archived' => 0], 'fullname ASC', 'id, fullname, idnumber');
            foreach ($records as $rec) {
                $key = 'programs_' . $rec->id;
                $programs[$key] = (object)[
                    'key' => $key,
                    'source' => 'enrol_programs',
                    'id' => (int)$rec->id,
                    'fullname' => format_string($rec->fullname, true, ['context' => $syscontext, 'escape' => false]),
                    'idnumber' => $rec->idnumber ?? '',
                ];
            }
        }

        // 2. MuTMS Programs.
        if (self::has_muprog()) {
            $records = $DB->get_records('tool_muprog_program', ['archived' => 0], 'fullname ASC', 'id, fullname, idnumber');
            foreach ($records as $rec) {
                $key = 'muprog_' . $rec->id;
                $programs[$key] = (object)[
                    'key' => $key,
                    'source' => 'tool_muprog',
                    'id' => (int)$rec->id,
                    'fullname' => format_string($rec->fullname, true, ['context' => $syscontext, 'escape' => false]),
                    'idnumber' => $rec->idnumber ?? '',
                ];
            }
        }

        return $programs;
    }

    /**
     * Extract course IDs from Open LMS Programs (enrol_programs) in the defined program sequence.
     *
     * @param int $programid
     * @return array Array of integer course IDs in sequence.
     */
    private static function get_enrol_programs_course_ids_in_sequence(int $programid): array {
        global $DB;

        // 1. Attempt tree-based sequence using enrol_programs content classes if available.
        if (class_exists('\enrol_programs\local\content\top')) {
            try {
                $top = \enrol_programs\local\content\top::load($programid);
                $courseids = [];
                $collect = function($item) use (&$collect, &$courseids) {
                    if ($item instanceof \enrol_programs\local\content\course) {
                        $cid = (int)$item->get_courseid();
                        if ($cid > 0) {
                            $courseids[] = $cid;
                        }
                    }
                    if (method_exists($item, 'get_children')) {
                        foreach ($item->get_children() as $child) {
                            $collect($child);
                        }
                    }
                };
                $collect($top);
                if (!empty($courseids)) {
                    return array_values(array_unique(array_filter($courseids)));
                }
            } catch (\Throwable $e) {
                // If top item is missing or tree invalid, fall back to direct DB parsing below.
            }
        }

        // 2. Direct sequence parser from enrol_programs_items table.
        $records = $DB->get_records('enrol_programs_items', ['programid' => $programid], 'id ASC');
        if (empty($records)) {
            return [];
        }

        $topitem = null;
        foreach ($records as $r) {
            if (!empty($r->topitem)) {
                $topitem = $r;
                break;
            }
        }

        $courseids = [];
        $visited = [];

        $traverse = function($item) use (&$traverse, &$records, &$courseids, &$visited) {
            if (isset($visited[$item->id])) {
                return;
            }
            $visited[$item->id] = true;

            if (!empty($item->courseid) && (int)$item->courseid > 0) {
                $courseids[] = (int)$item->courseid;
                return;
            }

            if (!empty($item->sequencejson)) {
                $seq = json_decode($item->sequencejson);
                if ($seq && !empty($seq->children) && is_array($seq->children)) {
                    foreach ($seq->children as $childid) {
                        if (isset($records[$childid])) {
                            $traverse($records[$childid]);
                        }
                    }
                }
            }
        };

        if ($topitem) {
            $traverse($topitem);
        }

        foreach ($records as $r) {
            if (!empty($r->courseid) && (int)$r->courseid > 0 && !in_array((int)$r->courseid, $courseids, true)) {
                $courseids[] = (int)$r->courseid;
            }
        }

        return array_values(array_unique(array_filter($courseids)));
    }

    /**
     * Extract course IDs from MuTMS Programs (tool_muprog) in the defined program sequence.
     *
     * @param int $programid
     * @return array Array of integer course IDs in sequence.
     */
    private static function get_muprog_course_ids_in_sequence(int $programid): array {
        global $DB;

        $records = $DB->get_records('tool_muprog_item', ['programid' => $programid], 'id ASC');
        if (empty($records)) {
            return [];
        }

        $topitem = null;
        foreach ($records as $r) {
            if (!empty($r->topitem)) {
                $topitem = $r;
                break;
            }
        }

        $courseids = [];
        $visited = [];

        $traverse = function($item) use (&$traverse, &$records, &$courseids, &$visited) {
            if (isset($visited[$item->id])) {
                return;
            }
            $visited[$item->id] = true;

            if (!empty($item->courseid) && (int)$item->courseid > 0) {
                $courseids[] = (int)$item->courseid;
                return;
            }

            if (!empty($item->sequencejson)) {
                $seq = json_decode($item->sequencejson);
                if ($seq && !empty($seq->children) && is_array($seq->children)) {
                    foreach ($seq->children as $childid) {
                        if (isset($records[$childid])) {
                            $traverse($records[$childid]);
                        }
                    }
                }
            }
        };

        if ($topitem) {
            $traverse($topitem);
        }

        foreach ($records as $r) {
            if (!empty($r->courseid) && (int)$r->courseid > 0 && !in_array((int)$r->courseid, $courseids, true)) {
                $courseids[] = (int)$r->courseid;
            }
        }

        return array_values(array_unique(array_filter($courseids)));
    }

    /**
     * Retrieve all course IDs belonging to a program in the program's defined sequence.
     *
     * @param string $programkey The composite program key (e.g. 'programs_1', 'muprog_2').
     * @return array Array of integer course IDs in program sequence.
     */
    public static function get_program_courses(string $programkey): array {
        static $cache = [];
        if (isset($cache[$programkey])) {
            return $cache[$programkey];
        }

        global $DB;

        if (empty($programkey)) {
            return [];
        }

        $parts = explode('_', $programkey, 2);
        if (count($parts) !== 2) {
            return [];
        }

        [$type, $idstr] = $parts;
        $id = (int)$idstr;
        if ($id <= 0) {
            return [];
        }

        $courseids = [];

        if ($type === 'programs' && self::has_enrol_programs()) {
            $courseids = self::get_enrol_programs_course_ids_in_sequence($id);

            $enrolcourses = $DB->get_fieldset_select(
                'enrol',
                'courseid',
                'enrol = ? AND customint1 = ? AND status = 0',
                ['programs', $id]
            );
            if (!empty($enrolcourses)) {
                foreach ($enrolcourses as $ecid) {
                    $ecid = (int)$ecid;
                    if ($ecid > 0 && !in_array($ecid, $courseids, true)) {
                        $courseids[] = $ecid;
                    }
                }
            }
        } else if ($type === 'muprog' && self::has_muprog()) {
            $courseids = self::get_muprog_course_ids_in_sequence($id);

            $enrolcourses = $DB->get_fieldset_select(
                'enrol',
                'courseid',
                'enrol = ? AND customint1 = ? AND status = 0',
                ['muprog', $id]
            );
            if (!empty($enrolcourses)) {
                foreach ($enrolcourses as $ecid) {
                    $ecid = (int)$ecid;
                    if ($ecid > 0 && !in_array($ecid, $courseids, true)) {
                        $courseids[] = $ecid;
                    }
                }
            }
        }

        $result = array_values(array_unique(array_filter(array_map('intval', $courseids))));
        $cache[$programkey] = $result;
        return $result;
    }

    /**
     * Retrieve course records in the given sequence order.
     *
     * @param array $courseids Array of integer course IDs.
     * @return array Array of course stdClass records ordered exactly as $courseids.
     */
    public static function get_courses_in_sequence(array $courseids): array {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $records = $DB->get_records_select('course', "id $insql", $inparams, '', 'id, fullname, shortname, idnumber');

        $ordered = [];
        $syscontext = \context_system::instance();
        foreach ($courseids as $cid) {
            if (isset($records[$cid])) {
                $c = $records[$cid];
                $ccontext = \context_course::instance($cid, IGNORE_MISSING) ?: $syscontext;
                $c->fullname = format_string($c->fullname, true, ['context' => $ccontext, 'escape' => false]);
                $c->shortname = format_string($c->shortname, true, ['context' => $ccontext, 'escape' => false]);
                $ordered[] = $c;
            }
        }

        return $ordered;
    }

    /**
     * Retrieve all user IDs enrolled in or allocated to a program.
     *
     * @param string $programkey The composite program key.
     * @return array Array of integer user IDs.
     */
    public static function get_program_user_ids(string $programkey): array {
        static $cache = [];
        if (isset($cache[$programkey])) {
            return $cache[$programkey];
        }

        global $DB;

        if (empty($programkey)) {
            return [];
        }

        $parts = explode('_', $programkey, 2);
        if (count($parts) !== 2) {
            return [];
        }

        [$type, $idstr] = $parts;
        $id = (int)$idstr;
        if ($id <= 0) {
            return [];
        }

        $userids = [];

        if ($type === 'programs' && self::has_enrol_programs()) {
            $allocusers = $DB->get_fieldset_select(
                'enrol_programs_allocations',
                'userid',
                'programid = ? AND archived = 0',
                [$id]
            );
            if (!empty($allocusers)) {
                $userids = array_merge($userids, $allocusers);
            }

            $sql = "SELECT DISTINCT ue.userid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE e.enrol = 'programs' AND e.customint1 = :id AND ue.status = 0 AND e.status = 0";
            $enrolusers = $DB->get_fieldset_sql($sql, ['id' => $id]);
            if (!empty($enrolusers)) {
                $userids = array_merge($userids, $enrolusers);
            }
        } else if ($type === 'muprog' && self::has_muprog()) {
            $allocusers = $DB->get_fieldset_select(
                'tool_muprog_allocation',
                'userid',
                'programid = ? AND archived = 0',
                [$id]
            );
            if (!empty($allocusers)) {
                $userids = array_merge($userids, $allocusers);
            }

            $sql = "SELECT DISTINCT ue.userid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE e.enrol = 'muprog' AND e.customint1 = :id AND ue.status = 0 AND e.status = 0";
            $enrolusers = $DB->get_fieldset_sql($sql, ['id' => $id]);
            if (!empty($enrolusers)) {
                $userids = array_merge($userids, $enrolusers);
            }
        }

        $result = array_values(array_unique(array_filter(array_map('intval', $userids))));
        $cache[$programkey] = $result;
        return $result;
    }

    /**
     * Retrieve full user records for program students, with optional search query.
     *
     * @param string $programkey The composite program key.
     * @param string $search Optional search query for name, email or ID number.
     * @return array Array of user stdClass records.
     */
    public static function get_program_users(string $programkey, string $search = ''): array {
        global $DB;

        $userids = self::get_program_user_ids($programkey);
        if (empty($userids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $where = "id $insql AND deleted = 0 AND suspended = 0";

        if (!empty($search)) {
            $search = trim($search);
            $fullnameconcat = $DB->sql_concat('firstname', "' '", 'lastname');
            $searchlike = '%' . $DB->sql_like_escape($search) . '%';
            $where .= " AND (" . $DB->sql_like($fullnameconcat, ':search1', false, false) . "
                          OR " . $DB->sql_like('email', ':search2', false, false) . "
                          OR " . $DB->sql_like('idnumber', ':search3', false, false) . ")";
            $params['search1'] = $searchlike;
            $params['search2'] = $searchlike;
            $params['search3'] = $searchlike;
        }

        $namefields = \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects;
        $fields = "id, email, idnumber, $namefields";
        $order = 'lastname ASC, firstname ASC, id ASC';
        return $DB->get_records_select('user', $where, $params, $order, $fields);
    }

    /**
     * Get details of a single program by its composite key.
     *
     * @param string $programkey
     * @return stdClass|null
     */
    public static function get_program_info(string $programkey): ?stdClass {
        global $DB;

        if (empty($programkey)) {
            return null;
        }

        $parts = explode('_', $programkey, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$type, $idstr] = $parts;
        $id = (int)$idstr;
        if ($id <= 0) {
            return null;
        }

        $syscontext = \context_system::instance();

        if ($type === 'programs' && self::has_enrol_programs()) {
            $rec = $DB->get_record('enrol_programs_programs', ['id' => $id]);
            if ($rec) {
                return (object)[
                    'key' => $programkey,
                    'source' => 'enrol_programs',
                    'id' => $id,
                    'fullname' => format_string($rec->fullname, true, ['context' => $syscontext, 'escape' => false]),
                    'idnumber' => $rec->idnumber ?? '',
                ];
            }
        } else if ($type === 'muprog' && self::has_muprog()) {
            $rec = $DB->get_record('tool_muprog_program', ['id' => $id]);
            if ($rec) {
                return (object)[
                    'key' => $programkey,
                    'source' => 'tool_muprog',
                    'id' => $id,
                    'fullname' => format_string($rec->fullname, true, ['context' => $syscontext, 'escape' => false]),
                    'idnumber' => $rec->idnumber ?? '',
                ];
            }
        }

        return null;
    }
}
