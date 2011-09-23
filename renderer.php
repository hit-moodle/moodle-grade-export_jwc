<?php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/gradelib.php');

class gradeexport_jwc_renderer extends plugin_renderer_base {
    public function require_idnumber($courseid) {
        global $CFG;

        $url = new moodle_url('/course/edit.php', array('id' => $courseid));
        $link = html_writer::link($url, '设置课程编号');
        return $this->box("此课程的课程编号为空，或者无效。请按照教务处官方发布的课程编号，{$link}。");
    }
}
