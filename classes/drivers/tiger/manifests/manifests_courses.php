<?php

namespace enrol_oes\drivers\tiger\manifests;

trait manifests_courses {
    
    /**
     * Manifests all imported courses and sections
     * 
     * @return void
     */
    public function manifest_courses()
    {
        // get all imported courses
        $imported_courses = $this->get_all_imported_type('course');

        // iterate through rows of course import table
        foreach ($imported_courses as $imported_course)
        {
            // create or update this course based on department and number
            $course = \enrol_oes\model\course::update_or_create([
                'department' => $imported_course->dept_code,
                'number' => $imported_course->course_nbr,
                'name' => $imported_course->course_title,
            ]);

            // attempt to lookup a cached manifested semester
            // if no cached semester exists, assume this course should not be manifested
            if ( ! $semester = $this->get_manifested_semester([
                'department' => $course->department,
                'term_code' => $imported_course->term_code,
                'session_key' => $imported_course->session_key,
            ]))
                continue;

            // create or update this section
            $section = \enrol_oes\model\section::update_or_create([
                'course_id' => $course->id,
                'semester_id' => $semester->id,
                'idnumber' => '', // do not set this one yet!
                'number' => $imported_course->section_nbr,
                'course_first_year' => (int) $imported_course->course_nbr < 5200 ? '1' : '0',
                'course_grade_type' => $imported_course->grade_system_code,
                'course_legal_writing' => '', // @TODO - how to determine this?
                'course_type' => $imported_course->class_type,
                'course_exception' => '', // @TODO - how to determine this?
            ]);
        }

        $this->oes_driver->enrol_plugin->trace->output('Courses manifested.');
        $this->oes_driver->enrol_plugin->trace->output('Sections manifested.');
    }

}