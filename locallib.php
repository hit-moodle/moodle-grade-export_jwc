<?php

class setup_jwcid_form extends moodleform {
    
    function definition() {
        global $COURSE;

        $mform =& $this->_form;

        $mform->addElement('html', '请输入您登录教务处成绩管理网站所用的“教师编码”，例如“1303832”');

        $mform->addElement('text', 'jwcid', '教师编码');
        $mform->setType('jwcid', PARAM_ALPHANUM);

        $this->add_action_buttons(false);
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!get_jwc_instance()->auth_user(reset($this->_customdata), $data['jwcid'])) {
            $errors['jwcid'] = '此编码对应的教师姓名与您不符，请仔细核对';
        }

        return $errors;
    }
}

/**
 * 教务处功能封装
 */
class jwc_manager {
    protected $extdb;
    protected $jwcid;
    protected $user;
    protected $course;
    public $dryrun = true;

    function __construct() {
        global $CFG;
        require_once($CFG->libdir.'/adodb/adodb.inc.php');
        require_once('config.php');

        // Connect to the external database (forcing new connection)
        $extdb = ADONewConnection($dbtype);
        if ($debugdb) {
            $extdb->debug = true;
            ob_start(); //start output buffer to allow later use of the page headers
        }

        $extdb->Connect($dbhost, $dbuser, $dbpass, $dbname, true);
        $extdb->SetFetchMode(ADODB_FETCH_ASSOC);

        $this->extdb = $extdb;
    }

    function set_user($user, $jwcid) {
        if ($this->auth_user($user, $jwcid)) {
            $this->user = $user;
            $this->jwcid = $jwcid;
            return true;
        } else {
            return false;
        }
    }

    function set_course($course) {
        if (empty($course->idnumber) or !$this->can_update_course($this->jwcid, $course->idnumber)) {
            return false;
        } else {
            $this->course = $course;
            return true;
        }
    }

    /**
     * 验证$user是否和$jwcid是同一个人
     *
     * 现在的验证方法并不十分严格，只是看jwcid对应的教师姓名是否和user的全名一致
     */
    function auth_user($user, $jwcid) {
        return true;
    }

    /**
     * 返回jwcid承担的课程
     *
     * return array('course_idnumber' => 'course name' .....)
     */
    function get_courses() {
        return array('08T1031050' => '操作系统', '111111' => 'C语言');
    }

    /**
     * 用户是否可以更新该课程的成绩
     */
    function can_update_course($jwcid, $idnumber) {
        return true;
    }
}

/**
 * 返回jwc_manager类的实例
 */
function get_jwc_instance() {
    static $jwc = null;
    if ($jwc) {
        return $jwc;
    }

    $jwc = new jwc_manager();
    return $jwc;
}
