<?php

namespace mod_facetoface\output;

defined('MOODLE_INTERNAL') || die();

class mobile {


    public static function view_facetoface($args) {
		$args = (object) $args;
		$cm = get_coursemodule_from_id('facetoface', $args->cmid);
		        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => '<h1>' . $cm->id . '</h1>',
                ],
            ],
        ];
    }

}
