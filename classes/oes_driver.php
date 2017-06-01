<?php

abstract class enrol_oes_oes_driver {
    
    protected $enrol_plugin;

    protected $import_strategy;

    protected $import_service;

    protected $manifest_strategy;

    public function __construct($enrol_plugin) {
        $this->enrol_plugin = $enrol_plugin;
    }

    /**
     * Instantiates and returns the given oes driver
     * 
     * @param  enrol_oes_plugin $enrol_plugin
     * @param  string $driver_name  oes driver name
     * @return oes_driver
     */
    public static function make($enrol_plugin, $driver_name)
    {
        $driver = 'enrol_oes\drivers\\' . $driver_name . '\\' . $driver_name . '_oes_driver';

        return new $driver($enrol_plugin);
    }

    public function __get($property)
    {
        // if this property exists, return the property
        if (property_exists($this, $property) && ! is_null($this->$property))
            return $this->$property;

        // otherwise, check to see if this could be a method
        if (method_exists($this, $property)) {
            // if so, instantiate, set, and return
            $this->$property = call_user_func_array(array($this, $property), []);

            return $this->$property;
        }

        return '';
    }

    /**
     * Returns this OES driver's import strategy
     * 
     * @return import_strategy
     */
    protected function import_strategy()
    {
        return $this->get_oes_component_class('\enrol_oes\drivers\\' . $this->driver_key . '\\' . $this->driver_key . '_import_strategy');
    }

    /**
     * Returns this OES driver's manifest strategy
     * 
     * @return manifest_strategy
     */
    protected function manifest_strategy()
    {
        return $this->get_oes_component_class('\enrol_oes\drivers\\' . $this->driver_key . '\\' . $this->driver_key . '_manifest_strategy');
    }

    /**
     * Returns this OES driver's import service
     * 
     * @return import_service
     */
    private function import_service()
    {
        return $this->get_oes_component_class('\enrol_oes\import\service\\' . $this->source_type . '_import_service');
    }

    private function get_oes_component_class($class)
    {
        return new $class($this);
    }

}