<?php

namespace enrol_oes\traits\plugin;

/*
  Moodle enrol plugin base "info" methods
 */
trait has_plugin_info {

    /**
     * BASE-NOT-IMPLEMENTED
     * 
     * Reads version.php and determines if it is necessary
     * to execute the cron job now.
     * @return bool
     */
    public function is_cron_required() {
        global $CFG;

        $name = $this->get_name();
        $versionfile = "$CFG->dirroot/enrol/$name/version.php";
        $plugin = new stdClass();
        include($versionfile);
        if (empty($plugin->cron)) {
            return false;
        }
        $lastexecuted = $this->get_config('lastcron', 0);
        if ($lastexecuted + $plugin->cron < time()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * BASE-NOT-IMPLEMENTED
     * 
     * Called for all enabled enrol plugins that returned true from is_cron_required().
     * @return void
     */
    public function cron() {
    }

    /**
     * BASE-NOT-IMPLEMENTED
     * 
     * Returns the user who is responsible for enrolments for given instance.
     *
     * Override if plugin knows anybody better than admin.
     *
     * @param int $instanceid enrolment instance id
     * @return stdClass user record
     */
    protected function get_enroller($instanceid) {
        return get_admin();
    }

    /**
     * BASE-NOT-IMPLEMENTED
     * 
     * Gets an array of the user enrolment actions
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue
     * @return array An array of user_enrolment_actions
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        return array();
    }

    /**
     * BASE-NOT-IMPLEMENTED
     * 
     * Returns true if the plugin has one or more bulk operations that can be performed on
     * user enrolments.
     *
     * @param course_enrolment_manager $manager
     * @return bool
     */
    public function has_bulk_operations(course_enrolment_manager $manager) {
       return false;
    }

    /**
     * BASE-NOT-IMPLEMENTED
     * 
     * Return an array of enrol_bulk_enrolment_operation objects that define
     * the bulk actions that can be performed on user enrolments by the plugin.
     *
     * @param course_enrolment_manager $manager
     * @return array
     */
    public function get_bulk_operations(course_enrolment_manager $manager) {
        return array();
    }

}