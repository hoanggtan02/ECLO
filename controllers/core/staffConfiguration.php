<?php

if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

//========================================Phòng ban========================================
$app->router("/staffConfiguration/department", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Cấu hình nhân sự");
    $vars['title1'] = $jatbi->lang("Phòng ban");
    $vars['active']= "department";
    // $vars['employee'] = $app->select("employee",["name (text)","sn (value)"],[]);
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
    $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'departmentId';
    $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

    $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';

    $where = [
        "AND" => [
            "OR" => [
                "department.departmentId[~]" => $searchValue,
                "department.personName[~]" => $searchValue,
            ],
            "department.status[<>]" => $status,
        ],
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderName => strtoupper($orderDir)]
    ];
    
    $count = $app->count("department",[
        "AND" => $where['AND'],
    ]);

    $app->select("department",  
        [
        'department.departmentId',
        'department.personName',
        'department.note',
        'department.status',
        ], $where, function ($data) use (&$datas,$jatbi,$app) {
        $datas[] = [
            "checkbox"        => $app->component("box",["data"=>$data['departmentId']]),
            "departmentId"    => $data['departmentId'],
            "departmentName"  => $data['personName'],
            "note"            => $data['note'],
            "status"          => $app->component("status",["url"=>"/staffConfiguration/department-status/".$data['departmentId'],"data"=>$data['status'],"permission"=>['staffConfiguration-department.edit']]),
            "action"          => $app->component("action",[
                "button" => [
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Sửa"),
                        'permission' => ['staffConfiguration-department.edit'],
                        'action' => ['data-url' => '/staffConfiguration/department-edit/'.$data['departmentId'], 'data-action' => 'modal']
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xóa"),
                        'permission' => ['staffConfiguration-department.delete'],
                        'action' => ['data-url' => '/staffConfiguration/department-delete?box='.$data['departmentId'], 'data-action' => 'modal']
                    ],
                ]
            ]),
        ];
    }); 

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $count,
        "recordsFiltered" => $count,
        "data" => $datas ?? [],
    ]);
})->setPermissions(['staffConfiguration']);

//----------------------------------------Cập nhật trạng thái phòng ban----------------------------------------
$app->router("/staffConfiguration/department-status/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $data = $app->get("department","*",["departmentId"=>$vars['id']]);
    if($data>1){
        if($data>1){
            if($data['status']==='A'){
                $status = "D";
            } 
            elseif($data['status']==='D'){
                $status = "A";
            }
            $app->update("department",["status"=>$status],["departmentId"=>$data['departmentId']]);
            $jatbi->logs('staffConfiguration','department-status',$data);
            echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
        }
        else {
            echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
        }
    }
    else {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
    }
})->setPermissions(['staffConfiguration-department.edit']);

//----------------------------------------Thêm phòng ban----------------------------------------
$app->router("/staffConfiguration/department-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thêm Phòng ban");
    $vars['data'] = [
        "status"        => 'A',
    ];
    echo $app->render('templates/staffConfiguration/department-post.html', $vars, 'global');
})->setPermissions(['staffConfiguration-department.add']);

$app->router("/staffConfiguration/department-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    if($app->xss($_POST['departmentId']) =='' && $app->xss($_POST['departmentName']) =='') {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Tên Phòng ban không được để trống.")]);
    } else {
        $insert = [
            "departmentId"  => $app->xss($_POST['departmentId']),
            "personName"    => $app->xss($_POST['departmentName']),
            "note"          => $app->xss($_POST['note'])?? '',
            "status"        => $app->xss($_POST['status']),
        ];
        $app->insert("department",$insert);
        $jatbi->logs('staffConfiguration','department-add',$insert);
        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Thêm thành công")]);
    }
    exit;

})->setPermissions(['staffConfiguration-department.add']);

//----------------------------------------Sửa phòng ban----------------------------------------
$app->router("/staffConfiguration/department-edit/{id}", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Sửa Phòng ban");
    $vars['data'] = $app->get("department","*",["departmentId"=>$vars['id']]);
    if($vars['data']>1){
        echo $app->render('templates/staffConfiguration/department-post.html', $vars, 'global');
    }
    else {
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
    }
})->setPermissions(['staffConfiguration-department.edit']);

$app->router("/staffConfiguration/department-edit/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    if($app->xss($_POST['departmentName'])=='') {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Tên Phòng ban không được để trống.")]);
    } else {
        $insert = [
            "personName"    => $app->xss($_POST['departmentName']),
            "note"          => $app->xss($_POST['note'])?? '',
            "status"        => $app->xss($_POST['status']),
        ];
        $app->update("department",$insert,["departmentId"=>$vars['id']]);
        $jatbi->logs('staffConfiguration','department-edit departmentId = '.$vars['id'],$insert);
        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
    }
})->setPermissions(['staffConfiguration-department.edit']);

//----------------------------------------Xóa phòng ban----------------------------------------
$app->router("/staffConfiguration/department-delete", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa Phòng ban");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['staffConfiguration-department.delete']);

$app->router("/staffConfiguration/department-delete", 'POST', function($vars) use ($app,$jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $boxid = explode(',', $app->xss($_GET['box']));
    $datas = $app->select("department","*",["departmentId"=>$boxid]);

    if(count($datas)>0){
        foreach($datas as $data){
            $app->delete("department",["departmentId"=>$data['departmentId']]);
        }
        $jatbi->logs('staffConfiguration','department-delete',$datas);
        echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Xóa thành công.")]);
    }
    else {
        echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xảy ra.")]);
    }
})->setPermissions(['staffConfiguration-department.delete']);

//========================================Chức vụ========================================
$app->router("/staffConfiguration/position", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Cấu hình nhân sự");
    $vars['title1'] = $jatbi->lang("Chức vụ");
    $vars['active']= "position";
    echo $app->render('templates/staffConfiguration/position.html', $vars);
})->setPermissions(['staffConfiguration-position']);

$app->router("/staffConfiguration/position", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';
    $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
    $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

    $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';

    $where = [
        "AND" => [
            "OR" => [
                "staff-position.id[~]" => $searchValue,
                "staff-position.name[~]" => $searchValue,
            ],
            "staff-position.status[<>]" => $status,
        ],
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderName => strtoupper($orderDir)]
    ];
    
    $count = $app->count("staff-position",[
        "AND" => $where['AND'],
    ]);

    $app->select("staff-position",  
        [
        'staff-position.id',
        'staff-position.name',
        'staff-position.note',
        'staff-position.status',
        ], $where, function ($data) use (&$datas,$jatbi,$app) {
        $datas[] = [
            "checkbox"          => $app->component("box",["data"=>$data['id']]),
            "id"                => $data['id'],
            "name"              => $data['name'],
            "note"              => $data['note'],
            "status"            => $app->component("status",["url"=>"/staffConfiguration/position-status/".$data['id'],"data"=>$data['status'],"permission"=>['staffConfiguration-position.edit']]),
            "action"            => $app->component("action",[
                "button" => [
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Sửa"),
                        'permission' => ['staffConfiguration-position.edit'],
                        'action' => ['data-url' => '/staffConfiguration/position-edit/'.$data['id'], 'data-action' => 'modal']
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xóa"),
                        'permission' => ['staffConfiguration-position.delete'],
                        'action' => ['data-url' => '/staffConfiguration/position-delete?box='.$data['id'], 'data-action' => 'modal']
                    ],
                ]
            ]),
        ];
    }); 

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $count,
        "recordsFiltered" => $count,
        "data" => $datas ?? [],
    ]);
    
})->setPermissions(['staffConfiguration-position']);

//----------------------------------------Cập nhật trạng thái chức vụ----------------------------------------
$app->router("/staffConfiguration/position-status/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $data = $app->get("staff-position","*",["id"=>$vars['id']]);
    if($data>1){
        if($data>1){
            if($data['status']==='A'){
                $status = "D";
            } 
            elseif($data['status']==='D'){
                $status = "A";
            }
            $app->update("staff-position",["status"=>$status],["id"=>$data['id']]);
            $jatbi->logs('staffConfiguration','position-status',$data);
            echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
        }
        else {
            echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
        }
    }
    else {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
    }
})->setPermissions(['staffConfiguration-position.edit']);

//----------------------------------------Thêm chức vụ----------------------------------------
$app->router("/staffConfiguration/position-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thêm Chức vụ");
    $vars['data'] = [
        "status"        => 'A',
    ];
    echo $app->render('templates/staffConfiguration/position-post.html', $vars, 'global');
})->setPermissions(['staffConfiguration-position.add']);

$app->router("/staffConfiguration/position-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type'  => 'application/json',
    ]);
    if($app->xss($_POST['name'])=='') {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Các trường bắt buộc không được để trống.")]);
        exit;
    } 

    $insert = [
        "name"           => $app->xss($_POST['name']),
        "note"           => $app->xss($_POST['note']),
        "status"         => $app->xss($_POST['status']),
    ];
    $app->insert("staff-position",$insert);
    $jatbi->logs('staffConfiguration','position-edit id = ',$insert);
    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Thêm thành công")]);
    exit;
 
})->setPermissions(['staffConfiguration-position.add']);

//----------------------------------------Sửa chức vụ----------------------------------------
$app->router("/staffConfiguration/position-edit/{id}", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Sửa Chức vụ");
    $vars['data'] = $app->get("staff-position","*",["id"=>$vars['id']]);
    if($vars['data']>1){
        echo $app->render('templates/staffConfiguration/position-post.html', $vars, 'global');
    }
    else {
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
    }
})->setPermissions(['staffConfiguration-position.edit']);

$app->router("/staffConfiguration/position-edit/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    if($app->xss($_POST['name'])=='') {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Các trường bắt buộc không được để trống.")]);
        exit;
    } 
    $insert = [
        "name"           => $app->xss($_POST['name']),
        "note"           => $app->xss($_POST['note']),
        "status"         => $app->xss($_POST['status']),
    ];
    $app->update("staff-position",$insert,["id"=>$vars['id']]);
    $jatbi->logs('staffConfiguration','position-edit id = ' . $vars['id'] ,$insert);
    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
    exit;
})->setPermissions(['staffConfiguration-position.edit']);

//----------------------------------------Xóa chức vụ----------------------------------------
$app->router("/staffConfiguration/position-delete", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa Chức vụ");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['staffConfiguration-position.delete']);

$app->router("/staffConfiguration/position-delete", 'POST', function($vars) use ($app,$jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $boxid = explode(',', $app->xss($_GET['box']));
    $datas = $app->select("staff-position","*",["id"=>$boxid]);

    if(count($datas)>0){
        foreach($datas as $data){
            $app->delete("staff-position",["id"=>$data['id']]);
        }
        $jatbi->logs('staffConfiguration','position-delete',$datas);
        echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Xóa thành công.")]);
    }
    else {
        echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xảảy ra.")]);
    }
})->setPermissions(['staffConfiguration-position.delete']);

//========================================Tiền lương========================================
$app->router("/staffConfiguration/salary", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Cấu hình nhân sự");
    $vars['title1'] = $jatbi->lang("Tiền lương");
    $vars['active']= "salary";

    // $vars['employee'] = $app->select("employee",["name (text)","sn (value)"],[]);
    echo $app->render('templates/staffConfiguration/salary.html', $vars);
})->setPermissions(['staffConfiguration-salary']);

$app->router("/staffConfiguration/salary", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';
    $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
    $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

    $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';

    $where = [
        "AND" => [
            "OR" => [
                "staff-salary.id[~]" => $searchValue,
                "staff-salary.name[~]" => $searchValue,
                "staff-salary.type[~]" => $searchValue,
            ],
            "staff-salary.status[<>]" => $status,
        ],
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderName => strtoupper($orderDir)]
    ];
    
    $count = $app->count("staff-salary",[
        "AND" => $where['AND'],
    ]);

    $app->select("staff-salary",  
        [
        'staff-salary.id',
        'staff-salary.name',
        'staff-salary.type',
        'staff-salary.price',
        'staff-salary.priceValue',
        'staff-salary.note',
        'staff-salary.status',
        ], $where, function ($data) use (&$datas,$jatbi,$app) {
            $price = number_format($data['price'], 0, '.', ','); // thêm dấu , vào tiền
            $datas[] = [
                "checkbox"      => $app->component("box",["data"=>$data['id']]),
                "id"            => $data['id'],
                "name"          => $data['name'],
                "type"          => $data['type'] == 1 ? 'Tiền lương': ($data['type'] == 2 ? 'Phụ cấp': 'Tăng ca'),
                "price"         => $data['priceValue']  == 1 ? $price . ' / ' . 'Giờ' : 
                                ($data['priceValue'] == 2 ? $price . ' / ' . 'Ngày' : $price . ' / ' . 'Tháng'),
                "note"          => $data['note'], 
                "status"        => $app->component("status",["url"=>"/staffConfiguration/salary-status/".$data['id'],"data"=>$data['status'],"permission"=>['staffConfiguration-salary.edit']]),
                "action"        => $app->component("action",[
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['staffConfiguration-salary.edit'],
                            'action' => ['data-url' => '/staffConfiguration/salary-edit/'.$data['id'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['staffConfiguration-salary.delete'],
                            'action' => ['data-url' => '/staffConfiguration/salary-delete?box='.$data['id'], 'data-action' => 'modal']
                        ],
                    ]
            ]),
        ];
    }); 
    
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $count,
        "recordsFiltered" => $count,
        "data" => $datas ?? [],
    ]);
})->setPermissions(['staffConfiguration-salary']);

//----------------------------------------Cập nhật trạng thái tiền lương----------------------------------------
$app->router("/staffConfiguration/salary-status/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $data = $app->get("staff-salary","*",["id"=>$vars['id']]);
    if($data>1){
        if($data>1){
            if($data['status']==='A'){
                $status = "D";
            } 
            elseif($data['status']==='D'){
                $status = "A";
            }
            $app->update("staff-salary",["status"=>$status],["id"=>$data['id']]);
            $jatbi->logs('staffConfiguration','salary-status',$data);
            echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
        }
        else {
            echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
        }
    }
    else {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
    }
})->setPermissions(['staffConfiguration-salary.edit']);

//----------------------------------------Thêm tiền lương----------------------------------------
$app->router("/staffConfiguration/salary-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thêm Tiền lương");
    $vars['data'] = [
        "type"          => "0",
        "priceValue"    => "0",
        "status"        => 'A',
    ];
    echo $app->render('templates/staffConfiguration/salary-post.html', $vars, 'global');
})->setPermissions(['staffConfiguration-salary.add']);

$app->router("/staffConfiguration/salary-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    if($app->xss($_POST['type']??'')=='' || $app->xss($_POST['name'])=='' || $app->xss($_POST['price'])=='' || $app->xss($_POST['priceValue']??'')=='') {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Các trường bắt buộc không được để trống.")]);
        exit;
    } 
    if($app->xss($_POST['price'])<=00) {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Số tiền không hợp lệ.")]);
        exit;
    } 
    $insert = [
        "name"           => $app->xss($_POST['name']),
        "type"           => $app->xss($_POST['type'])?? '',
        "price"          => $app->xss($_POST['price']),
        "priceValue"     => $app->xss($_POST['priceValue']),
        "note"           => $app->xss($_POST['note']),
        "status"         => $app->xss($_POST['status']),
    ];
    $app->insert("staff-salary",$insert);
    $jatbi->logs('staffConfiguration','salary-add',$insert);
    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
    exit;
 
})->setPermissions(['staffConfiguration-salary.add']);

//----------------------------------------Sửa tiền lương----------------------------------------
$app->router("/staffConfiguration/salary-edit/{id}", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Sửa Tiền lương");
    $vars['data'] = $app->get("staff-salary","*",["id"=>$vars['id']]);
    if($vars['data']>1){
        echo $app->render('templates/staffConfiguration/salary-post.html', $vars, 'global');
    }
    else {
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
    }
})->setPermissions(['staffConfiguration-salary.edit']);

$app->router("/staffConfiguration/salary-edit/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    if($app->xss($_POST['type']??'')=='' || $app->xss($_POST['name'])=='' || $app->xss($_POST['price'])=='' || $app->xss($_POST['priceValue']??'')=='') {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Các trường bắt buộc không được để trống.")]);
        exit;
    } 
    if($app->xss($_POST['price'])<=00) {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Số tiền không hợp lệ.")]);
        exit;
    } 
    $insert = [
        "name"           => $app->xss($_POST['name']),
        "type"           => $app->xss($_POST['type'])?? '',
        "price"          => $app->xss($_POST['price']),
        "priceValue"     => $app->xss($_POST['priceValue']),
        "note"           => $app->xss($_POST['note']),
        "status"         => $app->xss($_POST['status']),
    ];
    $app->update("staff-salary",$insert,["id"=>$vars['id']]);
    $jatbi->logs('staffConfiguration','salary-edit id = ' . $vars['id'] ,$insert);
    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
    exit;
})->setPermissions(['staffConfiguration-salary.edit']);

//----------------------------------------Xóa tiền lương----------------------------------------
$app->router("/staffConfiguration/salary-delete", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa tiền lương");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['staffConfiguration-salary.delete']);

$app->router("/staffConfiguration/salary-delete", 'POST', function($vars) use ($app,$jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $boxid = explode(',', $app->xss($_GET['box']));
    $datas = $app->select("staff-salary","*",["id"=>$boxid]);

    if(count($datas)>0){
        foreach($datas as $data){
            $app->delete("staff-salary",["id"=>$data['id']]);
        }
        $jatbi->logs('staffConfiguration','salary-delete',$datas);
        echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Xóa thành công.")]);
    }
    else {
        echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xảảy ra.")]);
    }
})->setPermissions(['staffConfiguration-salary.delete']);

//========================================Ngày lễ========================================
$app->router("/staffConfiguration/holiday", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Cấu hình nhân sự");
    $vars['title1'] = $jatbi->lang("Ngày lễ");
    $vars['active']= "holiday";

    echo $app->render('templates/staffConfiguration/holiday.html', $vars);
})->setPermissions(['staffConfiguration-holiday']);

$app->router("/staffConfiguration/holiday", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';
    $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
    $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

    $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';

    $where = [
        "AND" => [
            "OR" => [
                "staff-holiday.departmentId[~]" => $searchValue,
                "staff-holiday.name[~]" => $searchValue,
            ],
            "staff-holiday.status[<>]" => $status,
        ],
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderName => strtoupper($orderDir)]
    ];
    
    $count = $app->count("staff-salary",[
        "AND" => $where['AND'],
    ]);

    $app->select("staff-holiday", [
        "[>]department" => ["departmentId" => "departmentId"]
    ] ,
        [
        'staff-holiday.id',
        'staff-holiday.departmentId',
        'department.personName',
        'staff-holiday.name',
        'staff-holiday.startDate',
        'staff-holiday.endDate',
        'staff-holiday.salaryCoefficient',
        'staff-holiday.note',
        'staff-holiday.status',
        ], $where, function ($data) use (&$datas,$jatbi,$app) {
            $datas[] = [
                "checkbox"              => $app->component("box",["data"=>$data['id']]),
                "department"            => $data['departmentId'] == 0 ? 'Tất cả': $data['personName'] . ' - ' . $data['departmentId'] ,
                "name"                  => $data['name'],
                "day"                   => $data['startDate'] . ' - ' . $data['endDate'],
                "salaryCoefficient"     => $data['salaryCoefficient'],
                "note"                  => $data['note'], 
                "status"                => $app->component("status",["url"=>"/staffConfiguration/holiday-status/".$data['id'],"data"=>$data['status'],"permission"=>['staffConfiguration-holiday.edit']]),
                "action"        => $app->component("action",[
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['staffConfiguration-holiday.edit'],
                            'action' => ['data-url' => '/staffConfiguration/holiday-edit/'.$data['id'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['staffConfiguration-holiday.delete'],
                            'action' => ['data-url' => '/staffConfiguration/holiday-delete?box='.$data['id'], 'data-action' => 'modal']
                        ],
                    ]
            ]),
        ];
    }); 
    
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $count,
        "recordsFiltered" => $count,
        "data" => $datas ?? [],
    ]);
})->setPermissions(['staffConfiguration-holiday']);

//----------------------------------------Cập nhật trạng thái ngày lễ----------------------------------------
$app->router("/staffConfiguration/holiday-status/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $data = $app->get("staff-holiday","*",["id"=>$vars['id']]);
    if($data>1){
        if($data>1){
            if($data['status']==='A'){
                $status = "D";
            } 
            elseif($data['status']==='D'){
                $status = "A";
            }
            $app->update("staff-holiday",["status"=>$status],["id"=>$data['id']]);
            $jatbi->logs('staffConfiguration','holiday-status',$data);
            echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
        }
        else {
            echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
        }
    }
    else {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
    }
})->setPermissions(['staffConfiguration-holiday.edit']);

//----------------------------------------Thêm ngày lễ----------------------------------------
$app->router("/staffConfiguration/holiday-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thêm Ngày lễ");
    $vars['data'] = [
        "startDate"         => "1",
        "endDate"           => "1",
        "departmentId"      => 0,
        "salaryCoefficient" => 1,
        "priceValue"        => "0",
        "status"            => 'A',
    ];
    $vars['department'] = $app->select("department", ['departmentId','personName (departmentName)'], []);
    echo $app->render('templates/staffConfiguration/holiday-post.html', $vars, 'global');
})->setPermissions(['staffConfiguration-holiday.add']);

$app->router("/staffConfiguration/holiday-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    $salaryCoefficient = $app->xss($_POST['salaryCoefficient'] ?? '');
    if($app->xss($_POST['name'])=='' || $app->xss($_POST['startDate'])=='' || $app->xss($_POST['endDate'])=='' || $salaryCoefficient =='') {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Các trường bắt buộc không được để trống.")]);
        exit;
    } 
    if($app->xss($_POST['startDate']) > $app->xss($_POST['endDate'])) {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Ngày bắt đầu không được lớn hơn ngày kết thúc.")]);
        exit;
    }
    if (is_numeric($salaryCoefficient) && floatval($salaryCoefficient) >= 0) {// kiểm tra
    } else {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Hệ số lương không hợp lệ.")]);
        exit;
    }
    $insert = [
        "departmentId"          => $app->xss($_POST['departmentId']),
        "name"                  => $app->xss($_POST['name'])?? '',
        "startDate"             => $app->xss($_POST['startDate']),
        "endDate"               => $app->xss($_POST['endDate']),
        "salaryCoefficient"     => $app->xss($_POST['salaryCoefficient']),
        "note"                  => $app->xss($_POST['note']),
        "status"                => $app->xss($_POST['status']),
    ];
    $app->insert("staff-holiday",$insert);
    $jatbi->logs('staffConfiguration','holiday-add',$insert);
    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
    exit;
 
})->setPermissions(['staffConfiguration-holiday.add']);

//----------------------------------------Sửa ngày kễ----------------------------------------
$app->router("/staffConfiguration/holiday-edit/{id}", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Sửa Ngày lễ");
    $vars['data'] = $app->get("staff-holiday","*",["id"=>$vars['id']]);
    $vars['department'] = $app->select("department", ['departmentId','personName (departmentName)'], []);
    if($vars['data']>1){
        echo $app->render('templates/staffConfiguration/holiday-post.html', $vars, 'global');
    }
    else {
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
    }
})->setPermissions(['staffConfiguration-holiday.edit']);

$app->router("/staffConfiguration/holiday-edit/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $salaryCoefficient = $app->xss($_POST['salaryCoefficient'] ?? '');
    if($app->xss($_POST['name'])=='' || $app->xss($_POST['startDate'])=='' || $app->xss($_POST['endDate'])=='' || $salaryCoefficient =='') {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Các trường bắt buộc không được để trống.")]);
        exit;
    } 
    if($app->xss($_POST['startDate']) > $app->xss($_POST['endDate'])) {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Ngày bắt đầu không được lớn hơn ngày kết thúc.")]);
        exit;
    }
    if (is_numeric($salaryCoefficient) && floatval($salaryCoefficient) >= 0) {// kiểm tra
    } else {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Hệ số lương không hợp lệ.")]);
        exit;
    }
    $insert = [
        "departmentId"          => $app->xss($_POST['departmentId']),
        "name"                  => $app->xss($_POST['name'])?? '',
        "startDate"             => $app->xss($_POST['startDate']),
        "endDate"               => $app->xss($_POST['endDate']),
        "salaryCoefficient"     => $app->xss($_POST['salaryCoefficient']),
        "note"                  => $app->xss($_POST['note']),
        "status"                => $app->xss($_POST['status']),
    ];
    $app->update("staff-holiday",$insert,["id"=>$vars['id']]);
    $jatbi->logs('staffConfiguration','holiday-edit id = ' . $vars['id'] ,$insert);
    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
})->setPermissions(['staffConfiguration-holiday.edit']);

//----------------------------------------Xóa ngày lễ----------------------------------------
$app->router("/staffConfiguration/holiday-delete", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa Ngày lễa");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['staffConfiguration-holiday.delete']);

$app->router("/staffConfiguration/holiday-delete", 'POST', function($vars) use ($app,$jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $boxid = explode(',', $app->xss($_GET['box']));
    $datas = $app->select("staff-holiday","*",["id"=>$boxid]);

    if(count($datas)>0){
        foreach($datas as $data){
            $app->delete("staff-holiday",["id"=>$data['id']]);
        }
        $jatbi->logs('staffConfiguration','holidaya`-delete',$datas);
        echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Xóa thành công.")]);
    }
    else {
        echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xảảy ra.")]);
    }
})->setPermissions(['staffConfiguration-holiday.delete']);
$vars['title'] = $jatbi->lang("Chức vụ");

?> 