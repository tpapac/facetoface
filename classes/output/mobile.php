<?php

namespace mod_facetoface\output;



defined('MOODLE_INTERNAL') || die();

class mobile
{

    public static function view_facetoface($args)
    {
        global $DB, $OUTPUT, $CFG, $PAGE;
        $args = (object)$args;
        $dir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
        require_once($dir . '/config.php');
        require_once($dir . '/mod/facetoface/lib.php');
        require_once($dir . '/mod/facetoface/renderermobile.php');


        $cmid = \get_coursemodule_from_id('facetoface', $args->cmid);
        $cm = $DB->get_record('course_modules', array('id' => $args->courseid));
        $course = $DB->get_record('course', array('id' => $cm->course));
        $facetoface = $DB->get_record('facetoface', array('id' => $cm->instance));
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
//            $signupforstreamlink = html_writer::link(
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
                $options .= $OUTPUT->action_icon(new moodle_url('sessions.php', array('s' => $session->id)),
                        new pix_icon('t/edit', get_string('edit', 'facetoface')), null,
                        array('title' => get_string('editsession', 'facetoface'))) . ' ';
                $options .= $OUTPUT->action_icon(new moodle_url('sessions.php', array('s' => $session->id, 'c' => 1)),
                        new pix_icon('t/copy', get_string('copy', 'facetoface')), null,
                        array('title' => get_string('copysession', 'facetoface'))) . ' ';
                $options .= $OUTPUT->action_icon(new moodle_url('sessions.php', array('s' => $session->id, 'd' => 1)),
                        new pix_icon('t/delete', get_string('delete', 'facetoface')), null,
                        array('title' => get_string('deletesession', 'facetoface'))) . ' ';
            }
            if ($viewattendees) {
                $options .= html_writer::link('attendees.php?s=' . $session->id . '&backtoallsessions=' . $session->facetoface,
                        get_string('attendees', 'facetoface'),
                        array('title' => get_string('seeattendees', 'facetoface'))) . ' &nbsp; ';
                $options .= $OUTPUT->action_icon(new moodle_url('attendees.php', array('s' => $session->id, 'download' => 'xlsx')),
                        new pix_icon('f/spreadsheet', get_string('downloadexcel')), null,
                        array('title' => get_string('downloadexcel'))) . ' ';
                $options .= $OUTPUT->action_icon(new moodle_url('attendees.php', array('s' => $session->id, 'download' => 'ods')),
                        new pix_icon('f/calc', get_string('downloadods')), null,
                        array('title' => get_string('downloadods'))) . ' ' . '<br>';
            }
            if ($isbookedsession) {
                $options .= html_writer::link('signup.php?s=' . $session->id . '&backtoallsessions=' . $session->facetoface,
                        get_string('moreinfo', 'facetoface'),
                        array('title' => get_string('moreinfo', 'facetoface'))) . '<br>';
                if ($session->allowcancellations) {
                    $options .= html_writer::link('cancelsignup.php?s=' . $session->id . '&backtoallsessions=' . $session->facetoface,
                        get_string('cancelbooking', 'facetoface'), array('title' => get_string('cancelbooking', 'facetoface')));
                }
            } else if (!$sessionstarted && !$bookedsession && $signuplinks) {
                $options .= html_writer::link('signup.php?s=' . $session->id . '&backtoallsessions=' . $session->facetoface,
                    get_string('signup', 'facetoface'));
            }

            $args = '"{s: ' . ' $session->id . ' . ', backtoallsessions: ' . $session->facetoface . '}">';
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

        $data = [
            'dir' => $dir . '/mod/facetoface/lib.php',
            'cmid' => $cm->id,
            'course' => $course,
            'facetoface' => $facetoface,
            'locations' => $locations,
            'signupforstreamlink' => $signupforstreamlink,
            'tableheader' => $tableheader,
            'row' => $row,
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
}



