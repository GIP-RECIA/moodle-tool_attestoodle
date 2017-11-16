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
 * trainings used by Attestoodle
 *
 * @package    block_attestoodle
 * @copyright  2017 Pole de Ressource Numerique de l'Université du Mans
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attestoodle\factories;

use block_attestoodle\utils\singleton;
use block_attestoodle\utils\db_accessor;
use block_attestoodle\training;

defined('MOODLE_INTERNAL') || die;

class training_factory extends singleton {
    /** @var training_factory Instance of the training_factory singleton */
    protected static $instance;

    /** @var array Array containing all the trainings */
    private $array_trainings;

    protected function __construct() {
        parent::__construct();
        $this->array_trainings = array();
    }

    /**
     * Create a training from a Moodle request standard object, add it
     * to the array then return it
     *
     * @param stdClass $dbTraining Standard object from the Moodle request
     * @return training The training added in the array
     */
    private function create($dbTraining) {
        $id = $dbTraining->id;
        $name = $dbTraining->name;
        $desc = $dbTraining->description;

        $trainingToAdd = new training($id, $name, $desc);
        $this->array_trainings[] = $trainingToAdd;

        return $trainingToAdd;
    }

    public function create_trainings() {
        $dbTrainings = db_accessor::get_instance()->get_all_trainings();
        foreach ($dbTrainings as $training) {
            $this->create($training);
        }
    }

    public function get_trainings() {
        return $this->array_trainings;
    }

    public function get_trainings_as_stdClass() {
        return array_map(function($o) {
                return $o->get_object_as_stdClass();
            }, $this->array_trainings);
    }
}

