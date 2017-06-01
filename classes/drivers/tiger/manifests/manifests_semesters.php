<?php

namespace enrol_oes\drivers\tiger\manifests;

trait manifests_semesters {
    
    /**
     * Manifests all imported semesters
     * 
     * @return void
     */
    public function manifest_semesters()
    {
        // get all imported semesters
        $imported_semesters = $this->get_all_imported_type('semester');

        // iterate through rows of semester import table
        foreach ($imported_semesters as $sem)
        {
            // arrange semester input data
            list($year, $name) = $this->parse_term_code($sem->term_code);

            list($campus, $is_starting) = $this->parse_code_value($sem->code_value);

            if ( ! $year || ! $name || ! $campus)
                continue;

            // @TODO - difference in dates seems a little funky but follows existing system
            $grades_due_at = $campus == 'LAW' 
                ? $this->parse_date($sem->calendar_date) + (46 * 24 * 60 * 60) 
                : $this->parse_date($sem->calendar_date) + (21 * 24 * 60 * 60);

            // update or create this semester
            $semester = \enrol_oes\model\semester::update_or_create([
                'code' => $sem->term_code,
                'year' => $year,
                'name' => $name,
                'campus' => $campus,
                'session_key' => (string) $sem->session,
                'classes_start_at' => $this->parse_date($sem->calendar_date),
                'grades_due_at' => $grades_due_at,
            ]);

            // construct this semester's unique 
            // $semester_cache_key = $sem->term_code . '-' . $sem->session . '-' . $campus;

            $semester_cache_key = $this->get_semester_cache_key([
                'term_code' => $sem->term_code,
                'session_key' => $sem->session,
                'campus' => $campus
            ]);

            // cache this semester for later
            $this->manifested_semesters[$semester_cache_key] = $semester;
        }

        $this->oes_driver->enrol_plugin->trace->output('Semesters manifested.');
    }

    /**
     * Returns an array of a year and name from the given term code
     * 
     * @param  string $term_code
     * @return array
     */
    private function parse_term_code($term_code) {
        $year = (int) substr($term_code, 0, 4);

        $semester_code = substr($term_code, -2);

        switch ($semester_code) {
            case '1S': return array($year - 1, 'Fall'); // FALL
            case '2S': return array($year, 'Spring'); // SPRING
            case '3S': return array($year, 'Summer'); // SUMMER
            case '1T': return array($year - 1, 'WinterInt'); // WINTER_INT
            case '2T': return array($year, 'SpringInt'); // SPRING_INT
            case '3T': return array($year, 'SummerInt'); // SUMMER_INT
            default: return array(0, '');
        }
    }

    /**
     * Returns an array of a campus and "is starting" flag from the given code value
     * 
     * @param  string $code_value
     * @return array
     */
    private function parse_code_value($code_value) {
        $is_starting = false;

        switch ($code_value) {
            case 'CLSB': // LSU_SEM
            case 'CLSE': // LSU_FINAL
                $campus = 'LSU';
                $is_starting = ($code_value == 'CLSB');
                break;
            case 'LAWB': // LAW_SEM
            case 'LAWE': // LAWE
                $campus = 'LAW';
                $is_starting = ($code_value == 'LAWB');
                break;
            default:
                $campus = '';
                break;
        }

        return [$campus, $is_starting];
    }

    /**
     * Returns a unix timestamp given a string date
     * 
     * @param  string $date
     * @return int
     */
    private function parse_date($date) {
        $parts = explode('-', $date);
        
        return mktime(0, 0, 0, $parts[1], $parts[2], $parts[0]);
    }

}