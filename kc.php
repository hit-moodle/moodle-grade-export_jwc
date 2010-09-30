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

//取出课程选择表
$doc = new DOMDocument();
$doc->loadHTML($result);
$table = $doc->getElementsByTagName('table')->item(1);
$table_doc = new DOMDocument('1.0');
$t = $table_doc->importNode($table, TRUE);
$table_doc->appendChild($t);

//更改链接
$links = $t->getElementsByTagName('a');
$table_src = $table_doc->saveHTML();
$table_src = preg_replace('/<a href="cjlr_qzsd.asp.+<\/a>/', '', $table_src, -1, $count1);
$table_src = preg_replace('/cjlr_4.asp\?/', "export.php?id=$id&", $table_src, -1, $count2);

if ($count1 && $count2)
    echo $table_src;
else
    notice('该学期您没有课程，请重新选择学期。（也可能是教务处网站又变了，请联系管理员修改导出程序）', "xueqi.php?id=$id");

print_footer($course);

?>
