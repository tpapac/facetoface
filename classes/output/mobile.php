<?php

namespace mod_facetoface\output;

defined('MOODLE_INTERNAL') || die();

class mobile {

    public static function view_facetoface($args) {
	global $DB, $OUTPUT, $CFG;
	$args = (object) $args;
	$dir = 	dirname(dirname(dirname(dirname(dirname(__FILE__))))); 
	require_once($dir . '/config.php');
	require_once($dir . '/mod/facetoface/lib.php');
	require_once($dir . '/mod/facetoface/renderer.php');
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
	$ispis = '';
	$ispis .= $OUTPUT->box_start();
	$ispis .= $OUTPUT->heading(get_string('allsessionsin', 'facetoface', format_string($facetoface->name)), 2);
	$facetoface = $DB->get_record('facetoface', array('id'=>$cm->instance));
	if ($facetoface->intro) {
    		$ispis .= $OUTPUT->box_start('generalbox', 'description');
    		$ispis .= format_module_intro('facetoface', $facetoface, $cm->id);
    		$ispis .= $OUTPUT->box_end();
	} else {
    		$ispis .= \html_writer::empty_tag('br');
	}
	$locations = get_locations($facetoface->id);
	

	$ispis .= $OUTPUT->box_end();
        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' =>$ispis . 'asd',
                ],
            ],
        ];
    }

}
