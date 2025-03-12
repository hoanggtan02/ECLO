<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    //Nhóm kiểm soát
    $app->router("/manager/group-access", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Nhóm kiểm soát");
        echo $app->render('templates/group-access/group-access.html', $vars);
    })->setPermissions(['group-access']);

    $app->router("/manager/group-access", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
    
        // Nhận dữ liệu từ DataTable
        $draw = $_POST['draw'] ?? 0;
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;
        $searchValue = $_POST['search']['value'] ?? '';
    
        // Fix lỗi ORDER cột
        $orderColumnIndex = $_POST['order'][0]['column'] ?? 1; // Mặc định cột SN
        $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');
    
        // Danh sách cột hợp lệ
        $validColumns = ["acGroupNumber", "name"];
        $orderColumn = $validColumns[$orderColumnIndex] ?? "acGroupNumber";
    
        // Điều kiện lọc dữ liệu
        $where = [
            "AND" => [
                "OR" => [
                    "group-access.acGroupNumber[~]" => $searchValue,
                    "group-access.name[~]" => $searchValue,
                ]
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderColumn => $orderDir]
        ];
    
        // Đếm số bản ghi
        $count = $app->count("group-access", ["AND" => $where["AND"]]);
    
        // Truy vấn danh sách nhóm kiểm soát
        $datas = $app->select("group-access", ['acGroupNumber', 'name'], $where) ?? [];
    
        // Xử lý dữ liệu đầu ra
        $formattedData = array_map(function($data) use ($app, $jatbi) {
            return [
                "acGroupNumber" => $data['acGroupNumber'],
                "name" => $data['name'],
                "action" => $app->component("action", [
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['group-access.edit'],
                            'action' => ['data-url' => '/group-access/group-access-edit?id='.$data['acGroupNumber'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['group-access.deleted'],
                            'action' => ['data-url' => '/group-access/group-access-deleted?id='.$data['acGroupNumber'], 'data-action' => 'modal']
                        ],
                    ]
                ]),            
            ];
        }, $datas);
    
        // Xuất JSON
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $count,
            "recordsFiltered" => $count,
            "data" => $formattedData
        ]);
    })->setPermissions(['group-access']);

    $app->router("/manager/group-access-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Nhóm kiểm soát");
        echo $app->render('templates/group-access/group-access-post.html', $vars, 'global');
    })->setPermissions(['group-access.add']);
    
    $app->router("/manager/group-access-add", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        // Lấy dữ liệu từ form và kiểm tra XSS
        $sn   = $app->xss($_POST['sn'] ?? '');
        $name = $app->xss($_POST['name'] ?? '');
    
        // Kiểm tra dữ liệu đầu vào
        if (empty($sn) || empty($name)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            return;
        }
    
        try {
            // Dữ liệu để lưu vào database
            $insert = [
                "sn"   => $sn,
                "name" => $name,
            ];
    
            // Thêm dữ liệu vào database
            $app->insert("group_access", $insert);
    
            // Ghi log
            $jatbi->logs('group-access', 'group-access-add', $insert);
    
            echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
    
        } catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['group-access.add']);
    
?>