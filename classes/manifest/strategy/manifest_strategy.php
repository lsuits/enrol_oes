<?php

namespace enrol_oes\manifest\strategy;

abstract class manifest_strategy {
    
    public $manifest_data_types = [];

    protected $oes_driver;

    protected $db;

    public function __construct($oes_driver) {
        $this->oes_driver = $oes_driver;

        $this->initialize();
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

    /**
     * Helper for fetching all records of a given imported type
     * 
     * @param  string $type
     * @return array
     */
    public function get_all_imported_type($type)
    {
        return $this->db->get_records($this->get_import_table_name($type));
    }

    /**
     * Helper for retrieving the given type's import table name
     * 
     * @param  string $type
     * @return string
     */
    private function get_import_table_name($type)
    {
        return $this->oes_driver->import_strategy->get_import_table_name_for($type);
    }

}