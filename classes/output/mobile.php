<?php

namespace mod_facetoface\output;

use mod_facetoface_signup_form;

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
        }
        else {
            $signupforstreamlink = false;
        }
        if (empty($upcomingarray) && empty($upcomingtbdarray)) {
            $emptyarray = true;
        } else {
            $upcomingarray = array_merge($upcomingarray, $upcomingtbdarray);
        }
        $podaci = [
            [
                'name' => 'ante',
                'prezime' => 'nesto'
            ],
            [
                'name' => 'anteqwe',
                'prezime' => 'nesto'
            ],
            [
                'name' => 'antxycvgfbe',
                'prezime' => 'nesto'
            ],
        ];

        $data = [
              'dir' => $dir . '/mod/facetoface/lib.php',
            'cmid' => $cm->id,
            'course' => $course,
            'facetoface' => $facetoface,
            'locations' => $locations,
            'signupforstreamlink' => $signupforstreamlink,
            'customfields' => json_encode($customfields),
            'podaci' => $podaci,
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



