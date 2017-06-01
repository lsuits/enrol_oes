<?php

namespace enrol_oes\model;

use \enrol_oes\model\base\oes_model;

class section extends oes_model {

    public static $attributes = [
        'course_id',
        'semester_id',
        'idnumber',
        'number',
        'course_first_year',
        'course_grade_type',
        'course_legal_writing',
        'course_type',
        'course_exception',
    ];

    public static $unique_lookup_keys = [
        'course_id',
        'semester_id',
        'number',
    ];

    public static function get_model_tablename() {
        return 'enrol_oes_sections';
    }

    // belongs to a semester

    // belongs to a course (lazy load)
}