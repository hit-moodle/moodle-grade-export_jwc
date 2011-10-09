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

define('MAX_SUB_GRADE_COUNT', 8);
define('MAX_EXTRA_SUB_GRADE_COUNT', 2);
define('MAX_TOTAL_GRADE', 100);
define('KEY_EXPIRED_TIME', 300);

$key = optional_param('key', 0, PARAM_ALPHANUM);

if ($key) {
    // print xml
    $obj = $DB->get_record('grade_export_jwc', array('requestkey' => $key));
    if ($obj) {
        header('Content-type: application/xhtml+xml; charset=utf-8');
        echo $obj->xml;
    } else {
        echo '请求码无效或已过期';
    }
    die;
}

$id = required_param('id', PARAM_INT); // course id
$action = optional_param('action', '', PARAM_ACTION);

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

// 选择导出方式
if (empty($action)) {
    echo $output->choose_export_method();
    echo $output->footer();
    die;
}

if ($key = generate_jwc_xml($action == 'all')) {
    echo $output->success($key);
}

echo $output->footer();
// die here

function generate_jwc_xml($include_cats = false) {
    global $course, $output, $jwc, $DB;

    if ($include_cats) {
        echo $output->heading('导出分项成绩及总分到教务处');
    } else {
        echo $output->heading('导出总分到教务处');
    }

    //first make sure we have proper final grades - this must be done before constructing of the grade tree
    grade_regrade_final_grades($course->id);

    // 获得成绩类别和项信息
    $tree = new grade_tree($course->id, true, true);

    // 获取所有有效顶级成绩项，并整理数据
    $total_item = null;
    $sub_items = array();
    $extra_items = array();
    $tops = $tree->top_element['children'];
    $items = array();
    foreach ($tops as $top) {
        $children = end($top['children']);
        $grade_item = $children['object'];

        // 整理部分数据为整数，方便后面使用
        $grade_item->grademax = (int)$grade_item->grademax;
        $grade_item->aggregationcoef = (int)$grade_item->aggregationcoef;

        if ($grade_item->itemtype == 'course') {
            $grade_item->itemname = '总成绩';
            $total_item = $grade_item;
            continue;
        }

        if (!$include_cats || $grade_item->grademax <= 0) {
            continue;
        }

        if ($grade_item->itemtype == 'category') {
            //用类别名做成绩名
            $grade_item->itemname = $top['object']->fullname;
        }

        if ($grade_item->aggregationcoef) {
            // 额外加分
            $extra_items[$grade_item->id] = $grade_item;
        } else {
            $sub_items[$grade_item->id] = $grade_item;
        }
    }

    /// 验证成绩项是否符合教务处要求
    $result = true;

    // 总成绩满分必须是100分
    if ($total_item->grademax != MAX_TOTAL_GRADE) {
        echo $output->require_max_total_grade($total_item->grademax);
        $result = false;
    }

    if ($include_cats) {
        // 总成绩算法必须是“简单加权平均分”
        $total_aggregation = $tree->top_element['object']->aggregation;
        if ($total_aggregation != GRADE_AGGREGATE_WEIGHTED_MEAN2) {
            echo $output->require_aggregation($total_aggregation);
            $result = false;
        }

        // 子成绩项权重和必须为100
        // 所有非加分的分项相加为100，才合法，除非不包含子类别
        $weight_sum = 0;
        foreach ($sub_items as $item) {
            $weight_sum += $item->grademax;
        }
        if ($include_cats and $weight_sum != MAX_TOTAL_GRADE ) {
            echo $output->require_100_weight($weight_sum);
            $result = false;
        }

        // 子成绩项数量不能超过8
        if (count($sub_items) > MAX_SUB_GRADE_COUNT) {
            echo $output->require_max_subitems(count($sub_items));
            $result = false;
        }

        // 加分成绩项数量不能超过2
        if (count($extra_items) > MAX_EXTRA_SUB_GRADE_COUNT) {
            echo $output->require_max_extraitems(count($extra_items));
            $result = false;
        }
    }
    if (!$result) {
        echo $output->modify_items_link();
        return false;
    }

    echo $output->box_start();

    $xml = new gradebook_xml();

    echo '导出成绩项权重如下：';
    $itemtable = new html_table();
    $itemtable->head = array('成绩分项名称', '权重', '加分');
    foreach ($sub_items as $item) {
        $itemtable->data[] = new html_table_row(array($item->itemname, $item->grademax.'%', '否'));
        $xml->add_weight_item($item->id, $item->itemname, $item->grademax, $item->grademax);
    }
    $xml->add_empty_weight_item(MAX_SUB_GRADE_COUNT - count($sub_items));

    foreach ($extra_items as $item) {
        $itemtable->data[] = new html_table_row(array($item->itemname, $item->grademax.'%', '是'));
        $xml->add_weight_item($item->id, $item->itemname, $item->grademax, $item->grademax, true);
    }
    $xml->add_empty_weight_item(MAX_EXTRA_SUB_GRADE_COUNT - count($extra_items), true);

    $itemtable->data[] = new html_table_row(array($total_item->itemname, $total_item->grademax.'%', '-'));

    echo html_writer::table($itemtable);

    // 用户成绩
    echo '导出成绩如下：';
    $items = $sub_items + $extra_items;
    $items[$total_item->id] = $total_item;
    $geub = new grade_export_update_buffer();
    $gui = new graded_users_iterator($course, ($items));
    $gui->init();

    $usertable = new html_table();
    $usertable->head = array('姓名', '学号');
    foreach ($items as $item) {
        $usertable->head[] = $item->itemname;
    }
    while ($userdata = $gui->next_user()) {
        $user = $userdata->user;

        if ($user->auth != 'cas' || empty($user->idnumber)) {
            // 非cas用户成绩不可导出
            continue;
        }

        $row = array();
        $row[] = new html_table_cell($user->firstname);
        $row[] = new html_table_cell($user->idnumber);

        $grades = array();
        foreach ($userdata->grades as $itemid => $grade) {
            //$finalgrade = grade_format_gradevalue($grade->finalgrade, $items[$itemid], true, GRADE_DISPLAY_TYPE_REAL);
            $finalgrade = $grade->finalgrade;
            $row[] = new html_table_cell($finalgrade);
            if ($itemid != $total_item->id) {
                $grades[$itemid] = $finalgrade;
            } else {
                $grades[0] = $finalgrade;
            }
        }
        $xml->add_user($user->idnumber, $user->firstname, $grades);
        $usertable->data[] = new html_table_row($row);
    }
    $gui->close();
    $geub->close();
    echo html_writer::table($usertable);

    echo $output->box_end();

    // 存入数据库
    $new = new stdClass();
    $new->xml = $xml->saveXML();
    $new->requestkey = md5($new->xml);
    $new->expiredtime = time();
    if ($old = $DB->get_record('grade_export_jwc', array('requestkey' => $new->requestkey))) {
        $old->expiredtime = time() + KEY_EXPIRED_TIME;
        $DB->update_record('grade_export_jwc', $old);
    } else {
        $DB->insert_record('grade_export_jwc', $new);
    }

    return $new->requestkey;
}

class gradebook_xml extends DOMDocument {
    protected $gradebook;
    protected $weights;
    protected $grades;

    public function __construct() {
        parent::__construct('1.0', 'UTF-8');

        $node = $this->createElement('gradebook');
        $this->gradebook = $this->appendChild($node);
        $node = $this->createElement('weights');
        $this->weights = $this->gradebook->appendChild($node);
        $node = $this->createElement('grades');
        $this->grades = $this->gradebook->appendChild($node);
    }

    public function add_weight_item($id, $name, $weight, $maxgrade, $extra=false) {
        $node = $this->createElement('item');
        $node->setAttribute('id', $id);
        $node->setAttribute('name', $name);
        $node->setAttribute('weight', $maxgrade);
        $node->setAttribute('maxgrade', $maxgrade);
        $node->setAttribute('extra', $extra);
        $this->weights->appendChild($node);
    }

    public function add_empty_weight_item($count, $extra=false) {
        for ($i=0; $i<$count; $i++) {
            $node = $this->createElement('item');
            $node->setAttribute('id', 0);
            $node->setAttribute('name', '');
            $node->setAttribute('weight', 0);
            $node->setAttribute('maxgrade', 0);
            $node->setAttribute('extra', $extra);
            $this->weights->appendChild($node);
        }
    }

    public function add_user($idnumber, $name, array $grades) {
        $node = $this->createElement('user');
        $node->setAttribute('idnumber', $idnumber);
        $node->setAttribute('name', $name);
        $user_node = $this->grades->appendChild($node);
        foreach ($grades as $itemid => $grade) {
            if ($itemid != 0) {
                $node = $this->createElement('grade', $grade);
                $node->setAttribute('itemid', $itemid);
            } else {
                $node = $this->createElement('total', $grade);
            }
            $user_node->appendChild($node);
        }
    }
}

