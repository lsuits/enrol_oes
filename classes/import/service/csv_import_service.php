<?php

namespace enrol_oes\import\service;

use \enrol_oes\import\service\import_service;
use \enrol_oes\import\service\import_service_interface;
use \enrol_oes\import\service\exceptions\import_service_critical_exception;
use \enrol_oes\import\service\exceptions\import_service_item_exception;

class csv_import_service extends import_service implements import_service_interface {
    
    public $delimiter = ',';

    /**
     * Handles importing of a specific "data type" using a given import strategy
     * 
     * @param  string            $data_type  semester|student|teacher
     * @throws import_service_critical_exception
     * @return void
     */
    public function handle_data_type($data_type)
    {
        // make sure we have a file location specified
        if ( ! array_key_exists('file_location_' . $data_type, $this->oes_driver->import_strategy->config['source']['csv']))
            throw new import_service_critical_exception('Source CSV location not specified: ' . $data_type . ' import');

        $filename = $this->oes_driver->import_strategy->config['source']['csv']['file_location_' . $data_type];

        // make sure the file exists
        if ( ! file_exists($filename))
            throw new import_service_critical_exception('Source CSV missing (' . $data_type . ' import)');

        // get the contents of the file
        if ( ! $file = file_get_contents($filename))
            throw new import_service_critical_exception('Could not get contents of file. (' . $data_type . ' import)');

        // get row data as array
        $rows = $this->get_rows_from_file($file);

        // make sure there is data (at least a header)
        if ( ! count($rows))
            throw new import_service_critical_exception('Missing column headings row. (' . $data_type . ' import)');

        // check that first row contains all required fields
        if ( ! $this->row_contains_all_fields($rows[0], $this->oes_driver->import_strategy->get_import_fields_for($data_type, 'required')))
            throw new import_service_critical_exception('Missing one or more required columns (' . $data_type . ' import)');

        $collection_to_insert = [];

        // iterate through all rows
        foreach ($rows as $rid => $fields) {
            // first row assumed to be header, cache for later
            if ($rid == 0) {
                $this->set_imported_fields($fields);
                continue;
            }

            // ignore empty lines
            if (trim($fields) === '') 
                continue;

            // convert the csv row to array
            $row = explode($this->delimiter, $fields);

            // check that this row does not contain more fields than accepted
            if ( ! $this->is_valid_row($row)) {
                $this->handle_import_item_exception($data_type, 'Did not import line: ' . $rid . '. Possible import corruption.');
                
                continue;
            }

            $import_record = new \stdClass();
                
            // iterate through each field on the row
            foreach ($this->imported_fields as $fid => $field) {
                // pin the row id to this record temporarily, access later if necessary
                $import_record->rid = $rid;

                // if the import strategy supports this type of field for this data type
                if ($this->oes_driver->import_strategy->supports_import_field_for($field, $data_type)) {
                    // add this data to the record to be inserted
                    $import_record->$field = $row[$fid];
                    // @TODO - sanitize data here?!?!
                }
            }

            try {
                // if invalid, throw exception
                $this->validate_import_record($data_type, $import_record);

                $collection_to_insert[] = $import_record;

            } catch (import_service_item_exception $e) {
                $this->handle_import_item_exception($data_type, $e->getMessage());
            }
        }

        // if there are any records to be inserted, do it now
        if (count($collection_to_insert)) {
            $table_name = $this->oes_driver->import_strategy->get_import_table_name_for($data_type);

            $last_inserted_id = $this->db->insert_records($table_name, $collection_to_insert);
        }

        $this->oes_driver->enrol_plugin->trace->output(count($collection_to_insert) . ' records imported for type: ' . $data_type . '.');

        unset($file);
    }

    /**
     * Reports whether or not the given row data is valid for import
     *
     * (checks that the incoming row does not have more fields than accepted)
     * 
     * @param  array $row_data
     * @return bool
     */
    private function is_valid_row($row_data) {
        return ! (count($row_data) > count($this->imported_fields));
    }

    /**
     * Returns a filtered array of row data given raw csv contents
     * 
     * @param  string $file_contents
     * @return array
     */
    private function get_rows_from_file($file_contents)
    {
        // format the contents into an array of row data
        $data = \core_text::convert($file_contents, 'utf-8', 'utf-8');
        // $data = \core_text::convert($file_contents, $this->enrol_plugin->get_config('encoding', 'utf-8'), 'utf-8');
        $data = str_replace("\r", '', $data);
        $row_data = explode("\n", $data);
        
        return $row_data;
    }

    /**
     * Reports whether or not the given row contains all of the given fields
     * 
     * @param  array  $row
     * @param  array  $fields
     * @return boolean
     */
    private function row_contains_all_fields($row, $fields)
    {
        $input_fields = explode($this->delimiter, $row);

        foreach ($fields as $field) {
            if ( ! in_array($field, $input_fields))
                return false;
        }

        return true;
    }

    /**
     * Caches the imported fields for the current import
     * 
     * @param array $fields
     */
    private function set_imported_fields($fields)
    {
        $this->imported_fields = explode($this->delimiter, $fields);
    }

}