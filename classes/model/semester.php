<?php

namespace enrol_oes\model;

use \enrol_oes\model\base\oes_model;
// use \enrol_oes\model\base\has_meta_information_trait;

class semester extends oes_model {

    // use has_meta_information_trait;

    public static $attributes = [
        'code',
        'year',
        'name',
        'campus',
        'session_key',
        'classes_start_at',
        'grades_due_at'
    ];

    public static $unique_lookup_keys = [
        'year',
        'name',
        'campus',
        'session_key',
    ];

    public static function get_model_tablename() {
        return 'enrol_oes_semesters';
    }

    // has many departments (through courses)

    // has many courses (through sections)

}