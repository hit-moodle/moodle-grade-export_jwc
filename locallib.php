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

