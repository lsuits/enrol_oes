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
 * Open Enrollment System CLI tool.
 *
 * Execute task:
 * $ sudo -u www-data /usr/bin/php admin/tool/task/cli/schedule_task.php /
 * --execute=\\enrol_oes\\task\\oes_sync_task
 *
 * @package    enrol_oes
 * @copyright  2017, Louisiana State University
 * @copyright  2017, Chad Mazilly, Robert Russo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once("$CFG->libdir/clilib.php");

// Now get cli options.
list($options, $unrecognized) = cli_get_params(array('verbose'=>false, 'help'=>false), array('v'=>'verbose', 'h'=>'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
        "Process OES enrolments and send notifications.

Options:
-v, --verbose         Print verbose progress information
-h, --help            Print out this help

Example:
\$ sudo -u www-data /usr/bin/php enrol/oes/cli/sync.php
";

    echo $help;
    die;
}

// try {

    // instantiate a plugin instance
    $plugin = \enrol_oes_plugin_instance::make();
    
    cli_separator();
    cli_writeln('Enrollment sync has begun using driver: ' . $plugin->get_oes_driver_name());
    cli_separator();
    
    // run full enrollment synchronization
    $plugin->sync_enrollment(new text_progress_trace());

    cli_separator();
    cli_writeln('Enrollment sync has stopped.');
    cli_separator();
    exit;

// } catch (\enrol_oes\exceptions\oes_exception $e) {
    
//     cli_separator();
//     cli_error($e->getMessage());
//     cli_separator();
//     exit;
// }