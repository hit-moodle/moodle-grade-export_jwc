<?php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/gradelib.php');

class gradeexport_jwc_renderer extends plugin_renderer_base {
    public function require_idnumber($courseid, $candidates) {
        $courses = array();
        foreach ($candidates as $idnumber => $coursename) {
            $courses[] = "{$idnumber}（{$coursename}）";
        }
        $text = implode($courses, '，');
        $text = '此课程的课程编号为空，或者无效。请设置为教务处官方发布的课程编号。<br />您可以选择的包括：'.$text.'<br />请复制正确的编号到剪贴板，然后';

        $url = new moodle_url('/course/edit.php', array('id' => $courseid));
        $link = html_writer::link($url, '点击此处设置课程编号');

        return $this->notification($text.$link);
    }

    public function require_cas() {
        return $this->notification('为了安全，只有使用HITID登录的用户才能使用此功能。');
    }
}
