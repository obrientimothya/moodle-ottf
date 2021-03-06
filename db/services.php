<?php

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
 * Web service local plugin template external functions and service definitions.
 *
 * @package    localwstemplate
 * @copyright  2011 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We defined the web service functions to install.
$functions = array(
        'ottf_authorize' => array(
                'classname'   => 'ottf_external',
                'methodname'  => 'authorize',
                'classpath'   => 'local/ottf/externallib.php',
                'description' => 'Check if user is valid. Also indicates admin status.',
                'type'        => 'read'
        ),
        'ottf_total_enrolments' => array(
                'classname'   => 'ottf_external',
                'methodname'  => 'total_enrolments',
                'classpath'   => 'local/ottf/externallib.php',
                'description' => 'Return count of total enrolments across the platform.',
                'type'        => 'read'
        ),
        'ottf_get_users' => array(
                'classname'   => 'ottf_external',
                'methodname'  => 'get_users',
                'classpath'   => 'local/ottf/externallib.php',
                'description' => 'Return a list of users based on criteria.',
                'type'        => 'read'
        ),
        'ottf_total_countries' => array(
                'classname'   => 'ottf_external',
                'methodname'  => 'total_countries',
                'classpath'   => 'local/ottf/externallib.php',
                'description' => 'Return number of countries with active users.',
                'type'        => 'read'
        ),
        'ottf_total_hours' => array(
                'classname'   => 'ottf_external',
                'methodname'  => 'total_hours',
                'classpath'   => 'local/ottf/externallib.php',
                'description' => 'Return total number of learning hours.',
                'type'        => 'read'
        )
);
