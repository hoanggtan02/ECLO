<?php
$app->router("/api", 'POST', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Nhân viên");
    $vars['add'] = '/manager/employee-add';
    $vars['deleted'] = '/manager/employee-deleted';
; 
    echo $app->render('templates/employee/employee.html', $vars);
})->setPermissions(['employee']);
?>