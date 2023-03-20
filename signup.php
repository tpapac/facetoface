<?php
global $DB, $OUTPUT, $CFG, $PAGE, $USER;
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once('lib.php');
$session = \facetoface_get_session('1');
$facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface));
$course = $DB->get_record('course', array('id' => $facetoface->course));
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

$submissionid = facetoface_user_signup($session, $facetoface, $course, '', 'MDL_F2F_TEXT', $statuscode, false, false);

$message = get_string('bookingcompleted', 'facetoface');
if ($session->datetimeknown && $facetoface->confirmationinstrmngr) {
    $message .= html_writer::empty_tag('br') . \html_writer::empty_tag('br')
        . get_string('confirmationsentmgr', 'facetoface');
} else {
    $message .= \html_writer::empty_tag('br') . \html_writer::empty_tag('br') . get_string('confirmationsent', 'facetoface');
}

$timemessage = 4;