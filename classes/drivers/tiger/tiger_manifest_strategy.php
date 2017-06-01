<?php

namespace enrol_oes\drivers\tiger;

use \enrol_oes\manifest\strategy\manifest_strategy;
use \enrol_oes\manifest\strategy\manifest_strategy_interface;
use \enrol_oes\drivers\tiger\manifests\manifests_semesters;
use \enrol_oes\drivers\tiger\manifests\manifests_courses;
// use \enrol_oes\manifest\service\exceptions\manifest_service_critical_exception;
// use \enrol_oes\manifest\service\exceptions\manifest_service_item_exception;

class tiger_manifest_strategy extends manifest_strategy implements manifest_strategy_interface {
    
    use manifests_semesters, manifests_courses;

    public $manifested_semesters = [];

    public function manifest()
    {
        $this->manifest_semesters();

        $this->manifest_courses();
    }

    /**
     * Returns a unique semester key from the given params
     * 
     * @param  array $params
     * @return string
     */
    private function get_semester_cache_key($params) {
        return $params['term_code'] . '-' . $params['session_key'] . '-' . $params['campus'];
    }

    /**
     * Returns a semester from cache given lookup params
     * 
     * @param  array $params
     * @return semester|false
     */
    private function get_manifested_semester($params) {
        $filtered_campus = $params['department'] == 'LAW' ? 'LAW' : 'LSU';

        $key = $params['term_code'] . '-' . $params['session_key'] . '-' . $filtered_campus;

        return array_key_exists($key, $this->manifested_semesters) ? $this->manifested_semesters[$key] : false;
    }

}