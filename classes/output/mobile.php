<?php

namespace mod_facetoface\output;

defined('MOODLE_INTERNAL') || die();

class mobile {

    public static function view_facetoface() {
        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => '<h1 class="text-center">Pale</h1>',
                ],
            ],
        ];
    }

}