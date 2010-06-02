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


$ch = curl_init();

curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_REFERER, 'http://xscj.hit.edu.cn/');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($ch, CURLOPT_COOKIEFILE, PATH."/$COURSE->id/cookiefile.txt");
curl_setopt($ch, CURLOPT_COOKIEJAR, PATH."/$COURSE->id/cookiefile.txt");
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_URL,"http://xscj.hit.edu.cn/hitjwgl/teacher/CJGL/cjlr_2.asp");
//$POST['XQ']获取学期名称
$a = iconv('UTF-8', 'GB2312', $_POST['XQ']);
curl_setopt($ch, CURLOPT_POSTFIELDS, "XQ=$a");

//获取cjlr_2.asp页面
$result = curl_exec($ch);

//正则表达式匹配课程信息
preg_match_all("/<div\salign=\"center\"\sclass=\"style2\">(.+|\s+.+\s+|.+\s+.+)<\/div>/",$result,$matches,PREG_PATTERN_ORDER);

//打印课程信息
if($matches[0]){
include("$CFG->dirroot/grade/export/jwc/cjlr_2.html");
}else{
	 notice('不是合理的选课时间', "xueqi.php?id=$id");
}

print_footer($course);

?>
