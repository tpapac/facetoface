<?php

namespace mod_facetoface\output;

defined('MOODLE_INTERNAL') || die();

class mobile {

    public static function view_facetoface($args) {


        require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
        require_once('lib.php');
        require_once('renderer.php');

        global $DB, $OUTPUT;

        $id = optional_param('id', 0, PARAM_INT); // Course Module ID.
        $f = optional_param('f', 0, PARAM_INT); // Facetoface ID.
        $location = optional_param('location', '', PARAM_TEXT); // Location.
        $download = optional_param('download', '', PARAM_ALPHA); // Download attendance.

        if ($id) {
            if (!$cm = $DB->get_record('course_modules', array('id' => $id))) {
                throw new moodle_exception('error:incorrectcoursemoduleid', 'facetoface');
            }
            if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
                throw new moodle_exception('error:coursemisconfigured', 'facetoface');
            }
            if (!$facetoface = $DB->get_record('facetoface', array('id' => $cm->instance))) {
                throw new moodle_exception('error:incorrectcoursemodule', 'facetoface');
            }
        } else if ($f) {
            if (!$facetoface = $DB->get_record('facetoface', array('id' => $f))) {
                throw new moodle_exception('error:incorrectfacetofaceid', 'facetoface');
            }
            if (!$course = $DB->get_record('course', array('id' => $facetoface->course))) {
                throw new moodle_exception('error:coursemisconfigured', 'facetoface');
            }
            if (!$cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $course->id)) {
                throw new moodle_exception('error:incorrectcoursemoduleid', 'facetoface');
            }
        } else {
            throw new moodle_exception('error:mustspecifycoursemodulefacetoface', 'facetoface');
        }

        $context = context_module::instance($cm->id);
        $PAGE->set_url('/mod/facetoface/view.php', array('id' => $cm->id));
        $PAGE->set_context($context);
        $PAGE->set_cm($cm);
        $PAGE->set_pagelayout('standard');

        if (!empty($download)) {
            require_capability('mod/facetoface:viewattendees', $context);
            facetoface_download_attendance($facetoface->name, $facetoface->id, $location, $download);
            exit();
        }

        require_course_login($course, true, $cm);
        require_capability('mod/facetoface:view', $context);

// Logging and events trigger.
        $params = array(
            'context'  => $context,
            'objectid' => $facetoface->id
        );
        $event = \mod_facetoface\event\course_module_viewed::create($params);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('facetoface', $facetoface);
        $event->trigger();

        $title = $course->shortname . ': ' . format_string($facetoface->name);

        $PAGE->set_title($title);
        $PAGE->set_heading($course->fullname);

        $pagetitle = format_string($facetoface->name);

        $f2frenderer = $PAGE->get_renderer('mod_facetoface');

        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        $header = $OUTPUT->header();

        if (empty($cm->visible) and !has_capability('mod/facetoface:viewemptyactivities', $context)) {
            notice(get_string('activityiscurrentlyhidden'));
        }
        echo $OUTPUT->box_start();
        echo $OUTPUT->heading(get_string('allsessionsin', 'facetoface', format_string($facetoface->name)), 2);

        if ($facetoface->intro) {
            echo $OUTPUT->box_start('generalbox', 'description');
            echo format_module_intro('facetoface', $facetoface, $cm->id);
            echo $OUTPUT->box_end();
        } else {
            echo html_writer::empty_tag('br');
        }
        $locations = get_locations($facetoface->id);
        if (count($locations) > 2) {
            echo html_writer::start_tag('form', array('action' => 'view.php', 'method' => 'get', 'class' => 'formlocation'));
            echo html_writer::start_tag('div');
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'f', 'value' => $facetoface->id));
            echo html_writer::select($locations, 'location', $location, '', array('onchange' => 'this.form.submit();'));
            echo html_writer::end_tag('div'). html_writer::end_tag('form');
        }

        print_session_list($course->id, $facetoface, $location);

        if (has_capability('mod/facetoface:viewattendees', $context)) {
            echo $OUTPUT->heading(get_string('exportattendance', 'facetoface'));
            echo html_writer::start_tag('form', array('action' => 'view.php', 'method' => 'get'));
            echo html_writer::start_tag('div');
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'f', 'value' => $facetoface->id));
            echo get_string('format', 'facetoface') . '&nbsp;';
            $formats = array('excel' => get_string('excelformat', 'facetoface'),
                'ods' => get_string('odsformat', 'facetoface'));
            echo html_writer::select($formats, 'download', 'excel', '');
            echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('exporttofile', 'facetoface')));
            echo html_writer::end_tag('div'). html_writer::end_tag('form');
        }

        echo $OUTPUT->box_end();
        echo $OUTPUT->footer($course);



        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $header,
                ],
            ],
        ];
    }

}
