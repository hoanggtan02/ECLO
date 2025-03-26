<?php

if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

$app->router("/staffConfiguration/department", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Cấu hình nhân sự");
    $vars['title1'] = $jatbi->lang("Phòng ban");
    $vars['employee'] = $app->select("employee",["name (text)","sn (value)"],[]);
    echo $app->render('templates/staffConfiguration/department.html', $vars);
})->setPermissions(['staffConfiguration']);

$app->router("/staffConfiguration/department", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';
    $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
    $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

    $startTime = $app->xss($_POST['startTime'] ?? "");
    $endTime = $app->xss($_POST['endTime'] ?? "");
    $personSn = $app->xss($_POST['personSn'] ?? "");
    $personType = $app->xss($_POST['personType'] ?? "");

    // $where = [
    //     "AND" => [
    //         "OR" => [
    //             "record.id[~]" => $searchValue,
    //             "record.personName[~]" => $searchValue,
    //             "record.personSn[~]" => $searchValue,
    //         ],
    //     ],
    //     "LIMIT" => [$start, $length],
    //     "ORDER" => [$orderName => strtoupper($orderDir)]
    // ];
    
    // if(!empty($startTime)) {
    //     $where["AND"]["record.createTime[>=]"] = $startTime;
    // }
    // if(!empty($endTime)) {
    //     $endTime = date("Y-m-d", strtotime($endTime . " +1 day"));
    //     $where["AND"]["record.createTime[<=]"] = $endTime;
    // }
    // if(!empty($personSn)) {
    //     $where["AND"]["record.personSn"] = $personSn;
    // }
    // if($personType > -1) {
    //     $where["AND"]["record.personType"] = $personType;
    // }
    
    $count = $app->count("department",[
        // "AND" => $where['AND'],
    ]);

    $app->select("department",  
        [
        'department.departmentId',
        'department.departmentName',
        'department.note',
        'department.status',
        ], function ($data) use (&$datas,$jatbi,$app) {
        $datas[] = [
            "checkbox"        => $app->component("box",["data"=>$data['departmentId']]),
            "departmentId"    => $data['departmentId'],
            "departmentName"  => $data['departmentName'],
            "note"            => $data['note'],
            "status"          => $app->component("status",["data"=>$data['status'],"permission"=>['staffConfiguration']]),
            "action"          => "",
        ];
    }); 

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $count,
        "recordsFiltered" => $count,
        "data" => $datas ?? [],
    ]);

})->setPermissions(['staffConfiguration']);

$app->router("/staffConfiguration/department-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thêm Phòng ban");
            // $vars['permissions'] = $app->select("permissions","*",["deleted"=>0,"status"=>"A"]);
            // $vars['data'] = [
            //     "status" => 'A',
            //     "permission" => '',
            //     "gender" => '',
            // ];
    echo $app->render('templates/staffConfiguration/department-post.html', $vars, 'global');
})->setPermissions(['staffConfiguration']);

$app->router("/staffConfiguration/department-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    if($app->xss($_POST['departmentName'])=='') {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Tên Phòng ban không được để trống.")]);
        exit;
    }

    $insert = [
        // "type"           => 1,
        "departmentId"   => $app->xss($_POST['departmentId']),
        "departmentName" => $app->xss($_POST['departmentName']),
        "note"           => $app->xss($_POST['note']),
        "status"         => $app->xss($_POST['status']),
        // "lang"           => $_COOKIE['lang'] ?? 'vi',
    ];
    // $app->insert("department",$insert);
    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
    // echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
        // $jatbi->logs('accounts','accounts-add',$insert);
    exit;

})->setPermissions(['staffConfiguration']);




// Tân làm ở sau đây nha

?>
   