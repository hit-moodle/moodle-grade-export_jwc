<?php

class setup_jwcid_form extends moodleform {
    
    function definition() {
        global $COURSE;

        $mform =& $this->_form;

        $mform->addElement('html', '请输入您登录教务处网站所用的“教师编码”，例如“1303832”');

        $mform->addElement('text', 'jwcid', '教师编码');
        $mform->setType('jwcid', PARAM_ALPHANUM);

        $this->add_action_buttons(false);
    }

    function validation($data, $files) {
    }
}

/**
 * 教务处功能封装
 */
class jwc_manager {
    protected $extdb;

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
