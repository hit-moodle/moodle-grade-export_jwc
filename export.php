<?php

require_once '../../../config.php';
require_once $CFG->dirroot.'/grade/export/lib.php';
require_once $CFG->dirroot.'/grade/export/jwc/lib.php';

$id = required_param('id', PARAM_INT);   // course
$realexport = optional_param('realexport', false, PARAM_BOOL);   // course

if (! $course = get_record('course', 'id', $id)) {
    error('Course ID is incorrect');
}

require_course_login($course);

print_grade_page_head($COURSE->id, 'export', 'jwc', get_string('exportto', 'grades') . ' ' . get_string('modulename', 'gradeexport_jwc'));

if(!file_exists(PATH."/$COURSE->id/cookiefile.txt")) {
	redirect($CFG->wwwroot . '/grade/export/jwc/index.php?id=' . $id, "登录已过期", 1);
}

if (!$realexport) {
    echo '<strong>模拟导出过程，没有真实数据会被送到教务处。</strong><br />';
}

//提取给jwc的参数
$nv = array();
foreach($_GET as $name => $value) {
    if ($name != 'id')
        $nv[] = $name.'='.$value;
}
$jwc_params = implode('&', $nv);

       
//获取教学班次页面       
$ch = curl_init();

curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_REFERER, 'http://xscj.hit.edu.cn/');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($ch, CURLOPT_COOKIEFILE, PATH."/$COURSE->id/cookiefile.txt");
curl_setopt($ch, CURLOPT_COOKIEJAR, PATH."/$COURSE->id/cookiefile.txt");
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_URL, "http://xscj.hit.edu.cn/hitjwgl/teacher/CJGL/cjlr_4.asp");//选择教学班次的页面
curl_setopt($ch, CURLOPT_POSTFIELDS, iconv('UTF8', 'gbk', $jwc_params));

$result = curl_exec($ch);

//获得课程信息
$classes = array();

$doc = new DOMDocument();
$doc->loadHTML($result);
$tables = $doc->getElementsByTagName('table');
if ($tables->length < 3)
    bad_html();
$class_table = $tables->item(2);
foreach ($class_table->getElementsByTagName('tr') as $i => $tr) {
    if ($i == 0) // skip headline
        continue;
    $tds = $tr->getElementsByTagName('td');
    $class = new object();
    $class->name = trim($tds->item(2)->getElementsByTagName('div')->item(0)->nodeValue);
    $class->link = 'http://xscj.hit.edu.cn/hitjwgl/teacher/CJGL/'.$tds->item(5)->getElementsByTagName('a')->item(0)->getAttribute('href');
    $classes[] = $class;
}

//-------------------- 开始处理提交 ----------------------
foreach ($classes as $class) {
    $jwc_cols = array();   // jwc成绩项
    $users = array();       // jwc学生信息及输入框name等
    $moodle_cols = array(); // moodle上层类别和成绩项
    $modified_userid = array(); // 修改过成绩的用户的jwc id

    print_box_start();
    echo "导出班级：$class->name <br/>";
    curl_setopt($ch, CURLOPT_POST, FALSE);
    curl_setopt($ch, CURLOPT_URL, iconv('utf8', 'gbk', $class->link));
    $result = curl_exec($ch);
    $result = preg_replace('/gb2312/', 'gbk', $result);

    // parse grade table from jwc
    $doc = new DOMDocument();
    $doc->loadHTML($result);

    $form_table = $doc->getElementsByTagName('table')->item(1);
    $rows = $form_table->getElementsByTagName('tr');

    // 得到jwc的列
    $title_row = $rows->item(0);
    $cols = $title_row->getElementsByTagName('td');
    foreach ($cols as $col) {
        $jwc_cols[] = trim($col->nodeValue);
    }
    if (! (($NAME_COL = array_search('姓名', $jwc_cols))
        && ($ID_COL = array_search('学号', $jwc_cols))
        && ($TOTAL_COL = array_search('总成绩', $jwc_cols)))) {
        bad_html();
    }
    echo '教务处网站成绩项：'.implode('，', $jwc_cols).'<br />';

    // 获取jwc数据
    $count = 0;
    foreach ($rows as $row) {
        if ($count != 0) {
            $cols = $row->getElementsByTagName('td');
            $user_cols = array();
            $colnum = 0;
            foreach ($cols as $col) {
                $user_col = new object();
                $user_col->value = trim($col->nodeValue);
                $inputs = $col->getElementsByTagName('input');
                $name_value = array();
                foreach ($inputs as $input) {
                    if ($input->getAttribute('type') == 'text') {
                        $name_value[$input->getAttribute('name')] = $input->getAttribute('value');
                    }
                }
                $user_col->input = $name_value;
                $user_cols[] = $user_col;
                $colnum++;
            }
            $users[] = $user_cols;
        }
        $count++;
    }
    echo '教务处网站学生数：'.count($users).'<br />';


    // 得到jwc form的其它input和action url
    $form = $doc->getElementsByTagName('form')->item(0);
    $action_url = $form->getAttribute('action');
    $post_data = array();
    $inputs = $form->getElementsByTagName('input');
    foreach ($inputs as $input) {
        $type = $input->getAttribute('type');
        if ($type == 'hidden' || $type == 'submit')
            $post_data[$input->getAttribute('name')] = $input->getAttribute('value');
    }

    //----------------moodle数据整理---------------

    // 得到最上层的分类和成绩项信息
    $grade = new grade_tree($course->id, false, true);

    $topcats = $grade->top_element['children'];
    foreach ($topcats as $catid => $cat) {
        $obj = $cat['object'];
        if (is_a($obj, 'grade_category')) {
            //Use categary's name as categary's total name
            $obj->grade_item->itemname = $obj->fullname;
            $moodle_cols[$obj->grade_item->id] = $obj->grade_item;
        } else {
            if ($obj->itemtype == 'course')
                $obj->itemname = '总成绩';
            // grade item on top level
            $moodle_cols[$obj->id] = $obj;
        }
    }
    echo '本站顶级成绩项：';
    $names = array();
    foreach ($moodle_cols as $col) {
        $names[] = $col->itemname;
    }
    echo implode('，', $names).'<br />';

    //只需要传哪些？
    $temp_cols = array();
    foreach ($moodle_cols as $itemid => $mdl_col) {
        if (in_array($mdl_col->itemname, $jwc_cols))
            $temp_cols[$itemid] = $mdl_col;
    }
    $moodle_cols = $temp_cols;
    echo '将导出成绩项：';
    $names = array();
    foreach ($moodle_cols as $col) {
        $names[] = $col->itemname;
    }
    echo implode('，', $names).'<br />';

    // moodle成绩写入$users
    $geub = new grade_export_update_buffer();
    $gui = new graded_users_iterator($course, ($moodle_cols));
    $gui->init();

    while ($userdata = $gui->next_user()) {
        $moodle_user = $userdata->user;

        foreach($userdata->grades as $itemid => $grade) {
            if ($jwc_col_id = array_search($moodle_cols[$itemid]->itemname, $jwc_cols)) {
                $decimals = 2;
                if ($moodle_cols[$itemid]->itemname == '总成绩')  // 总成绩四舍五入
                    $decimals = 0;
                $finalgrade = grade_format_gradevalue($grade->finalgrade, $moodle_cols[$itemid], true, GRADE_DISPLAY_TYPE_REAL, $decimals);
                if ($finalgrade != '-') {
                    foreach ($users as $jwcid => $jwc_user) {
                        if ($jwc_user[$NAME_COL]->value == $moodle_user->lastname.$moodle_user->firstname
                            && $jwc_user[$ID_COL]->value == $moodle_user->idnumber)
                        {
                            $name = key($jwc_user[$jwc_col_id]->input);
                            $jwc_user[$jwc_col_id]->input[$name] = $finalgrade;
                            if (!in_array($jwcid, $modified_userid))
                                $modified_userid[] = $jwcid;
                        }
                    }
                }
            }
        }
    }
    $gui->close();
    $geub->close();

    echo '将导出学生数：'.count($modified_userid),'<br />';
    $table = new object();
    if (count($users) > count($modified_userid)) {
        // 找到所有有成绩的用户
        $graded_users = array();
        $geub = new grade_export_update_buffer();
        $gui = new graded_users_iterator($course, ($moodle_cols));
        $gui->init();
        while ($userdata = $gui->next_user()) {
            $user = $userdata->user;
            $graded_users[] = $user;
        }
        $gui->close();
        $geub->close();

        echo '<b>本站找不到与以下同学匹配的成绩。<br />这可能是因为他们在本站注册的学号或姓名有误，或者在本站的成绩为空，或者没有在本站选课。<br />请核实后，<a href="http://xscj.hit.edu.cn/hitjwgl/teacher/log.asp" target="_blank">手工录入</a>！</b>';
        $table->align = array ('left', 'left');//每一列在表格的left or right
        $table->cellpadding = 3;
        $table->width = '0%';
        $table->tablealign = 'left';
        $table->head = array('序号', '学号', '姓名', '可能对应');
        foreach ($users as $uid => $user) {
            if (!in_array($uid, $modified_userid)) {
                $line = array();
                $line[] = $uid+1;
                $line[] = $user[$ID_COL]->value;
                $line[] = $user[$NAME_COL]->value;

                $pulink = '';
                foreach ($graded_users as $muser) {
                    if (like($user[$NAME_COL]->value, $user[$ID_COL]->value, $muser)) {
                        $pulink .= '<a href="' . $CFG->wwwroot . '/course/user.php?user=' . $muser->id . '&amp;id=' . $course->id . '&amp;mode=grade" target="_blank">' . fullname($muser) . '</a> ';
                    }
                }
                $line[] = $pulink;

                $table->data[] = $line;
            }
        }//for

        print_table($table);
    }

    if ($realexport) {
        // 构造post_data
        foreach ($users as $user) {
            foreach ($user as $key => $col) {
                if ($key != 'modified') {
                    foreach ($col->input as $name => $value) {
                        $post_data[$name] = $value;
                    }
                }
            }
        }

        //---------------------准备完毕，开始传送-------------------

        //curl模拟提交
        $post_data_string = '';

        foreach($post_data as $key => $value) { 
            $post_data_string .= "$key=".$value."&";
        }
        $post_data_string = substr($post_data_string, 0, -1);//去掉最后的&符号
        $post_data_string = iconv('UTF-8', 'GBK', $post_data_string);

        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_URL, "http://xscj.hit.edu.cn/hitjwgl/teacher/CJGL/$action_url");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_string);

        if (curl_exec($ch))
            echo '导出成功，请到教务处网站确认，然后上交成绩。';
        else
            echo '导出失败！！！';
        print_box_end();
    } else {
        print_box_end();
        echo '<strong>模拟导出结束。如果对模拟结果满意，请点击此<a href="export.php?realexport=1&id='.$id.'&'.$jwc_params.'">链接</a>，进行真正的导出。</strong><br />';
    }

}
curl_close($ch);

print_footer($course);

function bad_html() {
//unlink(PATH."/$COURSE->id/cookiefile.txt");
    die('教务处网站有变化，需要更新导出程序。请联系管理员。');
}

function like($name, $idnumber, $user) {
    $user->lastname = trim($user->lastname);
    $user->firstname = trim($user->firstname);
    $user->idnumber = trim($user->idnumber);
    if ($user->lastname.$user->firstname == $name) {
        return true;
    } else if ($user->firstname.$user->lastname == $name) {
        return true;
    } else if ($idnumber == $user->idnumber) {
        return true;
    } else if ($user->firstname.'.'.$user->lastname == $name) {
        return true;
    } else if ($user->lastname.'.'.$user->firstname == $name) {
        return true;
    }

    return false;
}

?>
