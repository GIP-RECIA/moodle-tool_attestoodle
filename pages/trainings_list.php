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

// Importation de la config $CFG qui importe égalment $DB et $OUTPUT.
// @todo create an autoloader.
require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->dirroot.'/blocks/attestoodle/lib.php');
require_once($CFG->dirroot.'/blocks/attestoodle/classes/factories/trainings_factory.php');
require_once($CFG->dirroot.'/blocks/attestoodle/classes/factories/categories_factory.php');
require_once($CFG->dirroot.'/blocks/attestoodle/classes/factories/courses_factory.php');
require_once($CFG->dirroot.'/blocks/attestoodle/classes/factories/activities_factory.php');
require_once($CFG->dirroot.'/blocks/attestoodle/classes/factories/learners_factory.php');

require_once($CFG->dirroot.'/blocks/attestoodle/classes/training_from_category.php');
require_once($CFG->dirroot.'/blocks/attestoodle/classes/category.php');
require_once($CFG->dirroot.'/blocks/attestoodle/classes/course.php');
require_once($CFG->dirroot.'/blocks/attestoodle/classes/activity.php');
require_once($CFG->dirroot.'/blocks/attestoodle/classes/validated_activity.php');

use block_attestoodle\factories\trainings_factory;
use block_attestoodle\factories\categories_factory;

$PAGE->set_url(new moodle_url('/blocks/attestoodle/pages/trainings_list.php'));
// @todo May be replaced by "require_login(...)"
$PAGE->set_context(context_system::instance());

$PAGE->set_title(get_string('trainings_list_page_title', 'block_attestoodle'));
$PAGE->set_heading(get_string('trainings_list_main_title', 'block_attestoodle'));

echo $OUTPUT->header();

echo html_writer::start_div('clearfix');
// Link to the trainings management page.
echo html_writer::link(
        new moodle_url('/blocks/attestoodle/pages/trainings_management.php'),
        get_string('trainings_list_manage_trainings_link', 'block_attestoodle'),
        array('class' => 'btn btn-default attestoodle-button'));
echo html_writer::end_div();

// Create the trainings.
// trainings_factory::get_instance()->create_trainings();
categories_factory::get_instance()->create_categories();

// Retrieve the trainings.
// $databrut = trainings_factory::get_instance()->get_trainings();
$databrut = trainings_factory::get_instance()->get_trainings_from_categories();

if (count($databrut) > 0) {
    $data = parse_trainings_as_stdclass($databrut);

    $table = new html_table();
    $table->head = array(
        get_string('trainings_list_table_header_column_id', 'block_attestoodle'),
        get_string('trainings_list_table_header_column_name', 'block_attestoodle'),
        get_string('trainings_list_table_header_column_hierarchy', 'block_attestoodle'),
        get_string('trainings_list_table_header_column_description', 'block_attestoodle'),
        '');
    $table->data = $data;

    echo html_writer::table($table);
} else {
    $message = get_string('trainings_list_warning_no_trainings', 'block_attestoodle');
    echo $message;
}

echo $OUTPUT->footer();
