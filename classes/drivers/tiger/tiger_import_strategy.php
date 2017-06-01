<?php

namespace enrol_oes\drivers\tiger;

use \enrol_oes\import\strategy\import_strategy;
use \enrol_oes\import\strategy\import_strategy_interface;
// use \enrol_oes\import\service\exceptions\import_service_critical_exception;
// use \enrol_oes\import\service\exceptions\import_service_item_exception;

class tiger_import_strategy extends import_strategy implements import_strategy_interface {
    
    public $import_data_types = [
        'semester' => [
            'required_fields' => [
                'code_value',
                'term_code',
                'session',
                'calendar_date',
            ]
        ],

        'course' => [
            'required_fields' => [
                'term_code',
                'session_key',
                'campus_code',
                'dept_code',
                'course_nbr',
                'section_nbr',
                'class_type',
                'course_title',
                'grade_system_code',
            ]
        ],

        // 'student' => [
        //     'required_fields' => [
        //         'email' => [
        //             'validate' => 'email',
        //         ],
        //         'firstname' => [
        //             'type' => 'string',
        //             'length' => '38'
        //         ], 
        //         'lastname',
        //         // 'user_ferpa'
        //     ],
            
        //     'available_fields' => [
        //         'idnumber',
        //         'middlename',
        //         'nickname'
        //     ]
        // ],

        // 'teacher' => [
        //     'required_fields' => [
        //         'email' => [
        //             'validate' => 'email',
        //         ],
        //         'firstname', 
        //         'lastname',
        //         // 'good_person'
        //     ],
            
        //     'available_fields' => [
        //         'idnumber',
        //         'middlename',
        //         'nickname'
        //     ]
        // ]
    ];

    public $config = [
        'source' => [
            'csv' => [
                'file_location_semester' => '../semesters.csv',
                'file_location_course' => '../courses.csv',
                // 'file_location_student' => '../students.csv',
                // 'file_location_teacher' => '../teachers.csv',
            ]
        ]
    ];

    public function import()
    {
        $this->import_semesters();
        
        $this->import_courses();

        // alternative process...
        // foreach ($this->get_import_data_types() as $data_type) {
        //     $this->handle_import_data_type($data_type);
        // }
    }

    public function import_semesters()
    {
        $this->oes_driver->import_service->handle_data_type('semester', $this);
    }

    public function import_courses()
    {
        $this->oes_driver->import_service->handle_data_type('course', $this);
    }

}