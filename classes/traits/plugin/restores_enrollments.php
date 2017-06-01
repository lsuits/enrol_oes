<?php

namespace enrol_oes\traits\plugin;

/*
  Moodle enrol plugin base "restore"-based methods
 */
trait restores_enrollments {

    /**
     * Backup execution step hook to annotate custom fields.
     *
     * @param backup_enrolments_execution_step $step
     * @param stdClass $enrol
     */
    public function backup_annotate_custom_fields(backup_enrolments_execution_step $step, stdClass $enrol) {
        // Override as necessary to annotate custom fields in the enrol table.
    }

    /**
     * Automatic enrol sync executed during restore.
     * Useful for automatic sync by course->idnumber or course category.
     * @param stdClass $course course record
     */
    public function restore_sync_course($course) {
        // Override if necessary.
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        // Do not call this from overridden methods, restore and set new id there.
        $step->set_mapping('enrol', $oldid, 0);
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        // Override as necessary if plugin supports restore of enrolments.
    }

    /**
     * Restore role assignment.
     *
     * @param stdClass $instance
     * @param int $roleid
     * @param int $userid
     * @param int $contextid
     */
    public function restore_role_assignment($instance, $roleid, $userid, $contextid) {
        // No role assignment by default, override if necessary.
    }

    /**
     * Restore user group membership.
     * @param stdClass $instance
     * @param int $groupid
     * @param int $userid
     */
    public function restore_group_member($instance, $groupid, $userid) {
        // Implement if you want to restore protected group memberships,
        // usually this is not necessary because plugins should be able to recreate the memberships automatically.
    }

}