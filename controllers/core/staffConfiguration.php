<?php

if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

//========================================Phòng ban========================================
$app->router("/staffConfiguration/department", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Cấu hình nhân sự");
    $vars['title1'] = $jatbi->lang("Phòng ban");
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

    $personSn = $app->xss($_POST['personSn'] ?? "");
    $personType = $app->xss($_POST['personType'] ?? "");

    $where = [
        "AND" => [
            "OR" => [
                "department.departmentId[~]" => $searchValue,
                "department.departmentName[~]" => $searchValue,
            ],
        ],
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderName => strtoupper($orderDir)]
    ];
    
    // if(!empty($personSn)) {
    //     $where["AND"]["record.personSn"] = $personSn;
    // }
    // if($personType > -1) {
    //     $where["AND"]["record.personType"] = $personType;
    // }
    
    $count = $app->count("department",[
        "AND" => $where['AND'],
    ]);

    $app->select("department",  
        [
        'department.departmentId',
        'department.departmentName',
        'department.note',
        'department.status',
        ], $where, function ($data) use (&$datas,$jatbi,$app) {
        $datas[] = [
            "checkbox"        => $app->component("box",["data"=>$data['departmentId']]),
            "departmentId"    => $data['departmentId'],
            "departmentName"  => $data['departmentName'],
            "note"            => $data['note'],
            "status"          => $app->component("status",["data"=>$data['status'],"permission"=>['staffConfiguration']]),
            "action"          => $app->component("action",[
                "button" => [
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Sửa"),
                        // 'permission' => ['accounts.edit'],
                        'action' => ['data-url' => '/staffConfiguration/department-edit/'.$data['departmentId'], 'data-action' => 'modal']
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xóa"),
                        // 'permission' => ['accounts.deleted'],
                        // 'action' => ['data-url' => '/users/accounts-deleted?box='.$data['active'], 'data-action' => 'modal']
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

//----------------------------------------Thêm phòng ban----------------------------------------
$app->router("/staffConfiguration/department-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thêm Phòng ban");
            // $vars['permissions'] = $app->select("permissions","*",["deleted"=>0,"status"=>"A"]);
    
    echo $app->render('templates/staffConfiguration/department-post.html', $vars, 'global');
})->setPermissions(['staffConfiguration']);

$app->router("/staffConfiguration/department-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    if($app->xss($_POST['departmentName'])=='') {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Tên Phòng ban không được để trống.")]);
    } else {
        $insert = [
            "departmentName" => $app->xss($_POST['departmentName']),
            "note"           => $app->xss($_POST['note'])?? '',
            "status"         => $app->xss($_POST['status']),
        ];
        $app->insert("department",$insert);
        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
    }

    // $app->insert("department",$insert);
    // echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
    // echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
        // $jatbi->logs('accounts','accounts-add',$insert);
    exit;

})->setPermissions(['staffConfiguration']);

//----------------------------------------Sửa phòng ban----------------------------------------
$app->router("/staffConfiguration/department-edit/{id}", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Sửa Phòng ban");
    // $vars['permissions'] = $app->select("permissions","*",["deleted"=>0,"status"=>"A"]);
    $vars['data'] = $app->get("department","*",["departmentId"=>$vars['id']]);
    if($vars['data']>1){
        echo $app->render('templates/staffConfiguration/department-post.html', $vars, 'global');
    }
    else {
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
    }
})->setPermissions(['staffConfiguration']);

$app->router("/staffConfiguration/department-edit/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $data = $app->get("department","*",["departmentId"=>$vars['id']]);
    if($data>1) {
        if($app->xss($_POST['departmentName'])=='') {
            echo json_encode(["status"=>"error","content"=>$jatbi->lang("Tên Phòng ban không được để trống.")]);
        } else {
            $insert = [
                "departmentName" => $app->xss($_POST['departmentName']),
                "note"           => $app->xss($_POST['note'])?? '',
                "status"         => $app->xss($_POST['status']),
            ];
            $app->update("department",$insert,["departmentId"=>$data['departmentId']]);
            echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
        }
    } else {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
    }
})->setPermissions(['staffConfiguration']);

$app->router("/staffConfiguration/position", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Chức vụ");
    echo $app->render('templates/staffConfiguration/position.html', $vars);
})->setPermissions(['staffConfiguration']);

$app->router("/staffConfiguration/position", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    // Nhận dữ liệu từ DataTable
    $draw = $_POST['draw'] ?? 0;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';

    $orderColumnIndex = $_POST['order'][0]['column'] ?? 1; // Mặc định cột positionId
    $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');

    // Danh sách cột hợp lệ
    $validColumns = ["checkbox", "positionName", "positionId", "note", "active"];
    $orderColumn = $validColumns[$orderColumnIndex] ?? "id";

    // Điều kiện lọc dữ liệu
    $where = [
        "AND" => [
            "OR" => [
                "positions.positionId[~]" => $searchValue,
                "positions.positionName[~]" => $searchValue,
            ]
        ],
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderColumn => $orderDir]
    ];

    $count = $app->count("positions", ["AND" => $where["AND"]]);
    $datas = $app->select("positions", '*', $where) ?? [];

    // Xử lý dữ liệu đầu ra
    $formattedData = array_map(function($data) use ($app, $jatbi) {
        return [
            "checkbox" => $app->component("box", ["data" => $data['id']]),
            "positionName" => $data['positionName'],
            "positionId" => $data['positionId'],
            "note" => $data['note'] ?? $jatbi->lang("Không có ghi chú"),
            "active" => $app->component("status", [
                "url" => "/staffConfiguration/position-status/" . $data['id'], 
                "data" => $data['active'], 
                "permission" => ['staffConfiguration.edit']
            ]),
            "action" => $app->component("action", [
                "button" => [
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Sửa"),
                        'permission' => ['position.edit'],
                        'action' => ['data-url' => '/staffConfiguration/position-edit?id=' . $data['id'], 'data-action' => 'modal']
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xóa"),
                        'permission' => ['position.delete'],
                        'action' => ['data-url' => '/staffConfiguration/position-delete?id=' . $data['id'], 'data-action' => 'modal']
                    ]
                ]
            ]),
        ];
    }, $datas);

    // Trả về dữ liệu JSON
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $count,
        "recordsFiltered" => $count,
        "data" => $formattedData
    ]);
})->setPermissions(['staffConfiguration']);

//========================================Tiền lương========================================
$app->router("/staffConfiguration/salary", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Cấu hình nhân sự");
    $vars['title1'] = $jatbi->lang("Tiền lương");
    // $vars['employee'] = $app->select("employee",["name (text)","sn (value)"],[]);
    echo $app->render('templates/staffConfiguration/salary.html', $vars);
})->setPermissions(['staffConfiguration']);

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

    $personSn = $app->xss($_POST['personSn'] ?? "");
    $personType = $app->xss($_POST['personType'] ?? "");

    $where = [
        "AND" => [
            "OR" => [
                "staff-salary.id[~]" => $searchValue,
                "staff-salary.name[~]" => $searchValue,
                "staff-salary.type[~]" => $searchValue,
            ],
        ],
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderName => strtoupper($orderDir)]
    ];
    
    $count = $app->count("department",[
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
                "status"        => $app->component("status",["data"=>$data['status'],"permission"=>['staffConfiguration']]),
                "action"        => $app->component("action",[
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['staffConfiguration'],
                            'action' => ['data-url' => '/staffConfiguration/salary-edit/'.$data['id'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['staffConfiguration'],
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
})->setPermissions(['staffConfiguration']);

//----------------------------------------Thêm tiền lương----------------------------------------
$app->router("/staffConfiguration/salary-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thêm Tiền lương");
    $vars['data'] = [
        "type"          => "0",
        "priceValue"    => "0",
        "status"        => 'A',
    ];
    echo $app->render('templates/staffConfiguration/salary-post.html', $vars, 'global');
})->setPermissions(['staffConfiguration']);

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
    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
    exit;
 
})->setPermissions(['staffConfiguration']);

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
})->setPermissions(['staffConfiguration']);

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
    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
    exit;
})->setPermissions(['staffConfiguration']);

//----------------------------------------Xóa tiền lương----------------------------------------
$app->router("/staffConfiguration/salary-delete", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa tiền lương");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['staffConfiguration']);

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
        $jatbi->logs('staffConfiguration','salary-deleted',$datas);
        echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Xóa thành công.")]);
    }
    else {
        echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra.")]);
    }
  
})->setPermissions(['staffConfiguration']);
// Tân làm ở sau đây nha

?>