<?php

namespace mod_facetoface\output;

defined('MOODLE_INTERNAL') || die();

class mobile {

    public static function view_facetoface($args) {
        global $DB, $OUTPUT, $CFG;
        require_once($CFG->wwwroot . '/config.php');
        require_once($CFG->wwwroot . 'mod/facetoface/lib.php');
        require_once($CFG->wwwroot . 'mod/facetoface/renderer.php');
        $id = optional_param('id', 0, PARAM_INT); // Course Module ID.

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $id,
                ],
            ],
        ];
    }

}
