<?php

namespace enrol_oes\import\service;

use \enrol_oes\import\service\exceptions\import_service_item_exception;

abstract class import_service {
    
    protected $db;

    protected $oes_driver;

    public function __construct($oes_driver) {
        $this->oes_driver = $oes_driver;

        $this->initialize();
    }

    /**
     * Checks if the given import record is valid according to the given import strategy and data type
     * and throws exception if invalid
     * 
     * @param  string           $data_type
     * @param  stdClass         $import_record    a line-item object to be imported
     * @throws import_service_item_exception
     * @return void
     */
    public function validate_import_record($data_type, $import_record)
    {
        // $this->oes_driver->enrol_plugin->trace->output('you can log messages like this...');
        
        $invalid_fields = [];

        // iterate through each property on this object
        foreach ($import_record as $attribute => $value) {
            // if this attribute is invalid according to the import strategy, add to the stack of invalid fields for this record
            if ( ! $this->oes_driver->import_strategy->is_valid_import_data_for($data_type, $attribute, $value))
                $invalid_fields[] = $attribute;
        }

        // if there are any invalid fields, through an exception stating so
        if (count($invalid_fields)) {
            throw new import_service_item_exception('Did not import line: ' . $import_record->rid . '. Invalid fields: ' . implode(',', $invalid_fields));
        }
    }

    public function handle_import_item_exception($data_type, $error_message)
    {
        $table_name = $this->get_import_error_table_name();

        $error = new \stdClass();
        $error->data_type = $data_type;
        $error->message = $error_message;
        $error->created_at = time();

        $this->db->insert_record($table_name, $error, false);
    }

    public function get_import_error_table_name()
    {
        return 'enrol_oes_import_errors';
    }

    /**
     * Bootstrap this class with its necessary dependencies
     * 
     * @return void
     */
    private function initialize()
    {
        $this->db = $this->getDbClass();
    }

    /**
     * Return moodle's DB service
     * 
     * @return DB global
     */
    private function getDbClass()
    {
        global $DB;

        return $DB;
    }

}