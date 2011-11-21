<?php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/gradelib.php');

class gradeexport_jwc_renderer extends plugin_renderer_base {

    public function require_aggregation($agg) {
        $aggnames = array(GRADE_AGGREGATE_MEAN             => get_string('aggregatemean', 'grades'),
            GRADE_AGGREGATE_WEIGHTED_MEAN    => get_string('aggregateweightedmean', 'grades'),
            GRADE_AGGREGATE_WEIGHTED_MEAN2   => get_string('aggregateweightedmean2', 'grades'),
            GRADE_AGGREGATE_EXTRACREDIT_MEAN => get_string('aggregateextracreditmean', 'grades'),
            GRADE_AGGREGATE_MEDIAN           => get_string('aggregatemedian', 'grades'),
            GRADE_AGGREGATE_MIN              => get_string('aggregatemin', 'grades'),
            GRADE_AGGREGATE_MAX              => get_string('aggregatemax', 'grades'),
            GRADE_AGGREGATE_MODE             => get_string('aggregatemode', 'grades'),
            GRADE_AGGREGATE_SUM              => get_string('aggregatesum', 'grades'));
        $output = html_writer::tag('p',
                    '总成绩的汇总算法必须是“<strong>'.$aggnames[GRADE_AGGREGATE_WEIGHTED_MEAN2].'</strong>”才能与教务处兼容。而您使用的是“'.$aggnames[$agg].'”。');
        return $this->notification($output);
    }

    public function require_max_total_grade($current_maxgrade) {
        $output = html_writer::tag('p',
                    '总成绩的满分必须是“<strong>'.MAX_TOTAL_GRADE.'</strong>”才能与教务处兼容。而现在是“'.$current_maxgrade.'”。');
        return $this->notification($output);
    }

    public function require_100_weight($current_weight) {
        $output = html_writer::tag('p',
                    '所有顶级分项成绩的满分之和必须是“<strong>'.MAX_TOTAL_GRADE.'</strong>”才能与教务处兼容。而现在是“'.$current_weight.'”。');
        return $this->notification($output);
    }

    public function require_max_subitems($current) {
        $output = html_writer::tag('p',
                    '所有顶级非加分的分项成绩总数必须小于“<strong>'.MAX_SUB_GRADE_COUNT.'</strong>”才能与教务处兼容。而现在是“'.$current.'”。');
        return $this->notification($output);
    }

    public function require_max_extraitems($current) {
        $output = html_writer::tag('p',
                    '所有顶级加分分项成绩总数必须小于“<strong>'.MAX_EXTRA_SUB_GRADE_COUNT.'</strong>”才能与教务处兼容。而现在是“'.$current.'”。');
        return $this->notification($output);
    }

    public function require_idnumber($courseid = 0) {
        global $COURSE;
        if ($courseid == 0) {
            $courseid = $COURSE->id;
        }
        $text = '此课程的课程编号为空，或者无效，或者当前学期没有开课。请设置为教务处官方发布的课程编号。<br />';

        $url = new moodle_url('/course/edit.php', array('id' => $courseid));
        $link = html_writer::link($url, '点击此处设置课程编号');

        return $this->notification($text.$link);
    }

    public function require_cas() {
        return $this->notification('为了安全，只有使用HITID登录的用户才能使用此功能。');
    }

    public function modify_items_link($courseid = 0) {
        global $COURSE;
        if ($courseid == 0) {
            $courseid = $COURSE->id;
        }
        $url = new moodle_url('/grade/edit/tree/index.php', array('sesskey' => sesskey(), 'showadvanced' => 1, 'id' => $courseid));
        return $this->notification(html_writer::tag('p', html_writer::link($url, '点击此处修改成绩设置')));
    }

    public function choose_export_method() {
        global $PAGE;

        $output = $this->box_start();
        $output .= html_writer::tag('p', '准备将成绩导出到教务处成绩管理系统。');
        $output .= html_writer::tag('p', '导出后的成绩只是“保存”在教务处网站，您还有机会审核、修正，然后再提交。');
        $output .= html_writer::tag('p', '请选择导出方式：');

        $links = array();
        $url = $PAGE->url;
        $url->params(array('action' => 'totalonly'));
        $links[] = html_writer::link($url, '只导出总分');
        $url->params(array('action' => 'all'));
        $links[] = html_writer::link($url, '导出分项成绩和总分');
        $output .= html_writer::alist($links);

        $output .= $this->box_end();
        return $output;
    }

    public function success() {
        $link = html_writer::link('http://xscj.hit.edu.cn/hitjwgl/teacher/log.asp', '登录教务处', array('target' => '_blank'));
        return '成绩上传成功。可能有未使用乐学网或未使用HITID登录的学生成绩无法上传，请'.$link.'检查、确认和提交。';
    }
}
