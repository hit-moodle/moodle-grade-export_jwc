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

//ʹ��url
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_REFERER, 'http://xscj.hit.edu.cn/');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($ch, CURLOPT_COOKIEFILE, PATH."/$COURSE->id/cookiefile.txt");
curl_setopt($ch, CURLOPT_COOKIEJAR, PATH."/$COURSE->id/cookiefile.txt");

                     //��ʦ��¼ $result = curl_exec ($ch);
		
curl_setopt($ch, CURLOPT_POST, FALSE);
curl_setopt($ch, CURLOPT_URL, 'http://xscj.hit.edu.cn/hitjwgl/teacher/CJGL/cjlr_1.asp');

//��ȡcjlr_1.aspҳ��
$output = curl_exec ($ch);

//������ʽƥ��ѧ������
preg_match_all("/<option\s+value=(\d{4}.{4}|\d{4}.{4}\s+selected)\s+>(\d{4}.{8})<\/option>/",$output,$matches,PREG_PATTERN_ORDER);

//��ʾѧ��ѡ��ҳ��
include("$CFG->dirroot/grade/export/jwc/cjlr_1.html");

print_footer($course);

?>
