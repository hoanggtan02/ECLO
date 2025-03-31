<?php
if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

//========================================Đi trễ về sớm========================================
// Route để hiển thị giao diện quản lý đi trễ về sớm
$app->router("/staffConfiguration/latetime", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Cấu hình nhân sự");
    $vars['title1'] = $jatbi->lang("Đi trễ về sớm");
    $vars['add'] = '/staffConfiguration/latetime-add';
    $vars['deleted'] = '/staffConfiguration/latetime-deleted';
    $data = $app->select("latetime", ["id", "type", "name", "value", "amount", "apply_date", "content", "status"]);
    $vars['data'] = $data;
    echo $app->render('templates/staffConfiguration/latetime.html', $vars);
})->setPermissions(['latetime']);

// Route để xử lý yêu cầu POST từ DataTables và trả về dữ liệu JSON
$app->router("/staffConfiguration/latetime", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    // Lấy các tham số từ DataTables
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
    $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
    $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
    $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';
    // Điều kiện WHERE cho truy vấn
    $where = [
        "AND" => [
            "OR" => [
                "latetime.id[~]" => $searchValue,
                "latetime.type[~]" => $searchValue,
                "latetime.name[~]" => $searchValue,
                "latetime.value[~]" => $searchValue,
                "latetime.amount[~]" => $searchValue,
                "latetime.apply_date[~]" => $searchValue,
                "latetime.content[~]" => $searchValue,
            ],
            "status[<>]" => $status,
        ],
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderName => strtoupper($orderDir)]
      
    ];




    // Debug: Kiểm tra điều kiện WHERE
    error_log("WHERE condition for latetime: " . json_encode($where));

    // Đếm tổng số bản ghi (không tính LIMIT)
    $count = $app->count("latetime", ["AND" => $where['AND']]);
    error_log("Total records in latetime: " . $count);

    // Lấy dữ liệu từ bảng latetime
    $datas = [];
    $app->select("latetime", [
        "id",
        "type",
        "name",
        "value",
        "amount",
        "apply_date",
        "content",
        "status"
    ], $where, function ($data) use (&$datas, $jatbi, $app) {
        // Debug: Kiểm tra dữ liệu lấy được từ bảng latetime
        error_log("Record from latetime: " . json_encode($data));

        $content = $data['content'] ?: $jatbi->lang("Không có nội dung");
        // Thay thế ký tự xuống dòng \n bằng <br>
        $content = str_replace("\n", "<br>", $content);
        // Nếu chuỗi dài hơn 20 ký tự, tự động chèn <br> sau mỗi 20 ký tự
        $content = wordwrap($content, 20, "<br>", true);

        $datas[] = [
            "checkbox"    => $app->component("box", ["data" => $data['id']]),
            "id"          => $data['id'],
            "type"        => $data['type'] ?: $jatbi->lang("Không xác định"),
            "name"        => $data['name'] ?: $jatbi->lang("Không xác định"),
            "value"       => $data['value'] ? $data['value'] . ' phút' : $jatbi->lang("Không xác định"),
            "amount"      => $data['amount'] ? number_format($data['amount'], 0, '.', ',') . ' VNĐ' : $jatbi->lang("Không xác định"),
            "apply_date"  => $data['apply_date'] ? date('d/m/Y', strtotime($data['apply_date'])) : $jatbi->lang("Không xác định"),
            "content"     => $content,
            // "status"      => $app->component("status", ["data" => $data['status'], "permission" => ['staffConfiguration']]),
            "status"        => $app->component("status",["url"=>"/staffConfiguration/latetime-status/".$data['id'],"data"=>$data['status'],"permission"=>['latetime.edit']]),
            "action"      => $app->component("action", [
                "button" => [
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Sửa"),
                        'permission' => ['latetime.edit'],
                        'action' => ['data-url' => '/staffConfiguration/latetime-edit/' . $data['id'], 'data-action' => 'modal']
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xóa"),
                        'permission' => ['latetime.deleted'],
                        'action' => ['data-url' => '/staffConfiguration/latetime-deleted?box=' . $data['id'], 'data-action' => 'modal']
                    ],
                ]
            ]),
        ];
    });

    // Debug: Kiểm tra dữ liệu trả về
    error_log("Data for DataTables: " . json_encode($datas));

    // Trả về dữ liệu dưới dạng JSON cho DataTables
    $response = json_encode([
        "draw" => $draw,
        "recordsTotal" => $count,
        "recordsFiltered" => $count,
        "data" => $datas ?? []
    ], JSON_UNESCAPED_UNICODE);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Encode Error: " . json_last_error_msg());
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => 0,
            "recordsFiltered" => 0,
            "data" => [],
            "error" => "JSON Encode Error: " . json_last_error_msg()
        ]);
        return;
    }

    echo $response;
})->setPermissions(['latetime']);

//----------------------------------------Thêm Đi trễ về sớm----------------------------------------
// $app->router("/staffConfiguration/latetime-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
//     $vars['title'] = $jatbi->lang("Thêm Đi trễ về sớm");
//     $vars['data'] = [
//         "type"       => '',
//         "name"       => '',
//         "value"      => '',
//         "amount"     => '',
//         "apply_date" => date('Y-m-d'),
//         "content"    => '',
//         "status"     => '1',
//     ];
//     echo $app->render('templates/staffConfiguration/latetime-post.html', $vars, 'global');
// })->setPermissions(['latetime.add']);


$app->router("/staffConfiguration/latetime-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thêm Đi trễ về sớm");

    // Lấy danh sách nhân viên từ bảng employee
    $employees = $app->select("employee", ["sn", "name"], [], ["name" => "ASC"]); // Sắp xếp theo tên
    $vars['employees'] = $employees ?: [];

    $vars['data'] = [
        "sn"         => '', 
        "type"       => '',
        "name"       => '',
        "value"      => '',
        "amount"     => '',
        "apply_date" => date('Y-m-d'),
        "content"    => '',
        "status"     => '1',
    ];
    echo $app->render('templates/staffConfiguration/latetime-post.html', $vars, 'global');
})->setPermissions(['latetime.add']);

// $app->router("/staffConfiguration/latetime-add", 'POST', function($vars) use ($app, $jatbi) {
//     $app->header([
//         'Content-Type' => 'application/json',
//     ]);

//     // Kiểm tra dữ liệu đầu vào
//     $type = trim($_POST['type'] ?? '');
//     $name = trim($_POST['name'] ?? '');
//     $value = trim($_POST['value'] ?? '');
//     $amount = trim($_POST['amount'] ?? '');
//     $apply_date = trim($_POST['apply_date'] ?? '');
//     $content = trim($_POST['content'] ?? '');
//     $status = trim($_POST['status'] ?? '');

//     if ($type == '' || $name == '' || $value == '' || $amount == '' || $apply_date == '') {
//         echo json_encode([
//             'status' => 'error',
//             'content' => $jatbi->lang("Vui lòng điền đầy đủ thông tin bắt buộc"),
//         ]);
//         return;
//     }

//     // Chuẩn bị dữ liệu để lưu vào DB
//     $latetimeData = [
//         "type"       => $type,
//         "name"       => $name,
//         "value"      => (int) $value, // Chuyển về kiểu số nguyên
//         "amount"     => (float) $amount, // Chuyển về kiểu số thực
//         "apply_date" => date("Y-m-d", strtotime($apply_date)), // Định dạng ngày hợp lệ
//         "content"    => $content,
//         "status"     => $status,
//     ];

    

//     // Thêm dữ liệu vào bảng latetime
//     $inserted = $app->insert("latetime", $latetimeData);

//     if (!$inserted) {
//         echo json_encode([
//             'status' => 'error',
//             'content' => $jatbi->lang("Lỗi khi thêm vào cơ sở dữ liệu"),
//         ]);
//         return;
//     }

//     // Ghi log nếu thêm thành công
//     $jatbi->logs('latetime', 'latetime-add', $latetimeData);

//     // Trả về kết quả thành công
//     echo json_encode([
//         'status' => 'success',
//         'content' => $jatbi->lang("Thêm đi trễ về sớm thành công"),
//         'reload' => true,
//     ]);
// })->setPermissions(['latetime.add']);



$app->router("/staffConfiguration/latetime-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    // Kiểm tra dữ liệu đầu vào
    $type = trim($_POST['type'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $value = trim($_POST['value'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $apply_date = trim($_POST['apply_date'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if ($type == '' || $name == '' || $value == '' || $amount == '' || $apply_date == '') {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Vui lòng điền đầy đủ thông tin bắt buộc"),
        ]);
        return;
    }

    // Kiểm tra tên nhân viên có tồn tại trong bảng employee không
    $existingEmployee = $app->get("employee", ["name"], ["name" => $name]);
    if (!$existingEmployee) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Nhân viên không tồn tại"),
        ]);
        return;
    }

    // Chuẩn bị dữ liệu để lưu vào DB
    $latetimeData = [
        "type"       => $type,
        "name"       => $name, // Lưu trực tiếp tên nhân viên
        "value"      => (int) $value,
        "amount"     => (float) $amount,
        "apply_date" => date("Y-m-d", strtotime($apply_date)),
        "content"    => $content,
        "status"     => $status,
    ];

    // Thêm dữ liệu vào bảng latetime
    $inserted = $app->insert("latetime", $latetimeData);

    if (!$inserted) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Lỗi khi thêm vào cơ sở dữ liệu"),
        ]);
        return;
    }

    // Ghi log nếu thêm thành công
    $jatbi->logs('latetime', 'latetime-add', $latetimeData);

    // Trả về kết quả thành công
    echo json_encode([
        'status' => 'success',
        'content' => $jatbi->lang("Thêm đi trễ về sớm thành công"),
        'reload' => true,
    ]);
})->setPermissions(['latetime.add']);

//----------------------------------------Sửa Đi trễ về sớm----------------------------------------
$app->router("/staffConfiguration/latetime-edit/{id}", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $id = $vars['id']; // Lấy id từ URL

    // Kiểm tra xem bản ghi có tồn tại không
    $latetime = $app->select("latetime", ["id", "type", "name", "value", "amount", "apply_date", "content", "status"], ["id" => $id]);
    if (empty($latetime)) {
        $jatbi->error($jatbi->lang("Bản ghi không tồn tại"));
        return;
    }

    // Truyền dữ liệu vào template
    $vars['title'] = $jatbi->lang("Sửa Đi trễ về sớm");
    $vars['data'] = [
        'id'         => $latetime[0]['id'],
        'type'       => $latetime[0]['type'],
        'name'       => $latetime[0]['name'],
        'value'      => $latetime[0]['value'],
        'amount'     => $latetime[0]['amount'],
        'apply_date' => $latetime[0]['apply_date'],
        'content'    => $latetime[0]['content'],
        'status'     => $latetime[0]['status'],
        'edit'       => 1, // Đánh dấu là chế độ chỉnh sửa
    ];

    echo $app->render('templates/staffConfiguration/latetime-post.html', $vars, 'global');
})->setPermissions(['latetime.edit']);



$app->router("/staffConfiguration/latetime-edit/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    $id = $vars['id']; 

    // Kiểm tra xem bản ghi có tồn tại không
    $existingLatetime = $app->get("latetime", ["id"], ["id" => $id]);
    if (!$existingLatetime) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Bản ghi không tồn tại"),
        ]);
        return;
    }

    // Kiểm tra dữ liệu đầu vào
    $type = trim($_POST['type'] ?? '');
   
    $value = trim($_POST['value'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $apply_date = trim($_POST['apply_date'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if ($type == '' || $value == '' || $amount == '' || $apply_date == '') {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Vui lòng điền đầy đủ thông tin bắt buộc"),
        ]);
        return;
    }

    // Chuẩn bị dữ liệu để cập nhật vào DB
    $latetimeData = [
        "type"       => $type,
        
        "value"      => (int) $value,
        "amount"     => (float) $amount,
        "apply_date" => date("Y-m-d", strtotime($apply_date)),
        "content"    => $content,
        "status"     => $status,
    ];

    // Cập nhật dữ liệu vào bảng latetime
    $updated = $app->update("latetime", $latetimeData, ["id" => $id]);

    if (!$updated) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Lỗi khi cập nhật cơ sở dữ liệu"),
        ]);
        return;
    }

    // Ghi log nếu cập nhật thành công
    $jatbi->logs('latetime', 'latetime-edit', $latetimeData);

    // Trả về kết quả thành công
    echo json_encode([
        'status' => 'success',
        'content' => $jatbi->lang("Cập nhật đi trễ về sớm thành công"),
        'reload' => true,
    ]);
})->setPermissions(['latetime.edit']);

//----------------------------------------Xóa Đi trễ về sớm----------------------------------------
// Hiển thị giao diện xác nhận xóa
$app->router("/staffConfiguration/latetime-deleted", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa Đi trễ về sớm");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['latetime.deleted']);

// Xử lý logic xóa
$app->router("/staffConfiguration/latetime-deleted", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    // Lấy danh sách ID từ checkbox hoặc query string
    $box = $_POST['box'] ?? $_GET['box'] ?? null;
    if (!$box) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Vui lòng chọn ít nhất một bản ghi để xóa"),
        ]);
        return;
    }

    // Chuyển đổi $box thành mảng nếu nó là chuỗi
    $ids = is_array($box) ? $box : explode(',', $box);

    // Kiểm tra xem các ID có tồn tại trong bảng latetime không
    $existingRecords = $app->select("latetime", ["id"], ["id" => $ids]);
    if (empty($existingRecords)) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Không tìm thấy bản ghi nào để xóa"),
        ]);
        return;
    }

    // Lấy danh sách ID thực sự tồn tại
    $validIDs = array_column($existingRecords, 'id');

    // Xóa các bản ghi trong bảng latetime
    $deleted = $app->delete("latetime", ["id" => $validIDs]);

    if (!$deleted) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Lỗi khi xóa bản ghi"),
        ]);
        return;
    }

    // Ghi log nếu xóa thành công
    $jatbi->logs('latetime', 'latetime-deleted', ['ids' => $validIDs]);

    // Trả về kết quả thành công
    echo json_encode([
        'status' => 'success',
        'content' => $jatbi->lang("Xóa thành công"),
        'reload' => true,
    ]);
})->setPermissions(['latetime.deleted']);




$app->router("/staffConfiguration/latetime-status/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $data = $app->get("latetime","*",["id"=>$vars['id']]);
    if($data>1){
        if($data>1){
            if($data['status']==='A'){
                $status = "D";
            } 
            elseif($data['status']==='D'){
                $status = "A";
            }
            $app->update("latetime",["status"=>$status],["id"=>$data['id']]);
            $jatbi->logs('latetime','latetime-status',$data);
            echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
        }
        else {
            echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
        }
    }
    else {
        echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
    }
})->setPermissions(['latetime.edit']);
?>