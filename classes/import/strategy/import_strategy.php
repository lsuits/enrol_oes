<?php

namespace enrol_oes\import\strategy;

abstract class import_strategy {
    
    public $import_data_types = [];

    protected $oes_driver;

    public function __construct($oes_driver) {
        $this->oes_driver = $oes_driver;
    }

    /**
     * Fires the import strategy extension's dedicated import method for the specific data type, if any
     * 
     * @param  string $data_type
     * @return mixed
     */
    public function handle_import_data_type($data_type)
    {
        $method = 'import_type_' . $data_type;

        if (method_exists($this, $method))
            return $this->$method();
    }

    /**
     * Reports whether or not the given field is supported by the given data type
     * 
     * @param  string $field
     * @param  string $data_type
     * @return bool
     */
    public function supports_import_field_for($field, $data_type)
    {
        return in_array($field, $this->get_import_fields_for($data_type));
    }

    /**
     * Returns this import strategy's configured "import data types"
     * 
     * @param  boolean $keys_only  if false, returns entire import_data_types array
     * @return array
     */
    public function get_import_data_types($keys_only = true)
    {
        // @TODO - consider package-wide minimum requirements for these fields
        $types = $this->import_data_types;

        return $keys_only ? array_keys($types) : $types;
    }

    /**
     * Returns fields within the given "import data type" by "include type"
     * 
     * @param  string $data_type     student|teacher|etc.
     * @param  string $include_type  all|required|available
     * @return array
     */
    public function get_import_fields_for($data_type, $include_type = 'all')
    {
        // if not a main key, reject
        if ( ! in_array($data_type, $this->get_import_data_types()))
            throw new \Exception();

        // if including 'all' be sure to include both "required" and "available"
        if ($include_type == 'all') {
            $fields = array_merge(
                $this->get_import_fields_for($data_type, 'required'),
                $this->get_import_fields_for($data_type, 'available')
            );
        } else {
            if ( ! array_key_exists($include_type . '_fields', $this->import_data_types[$data_type])) {
                return [];
            } else {
                $fields = $this->import_data_types[$data_type][$include_type . '_fields'];
            }
        }

        // create output array by iterating through fields and making sure nested arrays are flattened
        $import_fields = [];

        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                $import_fields[] = $key;
            } else {
                $import_fields[] = $value;
            }
        }

        return $import_fields;
    }

    /**
     * Returns an option value (if any) for a given type, field, and option
     *
     * Optionally, a default value may be included and returned if no value found,
     * If no default value included, try to return an appropriate default value
     * 
     * @param  string $data_type     student|teacher|etc.
     * @param  string $field_key
     * @param  string $option_key    type|length
     * @param  string $default_value
     * @return string
     */
    public function get_import_field_option_for($data_type, $field_key, $option_key, $default_value = null)
    {
        // if not a main key, reject
        if ( ! in_array($data_type, $this->get_import_data_types()))
            throw new \Exception();

        // get full array for this data type
        $data_type_arr = $this->get_import_data_types(false)[$data_type];

        $option_value = null;

        // iterate through each of the include types
        foreach (['required_fields', 'available_fields'] as $include_type) {
            if ( ! is_null($option_value))
                continue;

            // if this include type is not defined, or not formatted properly, keep looking
            if ( ! array_key_exists($include_type, $data_type_arr) || ! is_array($data_type_arr[$include_type]))
                continue;

            // if this field key is not found within this include type, keep looking
            if ( ! array_key_exists($field_key, $data_type_arr[$include_type]))
                continue;

            // if we found it, but it's not defined
            if ( ! is_array($data_type_arr[$include_type][$field_key])) {
                $option_value = '';
                continue;
            }

            // if we found it, but it's not defined
            if ( ! array_key_exists($option_key, $data_type_arr[$include_type][$field_key])) {
                $option_value = '';
                continue;
            }

            // alas, we've found it. set it.
            $option_value = $data_type_arr[$include_type][$field_key][$option_key];
        }

        // if option value is empty or null at this point, determine a default value to return
        if (empty($option_value)) {
            // if a default value was specified
            if ( ! is_null($default_value)) {
                $option_value = $default_value;
            // otherwise, try to set an appropriate one based on the option key
            } else {
                $option_value = $this->get_import_field_default_option_value($option_key);
            }
        }

        return $option_value;
    }

    /**
     * Returns a default value for the given option key
     * 
     * @param  string $option_key
     * @return string
     */
    public function get_import_field_default_option_value($option_key)
    {
        switch ($option_key) {
            case 'type':
                return 'string';
                break;

            case 'length':
                return '80';
                break;
            
            default:
                return '';
                break;
        }
    }

    /**
     * Returns the import table name for the given data type
     * 
     * @param  string $data_type
     * @return string
     */
    public function get_import_table_name_for($data_type)
    {
        // if not a main key, reject
        if ( ! in_array($data_type, $this->get_import_data_types()))
            throw new \Exception();

        // @TODO - make this configurable
        $import_table_prefix = 'enrol_oes_import_';

        return $import_table_prefix . $data_type;
    }

    /**
     * Reports whether or not the given field is valid for the given data type
     * 
     * @param  string $data_type
     * @param  string $field
     * @param  string $value
     * @return bool
     */
    public function is_valid_import_data_for($data_type, $field_key, $value)
    {
        if ( ! $validate_as = $this->get_import_field_option_for($data_type, $field_key, 'validate'))
            return true;

        if ($validate_as == 'email')
            return filter_var($value, FILTER_VALIDATE_EMAIL);

        return true;
    }

}