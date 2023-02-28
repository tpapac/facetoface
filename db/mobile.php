<?php
global $CFG;
$addons = [
    'mod_facetoface' => [
        'handlers' => [
            'facetoface' => [
                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'view_facetoface',
                'displaydata' => [
                    'icon' => $CFG->wwwroot . '/mod/facetoface/pix/icon.png',
                    'class' => '',
                ],
                'offlinefunctions' => [
                    'mobile_course_view' => [],
                    'mobile_issues_view' => [],
                ], // Function that needs to be downloaded for offline.
            ],
        ],
        'lang' => [
            ['allsessionsin', 'facetoface']
        ],
    ],
];
