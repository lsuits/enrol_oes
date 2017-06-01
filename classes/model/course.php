<?php

namespace enrol_oes\model;

use \enrol_oes\model\base\oes_model;

class course extends oes_model {

    public static $attributes = [
        'department',
        'number',
        'name',
    ];

    public static $unique_lookup_keys = [
        'department',
        'number',
    ];

    public static function get_model_tablename() {
        return 'enrol_oes_courses';
    }

    // has many sections (lazy load)
}