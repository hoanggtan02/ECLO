<?php

if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

$app->router("/staffConfiguration", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Cấu hình nhân sự");
    $vars['employee'] = $app->select("employee",["name (text)","sn (value)"],[]);
    echo $app->render('templates/staffConfiguration/menuDepartment.html', $vars);
})->setPermissions(['staffConfiguration']);

?>
   