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

check_dir_exists(PATH."/$COURSE->id", true, true);

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_REFERER, 'http://xscj.hit.edu.cn/');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($ch, CURLOPT_COOKIEFILE, PATH."/$COURSE->id/cookiefile.txt");
curl_setopt($ch, CURLOPT_COOKIEJAR, PATH."/$COURSE->id/cookiefile.txt");
curl_setopt($ch, CURLOPT_POST, TRUE);
$post_data= "uid=$_POST[uid]&pwd=$_POST[pwd]";
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);	
curl_setopt($ch, CURLOPT_URL, 'http://xscj.hit.edu.cn/hitjwgl/teacher/login.asp');

//教师登录
$result = curl_exec ($ch);
echo iconv('GB2312', 'UTF-8', $result);
redirect($CFG->wwwroot . '/grade/export/jwc/xueqi.php?id=' . $id, "登录成功", 0);

print_footer($course);

?>
