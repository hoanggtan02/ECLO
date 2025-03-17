<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');
    $app->router("/admin/config", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['router'] = 'system';
        $vars['title'] = $jatbi->lang("Thông tin hệ thống");
        $apiData = [
            'deviceKey' => '77ed8738f236e8df86',
            'secret' => '123456',
        ];

        $headers = [
            'Content-Type: application/x-www-form-urlencoded'
            // Nếu API yêu cầu token, thêm header Authorization
            // 'Authorization: Bearer your_token'
        ];

        // Gửi yêu cầu POST đến API findList để lấy danh sách nhân viên
        $response = $app->apiPost(
            'http://camera.ellm.io:8190/api/device/get',
            $apiData,
            $headers
        );

        // Kiểm tra phản hồi từ API findList
        $apiResponse = json_decode($response, true);
        $vars['data']= $apiResponse;
        echo $app->render('templates/admin/system.html', $vars);
    })->setPermissions([]);


     
?>