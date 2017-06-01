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
 * Manifest base class for Open Enrollment System.
 *
 * This class is responsible for transferring data from temporary import tables
 * and into OES models/tables as described by a "manifest strategy"
 *
 * @package    enrol_oes
 * @copyright  2017, Louisiana State University
 * @copyright  2017, Chad Mazilly, Robert Russo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_oes\manifest;

use \enrol_oes\exceptions\oes_exception;

class manifest {
    
    protected $enrol_plugin;

    protected $oes_driver;

    protected $db;

    protected $dbman;

    public function __construct($enrol_oes_plugin, $oes_driver) {
        $this->enrol_plugin = $enrol_oes_plugin;
        $this->oes_driver = $oes_driver;

        $this->initialize();
    }

    /**
     * Run the full manifest process with regard to the configured manifest strategy
     * 
     * @throws oes_exception
     * @return void
     */
    public function handle()
    {
        // handle the manifest using the manifest strategy's logic
        try {
            $this->oes_driver->manifest_strategy->manifest();

            $this->oes_driver->enrol_plugin->trace->output('Manifest complete.');
            
            // finally, tear down ALL import tables as defined in the import strategy
            // $this->tear_down_all_import_tables();

        } catch (\Exception $e) {
            // something bad happened, abort here
            throw new oes_exception($e->getMessage());
        }
    }

    /**
     * Drop all temporary import tables created by this import strategy
     * 
     * @return void
     */
    private function tear_down_all_import_tables()
    {
        foreach ($this->oes_driver->import_strategy->get_import_data_types() as $data_type) {
            $this->drop_import_table($data_type);
        }

        $this->oes_driver->enrol_plugin->trace->output('Import tables deleted.');
    }

    /**
     * Drop a single temporary import table for the given data type
     * 
     * @param  string $data_type
     * @return void
     */
    private function drop_import_table($data_type)
    {
        $table_name = $this->oes_driver->import_strategy->get_import_table_name_for($data_type);

        $table = new \xmldb_table($table_name);

        // if an table exists, drop it
        if ($this->dbman->table_exists($table)) {
            $this->dbman->drop_table($table);
        }
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