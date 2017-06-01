<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Import base class for Open Enrollment System.
 *
 * This class is responsible for importing data from a source and into
 * temporary tables as described by an "import strategy"
 *
 * @package    enrol_oes
 * @copyright  2017, Louisiana State University
 * @copyright  2017, Chad Mazilly, Robert Russo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_oes\import;

use \enrol_oes\import\service\exceptions\import_service_critical_exception;
use \enrol_oes\exceptions\oes_exception;

class import {
    
    protected $enrol_plugin;

    protected $oes_driver;

    // protected $import_service;

    // protected $import_strategy;

    protected $db;

    protected $dbman;

    protected $imported_fields = [];

    public function __construct($enrol_oes_plugin, $oes_driver) {
        $this->enrol_plugin = $enrol_oes_plugin;
        $this->oes_driver = $oes_driver;

        $this->initialize();
    }

    /**
     * Run the full import process with regard to the configured import strategy
     * 
     * @throws oes_exception
     * @return void
     */
    public function handle()
    {
        // first, build ALL import tables as defined in the import strategy
        $this->build_all_import_tables();

        $this->enrol_plugin->trace->output('Import tables created.');

        // next, make sure we have an empty import errors table
        $this->reset_import_errors_table();

        // next, handle the import using the import strategy's logic
        try {
            $this->oes_driver->import_strategy->import();

            $this->enrol_plugin->trace->output($this->get_import_response_text());

        } catch (import_service_critical_exception $e) {
            // should abort and clean up here!
            throw new oes_exception($e->getMessage());
        }
    }

    /**
     * Construct all temporary import tables necessary for this import strategy
     * 
     * @return void
     */
    private function build_all_import_tables()
    {
        // may need reset_caches($tablenames = null) ??

        foreach ($this->oes_driver->import_strategy->get_import_data_types() as $data_type) {
            $this->build_import_table($data_type);
        }
    }

    /**
     * Construct a single temporary import table for the given data type
     * 
     * @param  string $data_type
     * @return void
     */
    private function build_import_table($data_type)
    {
        $table_name = $this->oes_driver->import_strategy->get_import_table_name_for($data_type);

        $table = new \xmldb_table($table_name);

        // if an table exists, drop it
        if ($this->dbman->table_exists($table)) {
            $this->dbman->drop_table($table);
        }

        // define table structure
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);

        // add all appropriate fields to table
        foreach ($this->oes_driver->import_strategy->get_import_fields_for($data_type) as $import_field) {
            // @TODO - more work here on scaffolding the tables dynamically. just proof of concept...
            $length = $this->oes_driver->import_strategy->get_import_field_option_for($data_type, $import_field, 'length');

            $table->add_field($import_field, XMLDB_TYPE_CHAR, $length, null, XMLDB_NOTNULL, null, null);
        }

        // @TODO - provide indexing options for dynamic fields above ?!?

        // set id to primary key
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        
        $this->dbman->create_table($table);
    }

    /**
     * Drops and constructs a fresh import error table as configured for this import service
     * 
     * @return void
     */
    private function reset_import_errors_table()
    {
        $table_name = $this->oes_driver->import_service->get_import_error_table_name();

        $table = new \xmldb_table($table_name);

        // if an table exists, drop it
        if ($this->dbman->table_exists($table)) {
            $this->dbman->drop_table($table);
        }

        // define table structure
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('data_type', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('message', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('created_at', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        
        $this->dbman->create_table($table);
    }

    /**
     * Returns an descriptive response based on the amount of import errors encountered
     * 
     * @return string
     */
    private function get_import_response_text()
    {
        $import_error_count = $this->get_import_error_count();

        return $import_error_count > 0 ? 
            'Import complete but with ' . $import_error_count . ' error(s).' : 
            'Import complete.';
    }

    /**
     * Returns the count of errors in the import error table
     * 
     * @return int
     */
    private function get_import_error_count()
    {
        $table_name = $this->oes_driver->import_service->get_import_error_table_name();

        return (int) $this->db->count_records($table_name);
    }

    /**
     * Bootstrap this class with its necessary dependencies
     * 
     * @return void
     */
    private function initialize()
    {
        $this->db = $this->getDbClass();

        $this->dbman = $this->getDbMan();
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
     * Return moodle's DB management service
     * 
     * @return database_manager
     */
    private function getDbMan()
    {
        global $DB;

        return $DB->get_manager();
    }

}