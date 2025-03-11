<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    $app->router("/manager/employee",'GET', function($vars) use ($app,$jatbi,$setting) {
        $response = $app->apiPost('http://camera.ellm.io:8190/api/person/findList', ['deviceKey' => '77ed8738f236e8df86', 'secret' => '123456','index' => '1','length' => '20'], ['Authorization: Bearer your_token']);
        echo $response;
        var_dump($response);
        echo $app->render('templates/home.html', $vars);
    });


?>

