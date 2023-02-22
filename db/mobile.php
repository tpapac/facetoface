<?php
global $CFG;
$addons = [
    'mod_facetoface' => [
        'handlers' => [
            'facetoface' => [
                'delegate' => 'CoreMainMenuHomeDelegate',
                'method' => 'view_facetoface',
                'displaydata' => [
                    'icon' => $CFG->wwwroot . '/mod/facetoface/pix/icon.gif',
                    'class' => '',
                ],
                'offlinefunctions' => [
                    'mobile_course_view' => [],
                    'mobile_issues_view' => [],
                ], // Function that needs to be downloaded for offline.
            ],
        ],
        'lang' => [
        ],
    ],
];