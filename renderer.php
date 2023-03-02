<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Copyright (C) 2007-2011 Catalyst IT (http://www.catalyst.net.nz)
 * Copyright (C) 2011-2013 Totara LMS (http://www.totaralms.com)
 * Copyright (C) 2014 onwards Catalyst IT (http://www.catalyst-eu.net)
 *
 * @package    mod
 * @subpackage facetoface
 * @copyright  2014 onwards Catalyst IT <http://www.catalyst-eu.net>
 * @author     Stacey Walker <stacey@catalyst-eu.net>
 * @author     Alastair Munro <alastair.munro@totaralms.com>
 * @author     Aaron Barnes <aaron.barnes@totaralms.com>
 * @author     Francois Marier <francois@catalyst.net.nz>
 */

defined('MOODLE_INTERNAL') || die();

class mod_facetoface_renderer extends plugin_renderer_base {

    /**
     * Builds session list table given an array of sessions
     */
    public function print_session_list_table($customfields, $sessions, $viewattendees, $editsessions, $signuplinks = true) {
        global $OUTPUT;
        $tableheader = array();
        foreach ($customfields as $field) {
            if (!empty($field->showinsummary)) {
                $tableheader[] = format_string($field->name);
            }
        }
        $tableheader[] = get_string('date', 'facetoface');
        $tableheader[] = get_string('time', 'facetoface');
        if ($viewattendees) {
            $tableheader[] = get_string('capacity', 'facetoface');
        } else {
            $tableheader[] = get_string('seatsavailable', 'facetoface');
        }
        $tableheader[] = get_string('status', 'facetoface');
        $tableheader[] = get_string('options', 'facetoface');
        foreach ($sessions as $session) {
            $isbookedsession = false;
            $bookedsession = $session->bookedsession;
            $sessionstarted = false;
            $sessionfull = false;

            $sessionrow = array();

            // Custom fields.
            $customdata = $session->customfielddata;
            foreach ($customfields as $field) {
                if (empty($field->showinsummary)) {
                    continue;
                }

                if (empty($customdata[$field->id])) {
                    $sessionrow[] = '&nbsp;';
                } else {
                    if (CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                        $sessionrow[] = str_replace(CUSTOMFIELD_DELIMITER, '<br>', $customdata[$field->id]->data);
                    } else {
                        $sessionrow[] = $customdata[$field->id]->data;
                    }

                }
            }

            // Dates/times.
            $allsessiondates = '';
            $allsessiontimes = '';
            if ($session->datetimeknown) {
                foreach ($session->sessiondates as $date) {
                    if (!empty($allsessiondates)) {
                        $allsessiondates .= '<br>';
                    }
                    $allsessiondates .= userdate($date->timestart, get_string('strftimedate'));
                    if (!empty($allsessiontimes)) {
                        $allsessiontimes .= '<br>';
                    }
                    $allsessiontimes .= userdate($date->timestart, get_string('strftimetime')) .
                        ' - ' . userdate($date->timefinish, get_string('strftimetime'));
                }
            } else {
                $allsessiondates = get_string('wait-listed', 'facetoface');
                $allsessiontimes = get_string('wait-listed', 'facetoface');
                $sessionwaitlisted = true;
            }
            $sessionrow[] = $allsessiondates;
            $sessionrow[] = $allsessiontimes;

            // Capacity.
            $signupcount = facetoface_get_num_attendees($session->id, MDL_F2F_STATUS_APPROVED);
            $stats = $session->capacity - $signupcount;
            if ($viewattendees) {
                $stats = $signupcount . ' / ' . $session->capacity;
            } else {
                $stats = max(0, $stats);
            }
            $sessionrow[] = $stats;

            // Status.
            $status = get_string('bookingopen', 'facetoface');
            if ($session->datetimeknown && facetoface_has_session_started($session, $timenow) && facetoface_is_session_in_progress($session, $timenow)) {
                $status = get_string('sessioninprogress', 'facetoface');
                $sessionstarted = true;
            } else if ($session->datetimeknown && facetoface_has_session_started($session, $timenow)) {
                $status = get_string('sessionover', 'facetoface');
                $sessionstarted = true;
            } else if ($bookedsession && $session->id == $bookedsession->sessionid) {
                $signupstatus = facetoface_get_status($bookedsession->statuscode);
                $status = get_string('status_' . $signupstatus, 'facetoface');
                $isbookedsession = true;
            } else if ($signupcount >= $session->capacity) {
                $status = get_string('bookingfull', 'facetoface');
                $sessionfull = true;
            }

            $sessionrow[] = $status;

            // Options.
            $options = '';
            if ($editsessions) {
                $options .= '<a href="' . new \moodle_url('sessions.php', array('s' => $session->id, 'c' => 1)) . '"><ion-icon name="settings-outline"></ion-icon></a>';
                $options .= '<a href="' . new \moodle_url('sessions.php', array('s' => $session->id, 'c' => 1)) . '"><ion-icon name="copy-outline"></ion-icon></a>';
                $options .= '<a href="' . new \moodle_url('sessions.php', array('s' => $session->id, 'd' => 1)) . '"><ion-icon name="trash-outline"></ion-icon></a>';
            }
            if ($viewattendees) {
                $options .= \html_writer::link('attendees.php?s=' . $session->id . '&backtoallsessions=' . $session->facetoface,
                        get_string('attendees', 'facetoface'),
                        array('title' => get_string('seeattendees', 'facetoface'))) . ' &nbsp; ';
                $options .= $OUTPUT->action_icon(new \moodle_url('attendees.php', array('s' => $session->id, 'download' => 'xlsx')),
                        new \pix_icon('f/spreadsheet', get_string('downloadexcel')), null,
                        array('title' => get_string('downloadexcel'))) . ' ';
                $options .= $OUTPUT->action_icon(new \moodle_url('attendees.php', array('s' => $session->id, 'download' => 'ods')),
                        new \pix_icon('f/calc', get_string('downloadods')), null,
                        array('title' => get_string('downloadods'))) . ' ' . '<br>';
            }
            if ($isbookedsession) {
                $options .= \html_writer::link('signup.php?s=' . $session->id . '&backtoallsessions=' . $session->facetoface,
                        get_string('moreinfo', 'facetoface'),
                        array('title' => get_string('moreinfo', 'facetoface'))) . '<br>';
                if ($session->allowcancellations) {
                    $options .= \html_writer::link('cancelsignup.php?s=' . $session->id . '&backtoallsessions=' . $session->facetoface,
                        get_string('cancelbooking', 'facetoface'), array('title' => get_string('cancelbooking', 'facetoface')));
                }
            } else if (!$sessionstarted && !$bookedsession && $signuplinks) {
                $options .= '<ion-item>
                <ion-label><ion-button expand="block" color="light" core-site-plugins-new-content title="Signup"
                        component="mod_facetoface" method="signup"
                        [args]="{s: ' .  $session->id . ',' . 'backtoallsessions: ' . $session->facetoface . '}">
                    Signup
                </ion-button></ion-label>
            </ion-item>';
            }
            $args = '"{s: ' . ' $session->id . ' . ', backtoallsessions: ' . $session->facetoface . '}">';
            if (empty($options)) {
                $options = get_string('none', 'facetoface');
            }
            $sessionrow[] = $options;

            $row[] = $sessionrow;

            // Set the CSS class for the row.
            if ($sessionstarted) {
                $row->attributes = array('class' => 'dimmed_text');
            } else if ($isbookedsession) {
                $row->attributes = array('class' => 'highlight');
            } else if ($sessionfull) {
                $row->attributes = array('class' => 'dimmed_text');
            }
            // Add row to table.
        }
        foreach ($row as $item) {
            $item = array_combine($tableheader, $item);
        }

       var_dump($row);
    }
}
