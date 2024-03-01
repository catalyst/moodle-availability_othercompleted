<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Front-end class.
 *
 * @package   availability_othercompleted
 * @copyright MU DOT MY PLT <support@mu.my>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_othercompleted;

use cm_info;
use completion_info;
use context_course;
use core_availability\info_module;
use section_info;
use Throwable;

defined('MOODLE_INTERNAL') || die();

class frontend extends \core_availability\frontend {
    /**
     * @var array Cached init parameters
     */
    protected $cacheparams = [];

    /**
     * @var string IDs of course, cm, and section for cache (if any)
     */
    protected $cachekey = '';

    protected function get_javascript_strings() {
        return array('option_complete', 'option_incomplete', 'label_cm', 'label_completion');
    }

    protected function get_javascript_init_params($course, cm_info $cm = null,
                                                  section_info $section = null) {
        // If availability not enabled, there's nothing to do.
        if (empty($cm->availability)) {
            return [];
        }

        try {
            // Find the course names of all courses used by all othercompleted availability conditions
            // So the JS module can use it to prefill the form.
            $coursenamesprefill = [];
            $ci = new info_module($cm);
            $tree = $ci->get_availability_tree();
            foreach ($tree->get_all_children('availability_othercompleted\condition') as $cond) {
                global $DB;
                $courseid = $cond->get_course();
                $coursenamesprefill[$courseid] = $DB->get_field('course', 'fullname', ['id' => $courseid]);
            }

            return [$coursenamesprefill];
        } catch (Throwable $e) {
            // Return no prefill - JS will just use "Unknown course" instead.
            return [];
        }
    }

    protected function allow_add($course, cm_info $cm = null,
                                 section_info $section = null) {
        global $CFG;

        // Check if completion is enabled for the course.
        require_once($CFG->libdir . '/completionlib.php');
        $info = new completion_info($course);
        return $info->is_enabled();
    }
}
