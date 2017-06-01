<?php

namespace enrol_oes\traits\plugin;

/*
  Moodle enrol plugin base "validation"-based methods
 */
trait has_plugin_validation {

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @since Moodle 3.1
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param object $instance The instance data loaded from the DB.
     * @param context $context The context of the instance we are editing
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        // No errors by default.
        debugging('enrol_plugin::edit_instance_validation() is missing. This plugin has no validation!', DEBUG_DEVELOPER);
        return array();
    }

    /**
     * Validates course edit form data
     *
     * @param object $instance enrol instance or null if does not exist yet
     * @param array $data
     * @param object $context context of existing course or parent category if course does not exist
     * @return array errors array
     */
    public function course_edit_validation($instance, array $data, $context) {
        return array();
    }

    /**
     * Validate a list of parameter names and types.
     * @since Moodle 3.1
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $rules array of ("fieldname"=>PARAM_X types - or "fieldname"=>array( list of valid options )
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     */
    public function validate_param_types($data, $rules) {
        $errors = array();
        $invalidstr = get_string('invaliddata', 'error');
        foreach ($rules as $fieldname => $rule) {
            if (is_array($rule)) {
                if (!in_array($data[$fieldname], $rule)) {
                    $errors[$fieldname] = $invalidstr;
                }
            } else {
                if ($data[$fieldname] != clean_param($data[$fieldname], $rule)) {
                    $errors[$fieldname] = $invalidstr;
                }
            }
        }
        return $errors;
    }

}