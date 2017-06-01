<?php

namespace enrol_oes\traits\plugin;

/*
  Moodle enrol plugin base "expiration"-based methods
 */
trait expires_enrollments {

    /**
     * Do any enrolments need expiration processing.
     *
     * Plugins that want to call this functionality must implement 'expiredaction' config setting.
     *
     * @param progress_trace $trace
     * @param int $courseid one course, empty mean all
     * @return bool true if any data processed, false if not
     */
    public function process_expirations(progress_trace $trace, $courseid = null) {
        global $DB;

        $name = $this->get_name();
        if (!enrol_is_enabled($name)) {
            $trace->finished();
            return false;
        }

        $processed = false;
        $params = array();
        $coursesql = "";
        if ($courseid) {
            $coursesql = "AND e.courseid = :courseid";
        }

        // Deal with expired accounts.
        $action = $this->get_config('expiredaction', ENROL_EXT_REMOVED_KEEP);

        if ($action == ENROL_EXT_REMOVED_UNENROL) {
            $instances = array();
            $sql = "SELECT ue.*, e.courseid, c.id AS contextid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = :enrol)
                      JOIN {context} c ON (c.instanceid = e.courseid AND c.contextlevel = :courselevel)
                     WHERE ue.timeend > 0 AND ue.timeend < :now $coursesql";
            $params = array('now'=>time(), 'courselevel'=>CONTEXT_COURSE, 'enrol'=>$name, 'courseid'=>$courseid);

            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $ue) {
                if (!$processed) {
                    $trace->output("Starting processing of enrol_$name expirations...");
                    $processed = true;
                }
                if (empty($instances[$ue->enrolid])) {
                    $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
                }
                $instance = $instances[$ue->enrolid];
                if (!$this->roles_protected()) {
                    // Let's just guess what extra roles are supposed to be removed.
                    if ($instance->roleid) {
                        role_unassign($instance->roleid, $ue->userid, $ue->contextid);
                    }
                }
                // The unenrol cleans up all subcontexts if this is the only course enrolment for this user.
                $this->unenrol_user($instance, $ue->userid);
                $trace->output("Unenrolling expired user $ue->userid from course $instance->courseid", 1);
            }
            $rs->close();
            unset($instances);

        } else if ($action == ENROL_EXT_REMOVED_SUSPENDNOROLES or $action == ENROL_EXT_REMOVED_SUSPEND) {
            $instances = array();
            $sql = "SELECT ue.*, e.courseid, c.id AS contextid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = :enrol)
                      JOIN {context} c ON (c.instanceid = e.courseid AND c.contextlevel = :courselevel)
                     WHERE ue.timeend > 0 AND ue.timeend < :now
                           AND ue.status = :useractive $coursesql";
            $params = array('now'=>time(), 'courselevel'=>CONTEXT_COURSE, 'useractive'=>ENROL_USER_ACTIVE, 'enrol'=>$name, 'courseid'=>$courseid);
            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $ue) {
                if (!$processed) {
                    $trace->output("Starting processing of enrol_$name expirations...");
                    $processed = true;
                }
                if (empty($instances[$ue->enrolid])) {
                    $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
                }
                $instance = $instances[$ue->enrolid];

                if ($action == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                    if (!$this->roles_protected()) {
                        // Let's just guess what roles should be removed.
                        $count = $DB->count_records('role_assignments', array('userid'=>$ue->userid, 'contextid'=>$ue->contextid));
                        if ($count == 1) {
                            role_unassign_all(array('userid'=>$ue->userid, 'contextid'=>$ue->contextid, 'component'=>'', 'itemid'=>0));

                        } else if ($count > 1 and $instance->roleid) {
                            role_unassign($instance->roleid, $ue->userid, $ue->contextid, '', 0);
                        }
                    }
                    // In any case remove all roles that belong to this instance and user.
                    role_unassign_all(array('userid'=>$ue->userid, 'contextid'=>$ue->contextid, 'component'=>'enrol_'.$name, 'itemid'=>$instance->id), true);
                    // Final cleanup of subcontexts if there are no more course roles.
                    if (0 == $DB->count_records('role_assignments', array('userid'=>$ue->userid, 'contextid'=>$ue->contextid))) {
                        role_unassign_all(array('userid'=>$ue->userid, 'contextid'=>$ue->contextid, 'component'=>'', 'itemid'=>0), true);
                    }
                }

                $this->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                $trace->output("Suspending expired user $ue->userid in course $instance->courseid", 1);
            }
            $rs->close();
            unset($instances);

        } else {
            // ENROL_EXT_REMOVED_KEEP means no changes.
        }

        if ($processed) {
            $trace->output("...finished processing of enrol_$name expirations");
        } else {
            $trace->output("No expired enrol_$name enrolments detected");
        }
        $trace->finished();

        return $processed;
    }

    /**
     * Send expiry notifications.
     *
     * Plugin that wants to have expiry notification MUST implement following:
     * - expirynotifyhour plugin setting,
     * - configuration options in instance edit form (expirynotify, notifyall and expirythreshold),
     * - notification strings (expirymessageenrollersubject, expirymessageenrollerbody,
     *   expirymessageenrolledsubject and expirymessageenrolledbody),
     * - expiry_notification provider in db/messages.php,
     * - upgrade code that sets default thresholds for existing courses (should be 1 day),
     * - something that calls this method, such as cron.
     *
     * @param progress_trace $trace (accepts bool for backwards compatibility only)
     */
    public function send_expiry_notifications($trace) {
        global $DB, $CFG;

        $name = $this->get_name();
        if (!enrol_is_enabled($name)) {
            $trace->finished();
            return;
        }

        // Unfortunately this may take a long time, it should not be interrupted,
        // otherwise users get duplicate notification.

        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);


        $expirynotifylast = $this->get_config('expirynotifylast', 0);
        $expirynotifyhour = $this->get_config('expirynotifyhour');
        if (is_null($expirynotifyhour)) {
            debugging("send_expiry_notifications() in $name enrolment plugin needs expirynotifyhour setting");
            $trace->finished();
            return;
        }

        if (!($trace instanceof progress_trace)) {
            $trace = $trace ? new text_progress_trace() : new null_progress_trace();
            debugging('enrol_plugin::send_expiry_notifications() now expects progress_trace instance as parameter!', DEBUG_DEVELOPER);
        }

        $timenow = time();
        $notifytime = usergetmidnight($timenow, $CFG->timezone) + ($expirynotifyhour * 3600);

        if ($expirynotifylast > $notifytime) {
            $trace->output($name.' enrolment expiry notifications were already sent today at '.userdate($expirynotifylast, '', $CFG->timezone).'.');
            $trace->finished();
            return;

        } else if ($timenow < $notifytime) {
            $trace->output($name.' enrolment expiry notifications will be sent at '.userdate($notifytime, '', $CFG->timezone).'.');
            $trace->finished();
            return;
        }

        $trace->output('Processing '.$name.' enrolment expiration notifications...');

        // Notify users responsible for enrolment once every day.
        $sql = "SELECT ue.*, e.expirynotify, e.notifyall, e.expirythreshold, e.courseid, c.fullname
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = :name AND e.expirynotify > 0 AND e.status = :enabled)
                  JOIN {course} c ON (c.id = e.courseid)
                  JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0)
                 WHERE ue.status = :active AND ue.timeend > 0 AND ue.timeend > :now1 AND ue.timeend < (e.expirythreshold + :now2)
              ORDER BY ue.enrolid ASC, u.lastname ASC, u.firstname ASC, u.id ASC";
        $params = array('enabled'=>ENROL_INSTANCE_ENABLED, 'active'=>ENROL_USER_ACTIVE, 'now1'=>$timenow, 'now2'=>$timenow, 'name'=>$name);

        $rs = $DB->get_recordset_sql($sql, $params);

        $lastenrollid = 0;
        $users = array();

        foreach($rs as $ue) {
            if ($lastenrollid and $lastenrollid != $ue->enrolid) {
                $this->notify_expiry_enroller($lastenrollid, $users, $trace);
                $users = array();
            }
            $lastenrollid = $ue->enrolid;

            $enroller = $this->get_enroller($ue->enrolid);
            $context = context_course::instance($ue->courseid);

            $user = $DB->get_record('user', array('id'=>$ue->userid));

            $users[] = array('fullname'=>fullname($user, has_capability('moodle/site:viewfullnames', $context, $enroller)), 'timeend'=>$ue->timeend);

            if (!$ue->notifyall) {
                continue;
            }

            if ($ue->timeend - $ue->expirythreshold + 86400 < $timenow) {
                // Notify enrolled users only once at the start of the threshold.
                $trace->output("user $ue->userid was already notified that enrolment in course $ue->courseid expires on ".userdate($ue->timeend, '', $CFG->timezone), 1);
                continue;
            }

            $this->notify_expiry_enrolled($user, $ue, $trace);
        }
        $rs->close();

        if ($lastenrollid and $users) {
            $this->notify_expiry_enroller($lastenrollid, $users, $trace);
        }

        $trace->output('...notification processing finished.');
        $trace->finished();

        $this->set_config('expirynotifylast', $timenow);
    }

    /**
     * Notify user about incoming expiration of their enrolment,
     * it is called only if notification of enrolled users (aka students) is enabled in course.
     *
     * This is executed only once for each expiring enrolment right
     * at the start of the expiration threshold.
     *
     * @param stdClass $user
     * @param stdClass $ue
     * @param progress_trace $trace
     */
    protected function notify_expiry_enrolled($user, $ue, progress_trace $trace) {
        global $CFG;

        $name = $this->get_name();

        $oldforcelang = force_current_language($user->lang);

        $enroller = $this->get_enroller($ue->enrolid);
        $context = context_course::instance($ue->courseid);

        $a = new stdClass();
        $a->course   = format_string($ue->fullname, true, array('context'=>$context));
        $a->user     = fullname($user, true);
        $a->timeend  = userdate($ue->timeend, '', $user->timezone);
        $a->enroller = fullname($enroller, has_capability('moodle/site:viewfullnames', $context, $user));

        $subject = get_string('expirymessageenrolledsubject', 'enrol_'.$name, $a);
        $body = get_string('expirymessageenrolledbody', 'enrol_'.$name, $a);

        $message = new stdClass();
        $message->notification      = 1;
        $message->component         = 'enrol_'.$name;
        $message->name              = 'expiry_notification';
        $message->userfrom          = $enroller;
        $message->userto            = $user;
        $message->subject           = $subject;
        $message->fullmessage       = $body;
        $message->fullmessageformat = FORMAT_MARKDOWN;
        $message->fullmessagehtml   = markdown_to_html($body);
        $message->smallmessage      = $subject;
        $message->contexturlname    = $a->course;
        $message->contexturl        = (string)new moodle_url('/course/view.php', array('id'=>$ue->courseid));

        if (message_send($message)) {
            $trace->output("notifying user $ue->userid that enrolment in course $ue->courseid expires on ".userdate($ue->timeend, '', $CFG->timezone), 1);
        } else {
            $trace->output("error notifying user $ue->userid that enrolment in course $ue->courseid expires on ".userdate($ue->timeend, '', $CFG->timezone), 1);
        }

        force_current_language($oldforcelang);
    }

    /**
     * Notify person responsible for enrolments that some user enrolments will be expired soon,
     * it is called only if notification of enrollers (aka teachers) is enabled in course.
     *
     * This is called repeatedly every day for each course if there are any pending expiration
     * in the expiration threshold.
     *
     * @param int $eid
     * @param array $users
     * @param progress_trace $trace
     */
    protected function notify_expiry_enroller($eid, $users, progress_trace $trace) {
        global $DB;

        $name = $this->get_name();

        $instance = $DB->get_record('enrol', array('id'=>$eid, 'enrol'=>$name));
        $context = context_course::instance($instance->courseid);
        $course = $DB->get_record('course', array('id'=>$instance->courseid));

        $enroller = $this->get_enroller($instance->id);
        $admin = get_admin();

        $oldforcelang = force_current_language($enroller->lang);

        foreach($users as $key=>$info) {
            $users[$key] = '* '.$info['fullname'].' - '.userdate($info['timeend'], '', $enroller->timezone);
        }

        $a = new stdClass();
        $a->course    = format_string($course->fullname, true, array('context'=>$context));
        $a->threshold = get_string('numdays', '', $instance->expirythreshold / (60*60*24));
        $a->users     = implode("\n", $users);
        $a->extendurl = (string)new moodle_url('/enrol/users.php', array('id'=>$instance->courseid));

        $subject = get_string('expirymessageenrollersubject', 'enrol_'.$name, $a);
        $body = get_string('expirymessageenrollerbody', 'enrol_'.$name, $a);

        $message = new stdClass();
        $message->notification      = 1;
        $message->component         = 'enrol_'.$name;
        $message->name              = 'expiry_notification';
        $message->userfrom          = $admin;
        $message->userto            = $enroller;
        $message->subject           = $subject;
        $message->fullmessage       = $body;
        $message->fullmessageformat = FORMAT_MARKDOWN;
        $message->fullmessagehtml   = markdown_to_html($body);
        $message->smallmessage      = $subject;
        $message->contexturlname    = $a->course;
        $message->contexturl        = $a->extendurl;

        if (message_send($message)) {
            $trace->output("notifying user $enroller->id about all expiring $name enrolments in course $instance->courseid", 1);
        } else {
            $trace->output("error notifying user $enroller->id about all expiring $name enrolments in course $instance->courseid", 1);
        }

        force_current_language($oldforcelang);
    }

}