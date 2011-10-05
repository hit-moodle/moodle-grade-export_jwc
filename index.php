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
if (!$jwcid or !$jwc->set_user($USER, $jwcid)) {
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
if (!$jwc->set_course($course)) {
    $current_courses = $jwc->get_courses();
    echo $output->require_idnumber($course->id, $current_courses);
    echo $output->footer();
    die;
}

// 选择导出方式
$action = optional_param('action', '', PARAM_ACTION);
if (empty($action)) {
    echo $output->choose_export_method();
} else {
    $confirmed = optional_param('confirmed', 0, PARAM_BOOL);
    export_to_jwc($action == 'all', !$confirmed);
}

echo $output->footer();
// die here

function export_to_jwc($include_cats = false, $dryrun = true) {
    global $course, $output;

    if ($include_cats) {
        echo $output->heading('导出分项成绩及总分到教务处');
    } else {
        echo $output->heading('导出总分到教务处');
    }

    //first make sure we have proper final grades - this must be done before constructing of the grade tree
    grade_regrade_final_grades($course->id);

    // 获得成绩类别和项信息
    $tree = new grade_tree($course->id, true, true);

    // 总成绩算法必须是“简单加权平均分”
    $total_aggregation = $tree->top_element['object']->aggregation;
    if ($total_aggregation != GRADE_AGGREGATE_WEIGHTED_MEAN2) {
        echo $output->require_aggregation($course->id, $total_aggregation);
        echo $output->footer();
        die;
    }

    // 处理顶级成绩项
    $tops = $tree->top_element['children'];
    foreach ($tops as $top) {
        $children = end($top['children']);
        $grade_item = $children['object'];

        if (!$include_cats and $grade_item->itemtype != 'course') {
            continue;
        }

        if ($grade_item->itemtype == 'course') {
            $grade_item->itemname = '总成绩';
        } else if ($grade_item->itemtype == 'category') {
            //用类别名做成绩名
            $grade_item->itemname = $top['object']->fullname;
        }

        $moodle_cols[$grade_item->id] = $grade_item;
    }
    echo '本站顶级成绩项：';
    $names = array();
    foreach ($moodle_cols as $col) {
        $names[] = $col->itemname;
    }
    echo implode('，', $names).'<br />';

    // 用户成绩
    $geub = new grade_export_update_buffer();
    $gui = new graded_users_iterator($course, ($moodle_cols));
    $gui->init();

    while ($userdata = $gui->next_user()) {
        foreach($userdata->grades as $itemid => $grade) {
        }
    }
}
