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
$table_src = preg_replace('/&#24405;&#20837;/', '&#27169;&#25311;&#23548;&#20986;', $table_src, -1, $count3);

if ($count1 && $count2 && $count3) {
    print_box('如果只导出总成绩，在教务处使用缺省设置，这里点击下面的“导出成绩”即可。<br />如果要导出分项成绩，需要在教务处和本站分别先设好各个成绩项和权重（本站主要使用类别划分成绩项），并保持两边对应的成绩项的名称、权重和分数范围一致，然后点击下面的“导出成绩”。<br />本成绩只导出在教务处和本站同名的成绩项/类别。如果某成绩项/类别本站有，教务处没有，会被忽略；如果某成绩项/类别教务处有，本站没有，会保持教务处的成绩值。<br />因为本导出程序只是将成绩“保存”到教务处，并不“上交”，所以所有操作都可逆，请放心使用。');
    echo $table_src;
} else {
    notice('该学期您没有课程，请重新选择学期。（也可能是教务处网站有变化，请联系管理员修改导出程序）', "xueqi.php?id=$id");
}

print_footer($course);

?>
