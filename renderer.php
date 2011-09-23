<?php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/gradelib.php');

class gradeexport_jwc_renderer extends plugin_renderer_base {
    public function require_idnumber($courseid) {
        global $CFG;

        $url = new moodle_url('/course/edit.php', array('id' => $courseid));
        $link = html_writer::link($url, '设置课程编号');
        return $this->notification("此课程的课程编号为空，或者无效。请{$link}为教务处官方发布的课程编号。");
    }

    public function require_cas() {
        return $this->notification('为了安全，只有使用HITID登录的用户才能使用此功能。');
    }
}
