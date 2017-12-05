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
 * learners used by Attestoodle
 *
 * @package    block_attestoodle
 * @copyright  2017 Pole de Ressource Numerique de l'Université du Mans
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attestoodle\factories;

use block_attestoodle\utils\singleton;
use block_attestoodle\utils\db_accessor;
use block_attestoodle\learner;
use block_attestoodle\validated_activity;

defined('MOODLE_INTERNAL') || die;

class learners_factory extends singleton {
    /** @var learners_factory Instance of the learner_factory singleton */
    protected static $instance;

    /** @var array Array containing all the learners */
    private $learners;

    /**
     * Constructor method
     */
    protected function __construct() {
        parent::__construct();
        $this->learners = array();
    }

    /**
     * Create a learner from a Moodle request standard object, add it
     * to the array then return it
     *
     * @param stdClass $dblearner Standard object from the Moodle request
     * @return learner The learner added in the array
     */
    private function create($dblearner) {
        $id = $dblearner->id;
        $firstname = $dblearner->firstname;
        $lastname = $dblearner->lastname;

        $learnertoadd = new learner($id, $firstname, $lastname);
        $this->retrieve_validated_activities($learnertoadd);

        $this->learners[] = $learnertoadd;

        return $learnertoadd;
    }

    /**
     * Method that retrieves the activities validated by a learner in DB and
     * stores them in the learner
     *
     * @todo The passing by reference may be useless
     *
     * @param learner $learner The learner to search activities for
     */
    private function retrieve_validated_activities(&$learner) {
        $dbactivities = db_accessor::get_instance()->get_activities_validated_by_learner($learner);

        foreach ($dbactivities as $dbactivity) {
            $activity = trainings_factory::get_instance()->retrieve_activity($dbactivity->coursemoduleid);
            if (isset($activity)) {
                $validatedactivity = new validated_activity($activity, $dbactivity->timemodified);
                $learner->add_validated_activity($validatedactivity);
            }
        }
    }

    /**
     * Getter of the $learners property
     *
     * @return array The learners stored in the factory
     */
    public function get_learners() {
        return $this->learners;
    }

    /**
     * Method that checks if a learner exists based on its ID
     *
     * @param string $id Id to search against
     * @return boolean TRUE if the learner exists, FALSE if not
     */
    public function has_learner($id) {
        $t = $this->retrieve_learner($id);
        return isset($t);
    }

    /**
     * Method that retrieve a learner within the list based on an ID
     *
     * @param string $id Id of the learner to search for
     * @return learner The learner retrieved or NULL if no learner
     * has been found
     */
    public function retrieve_learner($id) {
        $learner = null;
        foreach ($this->learners as $l) {
            if ($l->get_id() == $id) {
                $learner = $l;
                break;
            }
        }
        return $learner;
    }

    /**
     * Method that retrieves the learners registered to a specific course
     *
     * @param string $id Id of the course to retrieve learners for
     * @return learners The learners retrieved for the course
     */
    public function retrieve_learners_by_course($id) {
        $learners = array();

        $dblearners = db_accessor::get_instance()->get_learners_by_course($id);
        foreach ($dblearners as $learner) {
            if ($this->has_learner($learner->id)) {
                $learners[] = $this->retrieve_learner($learner->id);
            } else {
                $learners[] = $this->create($learner);
            }
        }

        return $learners;
    }
}
