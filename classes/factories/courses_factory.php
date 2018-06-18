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
 * This is the class that implements the pattern Factory to create the
 * courses used by Attestoodle
 *
 * @package    block_attestoodle
 * @copyright  2018 Pole de Ressource Numerique de l'Université du Mans
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attestoodle\factories;

use block_attestoodle\utils\singleton;
use block_attestoodle\utils\db_accessor;
use block_attestoodle\course;

defined('MOODLE_INTERNAL') || die;

class courses_factory extends singleton {
    /** @var courses_factory Instance of the courses_factory singleton */
    protected static $instance;

    /**
     * Create a course from a Moodle request standard object, add it
     * to the array then return it
     *
     * @param stdClass $dbcourse Standard object from the Moodle request
     * @return course The course newly created
     */
    private function create($dbcourse) {
        $id = $dbcourse->id;
        $name = $dbcourse->fullname;

        $coursetoadd = new course($id, $name);

        // TODO: adding activities one by one seems stupid.
        // Retrieve the activities of the course being created.
        $activities = activities_factory::get_instance()->retrieve_activities_by_course($id);

        foreach ($activities as $activity) {
            $coursetoadd->add_activity($activity);
        }

        // TODO: adding learners one by one with ->add_learner method seems stupid.
        // Retrieve the learners registered to the course being created.
        $learners = learners_factory::get_instance()->retrieve_learners_by_course($id);
        $coursetoadd->set_learners($learners);

        return $coursetoadd;
    }

    /**
     * Function that retrieves the courses corresponding to a specific training
     *
     * @param integer $id Id of the training to search courses for
     * @return course[] Array containing the courses objects
     */
    public function retrieve_courses_by_training($id) {
        $dbcourses = db_accessor::get_instance()->get_courses_by_training($id);
        $courses = array();
        foreach ($dbcourses as $course) {
            $courses[] = $this->create($course);
        }
        return $courses;
    }
}
