<?php

require_once '../../../config.php';
require_once $CFG->dirroot.'/grade/export/lib.php';
require_once $CFG->dirroot.'/grade/export/jwc/lib.php';

$id = required_param('id', PARAM_INT);   // course

if (! $course = get_record('course', 'id', $id)) {
    error('Course ID is incorrect');
}

require_course_login($course);

print_grade_page_head($COURSE->id, 'export', 'jwc', get_string('exportto', 'grades') . ' ' . get_string('modulename', 'gradeexport_jwc'));

//使用url
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_REFERER, 'http://xscj.hit.edu.cn/');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($ch, CURLOPT_COOKIEFILE, PATH."/$COURSE->id/cookiefile.txt");
curl_setopt($ch, CURLOPT_COOKIEJAR, PATH."/$COURSE->id/cookiefile.txt");

                     //教师登录 $result = curl_exec ($ch);
		
curl_setopt($ch, CURLOPT_POST, FALSE);
curl_setopt($ch, CURLOPT_URL, 'http://xscj.hit.edu.cn/hitjwgl/teacher/CJGL/cjlr_1.asp');

//获取cjlr_1.asp页面
$output = curl_exec ($ch);

//正则表达式匹配学期名称
preg_match_all("/<option\s+value=(\d{4}.{4}|\d{4}.{4}\s+selected)\s+>(\d{4}.{8})<\/option>/",$output,$matches,PREG_PATTERN_ORDER);

//显示学期选择页面
include("$CFG->dirroot/grade/export/jwc/cjlr_1.html");

print_footer($course);

?>
