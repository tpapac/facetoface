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
	    $f2frenderer = $PAGE->get_renderer('mod_facetoface');
        $cmid = get_coursemodule_from_id('facetoface', $args->cmid);
        if ($args->courseid) {
            if (!$cm = $DB->get_record('course_modules', array('id' => $args->courseid))) {
                throw new \moodle_exception('error:incorrectcoursemoduleid', 'facetoface');
            }
            if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
                throw new \moodle_exception('error:coursemisconfigured', 'facetoface');
            }
            if (!$facetoface = $DB->get_record('facetoface', array('id' => $cm->instance))) {
                throw new \moodle_exception('error:incorrectcoursemodule', 'facetoface');
            }
        } else if ($f) {
            if (!$facetoface = $DB->get_record('facetoface', array('id' => $f))) {
                throw new \moodle_exception('error:incorrectfacetofaceid', 'facetoface');
            }
            if (!$course = $DB->get_record('course', array('id' => $facetoface->course))) {
                throw new \moodle_exception('error:coursemisconfigured', 'facetoface');
            }
            if (!$cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $course->id)) {
                throw new \moodle_exception('error:incorrectcoursemoduleid', 'facetoface');
            }
        } else {
            throw new \moodle_exception('error:mustspecifycoursemodulefacetoface', 'facetoface');
        }

        $context = \context_module::instance($cmid->id);
        global $ispis;
        $ispis .= $OUTPUT->box_start();
        $ispis .= $OUTPUT->heading(get_string('allsessionsin', 'facetoface', format_string($facetoface->name)), 2);
        $facetoface = $DB->get_record('facetoface', array('id' => $cm->instance));
        if ($facetoface->intro) {
            $ispis .= $OUTPUT->box_start('generalbox', 'description');
            $ispis .= format_module_intro('facetoface', $facetoface, $cm->id);
            $ispis .= $OUTPUT->box_end();
        } else {
            $ispis .= \html_writer::empty_tag('br');
        }
        $locations = self::get_locations($facetoface->id);
        if (count($locations) > 2) {
            $ispis .= \html_writer::start_tag('form', array('action' => 'view.php', 'method' => 'get', 'class' => 'formlocation'));
            $ispis .= \html_writer::start_tag('div');
            $ispis .= \html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'f', 'value' => $facetoface->id));
            $ispis .= \html_writer::select($locations, 'location', $location, '', array('onchange' => 'this.form.submit();'));
            $ispis .= \html_writer::end_tag('div') . \html_writer::end_tag('form');
        }

        self::print_session_list($course->id, $facetoface, $location);




        $ispis .= $OUTPUT->box_end();
        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $ispis,
                ],
            ],
        ];
    }
    public static function signup($args) {
	    $args = (object) $args;
        global $DB, $CFG, $OUTPUT, $USER;
        $dir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
        require_once($dir . '/config.php');
        require_once($dir . '/mod/facetoface/lib.php');
        $signupispis = '';

        if (!$session = facetoface_get_session($args->s)) {
            throw new \moodle_exception($args->s, 'facetoface', '', 'asd', 'wasdmkalsdmaslkd');
        }
        if (!$facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface))) {
            throw new \moodle_exception('error:incorrectfacetofaceid', 'facetoface');
        }
        if (!$course = $DB->get_record('course', array('id' => $facetoface->course))) {
            throw new \moodle_exception('error:coursemisconfigured', 'facetoface');
        }
        if (!$cm = get_coursemodule_from_instance("facetoface", $facetoface->id, $course->id)) {
            throw new \moodle_exception('error:incorrectcoursemoduleid', 'facetoface');
        }

        require_course_login($course, true, $cm);
        $context = \context_course::instance($course->id);
        $contextmodule = \context_module::instance($cm->id);
        require_capability('mod/facetoface:view', $context);

        $returnurl = "$CFG->wwwroot/course/view.php?id=$course->id";
        if ($args->backtoallsessions) {
            $returnurl = "$CFG->wwwroot/mod/facetoface/view.php?f=$backtoallsessions";
        }


// Guests can't signup for a session, so offer them a choice of logging in or going back.
        if (isguestuser()) {
            $loginurl = $CFG->wwwroot . '/login/index.php';
            if (!empty($CFG->loginhttps)) {
                $loginurl = str_replace('http:', 'https:', $loginurl);
            }


            $out = \html_writer::tag('p', get_string('guestsno', 'facetoface')) .
                \html_writer::empty_tag('br') .
                \html_writer::tag('p', get_string('continuetologin', 'facetoface'));
            $signupispis .= $OUTPUT->confirm($out, $loginurl, get_local_referer(false));
            exit();
        }

        $manageremail = false;
        if (get_config(null, 'facetoface_addchangemanageremail')) {
            $manageremail = facetoface_get_manageremail($USER->id);
        }

        $showdiscountcode = ($session->discountcost > 0);

        $mform = new \mod_facetoface_signup_form(null, compact('s', 'backtoallsessions', 'manageremail', 'showdiscountcode'));
        if ($mform->is_cancelled()) {
            redirect($returnurl);
        }

        $isbulksignup = $facetoface->multiplesignupmethod == MOD_FACETOFACE_SIGNUP_MULTIPLE_PER_ACTIVITY;
        if ($fromform = $mform->get_data()) { // Form submitted.

            if (empty($fromform->submitbutton)) {
                throw new \moodle_exception('error:unknownbuttonclicked', 'facetoface', $returnurl);
            }

            // User can not update Manager's email (depreciated functionality).
            if (!empty($fromform->manageremail)) {

                // Logging and events trigger.
                $params = array(
                    'context' => $contextmodule,
                    'objectid' => $session->id
                );
                $event = \mod_facetoface\event\update_manageremail_failed::create($params);
                $event->add_record_snapshot('facetoface_sessions', $session);
                $event->add_record_snapshot('facetoface', $facetoface);
                $event->trigger();
            }

            // Get signup type.
            if (!$session->datetimeknown) {
                $statuscode = MDL_F2F_STATUS_WAITLISTED;
            } else if (facetoface_get_num_attendees($session->id) < $session->capacity) {

                // Save available.
                $statuscode = MDL_F2F_STATUS_BOOKED;
            } else {
                $statuscode = MDL_F2F_STATUS_WAITLISTED;
            }

            if ($isbulksignup) {
                $error = '';
                $message = get_string('bookingcompleted', 'facetoface');

                foreach (facetoface_get_future_sessions($facetoface->id) as $session) {
                    if (!facetoface_session_has_capacity($session, $context) && (!$session->allowoverbook)) {
                        $error = \html_writer::empty_tag('br') . \html_writer::empty_tag('br') . get_string('somesessionsfull', 'facetoface');
                        continue;
                    }

                    // This shouldn't happen. Bulk signup can only be enabled when multiple signups are allowed.
                    if ($facetoface->signuptype == MOD_FACETOFACE_SIGNUP_SINGLE && facetoface_get_user_submissions($facetoface->id, $USER->id)) {
                        throw new \moodle_exception('alreadysignedup', 'facetoface', $returnurl);
                    }

                    if (facetoface_manager_needed($facetoface) && !facetoface_get_manageremail($USER->id)) {
                        throw new \moodle_exception('error:manageremailaddressmissing', 'facetoface', $returnurl);
                    }

                    if ($submissionid = facetoface_user_signup($session, $facetoface, $course, $fromform->discountcode, $fromform->notificationtype, $statuscode, false, false)) {
                        // Logging and events trigger.
                        $params = array(
                            'context' => $contextmodule,
                            'objectid' => $session->id
                        );
                        $event = \mod_facetoface\event\signup_success::create($params);
                        $event->add_record_snapshot('facetoface_sessions', $session);
                        $event->add_record_snapshot('facetoface', $facetoface);
                        $event->trigger();
                    }
                }

                $timemessage = 4;
                redirect($returnurl, $message . $error, $timemessage);
            }

            if (!facetoface_session_has_capacity($session, $context) && (!$session->allowoverbook)) {
                throw new \moodle_exception('sessionisfull', 'facetoface', $returnurl);
            } else if ($facetoface->signuptype == MOD_FACETOFACE_SIGNUP_SINGLE && facetoface_get_user_submissions($facetoface->id, $USER->id)) {
                throw new \moodle_exception('alreadysignedup', 'facetoface', $returnurl);
            } else if (facetoface_manager_needed($facetoface) && !facetoface_get_manageremail($USER->id)) {
                throw new \moodle_exception('error:manageremailaddressmissing', 'facetoface', $returnurl);
            } else if ($submissionid = facetoface_user_signup($session, $facetoface, $course, $fromform->discountcode, $fromform->notificationtype, $statuscode)) {

                // Logging and events trigger.
                $params = array(
                    'context' => $contextmodule,
                    'objectid' => $session->id
                );
                $event = \mod_facetoface\event\signup_success::create($params);
                $event->add_record_snapshot('facetoface_sessions', $session);
                $event->add_record_snapshot('facetoface', $facetoface);
                $event->trigger();

                $message = get_string('bookingcompleted', 'facetoface');
                if ($session->datetimeknown && $facetoface->confirmationinstrmngr) {
                    $message .= \html_writer::empty_tag('br') . \html_writer::empty_tag('br')
                        . get_string('confirmationsentmgr', 'facetoface');
                } else {
                    $message .= \html_writer::empty_tag('br') . \html_writer::empty_tag('br') . get_string('confirmationsent', 'facetoface');
                }

                $timemessage = 4;
                redirect($returnurl, $message, $timemessage);
            } else {

                // Logging and events trigger.
                $params = array(
                    'context' => $contextmodule,
                    'objectid' => $session->id
                );
                $event = \mod_facetoface\event\signup_failed::create($params);
                $event->add_record_snapshot('facetoface_sessions', $session);
                $event->add_record_snapshot('facetoface', $facetoface);
                $event->trigger();

                throw new \moodle_exception('error:problemsigningup', 'facetoface', $returnurl);
            }

            redirect($returnurl);
        } else if ($manageremail !== false) {

            // Set values for the form.
            $toform = new stdClass();
            $toform->manageremail = $manageremail;
            $mform->set_data($toform);
        }



        $heading = get_string('signupfor', 'facetoface', format_string($facetoface->name));

        $viewattendees = has_capability('mod/facetoface:viewattendees', $context);
        $signedup = facetoface_check_signup($facetoface->id);

        if ($facetoface->signuptype == MOD_FACETOFACE_SIGNUP_SINGLE && $signedup && $signedup != $session->id) {
            throw new \moodle_exception('error:signedupinothersession', 'facetoface', $returnurl);
        }

        $signupispis .= $OUTPUT->box_start();
        $signupispis .= $OUTPUT->heading($heading);

        $timenow = time();

        if (!$isbulksignup && $session->datetimeknown && facetoface_has_session_started($session, $timenow)) {
            $inprogressstr = get_string('cannotsignupsessioninprogress', 'facetoface');
            $overstr = get_string('cannotsignupsessionover', 'facetoface');

            $errorstring = facetoface_is_session_in_progress($session, $timenow) ? $inprogressstr : $overstr;

            $signupispis .= \html_writer::empty_tag('br') . $errorstring;
            $signupispis .= $OUTPUT->box_end();

            exit;
        }

        if (!$isbulksignup && !$signedup && !facetoface_session_has_capacity($session, $context) && (!$session->allowoverbook)) {
            throw new \moodle_exception('sessionisfull', 'facetoface', $returnurl);
            $signupispis .= $OUTPUT->box_end();

            exit;
        }

        if (!$isbulksignup) {
            $signupispis .= facetoface_print_session($session, $viewattendees);
        }

        if (!$isbulksignup && $signedup) {
            if (!($session->datetimeknown && facetoface_has_session_started($session, $timenow)) && $session->allowcancellations) {

                // Cancellation link.
                $cancellationurl = new moodle_url('cancelsignup.php', array('s' => $session->id, 'backtoallsessions' => $backtoallsessions));
                $signupispis .= \html_writer::link($cancellationurl, get_string('cancelbooking', 'facetoface'), array('title' => get_string('cancelbooking', 'facetoface')));
                $signupispis .= ' &ndash; ';
            }

            // See attendees link.
            if ($viewattendees) {
                $attendeesurl = new moodle_url('attendees.php', array('s' => $session->id, 'backtoallsessions' => $backtoallsessions));
                $signupispis .= \html_writer::link($attendeesurl, get_string('seeattendees', 'facetoface'), array('title' => get_string('seeattendees', 'facetoface')));
            }

            $signupispis .= \html_writer::empty_tag('br') . \html_writer::link($returnurl, get_string('goback', 'facetoface'), array('title' => get_string('goback', 'facetoface')));
        }

        $managerrequired = facetoface_manager_needed($facetoface) && !facetoface_get_manageremail($USER->id);
        if (!$signedup && $managerrequired) {

            // Don't allow signup to proceed if a manager is required.
            // Check to see if the user has a managers email set.
            $signupispis .= \html_writer::tag('p', \html_writer::tag('strong', get_string('error:manageremailaddressmissing', 'facetoface')));
            $signupispis .= \html_writer::empty_tag('br') . \html_writer::link($returnurl, get_string('goback', 'facetoface'), array('title' => get_string('goback', 'facetoface')));

        }

        $hascap = has_capability('mod/facetoface:signup', $context);
        if (!$signedup && !$managerrequired && !$hascap) {
            $signupispis .= \html_writer::tag('p', \html_writer::tag('strong', get_string('error:nopermissiontosignup', 'facetoface')));
            $signupispis .= \html_writer::empty_tag('br') . \html_writer::link(
                    $returnurl,
                    get_string('goback', 'facetoface'),
                    array('title' => get_string('goback', 'facetoface'))
                );
        }

        if ($facetoface->signuptype == MOD_FACETOFACE_SIGNUP_MULTIPLE || (!$signedup && !$managerrequired && $hascap)) {
            // Signup form.
         //   $mform->display();
        }

        $signupispis .= $OUTPUT->box_end();


        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' =>'<h1>Test</h1>' . $signupispis,
                ],
            ],
        ];
    }
    function print_session_list($courseid, $facetoface, $location)
    {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE, $ispis;

        $f2frenderer = $PAGE->get_renderer('mod_facetoface');
        $timenow = time();

        $context = \context_course::instance($courseid);
        $viewattendees = \has_capability('mod/facetoface:viewattendees', $context);
        $editsessions = \has_capability('mod/facetoface:editsessions', $context);
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

        // Upcoming sessions.
        $ispis .= $OUTPUT->heading(get_string('upcomingsessions', 'facetoface'));

        if (!empty($upcomingarray) && $bulksignup) {
            $firstsession = $sessions[array_keys($sessions)[0]];
            $signupforstreamlink = \html_writer::link(
                'signup.php?s=' . $firstsession->id . '&backtoallsessions=' . $session->facetoface,
                get_string('signupforstream', 'facetoface')
            );

            $ispis .= \html_writer::tag('p', $signupforstreamlink);
        }
        if (empty($upcomingarray) && empty($upcomingtbdarray)) {
            print_string('noupcoming', 'facetoface');
        } else {
            $upcomingarray = array_merge($upcomingarray, $upcomingtbdarray);
            $ispis .= \mod_facetoface_renderermobile::print_session_list_table($customfields, $upcomingarray, $viewattendees, $editsessions, !$bulksignup);
        }


        if ($editsessions) {
            $addsessionlink = \html_writer::link(
                new \moodle_url('sessions.php', array('f' => $facetoface->id)),
                get_string('addsession', 'facetoface')
            );
            $ispis .= \html_writer::tag('p', $addsessionlink);
        }

        // Previous sessions.

    }

    function get_locations($facetofaceid){
        global $CFG, $DB;

        $locationfieldid = $DB->get_field('facetoface_session_field', 'id', array('shortname' => 'location'));
        if (!$locationfieldid) {
            return array();
        }

        $sql = "SELECT DISTINCT d.data AS location
              FROM {facetoface} f
              JOIN {facetoface_sessions} s ON s.facetoface = f.id
              JOIN {facetoface_session_data} d ON d.sessionid = s.id
             WHERE f.id = ? AND d.fieldid = ?";

        if ($records = $DB->get_records_sql($sql, array($facetofaceid, $locationfieldid))) {
            $locationmenu[''] = get_string('alllocations', 'facetoface');

            $i = 1;
            foreach ($records as $record) {
                $locationmenu[$record->location] = format_string($record->location);
                $i++;
            }

            return $locationmenu;
        }

        return array();
    }


}
