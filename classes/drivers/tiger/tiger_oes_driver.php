<?php

namespace enrol_oes\drivers\tiger;

use \enrol_oes_oes_driver as oes_driver;
use \enrol_oes_oes_driver_interface as oes_driver_interface;

class tiger_oes_driver extends oes_driver implements oes_driver_interface {
    
    protected $driver_key = 'tiger';

    protected $source_type = 'csv';

}