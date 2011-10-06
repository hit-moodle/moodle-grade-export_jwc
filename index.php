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
$action = optional_param('action', '', PARAM_ACTION);
$confirmed = optional_param('confirmed', 0, PARAM_BOOL);

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
if (empty($action)) {
    echo $output->choose_export_method();
    echo $output->footer();
    die;
}

$dryrun = !$confirmed;
if ($dryrun) {
    echo $output->notification('现在是模拟运行，不会改写教务处数据库');
}

if (export_to_jwc($action == 'all', $dryrun)) {
    if ($dryrun) {
        echo $output->notification('模拟运行结束，未发现问题。如果您对上面信息没有异议，请点击下面的按钮，正式将数据导出。');
        $url = $PAGE->url;
        $url->params(array('action' => $action, 'confirmed' => 1));
        echo $output->single_button($url, '将成绩导出到教务处');
    } else {
        echo $output->success();
    }
}

echo $output->footer();
// die here

function export_to_jwc($include_cats = false, $dryrun = true) {
    global $course, $output, $jwc;

    $jwc->dryrun = $dryrun;

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
    $items = array();
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

        if ($grade_item->grademax > 0) { //ignore 0 max grade items
            // 整理数据
            $grade_item->grademax = (int)$grade_item->grademax;
            $grade_item->aggregationcoef = (int)$grade_item->aggregationcoef;

            $items[$grade_item->id] = $grade_item;
        }
    }

    echo $output->box_start();

    echo '导出成绩项如下：';
    $itemtable = new html_table();
    $itemtable->head = array('成绩分项名称', '权重', '加分');
    foreach ($items as $item) {
        if ($item->itemtype == 'course') {
            $extracredit = '-';
        } else {
            $extracredit = $item->aggregationcoef ? '是' : '否';
        }
        $itemtable->data[] = new html_table_row(array($item->itemname, $item->grademax, $extracredit));
    }
    echo html_writer::table($itemtable);

    // 导出权重
    if (!$jwc->export_weights($items)) {
        echo $output->error_text('成绩项导出出错！');
        echo $output->box_end();
        return false;
    }

    // 用户成绩
    $geub = new grade_export_update_buffer();
    $gui = new graded_users_iterator($course, $items);
    $gui->init();

    while ($userdata = $gui->next_user()) {
        foreach($userdata->grades as $itemid => $grade) {
        }
    }

    echo $output->box_end();

    return true;
}
