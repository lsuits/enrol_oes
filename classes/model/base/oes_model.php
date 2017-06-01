<?php

namespace enrol_oes\model\base;

use \enrol_oes\model\base\dao_model;
// use \enrol_oes\model\base\oes_model_interface;

abstract class oes_model extends dao_model {

    /**
     * Returns a filtered array from given params, containing only this model's unique lookup keys
     * 
     * @param  array $params
     * @return array
     */
    public static function get_lookup_params($params) {
        $lookup_keys = ! empty(static::$unique_lookup_keys) ?: [];

        return array_intersect_key($params, array_flip(static::$unique_lookup_keys));
    }

    /**
     * Updates or creates a model depending upon whether this model already exists according to unique lookup keys
     * 
     * @param  array $params
     * @return oes_model
     */
    public static function update_or_create($params) {

        // if there exists no model with same "unique lookup keys"
        if ( ! $existing = self::get(self::get_lookup_params($params))) {
            return self::create($params);

        // otherwise, update the existing model
        } else {
            return $existing->update_with($params);
        }
    }

    /**
     * Returns a model with the given id
     * 
     * @param  int $id
     * @return oes_model
     */
    public static function by_id($id) {
        return self::get(array('id' => $id));
    }

    /**
     * Returns a model with the given params
     * 
     * @param  array $params
     * @param  string $fields
     * @return oes_model
     */
    public static function get($params, $fields = '*') {
        return current(self::get_all($params, '', $fields));
    }

    public static function get_all($params = array(), $sort = '', $fields = '*', $offset = 0, $limit = 0) {
        return self::get_all_internal($params, $sort, $fields, $offset, $limit);
    }

    /**
     * [save description]
     * @return bool
     */
    public function save() {
        $saved = parent::save();

        return $saved;
    }

    public function update_with($params = array()) {
        foreach ($params as $key => $value) {
            if (in_array($key, static::$attributes)) {
                $this->$key = $value;
            }
        }

        return $this->save();
    }

    public static function create($params = array()) {
        $model = new static;

        foreach (static::$attributes as $attribute) {
            $model->$attribute = array_key_exists($attribute, $params) ? $params[$attribute] : null;
        }

        return $model->save();
    }

    public static function delete($id) {
        self::delete_meta(array(self::call('get_name').'id' => $id));

        return parent::delete($id);
    }

    public static function delete_all($params = array()) {
        return self::delete_all_internal($params);
    }

    /////////////

    /**
     * @param array | ues_dao_filter_builder $params
     * 
     */
    public static function count($params = array()) {
        global $DB;

        if (self::call('params_contains_meta', $params)) {
            $send = is_array($params) ? $params : $params->get();

            list($send, $joins) = self::strip_joins($params);
            list($tables, $filters) = self::meta_sql_builder($send);

            $sql = 'SELECT COUNT(z.id) FROM ' . $tables . $joins .
                ' WHERE ' . $filters;

            return $DB->count_records_sql($sql);
        } else {
            return parent::count($params);
        }
    }

    /**
     * 
     * @param type $object
     * @param type $params
     * @return oes_model
     */
    public static function upgrade_and_get($object, $params) {
        return self::with_class(function ($class) use ($object, $params) {
            $model = $class::upgrade($object);

            if ($prev = $class::get($params)) {
                $model->id = $prev->id;
            }

            return $model;
        });
    }

    public static function params_contains_meta($params) {
        return false;

        // TODO: take a look at this closer...

        $name = self::call('get_name');

        foreach ($params as $field => $i) {
            if (preg_match('/^'.$name.'_/', $field)) {
                return true;
            }
        }

        return false;
    }

}