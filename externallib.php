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
 * OTTF API
 *
 * @package    ottfapi
 * @copyright  2016 Code Bigger (http://codebigger.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

class ottf_external extends external_api {

  /**
     * Returns description of get_users() parameters.
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_users_parameters() {
        return new external_function_parameters(
            array(
                'criteria' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'key' => new external_value(PARAM_ALPHA, 'the user column to search, expected keys (value format) are:
                                "id" (int) matching user id,
                                "lastname" (string) user last name (Note: you can use % for searching but it may be considerably slower!),
                                "firstname" (string) user first name (Note: you can use % for searching but it may be considerably slower!),
                                "idnumber" (string) matching user idnumber,
                                "username" (string) matching user username,
                                "email" (string) user email (Note: you can use % for searching but it may be considerably slower!),
                                "auth" (string) matching user auth plugin'),
                            'value' => new external_value(PARAM_RAW, 'the value to search')
                        )
                    ), 'the key/value pairs to be considered in user search. Values can not be empty.
                        Specify different keys only once (fullname => \'user1\', auth => \'manual\', ...) -
                        key occurences are forbidden.
                        The search is executed with AND operator on the criterias. Invalid criterias (keys) are ignored,
                        the search is still executed on the valid criterias.
                        You can search without criteria, but the function is not designed for it.
                        It could very slow or timeout. The function is designed to search some specific users.'
                ),
                'offset' => new external_value(PARAM_INT, 'Seek to offset before returning the next set of records.', VALUE_DEFAULT, 0),
                'pagesize' => new external_value(PARAM_INT, 'Number of records to return.', VALUE_DEFAULT, 25)
            )
        );
    }
    /**
     * Retrieve matching user. Based on core_user_get_users, with added params.
     *
     * @throws moodle_exception
     * @param array $criteria the allowed array keys are id/lastname/firstname/idnumber/username/email/auth.
     * @return array An array of arrays containing user profiles.
     * @since Moodle 2.5
     */
    public static function get_users($criteria = array(), $offset = '', $pagesize = '') {
        global $CFG, $USER, $DB;
        require_once($CFG->dirroot . "/user/lib.php");
        $params = self::validate_parameters(self::get_users_parameters(),
                array('criteria' => $criteria));
        // Validate the criteria and retrieve the users.
        $users = array();
        $warnings = array();
        $sqlparams = array();
        $usedkeys = array();
        // Do not retrieve deleted users.
        $sql = ' deleted = 0';
        foreach ($params['criteria'] as $criteriaindex => $criteria) {
            // Check that the criteria has never been used.
            if (array_key_exists($criteria['key'], $usedkeys)) {
                throw new moodle_exception('keyalreadyset', '', '', null, 'The key ' . $criteria['key'] . ' can only be sent once');
            } else {
                $usedkeys[$criteria['key']] = true;
            }
            $invalidcriteria = false;
            // Clean the parameters.
            $paramtype = PARAM_RAW;
            switch ($criteria['key']) {
                case 'id':
                    $paramtype = PARAM_INT;
                    break;
                case 'idnumber':
                    $paramtype = PARAM_RAW;
                    break;
                case 'country':
                    $paramtype = PARAM_RAW;
                    break;
                case 'username':
                    $paramtype = PARAM_RAW;
                    break;
                case 'email':
                    // We use PARAM_RAW to allow searches with %.
                    $paramtype = PARAM_RAW;
                    break;
                case 'auth':
                    $paramtype = PARAM_AUTH;
                    break;
                case 'lastname':
                case 'firstname':
                    $paramtype = PARAM_TEXT;
                    break;
                default:
                    // Send back a warning that this search key is not supported in this version.
                    // This warning will make the function extandable without breaking clients.
                    $warnings[] = array(
                        'item' => $criteria['key'],
                        'warningcode' => 'invalidfieldparameter',
                        'message' =>
                            'The search key \'' . $criteria['key'] . '\' is not supported, look at the web service documentation'
                    );
                    // Do not add this invalid criteria to the created SQL request.
                    $invalidcriteria = true;
                    unset($params['criteria'][$criteriaindex]);
                    break;
            }
            if (!$invalidcriteria) {
                $cleanedvalue = clean_param($criteria['value'], $paramtype);
                $sql .= ' AND ';
                // Create the SQL.
                switch ($criteria['key']) {
                    case 'id':
                    case 'idnumber':
                    case 'username':
                    case 'country':
                    case 'auth':
                        $sql .= $criteria['key'] . ' = :' . $criteria['key'];
                        $sqlparams[$criteria['key']] = $cleanedvalue;
                        break;
                    case 'email':
                    case 'lastname':
                    case 'firstname':
                        $sql .= $DB->sql_like($criteria['key'], ':' . $criteria['key'], false);
                        $sqlparams[$criteria['key']] = $cleanedvalue;
                        break;
                    default:
                        break;
                }
            }
        }
        $users = $DB->get_records_select('user', $sql, $sqlparams, 'lastname ASC, firstname ASC', '*', $offset, $pagesize);
        // Finally retrieve each users information.
        $returnedusers = array();
        foreach ($users as $user) {
            $userdetails = user_get_user_details_courses($user);
            // Return the user only if all the searched fields are returned.
            // Otherwise it means that the $USER was not allowed to search the returned user.
            if (!empty($userdetails)) {
                $validuser = true;
                foreach ($params['criteria'] as $criteria) {
                    if (empty($userdetails[$criteria['key']])) {
                        $validuser = false;
                    }
                }
                if ($validuser) {
                    $returnedusers[] = $userdetails;
                }
            }
        }

        return array('users' => $returnedusers, 'warnings' => $warnings, 'page' => array('offset' => $offset, 'pagesize' => $pagesize));
    }
    /**
     * Returns description of get_users result value.
     *
     * @return external_description
     * @since Moodle 2.5
     */
    public static function get_users_returns() {
        return new external_single_structure(
            array('users' => new external_multiple_structure(
                                self::user_description()
                             ),
                  'warnings' => new external_warnings('always set to \'key\'', 'faulty key name'),
                  'page' => new external_single_structure(array(
                      'offset' => new external_value(PARAM_INT, 'current offset value'),
                      'pagesize' => new external_value(PARAM_INT, 'current page size')
                      )
                    )
            )
        );
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function authorize_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * Returns welcome message
     * @return string welcome message
     */
    public static function authorize() {
        global $USER;

        $admin = "false";

        //Context validationnt
        $context = get_context_instance(CONTEXT_USER, $USER->id);
        self::validate_context($context);

        // Check if user has admin roles
        if ( has_capability('moodle/user:create', $context) && has_capability('moodle/user:update', $context) ) {
            $admin = "true";
        }

        return array('auth' => "true", 'admin' => $admin);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function authorize_returns() {
        return new external_single_structure(array(
          'auth' => new external_value(PARAM_TEXT, 'true or false'),
          'admin' => new external_value(PARAM_TEXT, 'true or false')
        ));
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function total_enrolments_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * Returns welcome message
     * @return string welcome message
     */
    public static function total_enrolments() {
      global $DB;
        //Capability checking
        //OPTIONAL but in most web service it should present
        // if (!has_capability('moodle/user:viewdetails', $context)) {
        //     throw new moodle_exception('cannotviewprofile');
        // }
        $sql = "SELECT COUNT(u.id)
        FROM mdl_user u
        JOIN mdl_user_enrolments ue ON ue.userid = u.id
        JOIN mdl_enrol e ON e.id = ue.enrolid
        JOIN mdl_role_assignments ra ON ra.userid = u.id
        JOIN mdl_context ct ON ct.id = ra.contextid AND ct.contextlevel = 50
        JOIN mdl_course c ON c.id = ct.instanceid AND e.courseid = c.id
        JOIN mdl_role r ON r.id = ra.roleid AND r.shortname = 'student'
        WHERE
        e.status = 0 AND u.suspended = 0 AND u.deleted = 0
        AND
        (ue.timeend = 0 OR ue.timeend > NOW()) AND ue.status = 0";

        return $DB->count_records_sql($sql);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function total_enrolments_returns() {
        return new external_value(PARAM_INT, 'Count of enrolments.');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function total_countries_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * Returns welcome message
     * @return string welcome message
     */
    public static function total_countries() {
      global $DB;
        //Capability checking
        //OPTIONAL but in most web service it should present
        // if (!has_capability('moodle/user:viewdetails', $context)) {
        //     throw new moodle_exception('cannotviewprofile');
        // }
        $sql = "SELECT COUNT(c.country) total_countries
        FROM (SELECT DISTINCT country FROM mdl_user WHERE deleted = 0) c";

        return $DB->count_records_sql($sql);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function total_countries_returns() {
        return new external_value(PARAM_INT, 'Count of countries.');
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function total_hours_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * Returns welcome message
     * @return string welcome message
     */
    public static function total_hours() {
      global $DB;
        //Capability checking
        //OPTIONAL but in most web service it should present
        // if (!has_capability('moodle/user:viewdetails', $context)) {
        //     throw new moodle_exception('cannotviewprofile');
        // }
        $sql = "SELECT ROUND(SUM(g.finalgrade), 0) total_hours
        FROM  `mdl_grade_grades` g
        INNER JOIN  `mdl_user` u ON g.userid = u.id
        WHERE u.deleted =0";

        return $DB->count_records_sql($sql);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function total_hours_returns() {
        return new external_value(PARAM_INT, 'Count of countries.');
    }

    /**
 * Create user return value description.
 *
 * @param array $additionalfields some additional field
 * @return single_structure_description
 */
public static function user_description($additionalfields = array()) {
    $userfields = array(
        'id'    => new external_value(PARAM_INT, 'ID of the user'),
        'username'    => new external_value(PARAM_RAW, 'The username', VALUE_OPTIONAL),
        'firstname'   => new external_value(PARAM_NOTAGS, 'The first name(s) of the user', VALUE_OPTIONAL),
        'lastname'    => new external_value(PARAM_NOTAGS, 'The family name of the user', VALUE_OPTIONAL),
        'fullname'    => new external_value(PARAM_NOTAGS, 'The fullname of the user'),
        'email'       => new external_value(PARAM_TEXT, 'An email address - allow email as root@localhost', VALUE_OPTIONAL),
        'address'     => new external_value(PARAM_TEXT, 'Postal address', VALUE_OPTIONAL),
        'phone1'      => new external_value(PARAM_NOTAGS, 'Phone 1', VALUE_OPTIONAL),
        'phone2'      => new external_value(PARAM_NOTAGS, 'Phone 2', VALUE_OPTIONAL),
        'icq'         => new external_value(PARAM_NOTAGS, 'icq number', VALUE_OPTIONAL),
        'skype'       => new external_value(PARAM_NOTAGS, 'skype id', VALUE_OPTIONAL),
        'yahoo'       => new external_value(PARAM_NOTAGS, 'yahoo id', VALUE_OPTIONAL),
        'aim'         => new external_value(PARAM_NOTAGS, 'aim id', VALUE_OPTIONAL),
        'msn'         => new external_value(PARAM_NOTAGS, 'msn number', VALUE_OPTIONAL),
        'department'  => new external_value(PARAM_TEXT, 'department', VALUE_OPTIONAL),
        'institution' => new external_value(PARAM_TEXT, 'institution', VALUE_OPTIONAL),
        'idnumber'    => new external_value(PARAM_RAW, 'An arbitrary ID code number perhaps from the institution', VALUE_OPTIONAL),
        'interests'   => new external_value(PARAM_TEXT, 'user interests (separated by commas)', VALUE_OPTIONAL),
        'firstaccess' => new external_value(PARAM_INT, 'first access to the site (0 if never)', VALUE_OPTIONAL),
        'lastaccess'  => new external_value(PARAM_INT, 'last access to the site (0 if never)', VALUE_OPTIONAL),
        'auth'        => new external_value(PARAM_PLUGIN, 'Auth plugins include manual, ldap, imap, etc', VALUE_OPTIONAL),
        'confirmed'   => new external_value(PARAM_INT, 'Active user: 1 if confirmed, 0 otherwise', VALUE_OPTIONAL),
        'lang'        => new external_value(PARAM_SAFEDIR, 'Language code such as "en", must exist on server', VALUE_OPTIONAL),
        'calendartype' => new external_value(PARAM_PLUGIN, 'Calendar type such as "gregorian", must exist on server', VALUE_OPTIONAL),
        'theme'       => new external_value(PARAM_PLUGIN, 'Theme name such as "standard", must exist on server', VALUE_OPTIONAL),
        'timezone'    => new external_value(PARAM_TIMEZONE, 'Timezone code such as Australia/Perth, or 99 for default', VALUE_OPTIONAL),
        'mailformat'  => new external_value(PARAM_INT, 'Mail format code is 0 for plain text, 1 for HTML etc', VALUE_OPTIONAL),
        'description' => new external_value(PARAM_RAW, 'User profile description', VALUE_OPTIONAL),
        'descriptionformat' => new external_format_value('description', VALUE_OPTIONAL),
        'city'        => new external_value(PARAM_NOTAGS, 'Home city of the user', VALUE_OPTIONAL),
        'url'         => new external_value(PARAM_URL, 'URL of the user', VALUE_OPTIONAL),
        'country'     => new external_value(PARAM_ALPHA, 'Home country code of the user, such as AU or CZ', VALUE_OPTIONAL),
        'profileimageurlsmall' => new external_value(PARAM_URL, 'User image profile URL - small version'),
        'profileimageurl' => new external_value(PARAM_URL, 'User image profile URL - big version'),
        'customfields' => new external_multiple_structure(
            new external_single_structure(
                array(
                    'type'  => new external_value(PARAM_ALPHANUMEXT, 'The type of the custom field - text field, checkbox...'),
                    'value' => new external_value(PARAM_RAW, 'The value of the custom field'),
                    'name' => new external_value(PARAM_RAW, 'The name of the custom field'),
                    'shortname' => new external_value(PARAM_RAW, 'The shortname of the custom field - to be able to build the field class in the code'),
                )
            ), 'User custom fields (also known as user profile fields)', VALUE_OPTIONAL),
        'preferences' => new external_multiple_structure(
            new external_single_structure(
                array(
                    'name'  => new external_value(PARAM_ALPHANUMEXT, 'The name of the preferences'),
                    'value' => new external_value(PARAM_RAW, 'The value of the custom field'),
                )
        ), 'Users preferences', VALUE_OPTIONAL)
    );
    if (!empty($additionalfields)) {
        $userfields = array_merge($userfields, $additionalfields);
    }
    return new external_single_structure($userfields);
  }

}
