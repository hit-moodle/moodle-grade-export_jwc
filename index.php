<?php

require_once '../../../config.php';
require_once $CFG->dirroot.'/grade/export/lib.php';

$id = required_param('id', PARAM_INT);   // course

if (! $course = get_record('course', 'id', $id)) {
    error('Course ID is incorrect');
}

require_course_login($course);

print_grade_page_head($COURSE->id, 'export', 'jwc', get_string('exportto', 'grades') . ' ' . get_string('modulename', 'gradeexport_jwc'));

include("$CFG->dirroot/grade/export/jwc/denglu.html");

print_footer($course);

?>
