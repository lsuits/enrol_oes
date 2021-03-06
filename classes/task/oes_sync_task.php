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
 * Scheduled task for processing Open Enroll System enrolments.
 *
 * @package    enrol_oes
 * @copyright  2017, Louisiana State University
 * @copyright  2017, Chad Mazilly, Robert Russo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_oes\task;

defined('MOODLE_INTERNAL') || die;

class oes_sync_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return \enrol_oes_string::display('sync_taskname');
    }

    /**
     * Do the job.
     * 
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        if (!enrol_is_enabled('oes')) {
            return;
        }

        $plugin = \enrol_oes_plugin_instance::make();

        $response = $plugin->just_do_it(new null_progress_trace());
        
        return $response;
    }

}
