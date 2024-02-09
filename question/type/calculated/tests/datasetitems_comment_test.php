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

namespace qtype_calculated;

use qtype_calculated;
use question_bank;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/calculated/questiontype.php');
require_once($CFG->dirroot . '/question/type/calculated/tests/helper.php');

/**
 * Unit tests for question/type/calculated/questiontype.php.
 *
 * @package    qtype_calculated
 * @copyright  2024 University College London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \question_type
 * @covers \qtype_calculated
 */
class datasetitems_comment_test extends \advanced_testcase {

    /**
     * Set up.
     *
     * @return void
     */
    protected function setUp(): void {
        $this->qtype = new qtype_calculated();
    }

    /**
     * Tear down.
     *
     * @return void
     */
    protected function tearDown(): void {
        $this->qtype = null;
    }

    /**
     * Check that comments on answers do not contain 'ERROR'.
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_comment_on_datasetitems(): void {
        global $DB;

        $this->resetAfterTest();

        // Create a question.
        $q = \test_question_maker::get_question_data('calculated', 'mult');
        $q->id = 99;

        // Add units for the question. The issue only applies if the answer contains a unit string.
        $units = [];
        $unit = new \stdClass();
        $unit->question = $q->id;
        $unit->multiplier = 1.0;
        $unit->unit = "cm";
        $units[] = $unit;
        $DB->insert_records("question_numerical_units", $units);

        $qtypeobj = question_bank::get_qtype($this->qtype->name());
        $fakedata = ["a" => "5.7", "b" => "3.3"];

        $result = $this->qtype->comment_on_datasetitems($qtypeobj, $q->id, $q->questiontext, $q->options->answers, $fakedata, 1);

        // Make sure "ERROR" is not part of the answers.
        foreach ($result->stranswers as $answer) {
            $this->assertFalse(strstr($answer, "ERROR"), "Assert that 'ERROR' is not part of the answer!");
        }
    }
}
