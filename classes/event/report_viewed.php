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
 * Event triggered when the program report is viewed.
 *
 * @package     report_programreport
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_programreport\event;

defined('MOODLE_INTERNAL') || die();

use core\event\base;

/**
 * Event class for report_programreport viewed.
 */
class report_viewed extends base {

    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        $programkey = $this->other['programkey'] ?? 'none';
        return "The user with id '{$this->userid}' viewed the program report for program '{$programkey}'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventreportviewed', 'report_programreport');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        $params = [];
        if (!empty($this->other['programkey'])) {
            $params['program'] = $this->other['programkey'];
        }
        return new \moodle_url('/report/programreport/index.php', $params);
    }
}
