<?php

require_once '../../../config.php';
require_once $CFG->dirroot.'/grade/export/lib.php';
require_once $CFG->dirroot.'/grade/export/jwc/lib.php';

$id = required_param('id', PARAM_INT);   // course
$KCLB = required_param('KCLB', PARAM_RAW);
$xq = required_param('xq', PARAM_RAW);
$KcCode = required_param('KcCode', PARAM_RAW);
$KCXZ = required_param('KCXZ', PARAM_RAW);
$kcm  = required_param('kcm', PARAM_RAW); 


if (! $course = get_record('course', 'id', $id)) {
    error('Course ID is incorrect');
}

require_course_login($course);

print_grade_page_head($COURSE->id, 'export', 'jwc', get_string('exportto', 'grades') . ' ' . get_string('modulename', 'gradeexport_jwc'));

if(!file_exists(PATH."/$COURSE->id/cookiefile.txt")) {
	redirect($CFG->wwwroot . '/grade/export/jwc/index.php?id=' . $id, "登录已过期", 1);
}
//传送的数据应该为GB2312编码
$KcCode = iconv('UTF-8', 'GB2312', $KcCode);
$KCXZ = iconv('UTF-8', 'GB2312', $KCXZ);
$kcm = iconv('UTF-8', 'GB2312', $kcm);
$xq = iconv('UTF-8', 'GB2312', $xq);
$KCLB = iconv('UTF-8', 'GB2312', $KCLB);
$submit= iconv('UTF-8', 'GB2312', "下一步");
$post_data = "FS=1&CJLJ=1&kcCode=$KcCode&
kcxz=$KCXZ&
kcm=$kcm&
skxm=''&
xq=$xq&
kclb=$KCLB&
cxdk=''&
Submit=$submit";

       
//获取教学班次页面       
$ch = curl_init();

curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_REFERER, 'http://xscj.hit.edu.cn/');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($ch, CURLOPT_COOKIEFILE, PATH."/$COURSE->id/cookiefile.txt");
curl_setopt($ch, CURLOPT_COOKIEJAR, PATH."/$COURSE->id/cookiefile.txt");
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_URL, "http://xscj.hit.edu.cn/hitjwgl/teacher/CJGL/cjlr_4.asp");//选择教学班次的页面
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

$result = curl_exec($ch);

//获得课程信息
preg_match_all("/<a\shref='CJ_LRStart.asp\?(ID=\d+&fxddkb=.*&kcm=.+&kclb=.+&xq=.+&kccode=\w+&Rs=\d+&JZFS=\d+&KCXZ=.+&JF=.+&BH=\d+)'>.{8}<\/a>/",$result,$matches,PREG_PATTERN_ORDER);

//获取考试成绩cj
$id_grade = array();//学生成绩

$st_in_cms = array(); 
$i = 0;//cms学生信息

$groupid = 0;
$grade_items = grade_item::fetch_all(array('courseid'=>$course->id));
$itemlist = '';
$columns = array();
if (!empty($itemlist)) {
   $itemids = explode(',', $itemlist);
   // remove items that are not requested
   foreach ($itemids as $itemid) {
   if (array_key_exists($itemid, $grade_items)) {
     $columns[$itemid] = $grade_items[$itemid];
      }
   }
} else {
    foreach ($grade_items as $itemid=>$unused) {
      $columns[$itemid] = $grade_items[$itemid];
    }
}
$geub = new grade_export_update_buffer();
$gui = new graded_users_iterator($course, $columns, $groupid);
$gui->init();

while ($userdata = $gui->next_user()) {
	$user = $userdata->user;
	$st_in_cms[$i]->id = $user->idnumber;
	$st_in_cms[$i++]->xm = $user->lastname.$user->firstname;

	foreach($userdata->grades as $itemid => $grade) {
		$id_grade[$user->idnumber] = round($userdata->grades[$itemid]->finalgrade);
		break;
	}
}
$gui->close();
$geub->close();


$st_nexist_in_cms = array();
$n = 0;//用于记录教务处存在而cms上不存在的学生的学号，班号，姓名

$is_post_null = 1;//post的学生信息是否为空
$cj_post = 0;//成功传递成绩的数目
//循环处理每个班次
foreach($matches[1] as $param) {
//获取CJ_LRStart.asp页面
curl_setopt($ch, CURLOPT_URL, "http://xscj.hit.edu.cn/hitjwgl/teacher/CJGL/CJ_LRStart.asp?".$param);
curl_setopt($ch, CURLOPT_POST, FALSE);

$result = curl_exec($ch);

//正则表达式比配xh，WJ，HK
preg_match_all("/<input\sname=\"xh\d+\"\stype=\"hidden\"\svalue=\"(\d+)\">/",$result,$xh,PREG_PATTERN_ORDER);
preg_match_all("/<input\sname=\"WJ\d+\"\stype=\"hidden\"\svalue=\"(.*?)\">/",$result,$WJ,PREG_PATTERN_ORDER);
preg_match_all("/<input\sname=\"HK\d+\"\stype=\"hidden\"\svalue=\"(.*?)\">/",$result,$HK,PREG_PATTERN_ORDER);
preg_match_all("/<input\sclass=QT\sname=\"cj\d+\"\stype=\"text\"\ssize=\"6\"\smaxlength=\"5\"\s+onChange=\"checkNum\(cj\d+\);\"\s+onkeypress=\"return\shandleEnter\(this,\sevent\)\"\s+value=\"(\d+)\".+>/",$result,$cj,PREG_PATTERN_ORDER);
//其他hidden参数
preg_match_all("/<input\sname=\"(\w+)\"\stype=\"hidden\"\sid=\"\w+\"\svalue=\"(.*)\">/",$result,$other_data,PREG_PATTERN_ORDER);
preg_match_all("/<td\sclass=\"style2\"><div\salign=\"center\">(\w+)<\/div><\/td>/",$result,$bh,PREG_PATTERN_ORDER);
preg_match_all("/<td\sclass=\"style2\"><div\salign=\"center\">(.+)\s*.\s*<input\sname=\"WJ/",$result,$xm,PREG_PATTERN_ORDER);
preg_match_all("/<input\sname=\"ALLXS\"\stype=\"hidden\"\svalue=\"(.*)\">/",$result,$xs_num,PREG_PATTERN_ORDER);

$st_in_jwc = array();//教务处学生信息

foreach($xh[1] as $i => $jxh) {
	$st_in_jwc[$i]->id = iconv('GBK', 'UTF-8', $jxh);
	$st_in_jwc[$i]->xm = trim(iconv('GBK', 'UTF-8', $xm[1][$i]));
} 

$post_data = array();

//构造$postdata，如果学生信息在教务处存在，而cms上不存在，则将其加入$st_nexist_in_cms
foreach($st_in_jwc as $i => $st) {
	  $post_data['xh'.(++$i)] = $st->id;
	  $post_data['WJ'.$i] = $WJ[1][$i - 1];
	  $post_data['HK'.$i] = $HK[1][$i - 1];
	  $post_data['qt'.$i] = ' ';
	if(in_array($st,$st_in_cms)) {
	  $post_data['cj'.$i] = (int)$id_grade[$st->id];
	  $cj_post++;

	} else {
		$post_data['cj'.$i] = $cj[1][$i - 1];
		$st_nexist_in_cms[$n]->id = $st->id;
		$st_nexist_in_cms[$n]->bh = iconv('GBK', 'UTF-8', $bh[1][$i - 1]);
		$st_nexist_in_cms[$n++]->xm = $st->xm;
	}
}

foreach($post_data as $data) {
	 $is_post_null = 0;
}

foreach($other_data[1] as $i => $oid) {
	$post_data[$oid] = $other_data[2][$i];
}
$post_data['ALLXS'] = $xs_num[1][0]; 

//curl模拟提交
$url='http://xscj.hit.edu.cn/hitjwgl/teacher/CJGL/CJ_LRSave.asp';
$post_data_string = '';

foreach($post_data as $key=>$value) { 
	$post_data_string .= "$key=".$value."&";
}
	
$post_data=substr($post_data_string,0,-1);//去掉最后的&符号

curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

$result = curl_exec($ch);
}
//循环处理结束

curl_close($ch);

if (!$st_nexist_in_cms) {
  echo '<b>成绩全部提交成功'.'&nbsp&nbsp'.'本次共在教务处保存了'.sizeof($matches[1]).'个班级'.$cj_post.'位同学的成绩'.'&nbsp'."<a href=http://xscj.hit.edu.cn/hitjwgl/teacher/log.asp>请登录教务处网站确认并最终上交</b></a>";
} else {
	if(!$is_post_null) {//提交了一部分同学的成绩
		echo '<b>部分学生成绩提交成功'.'&nbsp&nbsp'.'本次共在教务处保存了'.sizeof($matches[1]).'个班级'.$cj_post.'位同学的成绩'.'&nbsp'."<a href=http://xscj.hit.edu.cn/hitjwgl/teacher/log.asp>请登录教务处网站确认并最终上交</b></a>";
	}
	echo '<br>';
	$cj_npost = sizeof($st_nexist_in_cms);
  echo '<b>本网站中不存在以下'.$cj_npost.'位同学的成绩，请教师核实后，手工录入！</b>';
	$table->align = array ('left', 'left', 'left');//每一列在表格的left or right
  $table->cellpadding = 3;
  $table->width = '50%';
  $table->tablealign = 'left';
  $table->head = array('学号', '姓名', '班号');
	foreach ($st_nexist_in_cms as $i => $st) {
        $line = array();
        $line[] = $st->id;
        $line[] = $st->xm;
        $line[] = $st->bh;
        $table->data[] = $line;
    }//for

    print_table($table);
}
//unlink(PATH."/$COURSE->id/cookiefile.txt");
print_footer($course);

?>
