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
 * Provides the class attestoodle\privacy\provider.
 *
 * @package    tool_attestoodle
 * @copyright  2019 Pole de Ressource Numerique de l'Universite du Mans
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_attestoodle\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy API implementation for Attestoodle.
 *
 * @copyright 2018 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

     /**
      * Describe all the places where the Attestoodle tool stores some personal data.
      *
      * @param collection $collection Collection of items to add metadata to.
      * @return collection Collection with our added items.
      */
    public static function get_metadata(collection $collection) : collection {
        // Filesystem Certificate pdf generated.
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');

        // Tables where user data is stored.
        $collection->add_database_table('tool_attestoodle_launch_log',
            [
                'operatorid' => 'privacy:metadata:tool_attestoodle_launch_log:operatorid',
                'timegenerated' => 'privacy:metadata:tool_attestoodle_launch_log:timegenerated',
                'begindate' => 'privacy:metadata:tool_attestoodle_launch_log:begindate',
                'enddate' => 'privacy:metadata:tool_attestoodle_launch_log:enddate',
            ],
            'privacy:metadata:tool_attestoodle_launch_log'
        );

        $collection->add_database_table('tool_attestoodle_certif_log',
            [
                'learnerid' => 'privacy:metadata:tool_attestoodle_certif_log:learnerid',
                'filename' => 'privacy:metadata:tool_attestoodle_certif_log:filename',
            ],
            'privacy:metadata:tool_attestoodle_certif_log'
        );

        $collection->add_database_table('tool_attestoodle_value_log',
            [
                'moduleid' => 'privacy:metadata:tool_attestoodle_value_log:moduleid',
                'creditedtime' => 'privacy:metadata:tool_attestoodle_value_log:creditedtime',
            ],
            'privacy:metadata:tool_attestoodle_value_log'
        );

        $collection->add_database_table('tool_attestoodle_user_style',
            [
                'userid' => 'privacy:metadata:tool_attestoodle_user_style:userid',
                'templateid' => 'privacy:metadata:tool_attestoodle_user_style:templateid',
                'enablecertificate' => 'privacy:metadata:tool_attestoodle_user_style:enablecertificate',
            ],
            'privacy:metadata:tool_attestoodle_user_style'
        );
        return $collection;
    }

    // Methods for \core_privacy\local\request\plugin\provider.

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int           $userid       The user to search.
     * @return  contextlist   $contextlist  The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new contextlist();

        $sqlfile = "SELECT distinct f.contextid
                      FROM {files} f
                INNER JOIN {tool_attestoodle_certif_log} c on c.filename=f.filename
                     WHERE f.component = 'tool_attestoodle'
                     AND c.learnerid = :userid";

        $contextlist->add_from_sql($sqlfile, ['userid' => $userid]);
        return $contextlist;
    }

    /**
     * Delete personal data for the user in a list of contexts.
     *
     * We only consider the deletion of learner data the deletion of attestations
     * generated by the operator is not taken into account because it may generate
     * problems.
     *
     * @param approved_contextlist $contextlist List of contexts to delete data from.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;

        // Delete file record.
        $fs = get_file_storage();
        $usercontext = \context_user::instance($userid);

        $sql = "SELECT distinct filename as filename
                  FROM {tool_attestoodle_certif_log}
                 WHERE learnerid = :userid";
        $params = ['userid' => $userid, ];
        $result = $DB->get_records_sql($sql, $params);

        foreach ($result as $record) {
            $fileinfo = array(
                'contextid' => $usercontext->id,
                'component' => 'tool_attestoodle',
                'filearea' => 'certificates',
                'filepath' => '/',
                'itemid' => 0,
                'filename' => $record->filename
            );
            $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
                $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);
            if ($file) {
                $file->delete();
            }
        }
        // Delete certificate values.
        $request = "delete from {tool_attestoodle_value_log}
                     where certificateid in (select id from {tool_attestoodle_certif_log}
                                               where learnerid = :userid)";
        $DB->execute($request, $params);

        // Delete certificate.
        $DB->delete_records('tool_attestoodle_certif_log', ['learnerid' => $userid]);

        // Delete launch with no detail.
        $sql = "delete from {tool_attestoodle_launch_log}
                 where id not in (select launchid from {tool_attestoodle_certif_log})";
        $result = $DB->execute($sql, array());

        $DB->delete_records('tool_attestoodle_user_style', ['userid' => $userid]);
        $DB->delete_records('tool_attestoodle_learner', ['userid' => $userid]);
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            $subcontext = "";
            if ($context instanceof \context_user) {
                $subcontext = get_string('pluginname', 'tool_attestoodle');
            }
            writer::with_context($context)->export_area_files([$subcontext], 'tool_attestoodle', 'certificates', 0);
        }

        $usercontext = \context_user::instance($userid);
        $sqlrq1 = "select id,trainingid,launchid
                    from {tool_attestoodle_certif_log}
                   where learnerid = :userid";
        $params = ['userid' => $userid, ];
        $result1 = $DB->get_records_sql($sqlrq1, $params);
        $certificate = [];

        foreach ($result1 as $rowcertif) {
            $training = $DB->get_record('tool_attestoodle_training', array('id' => $rowcertif->trainingid));
            $certif = new \stdClass();

            if (empty($training->name)) {
                $categ = $DB->get_record('course_categories', array('id' => $training->categoryid));
                $training->name = $categ->name;
            }
            $certif->training = $training->name;

            $launch = $DB->get_record('tool_attestoodle_launch_log', array('id' => $rowcertif->launchid));

            $period = get_string('fromdate', 'tool_attestoodle', $launch->begindate)
                    . ' ' . get_string('todate', 'tool_attestoodle', $launch->enddate);
            $certif->period = $period;

            $result2 = $DB->get_records('tool_attestoodle_value_log', array('certificateid' => $rowcertif->id));
            $contextdata = [];
            $total = 0;
            foreach ($result2 as $row) {
                $req = "select distinct name as name
                          from {tool_attestoodle_milestone}
                         where moduleid = :moduleid";
                $module = $DB->get_record_sql($req, array('moduleid' => $row->moduleid));
                $name = get_string('error_unknown_item', 'tool_attestoodle');
                if (!empty($module->name)) {
                    $name = $module->name;
                }
                $total += $row->creditedtime;

                $contextdata[] = [
                    'module' => $name,
                    'creditedtime' => parse_minutes_to_hours($row->creditedtime),
                ];
            }
            $certif->total = parse_minutes_to_hours($total);
            $certif->milestones = $contextdata;
            $certificate[] = $certif;
        }

        $datas = new \stdClass();
        $datas->certificates = $certificate;
        $subcontext = get_string('pluginname', 'tool_attestoodle');
        writer::with_context($usercontext)->export_data([$subcontext], $datas);
    }

    /**
     * Delete all personal data for all users in the specified context.
     * //to delete all data for all users in the specified context.
     * @param context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
    }


    // Method for \core_privacy\local\request\core_userlist_provider.
    /**
     * Get the list of users who have data within a context.
     * //to locate the users who hold any personal data in a specific context
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
    }

    /**
     * Delete multiple users within a single context.
     * //to delete data for multiple users in the specified context.
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
    }
}