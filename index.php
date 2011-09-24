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

require_once '../../../config.php';
require_once $CFG->dirroot.'/grade/export/lib.php';
require_once $CFG->dirroot.'/grade/export/jwc/locallib.php';

$id = required_param('id', PARAM_INT); // course id

$PAGE->set_url('/grade/export/jwc/index.php', array('id'=>$id));

if (!$course = $DB->get_record('course', array('id'=>$id))) {
    print_error('nocourseid');
}

require_login($course);
$context = get_context_instance(CONTEXT_COURSE, $id);

require_capability('moodle/grade:export', $context);
require_capability('gradeexport/jwc:view', $context);

print_grade_page_head($COURSE->id, 'export', 'jwc', get_string('exportto', 'grades') . ' ' . get_string('pluginname', 'gradeexport_jwc'));

if (!empty($CFG->gradepublishing)) {
    $CFG->gradepublishing = has_capability('gradeexport/jwc:publish', $context);
}

$output = $PAGE->get_renderer('gradeexport_jwc');

// CAS用户？
if ($USER->auth != 'cas') {
    echo $output->require_cas();
    echo $output->footer();
    die;
}

$jwc = get_jwc_instance();

// 设置教师编号
$jwcid = $DB->get_field('grade_export_jwc', 'jwcid', array('userid' => $USER->id));
if (!$jwcid or !$jwc->auth_user($USER, $jwcid)) {
    $form = new setup_jwcid_form(new moodle_url('/grade/export/jwc/index.php', array('id' =>$id)), array($USER));
    if ($jwcid) {
        echo $output->notification('教师编号有误，请重新设置');
        $form->set_data(array('jwcid' => $jwcid));
    }
    if ($data = $form->get_data()) {
        $jwcid = $data->jwcid;
        $data->userid = $USER->id;
        $DB->insert_record('grade_export_jwc', $data);
    } else {
        $form->display();
        echo $output->footer();
        die;
    }
}

// 课程编号是否存在
if (empty($course->idnumber)) {
    $current_courses = $jwc->get_courses($jwcid);
    echo $output->require_idnumber($course->id, $current_courses);
    echo $output->footer();
    die;
}

echo $output->footer();

