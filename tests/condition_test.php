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
 * Unit tests for the other completion condition.
 *
 * @package   availability_othercompleted
 * @copyright MU DOT MY PLT <support@mu.my>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use availability_othercompleted\condition;
use core_availability\info;
use core_availability\mock_info;
use core_availability\tree;

global $CFG;
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot.'/completion/criteria/completion_criteria_self.php');

/**
 * Unit tests for the completion condition.
 *
 * @package   availability_othercompleted
 * @copyright MU DOT MY PLT <support@mu.my>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class availability_othercompleted_condition_testcase extends advanced_testcase {
    /**
     * include class needed
     */
    public function setUp(): void {
        // include class information mock to use.
        global $CFG;
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info.php');
    }

    /**
     * Tests constructing and using condition as part of tree.
     */
    public function test_in_tree() {
        global $USER, $CFG;
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create course with completion turned on and a Page.
        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $page = $generator->get_plugin_generator('mod_page')->create_instance(
            ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);

        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($page->cmid);
        $info = new mock_info($course, $USER->id);

        $structure = (object)[
            'op'   => '|',
            'show' => true,
            'c'    => [
                (object)[
                    'type' => 'othercompleted',
                    'cm'   => (int)$course->id,
                    'e'    => COMPLETION_COMPLETE,
                ],
            ],
        ];
        $tree = new tree($structure);

        // Initial check (user has not completed activity).
        $result = $tree->check_available(false, $info, true, $USER->id);
        $this->assertFalse($result->is_available());

        $criteriadata = new stdClass();
        $criteriadata->id = $course->id;
        // Self completion.
        $criteriadata->criteria_self = 1;

        $criterion = new completion_criteria_self();
        $criterion->update_config($criteriadata);

        $ccompletion = new completion_completion(array('course' => $course->id, 'userid' => $USER->id));
        $ccompletion->mark_complete();

        // Now it's true!
        $result = $tree->check_available(false, $info, true, $USER->id);
        $this->assertTrue($result->is_available());
    }

    /**
     * Tests that the "complete" and "incomplete" variants of the condition behave as
     * opposites of each other when the structure is built from stored/decoded JSON,
     * where numeric values such as ->e arrive as strings (e.g. "0"/"1") rather than
     * ints. Regression test for a bug where a strict (===) comparison against the
     * int 0 meant the "incomplete" variant never inverted its result.
     */
    public function test_in_tree_json_string_values() {
        global $USER, $CFG;
        $this->resetAfterTest();

        $this->setAdminUser();

        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $page = $generator->get_plugin_generator('mod_page')->create_instance(
            ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);

        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($page->cmid);
        $info = new mock_info($course, $USER->id);

        // Simulate loading the availability structure as it is actually stored in the
        // database: a JSON string decoded back into an object, where ->e is a JSON
        // string value (e.g. "1" or "0"), exactly as submitted by the editing form.
        $completetree = new tree(json_decode('{"op":"|","show":true,"c":[{"type":"othercompleted","cm":'
            . (int)$course->id . ',"e":"' . COMPLETION_COMPLETE . '"}]}'));
        $incompletetree = new tree(json_decode('{"op":"|","show":true,"c":[{"type":"othercompleted","cm":'
            . (int)$course->id . ',"e":"' . COMPLETION_INCOMPLETE . '"}]}'));

        // Before completion: "complete" should be unavailable, "incomplete" available.
        $this->assertFalse($completetree->check_available(false, $info, true, $USER->id)->is_available());
        $this->assertTrue($incompletetree->check_available(false, $info, true, $USER->id)->is_available());

        $criteriadata = new stdClass();
        $criteriadata->id = $course->id;
        $criteriadata->criteria_self = 1;
        $criterion = new completion_criteria_self();
        $criterion->update_config($criteriadata);

        $ccompletion = new completion_completion(array('course' => $course->id, 'userid' => $USER->id));
        $ccompletion->mark_complete();

        // After completion: results must flip, never match each other.
        $this->assertTrue($completetree->check_available(false, $info, true, $USER->id)->is_available());
        $this->assertFalse($incompletetree->check_available(false, $info, true, $USER->id)->is_available());
    }

    /**
     * Tests that is_available() honours the tree's NOT flag, as happens when this
     * condition is nested inside a "None of the following" restriction set
     * (tree op "!&"/"!|"). Regression test: previously $not was accepted but
     * silently ignored, so the enforced access did not match the restriction
     * description shown to users (which does honour $not via get_description()).
     */
    public function test_not_flag_is_honoured() {
        global $USER, $CFG;
        $this->resetAfterTest();

        $this->setAdminUser();

        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $page = $generator->get_plugin_generator('mod_page')->create_instance(
            ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);

        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($page->cmid);
        $info = new mock_info($course, $USER->id);

        // "None of the following" restriction set (op "!&") wrapping a single
        // "must be marked complete" condition, as produced by the restrict access UI.
        $nottree = new tree(json_decode('{"op":"!&","show":true,"c":[{"type":"othercompleted","cm":'
            . (int)$course->id . ',"e":"' . COMPLETION_COMPLETE . '"}]}'));

        // Before completion: NOT(complete) for an incomplete user should be available.
        $this->assertTrue($nottree->check_available(false, $info, true, $USER->id)->is_available());

        $criteriadata = new stdClass();
        $criteriadata->id = $course->id;
        $criteriadata->criteria_self = 1;
        $criterion = new completion_criteria_self();
        $criterion->update_config($criteriadata);

        $ccompletion = new completion_completion(array('course' => $course->id, 'userid' => $USER->id));
        $ccompletion->mark_complete();

        // After completion: NOT(complete) for a now-complete user should be unavailable.
        $this->assertFalse($nottree->check_available(false, $info, true, $USER->id)->is_available());
    }

    /**
     * Tests the constructor including error conditions. Also tests the
     * string conversion feature (intended for debugging only).
     */
    public function test_constructor() {
        // No parameters.
        $structure = new stdClass();
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertStringContainsString('Missing or invalid ->cm', $e->getMessage());
        }

        // Invalid $cm.
        $structure->cm = 'hello';
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertStringContainsString('Missing or invalid ->cm', $e->getMessage());
        }

        // Missing $e.
        $structure->cm = 42;
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertStringContainsString('Missing or invalid ->e', $e->getMessage());
        }

        // Invalid $e.
        $structure->e = 99;
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertStringContainsString('Missing or invalid ->e', $e->getMessage());
        }

        // Successful construct & display with all different expected values.
        $structure->e = COMPLETION_COMPLETE;
        $cond = new condition($structure);
        $this->assertEquals('{othercompleted:cm42 COMPLETE}', (string)$cond);
    }

    /**
     * Tests the save() function.
     */
    public function test_save() {
        $structure = (object)['cm' => 42, 'e' => COMPLETION_COMPLETE];
        $cond = new condition($structure);
        $structure->type = 'othercompleted';
        $this->assertEquals($structure, $cond->save());
    }

    /**
     * Tests the is_available and get_description functions.
     */
    public function test_usage() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();

        // Create course with completion turned on.
        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id);
        $this->setUser($user);

        // Create a Page with manual completion for basic checks.
        $page = $generator->get_plugin_generator('mod_page')->create_instance(
            [
                'course'         => $course->id,
                'name'           => 'Page!',
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);

        // Create an assignment - we need to have something that can be graded
        // so as to test the PASS/FAIL states. Set it up to be completed based
        // on its grade item.
        $assignrow = $this->getDataGenerator()->create_module('assign', [
            'course'         => $course->id,
            'name'           => 'Assign!',
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);
        $DB->set_field('course_modules', 'completiongradeitemnumber', 0,
                       ['id' => $assignrow->cmid]);
        $assign = new assign(context_module::instance($assignrow->cmid), false, false);

        // Get basic details.
        $modinfo = get_fast_modinfo($course);
        $pagecm = $modinfo->get_cm($page->cmid);
        $assigncm = $assign->get_course_module();
        $info = new mock_info($course, $user->id);

        // COMPLETE state (false), positive and NOT.
        $cond = new condition((object)[
            'cm' => (int)$pagecm->id,
            'e'  => COMPLETION_COMPLETE,
        ]);
        $information = $cond->get_description(false, false, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertMatchesRegularExpression('~You have completed course.*~', $information);

        // COMPLETE state (true).
        $cond = new condition((object)[
            'cm' => (int)$pagecm->id,
            'e'  => COMPLETION_COMPLETE,
        ]);
        $information = $cond->get_description(false, true, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertMatchesRegularExpression('~You have incompleted course.*~', $information);

    }

    /**
     * Tests completion_value_used static function.
     */
    public function test_completion_value_used() {
        global $CFG, $DB;
        $this->resetAfterTest();

        // Create course with completion turned on and some sections.
        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
            ['numsections' => 1, 'enablecompletion' => 1],
            ['createsections' => true]);
        availability_othercompleted\condition::wipe_static_cache();

        // Create three pages with manual completion.
        $page1 = $generator->get_plugin_generator('mod_page')->create_instance(
            ['course' => $course->id, 'othercompleted' => COMPLETION_TRACKING_MANUAL]);
        $page2 = $generator->get_plugin_generator('mod_page')->create_instance(
            ['course' => $course->id, 'othercompleted' => COMPLETION_TRACKING_MANUAL]);
        $page3 = $generator->get_plugin_generator('mod_page')->create_instance(
            ['course' => $course->id, 'othercompleted' => COMPLETION_TRACKING_MANUAL]);

        // Set up page3 to depend on page1, and section1 to depend on page2.
        $DB->set_field('course_modules', 'availability',
                       '{"op":"|","show":true,"c":[' .
                       '{"type":"othercompleted","e":1,"cm":' . $page1->cmid . '}]}',
                       ['id' => $page3->cmid]);
        $DB->set_field('course_sections', 'availability',
                       '{"op":"|","show":true,"c":[' .
                       '{"type":"othercompleted","e":1,"cm":' . $page2->cmid . '}]}',
                       ['course' => $course->id, 'section' => 1]);

        // Now check: nothing depends on page3 but something does on the others.
        $this->assertTrue(availability_othercompleted\condition::completion_value_used(
            $course, $page1->cmid));
        $this->assertTrue(availability_othercompleted\condition::completion_value_used(
            $course, $page2->cmid));
        $this->assertFalse(availability_othercompleted\condition::completion_value_used(
            $course, $page3->cmid));
    }

    /**
     * Tests the update_dependency_id() function.
     */
    public function test_update_dependency_id() {
        $cond = new condition((object)[
            'cm' => 123,
            'e'  => COMPLETION_COMPLETE,
        ]);
        $this->assertFalse($cond->update_dependency_id('frogs', 123, 456));
        $this->assertFalse($cond->update_dependency_id('course_modules', 12, 34));
        $this->assertTrue($cond->update_dependency_id('course_modules', 123, 456));
        $after = $cond->save();
        $this->assertEquals(456, $after->cm);
    }
}
