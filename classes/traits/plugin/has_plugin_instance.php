<?php

namespace enrol_oes\traits\plugin;

/*
  Moodle enrol plugin base "instance"-based methods
 */
trait has_plugin_instance {

    /**
     * Returns defaults for new instances.
     * @since Moodle 3.1
     * @return array
     */
    public function get_instance_defaults() {
        return array();
    }
    
    /**
     * Return information for enrolment instance containing list of parameters required
     * for enrolment, name of enrolment plugin etc.
     *
     * @param stdClass $instance enrolment instance
     * @return array instance info.
     */
    public function get_enrol_info(stdClass $instance) {
        return null;
    }

    /**
     * Return whether or not, given the current state, it is possible to add a new instance
     * of this enrolment plugin to the course.
     *
     * Default implementation is just for backwards compatibility.
     *
     * @param int $courseid
     * @return boolean
     */
    public function can_add_instance($courseid) {
        $link = $this->get_newinstance_link($courseid);
        return !empty($link);
    }

    /**
     * Return whether or not, given the current state, it is possible to edit an instance
     * of this enrolment plugin in the course. Used by the standard editing UI
     * to generate a link to the edit instance form if editing is allowed.
     *
     * @param stdClass $instance
     * @return boolean
     */
    public function can_edit_instance($instance) {
        $context = context_course::instance($instance->courseid);

        return has_capability('enrol/' . $instance->enrol . ':config', $context);
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        // override for most plugins, check if instance already exists in cases only one instance is supported
        return NULL;
    }

    /**
     * @deprecated since Moodle 2.8 MDL-35864 - please use can_delete_instance() instead.
     */
    public function instance_deleteable($instance) {
        throw new coding_exception('Function enrol_plugin::instance_deleteable() is deprecated, use
                enrol_plugin::can_delete_instance() instead');
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass  $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        return false;
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        debugging("The enrolment plugin '".$this->get_name()."' should override the function can_hide_show_instance().", DEBUG_DEVELOPER);
        return true;
    }

    /**
     * Add new instance of enrol plugin.
     * @param object $course
     * @param array instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_instance($course, array $fields = NULL) {
        global $DB;

        if ($course->id == SITEID) {
            throw new coding_exception('Invalid request to add enrol instance to frontpage.');
        }

        $instance = new stdClass();
        $instance->enrol          = $this->get_name();
        $instance->status         = ENROL_INSTANCE_ENABLED;
        $instance->courseid       = $course->id;
        $instance->enrolstartdate = 0;
        $instance->enrolenddate   = 0;
        $instance->timemodified   = time();
        $instance->timecreated    = $instance->timemodified;
        $instance->sortorder      = $DB->get_field('enrol', 'COALESCE(MAX(sortorder), -1) + 1', array('courseid'=>$course->id));

        $fields = (array)$fields;
        unset($fields['enrol']);
        unset($fields['courseid']);
        unset($fields['sortorder']);
        foreach($fields as $field=>$value) {
            $instance->$field = $value;
        }

        $instance->id = $DB->insert_record('enrol', $instance);

        \core\event\enrol_instance_created::create_from_record($instance)->trigger();

        return $instance->id;
    }

    /**
     * Update instance of enrol plugin.
     *
     * @since Moodle 3.1
     * @param stdClass $instance
     * @param stdClass $data modified instance fields
     * @return boolean
     */
    public function update_instance($instance, $data) {
        global $DB;
        $properties = array('status', 'name', 'password', 'customint1', 'customint2', 'customint3',
                            'customint4', 'customint5', 'customint6', 'customint7', 'customint8',
                            'customchar1', 'customchar2', 'customchar3', 'customdec1', 'customdec2',
                            'customtext1', 'customtext2', 'customtext3', 'customtext4', 'roleid',
                            'enrolperiod', 'expirynotify', 'notifyall', 'expirythreshold',
                            'enrolstartdate', 'enrolenddate', 'cost', 'currency');

        foreach ($properties as $key) {
            if (isset($data->$key)) {
                $instance->$key = $data->$key;
            }
        }
        $instance->timemodified = time();

        $update = $DB->update_record('enrol', $instance);
        if ($update) {
            \core\event\enrol_instance_updated::create_from_record($instance)->trigger();
        }
        return $update;
    }

    /**
     * Add new instance of enrol plugin with default settings,
     * called when adding new instance manually or when adding new course.
     *
     * Not all plugins support this.
     *
     * @param object $course
     * @return int id of new instance or null if no default supported
     */
    public function add_default_instance($course) {
        return null;
    }

    /**
     * Update instance status
     *
     * Override when plugin needs to do some action when enabled or disabled.
     *
     * @param stdClass $instance
     * @param int $newstatus ENROL_INSTANCE_ENABLED, ENROL_INSTANCE_DISABLED
     * @return void
     */
    public function update_status($instance, $newstatus) {
        global $DB;

        $instance->status = $newstatus;
        $DB->update_record('enrol', $instance);

        $context = context_course::instance($instance->courseid);
        \core\event\enrol_instance_updated::create_from_record($instance)->trigger();

        // Invalidate all enrol caches.
        $context->mark_dirty();
    }

    /**
     * Delete course enrol plugin instance, unenrol all users.
     * @param object $instance
     * @return void
     */
    public function delete_instance($instance) {
        global $DB;

        $name = $this->get_name();
        if ($instance->enrol !== $name) {
            throw new coding_exception('invalid enrol instance!');
        }

        //first unenrol all users
        $participants = $DB->get_recordset('user_enrolments', array('enrolid'=>$instance->id));
        foreach ($participants as $participant) {
            $this->unenrol_user($instance, $participant->userid);
        }
        $participants->close();

        // now clean up all remainders that were not removed correctly
        $DB->delete_records('groups_members', array('itemid'=>$instance->id, 'component'=>'enrol_'.$name));
        $DB->delete_records('role_assignments', array('itemid'=>$instance->id, 'component'=>'enrol_'.$name));
        $DB->delete_records('user_enrolments', array('enrolid'=>$instance->id));

        // finally drop the enrol row
        $DB->delete_records('enrol', array('id'=>$instance->id));

        $context = context_course::instance($instance->courseid);
        \core\event\enrol_instance_deleted::create_from_record($instance)->trigger();

        // Invalidate all enrol caches.
        $context->mark_dirty();
    }

}