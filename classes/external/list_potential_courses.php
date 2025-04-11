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

namespace availability_othercompleted\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Gets potential courses for form autocomplete.
 * @package   availability_othercompleted
 * @author    Matthew Hilton (matthewhilton@catalyst-au.net)
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_potential_courses extends external_api {

    /**
     * Specifies parameters
     * @return mixed external function parameters
     */
    public static function get_parameters() {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Query string to filter results', VALUE_DEFAULT, ''),
            ]);
    }

    /**
     * Lists potential courses from the site.
     * @param string $query query string to query courses fullname
     * @return array courses selected by the query string
     */
    public static function get($query = '') {
        global $DB;

        if ($query == '') {
            return [];
        }

        $courses = $DB->get_records_select(
            'course',
            'enablecompletion = 1 AND ' . $DB->sql_like('fullname', ':name', false, false),
            ['name' => '%'.$query.'%'],
            '',
            'id, fullname, enablecompletion'
        );

        $availablecourses = array_filter($courses, function($course) {
            global $SITE;

            // Filter these by courses they can manage.
            $context = context_course::instance($course->id);
            $canmanage = has_capability('moodle/course:update', $context);

            // Ignore dashboard/site course.
            $notsitecourse = $course->id != $SITE->id;

            return $canmanage && $notsitecourse;
        });

        return $availablecourses;
    }

    /**
     * Specifies return structure
     * @return mixed external function return structure
     */
    public static function get_returns() {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'id of the course'),
            'fullname' => new external_value(PARAM_TEXT, 'The fullname of the course'),
        ]));
    }
}
