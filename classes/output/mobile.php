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
        require_once($dir . '/mod/facetoface/renderer.php');
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
                    'html' => $PAGE->url . 'asd',
                ],
            ],
        ];
    }
    public static function signup() {
        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => '<h1 class="text-center">SIGNUP</h1>',
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
            $signupforstreamlink = \html_writer::div('<ion-item>
                <ion-label><ion-button expand="block" color="light" core-site-plugins-new-content title="xxx"
                        component="mod_facetoface" method="signup"
                        [args]="">
                   
                </ion-button></ion-label>
            </ion-item>');


//            link(
//                'signup.php?s=' . $firstsession->id . '&backtoallsessions=' . $session->facetoface,
//                get_string('signupforstream', 'facetoface')
//            );

            $ispis .= \html_writer::tag('p', $signupforstreamlink);
        }
        if (empty($upcomingarray) && empty($upcomingtbdarray)) {
            print_string('noupcoming', 'facetoface');
        } else {
            $upcomingarray = array_merge($upcomingarray, $upcomingtbdarray);
            $ispis .= $f2frenderer->print_session_list_table($customfields, $upcomingarray, $viewattendees, $editsessions, !$bulksignup);
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
