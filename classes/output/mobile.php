<?php

namespace mod_facetoface\output;


use stdClass;

defined('MOODLE_INTERNAL') || die();

class mobile
{

    public static function view_facetoface($args)
    {
        global $DB, $OUTPUT, $CFG, $PAGE, $USER;
        $args = (object)$args;
        $dir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
        require_once($dir . '/config.php');
        require_once($dir . '/mod/facetoface/lib.php');
        require_once($dir . '/mod/facetoface/renderermobile.php');

        $cmid = \get_coursemodule_from_id('facetoface', $args->cmid);
        $cm = $DB->get_record('course_modules', array('id' => $args->courseid));
        $course = $DB->get_record('course', array('id' => $cm->course));
        $facetoface = $DB->get_record('facetoface', array('id' => $cmid->instance));
        $context = \context_module::instance($cmid->id);
        $locations = false;

        $timenow = time();
        $context = \context_course::instance($course->id);
        $viewattendees = has_capability('mod/facetoface:viewattendees', $context);
        $editsessions = has_capability('mod/facetoface:editsessions', $context);
        $multiplesignups = $facetoface->signuptype == MOD_FACETOFACE_SIGNUP_MULTIPLE;
        $bulksignup = $facetoface->multiplesignupmethod == MOD_FACETOFACE_SIGNUP_MULTIPLE_PER_ACTIVITY;
        $bookedsession = null;
        if ($submissions = facetoface_get_user_submissions($facetoface->id, $USER->id)) {
            $bookedsessionmap = array_combine(
                array_column($submissions, 'sessionid'),
                $submissions
            );

            $submission = array_shift($submissions);
            $bookedsession = $submission;
        }
        $customfields = facetoface_get_session_customfields();
        $tableheader = array();
        foreach ($customfields as $field) {
            if (!empty($field->showinsummary)) {
                $tableheader[] = format_string($field->name);
            }
        }
        $tableheader[] = get_string('date', 'facetoface');
        $tableheader[] = get_string('time', 'facetoface');
        if ($viewattendees) {
            $tableheader[] = get_string('capacity', 'facetoface');
        } else {
            $tableheader[] = get_string('seatsavailable', 'facetoface');
        }
        $tableheader[] = get_string('status', 'facetoface');
        $tableheader[] = get_string('options', 'facetoface');
        $upcomingarray = array();
        $previousarray = array();
        $upcomingtbdarray = array();

        if ($sessions = facetoface_get_sessions($facetoface->id, $location)) {
            foreach ($sessions as $session) {

                $sessionstarted = false;
                $sessionfull = false;
                $sessionwaitlisted = false;
                $isbookedsession = false;

                $sessiondata = $session;
                $sessiondata->bookedsession = $multiplesignups ? ($bookedsessionmap[$session->id] ?? []) : $bookedsession;

                // Add custom fields to sessiondata.
                $customdata = $DB->get_records('facetoface_session_data', array('sessionid' => $session->id), '', 'fieldid, data');
                $sessiondata->customfielddata = $customdata;

                // Is session waitlisted.
                if (!$session->datetimeknown) {
                    $sessionwaitlisted = true;
                }

                // Check if session is started.
                $sessionstarted = facetoface_has_session_started($session, $timenow);
                if ($session->datetimeknown && $sessionstarted && facetoface_is_session_in_progress($session, $timenow)) {
                    $sessionstarted = true;
                } else if ($session->datetimeknown && $sessionstarted) {
                    $sessionstarted = true;
                }

                // Put the row in the right table.
                if ($sessionstarted) {
                    $previousarray[] = $sessiondata;
                } else if ($sessionwaitlisted) {
                    $upcomingtbdarray[] = $sessiondata;
                } else { // Normal scheduled session.
                    $upcomingarray[] = $sessiondata;
                }
            }
        }
        if (!empty($upcomingarray) && $bulksignup) {
            $firstsession = $sessions[array_keys($sessions)[0]];
//            $signupforstreamlink = \html_writer::link(
//                'signup.php?s=' . $firstsession->id . '&backtoallsessions=' . $session->facetoface,
//                get_string('signupforstream', 'facetoface')
//            );
        } else {
            $signupforstreamlink = false;
        }
        if (empty($upcomingarray) && empty($upcomingtbdarray)) {
            $emptyarray = true;
        } else {
            $upcomingarray = array_merge($upcomingarray, $upcomingtbdarray);
        }
        $signuplinks = true;
        $tableheader = array();
        foreach ($customfields as $field) {
            if (!empty($field->showinsummary)) {
                $tableheader[] = format_string($field->name);
            }
        }
        $tableheader[] = get_string('date', 'facetoface');
        $tableheader[] = get_string('time', 'facetoface');
        if ($viewattendees) {
            $tableheader[] = get_string('capacity', 'facetoface');
        } else {
            $tableheader[] = get_string('seatsavailable', 'facetoface');
        }
        $tableheader[] = get_string('status', 'facetoface');
        $tableheader[] = get_string('options', 'facetoface');
        foreach ($sessions as $session) {
            $isbookedsession = false;
            $bookedsession = $session->bookedsession;
            $sessionstarted = false;
            $sessionfull = false;

            $sessionrow = array();

            // Custom fields.
            $customdata = $session->customfielddata;
            foreach ($customfields as $field) {
                if (empty($field->showinsummary)) {
                    continue;
                }

                if (empty($customdata[$field->id])) {
                    $sessionrow[] = '&nbsp;';
                } else {
                    if (CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                        $sessionrow[] = str_replace(CUSTOMFIELD_DELIMITER, '<br>', $customdata[$field->id]->data);
                    } else {
                        $sessionrow[] = $customdata[$field->id]->data;
                    }

                }
            }

            // Dates/times.
            $allsessiondates = '';
            $allsessiontimes = '';
            if ($session->datetimeknown) {
                foreach ($session->sessiondates as $date) {
                    if (!empty($allsessiondates)) {
                        $allsessiondates .= '<br>';
                    }
                    $allsessiondates .= userdate($date->timestart, get_string('strftimedate'));
                    if (!empty($allsessiontimes)) {
                        $allsessiontimes .= '<br>';
                    }
                    $allsessiontimes .= userdate($date->timestart, get_string('strftimetime')) .
                        ' - ' . userdate($date->timefinish, get_string('strftimetime'));
                }
            } else {
                $allsessiondates = get_string('wait-listed', 'facetoface');
                $allsessiontimes = get_string('wait-listed', 'facetoface');
                $sessionwaitlisted = true;
            }
            $sessionrow[] = $allsessiondates;
            $sessionrow[] = $allsessiontimes;

            // Capacity.
            $signupcount = facetoface_get_num_attendees($session->id, MDL_F2F_STATUS_APPROVED);
            $stats = $session->capacity - $signupcount;
            if ($viewattendees) {
                $stats = $signupcount . ' / ' . $session->capacity;
            } else {
                $stats = max(0, $stats);
            }
            $sessionrow[] = $stats;

            // Status.
            $status = get_string('bookingopen', 'facetoface');
            if ($session->datetimeknown && facetoface_has_session_started($session, $timenow) && facetoface_is_session_in_progress($session, $timenow)) {
                $status = get_string('sessioninprogress', 'facetoface');
                $sessionstarted = true;
            } else if ($session->datetimeknown && facetoface_has_session_started($session, $timenow)) {
                $status = get_string('sessionover', 'facetoface');
                $sessionstarted = true;
            } else if ($bookedsession && $session->id == $bookedsession->sessionid) {
                $signupstatus = facetoface_get_status($bookedsession->statuscode);
                $status = get_string('status_' . $signupstatus, 'facetoface');
                $isbookedsession = true;
            } else if ($signupcount >= $session->capacity) {
                $status = get_string('bookingfull', 'facetoface');
                $sessionfull = true;
            }

            $sessionrow[] = $status;

            // Options.
            $options = '';
            if ($editsessions) {
                $options .= '<a href="' . new \moodle_url('sessions.php', array('s' => $session->id, 'c' => 1)) . '"><ion-icon name="settings-outline"></ion-icon></a>';
                $options .= '<a href="' . new \moodle_url('sessions.php', array('s' => $session->id, 'c' => 1)) . '"><ion-icon name="copy-outline"></ion-icon></a>';
                $options .= '<a href="' . new \moodle_url('sessions.php', array('s' => $session->id, 'd' => 1)) . '"><ion-icon name="trash-outline"></ion-icon></a>';
            }
            if ($viewattendees) {
                $options .= \html_writer::link('attendees.php?s=' . $session->id . '&backtoallsessions=' . $session->facetoface,
                        get_string('attendees', 'facetoface'),
                        array('title' => get_string('seeattendees', 'facetoface'))) . ' &nbsp; ';
                $options .= $OUTPUT->action_icon(new \moodle_url('attendees.php', array('s' => $session->id, 'download' => 'xlsx')),
                        new \pix_icon('f/spreadsheet', get_string('downloadexcel')), null,
                        array('title' => get_string('downloadexcel'))) . ' ';
                $options .= $OUTPUT->action_icon(new \moodle_url('attendees.php', array('s' => $session->id, 'download' => 'ods')),
                        new \pix_icon('f/calc', get_string('downloadods')), null,
                        array('title' => get_string('downloadods'))) . ' ' . '<br>';
            }
            if ($isbookedsession) {
                $options .= \html_writer::link('signup.php?s=' . $session->id . '&backtoallsessions=' . $session->facetoface,
                        get_string('moreinfo', 'facetoface'),
                        array('title' => get_string('moreinfo', 'facetoface'))) . '<br>';
                if ($session->allowcancellations) {
                    $options .= \html_writer::link('cancelsignup.php?s=' . $session->id . '&backtoallsessions=' . $session->facetoface,
                        get_string('cancelbooking', 'facetoface'), array('title' => get_string('cancelbooking', 'facetoface')));
                }
            } else if (!$sessionstarted && !$bookedsession && $signuplinks) {
                $options .= '<ion-item>
                <ion-label><ion-button expand="block" color="light" core-site-plugins-new-content title="Signup"
                        component="mod_facetoface" method="signup"
                        [args]="{s: ' . $session->id . ',' . 'backtoallsessions: ' . $session->facetoface  .
                         ',' . 'c: ' . $args->cmid . ',' . 'r: ' . $args->courseid .
                    '}">
       
                    Signup
                </ion-button></ion-label>
            </ion-item>';
            }
            if (empty($options)) {
                $options = get_string('none', 'facetoface');
            }
            $sessionrow[] = $options;

            $row[] = $sessionrow;

            // Set the CSS class for the row.
            if ($sessionstarted) {
                $row->attributes = array('class' => 'dimmed_text');
            } else if ($isbookedsession) {
                $row->attributes = array('class' => 'highlight');
            } else if ($sessionfull) {
                $row->attributes = array('class' => 'dimmed_text');
            }
            // Add row to table.
        }
        $temp = [];
        $items = [];

// Combine table header with row data
        foreach ($row as $item) {
            $temp[] = array_combine($tableheader, $item);
        }

// Create objects for each key-value pair
        foreach ($temp as $item) {
            $inserti = [];
            foreach ($item as $key => $value) {
                // Create a new object for each key-value pair
                $insert = new stdClass();
                $insert->header = $key;
                $insert->value = $value;

                // Add the object to the $items array
                $inserti[] = $insert;
            }
            $items[] = $inserti;
        }

        $data = [
            'dir' => $dir . '/mod/facetoface/lib.php',
            'cmid' => $cm->id,
            'course' => $course,
            'facetoface' => $facetoface,
            'locations' => $locations,
            'signupforstreamlink' => $signupforstreamlink,
            'tableheader' => $tableheader,
            'rows' => $items,
        ];
        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_facetoface/form_view', $data),
                ],
            ],
        ];
    }


    public static function signup($args)
    {
        global $DB, $OUTPUT, $CFG, $PAGE, $USER;
        $args = (object)$args;
        $dir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
        require_once($dir . '/config.php');
        require_once($dir . '/mod/facetoface/lib.php');

        $session = \facetoface_get_session($args->s);
        $facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface));
        $course = $DB->get_record('course', array('id' => $facetoface->course));
        $cm = get_coursemodule_from_instance("facetoface", $facetoface->id, $course->id);
        $context = \context_course::instance($course->id);
        $viewattendees = has_capability('mod/facetoface:viewattendees', $context);
        $customfields = facetoface_get_session_customfields();
        $customdata = $DB->get_records('facetoface_session_data', array('sessionid' => $session->id), '', 'fieldid, data');
        $table = new stdClass();
        foreach ($customfields as $field) {
            $data = '';
            if (!empty($customdata[$field->id])) {
                if (CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                    $values = explode(CUSTOMFIELD_DELIMITER, $customdata[$field->id]->data);
                    $data = implode(\html_writer::empty_tag('br'), $values);
                } else {
                    $data = $customdata[$field->id]->data;
                }
            }
            $insert = new stdClass();
            $insert->header = str_replace(' ', '&nbsp;', $field->name);
            $insert->value = $data;
            $table->data[] = $insert;
        }
        $strdatetime = str_replace(' ', '&nbsp;', get_string('sessiondatetime', 'facetoface'));
        if ($session->datetimeknown) {
            $html = '';
            foreach ($session->sessiondates as $date) {
                if (!empty($html)) {
                    $html .= '<br>';
                }
                $timestart = \userdate($date->timestart, 'strftimedatetime');
                $timefinish = \userdate($date->timefinish, 'strftimedatetime');
                $html .= "$timestart &ndash; $timefinish";
            }
            $insert = new stdClass();
            $insert->header = $strdatetime;
            $insert->value = $html;
            $table->data[] = $insert;
        } else {
            $insert = new stdClass();
            $insert->header = $strdatetime;
            $insert->value = \html_writer::tag('i', 'wait-listed');
            $table->data[] = $insert;
        }
        $signupcount = facetoface_get_num_attendees($session->id);
        $placesleft = $session->capacity - $signupcount;
        $calendaroutput = false;
        if ($viewattendees) {
            if ($session->allowoverbook) {
                $insert = new stdClass();
                $insert->header = get_string('capacity', 'facetoface');
                $insert->value = $session->capacity . ' (' . strtolower(get_string('allowoverbook', 'facetoface')) . ')';
                $table->data[] = $insert;
            } else {
                $insert = new stdClass();
                $insert->header = get_string('capacity', 'facetoface');
                $insert->value = $session->capacity;
                $table->data[] = $insert;
            }
        } else if (!$calendaroutput) {
            $insert = new stdClass();
            $insert->header = get_string('seatsavailable', 'facetoface');
            $insert->value = max(0, $placesleft);
            $table->data[] = $insert;
        }
        $facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface));
        if ($facetoface->approvalreqd) {
            $insert = new stdClass();
            $insert->header = '';
            $insert->value = et_string('sessionrequiresmanagerapproval', 'facetoface');
            $table->data[] = $insert;
        }

        // Display waitlist notification.
        if (!$hidesignup && $session->allowoverbook && $placesleft < 1) {
            $insert = new stdClass();
            $insert->header = '';
            $insert->value = get_string('userwillbewaitlisted', 'facetoface');
            $table->data[] = $insert;
        }

        if (!empty($session->duration)) {
            $insert = new stdClass();
            $insert->header = get_string('duration', 'facetoface');
            $insert->value = facetoface_format_duration($session->duration);
            $table->data[] = $insert;
        }
        if (!empty($session->normalcost)) {
            $insert = new stdClass();
            $insert->header = get_string('normalcost', 'facetoface');
            $insert->value = format_cost($session->normalcost);
            $table->data[] = $insert;
        }
        if (!empty($session->discountcost)) {
            $insert = new stdClass();
            $insert->header = get_string('discountcost', 'facetoface');
            $insert->value = format_cost($session->discountcost);
            $table->data[] = $insert;
        }
        if (!empty($session->details)) {
            $details = strip_tags($session->details);
            $insert = new stdClass();
            $insert->header = get_string('details', 'facetoface');
            $insert->value = $details;
            $table->data[] = $insert;
        }
        if ($trainerroles) {

            // Get trainers.
            $trainers = facetoface_get_trainers($session->id);
            foreach ($trainerroles as $role => $rolename) {
                $rolename = $rolename->name;

                if (empty($trainers[$role])) {
                    continue;
                }

                $trainernames = array();
                foreach ($trainers[$role] as $trainer) {
                    // $trainerurl = new moodle_url('/user/view.php', array('id' => $trainer->id));
                    // $trainernames[] = html_writer::link($trainerurl, fullname($trainer));
                }
//                $insert = new stdClass();
//                $insert->header = $rolename;
//                $insert->value = format_text($details, FORMAT_HTML, array('context' => \context_system::instance()));
//                $table->data[] = $insert;
//                $table->data[] = array($rolename, implode(', ', $trainernames));
            }
        }

        // Display trainers.
        $trainerroles = facetoface_get_trainer_roles();
        $data = [
            's' => $args->s,
            'backtoallsessions' => $args->backtoallsessions,
            'session' => $session,
            'table' => $table->data,
            'courseidi' => $args->courseidi,
            'c' => $args->c,
            'r' => $args->r
        ];
        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_facetoface/signup', $data),
                ],
            ],
        ];
    }

    public static function signupConfirm($args)
    {
        global $DB, $OUTPUT, $CFG, $PAGE, $USER;
        $args = (object)$args;
        $dir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
        require_once($dir . '/config.php');
        require_once($dir . '/mod/facetoface/lib.php');
        $session = \facetoface_get_session($args->s);
        $facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface));
        $course = $DB->get_record('course', array('id' => $facetoface->course));
        $courseurl = $CFG->wwwroot.'/course/view.php?id=' . $course->id;
        $cm = get_coursemodule_from_instance("facetoface", $facetoface->id, $course->id);
        $context = \context_course::instance($course->id);

        // Get signup type.
        if (!$session->datetimeknown) {
            $statuscode = MDL_F2F_STATUS_WAITLISTED;
        } else if (facetoface_get_num_attendees($session->id) < $session->capacity) {

            // Save available.
            $statuscode = MDL_F2F_STATUS_BOOKED;
        } else {
            $statuscode = MDL_F2F_STATUS_WAITLISTED;
        }

        $submissionid = facetoface_user_signup($session, $facetoface, $course, '', $args->notification, $statuscode, false, false);

        $message = get_string('bookingcompleted', 'facetoface');
        if ($session->datetimeknown && $facetoface->confirmationinstrmngr) {
            $message .= html_writer::empty_tag('br') . \html_writer::empty_tag('br')
                . get_string('confirmationsentmgr', 'facetoface');
        } else {
            $message .= \html_writer::empty_tag('br') . \html_writer::empty_tag('br') . get_string('confirmationsent', 'facetoface');
        }

        $timemessage = 4;

        $data = [
            'manager' => $args->manager,
            'notification' => $args->notification,
            's' => $args->s,
            'courseurl' => $courseurl,
            'courseid' => $args->courseid
        ];
        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_facetoface/signupConfirm', $data),
                ],
            ],
        ];
    }
}



