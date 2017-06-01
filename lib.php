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
 * Open Enrollment System enrolment plugin
 *
 * @package    enrol_oes
 * @copyright  2017, Louisiana State University
 * @copyright  2017, Chad Mazilly, Robert Russo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \enrol_oes\traits\plugin\has_plugin_presentation;
use \enrol_oes\traits\plugin\has_plugin_config;
use \enrol_oes\traits\plugin\has_plugin_authorization;
use \enrol_oes\traits\plugin\has_plugin_events;
use \enrol_oes\traits\plugin\has_plugin_validation;
use \enrol_oes\traits\plugin\has_plugin_instance;
use \enrol_oes\traits\plugin\has_plugin_info;
use \enrol_oes\traits\plugin\expires_enrollments;
use \enrol_oes\traits\plugin\restores_enrollments;
use \enrol_oes_oes_driver as oes_driver;
use \enrol_oes\exceptions\oes_exception;

class enrol_oes_plugin extends enrol_plugin {
    
    use has_plugin_presentation,
        has_plugin_config,
        has_plugin_authorization,
        has_plugin_events,
        has_plugin_validation,
        has_plugin_instance,
        has_plugin_info,
        expires_enrollments,
        restores_enrollments;

    protected $config = null;

    public $trace;

    /**
     * Setter for progress trace output
     * 
     * @param progress_trace $trace
     * @return void
     */
    private function set_trace($trace) {
        $this->trace = new combined_progress_trace(array($trace, new progress_trace_buffer(new text_progress_trace(), false)));
    }

    /**
     * Returns the configured OES driver object
     * 
     * @return oes_driver
     */
    public function get_oes_driver()
    {
        $driver_name = $this->get_oes_driver_name();

        return \enrol_oes_oes_driver::make($this, $driver_name);
    }

    /**
     * Returns the configured OES driver key name
     * 
     * @return string
     */
    public function get_oes_driver_name()
    {
        // @TODO: make this configurable!
        return 'tiger';
    }

    /**
     * Returns name of this enrol plugin
     * @return string
     */
    public function get_name() {
        // second word in class is always enrol name, sorry, no fancy plugin names with _
        $words = explode('_', get_class($this));
        return $words[1];
    }

    /**
     * Returns localised name of enrol instance
     *
     * @param object $instance (null is accepted too)
     * @return string
     */
    public function get_instance_name($instance) {
        if (empty($instance->name)) {
            $enrol = $this->get_name();
            return get_string('pluginname', 'enrol_'.$enrol);
        } else {
            $context = context_course::instance($instance->courseid);
            return format_string($instance->name, true, array('context'=>$context));
        }
    }

    ///////////////////////////////////////
    ///////////////////////////////////////
    ///
    ///   OES DATA IMPORT API
    ///  
    ///////////////////////////////////////
    ///////////////////////////////////////

    /**
     * Imports data from a given type of source
     * 
     * @param progress_trace $trace
     * @return void
     */
    public function sync_enrollment(progress_trace $trace)
    {
        // instantiate trace output
        $this->set_trace($trace);

        try {
            
            // first, check that enrollment is enabled
            if ( ! enrol_is_enabled('oes'))
                throw new oes_exception('OES plugin is disabled.');

            // get the configured OES driver
            $oes_driver = $this->get_oes_driver();

            // bootstrap import class, fire import process, report progress
            $import = new \enrol_oes\import\import($this, $oes_driver);
            $import->handle();

            // bootstrap manifest class, fire manifest process, report progress
            $manifest = new \enrol_oes\manifest\manifest($this, $oes_driver);
            $manifest->handle();
            
            $this->trace->output('Enrollment sync completed successfully!');

        } catch (oes_exception $e) {
            
            // return error response on failure
            $this->trace->output($e->getMessage());

            $this->trace->output('Enrollment sync failed!');
        }
    }

    ///////

    /**
     * BASE-NOT-IMPLEMENTED
     * 
     * Forces synchronisation of user enrolments.
     *
     * This is important especially for external enrol plugins,
     * this function is called for all enabled enrol plugins
     * right after every user login.
     *
     * @param object $user user record
     * @return void
     */
    public function sync_user_enrolments($user) {
        // override if necessary
    }

    /**
     * BASE-NOT-IMPLEMENTED
     * 
     * Enrol user into course via enrol instance.
     *
     * 
     *
     * @param stdClass $instance
     * @param int $userid
     * @param int $roleid optional role id
     * @param int $timestart 0 means unknown
     * @param int $timeend 0 means forever
     * @param int $status default to ENROL_USER_ACTIVE for new enrolments, no change by default in updates
     * @param bool $recovergrades restore grade history
     * @return void
     */
    public function enrol_user(stdClass $instance, $userid, $roleid = null, $timestart = 0, $timeend = 0, $status = null, $recovergrades = null) {
        global $DB, $USER, $CFG; // CFG necessary!!!

        // check for valid conditions

        // contains course instance?
        if ($instance->courseid == SITEID) {
            throw new coding_exception('invalid attempt to enrol into frontpage course!');
        }

        $name = $this->get_name();
        $courseid = $instance->courseid;

        // enrol instance is ok?
        if ($instance->enrol !== $name) {
            throw new coding_exception('invalid enrol instance!');
        }
        
        $context = context_course::instance($instance->courseid, MUST_EXIST);

        $inserted = false;
        $updated  = false;
        if ($ue = $DB->get_record('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$userid))) {
            //only update if timestart or timeend or status are different.
            if ($ue->timestart != $timestart or $ue->timeend != $timeend or (!is_null($status) and $ue->status != $status)) {
                $this->update_user_enrol($instance, $userid, $status, $timestart, $timeend);
            }
        } else {
            $ue = new stdClass();
            $ue->enrolid      = $instance->id;
            $ue->status       = is_null($status) ? ENROL_USER_ACTIVE : $status;
            $ue->userid       = $userid;
            $ue->timestart    = $timestart;
            $ue->timeend      = $timeend;
            $ue->modifierid   = $USER->id;
            $ue->timecreated  = time();
            $ue->timemodified = $ue->timecreated;
            $ue->id = $DB->insert_record('user_enrolments', $ue);

            $inserted = true;
        }

        if ($inserted) {
            // Trigger event.
            $event = \core\event\user_enrolment_created::create(
                    array(
                        'objectid' => $ue->id,
                        'courseid' => $courseid,
                        'context' => $context,
                        'relateduserid' => $ue->userid,
                        'other' => array('enrol' => $name)
                        )
                    );
            $event->trigger();
            // Check if course contacts cache needs to be cleared.
            require_once($CFG->libdir . '/coursecatlib.php');
            coursecat::user_enrolment_changed($courseid, $ue->userid,
                    $ue->status, $ue->timestart, $ue->timeend);
        }

        if ($roleid) {
            // this must be done after the enrolment event so that the role_assigned event is triggered afterwards
            if ($this->roles_protected()) {
                role_assign($roleid, $userid, $context->id, 'enrol_'.$name, $instance->id);
            } else {
                role_assign($roleid, $userid, $context->id);
            }
        }

        // Recover old grades if present.
        if (!isset($recovergrades)) {
            $recovergrades = $CFG->recovergradesdefault;
        }
        
        if ($recovergrades) {
            require_once("$CFG->libdir/gradelib.php");
            grade_recover_history_grades($userid, $courseid);
        }

        // reset current user enrolment caching
        if ($userid == $USER->id) {
            if (isset($USER->enrol['enrolled'][$courseid])) {
                unset($USER->enrol['enrolled'][$courseid]);
            }
            if (isset($USER->enrol['tempguest'][$courseid])) {
                unset($USER->enrol['tempguest'][$courseid]);
                remove_temp_course_roles($context);
            }
        }
    }

    /**
     * BASE-NOT-IMPLEMENTED
     * 
     * Store user_enrolments changes and trigger event.
     *
     * @param stdClass $instance
     * @param int $userid
     * @param int $status
     * @param int $timestart
     * @param int $timeend
     * @return void
     */
    public function update_user_enrol(stdClass $instance, $userid, $status = NULL, $timestart = NULL, $timeend = NULL) {
        global $DB, $USER, $CFG;

        $name = $this->get_name();

        if ($instance->enrol !== $name) {
            throw new coding_exception('invalid enrol instance!');
        }

        if (!$ue = $DB->get_record('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$userid))) {
            // weird, user not enrolled
            return;
        }

        $modified = false;
        if (isset($status) and $ue->status != $status) {
            $ue->status = $status;
            $modified = true;
        }
        if (isset($timestart) and $ue->timestart != $timestart) {
            $ue->timestart = $timestart;
            $modified = true;
        }
        if (isset($timeend) and $ue->timeend != $timeend) {
            $ue->timeend = $timeend;
            $modified = true;
        }

        if (!$modified) {
            // no change
            return;
        }

        $ue->modifierid = $USER->id;
        $DB->update_record('user_enrolments', $ue);
        context_course::instance($instance->courseid)->mark_dirty(); // reset enrol caches

        // Invalidate core_access cache for get_suspended_userids.
        cache_helper::invalidate_by_definition('core', 'suspended_userids', array(), array($instance->courseid));

        // Trigger event.
        $event = \core\event\user_enrolment_updated::create(
                array(
                    'objectid' => $ue->id,
                    'courseid' => $instance->courseid,
                    'context' => context_course::instance($instance->courseid),
                    'relateduserid' => $ue->userid,
                    'other' => array('enrol' => $name)
                    )
                );
        $event->trigger();

        require_once($CFG->libdir . '/coursecatlib.php');
        coursecat::user_enrolment_changed($instance->courseid, $ue->userid,
                $ue->status, $ue->timestart, $ue->timeend);
    }

    /**
     * BASE-NOT-IMPLEMENTED
     * 
     * Unenrol user from course,
     * the last unenrolment removes all remaining roles.
     *
     * @param stdClass $instance
     * @param int $userid
     * @return void
     */
    public function unenrol_user(stdClass $instance, $userid) {
        global $CFG, $USER, $DB;
        require_once("$CFG->dirroot/group/lib.php");

        $name = $this->get_name();
        $courseid = $instance->courseid;

        if ($instance->enrol !== $name) {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid, MUST_EXIST);

        if (!$ue = $DB->get_record('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$userid))) {
            // weird, user not enrolled
            return;
        }

        // Remove all users groups linked to this enrolment instance.
        if ($gms = $DB->get_records('groups_members', array('userid'=>$userid, 'component'=>'enrol_'.$name, 'itemid'=>$instance->id))) {
            foreach ($gms as $gm) {
                groups_remove_member($gm->groupid, $gm->userid);
            }
        }

        role_unassign_all(array('userid'=>$userid, 'contextid'=>$context->id, 'component'=>'enrol_'.$name, 'itemid'=>$instance->id));
        $DB->delete_records('user_enrolments', array('id'=>$ue->id));

        // add extra info and trigger event
        $ue->courseid  = $courseid;
        $ue->enrol     = $name;

        $sql = "SELECT 'x'
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON (e.id = ue.enrolid)
                 WHERE ue.userid = :userid AND e.courseid = :courseid";
        if ($DB->record_exists_sql($sql, array('userid'=>$userid, 'courseid'=>$courseid))) {
            $ue->lastenrol = false;

        } else {
            // the big cleanup IS necessary!
            require_once("$CFG->libdir/gradelib.php");

            // remove all remaining roles
            role_unassign_all(array('userid'=>$userid, 'contextid'=>$context->id), true, false);

            //clean up ALL invisible user data from course if this is the last enrolment - groups, grades, etc.
            groups_delete_group_members($courseid, $userid);

            grade_user_unenrol($courseid, $userid);

            $DB->delete_records('user_lastaccess', array('userid'=>$userid, 'courseid'=>$courseid));

            $ue->lastenrol = true; // means user not enrolled any more
        }
        // Trigger event.
        $event = \core\event\user_enrolment_deleted::create(
                array(
                    'courseid' => $courseid,
                    'context' => $context,
                    'relateduserid' => $ue->userid,
                    'objectid' => $ue->id,
                    'other' => array(
                        'userenrolment' => (array)$ue,
                        'enrol' => $name
                        )
                    )
                );
        $event->trigger();
        // reset all enrol caches
        $context->mark_dirty();

        // Check if courrse contacts cache needs to be cleared.
        require_once($CFG->libdir . '/coursecatlib.php');
        coursecat::user_enrolment_changed($courseid, $ue->userid, ENROL_USER_SUSPENDED);

        // reset current user enrolment caching
        if ($userid == $USER->id) {
            if (isset($USER->enrol['enrolled'][$courseid])) {
                unset($USER->enrol['enrolled'][$courseid]);
            }
            if (isset($USER->enrol['tempguest'][$courseid])) {
                unset($USER->enrol['tempguest'][$courseid]);
                remove_temp_course_roles($context);
            }
        }
    }

    /**
     * BASE-NOT-IMPLEMENTED
     * 
     * Attempt to automatically enrol current user in course without any interaction,
     * calling code has to make sure the plugin and instance are active.
     *
     * This should return either a timestamp in the future or false.
     *
     * @param stdClass $instance course enrol instance
     * @return bool|int false means not enrolled, integer means timeend
     */
    public function try_autoenrol(stdClass $instance) {
        global $USER;

        return false;
    }

    /**
     * BASE-NOT-IMPLEMENTED
     * 
     * Attempt to automatically gain temporary guest access to course,
     * calling code has to make sure the plugin and instance are active.
     *
     * This should return either a timestamp in the future or false.
     *
     * @param stdClass $instance course enrol instance
     * @return bool|int false means no guest access, integer means timeend
     */
    public function try_guestaccess(stdClass $instance) {
        global $USER;

        return false;
    }

    /**
     * BASE-NOT-IMPLEMENTED
     * 
     * Returns link to manual enrol UI if exists.
     * Does the access control tests automatically.
     *
     * @param object $instance
     * @return moodle_url
     */
    public function get_manual_enrol_link($instance) {
        return NULL;
    }

    /**
     * BASE-NOT-IMPLEMENTED
     * 
     * Returns list of unenrol links for all enrol instances in course.
     *
     * @param int $instance
     * @return moodle_url or NULL if self unenrolment not supported
     */
    public function get_unenrolself_link($instance) {
        global $USER, $CFG, $DB;

        $name = $this->get_name();
        if ($instance->enrol !== $name) {
            throw new coding_exception('invalid enrol instance!');
        }

        if ($instance->courseid == SITEID) {
            return NULL;
        }

        if (!enrol_is_enabled($name)) {
            return NULL;
        }

        if ($instance->status != ENROL_INSTANCE_ENABLED) {
            return NULL;
        }

        if (!file_exists("$CFG->dirroot/enrol/$name/unenrolself.php")) {
            return NULL;
        }

        $context = context_course::instance($instance->courseid, MUST_EXIST);

        if (!has_capability("enrol/$name:unenrolself", $context)) {
            return NULL;
        }

        if (!$DB->record_exists('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$USER->id, 'status'=>ENROL_USER_ACTIVE))) {
            return NULL;
        }

        return new moodle_url("/enrol/$name/unenrolself.php", array('enrolid'=>$instance->id));
    }

}