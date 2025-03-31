<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

// Khung thời gian
    $app->router("/insurance", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Bảo Hiểm");
        $vars['add'] = '/insurance-add';
        $vars['deleted'] = '/insurance-deleted';
        $vars['edit'] = '/insurance-edit';
        $vars['approved'] = '/insurance-approved';
        $data = $app->select("insurance", ["idbh", "employee", "money", "moneybhxh", "numberbhxh", "daybhxh", "placebhxh", "numberyte", "dayyte", "placeyte", "statu", "note"]);
        $vars['data'] = $data;
        echo $app->render('templates/employee/insurance.html', $vars);
    })->setPermissions(['insurance']);

    $app->router("/insurance", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
    
        // Kiểm tra dữ liệu nhận từ DataTables
        error_log("Received POST Data: " . print_r($_POST, true));
    
        // Nhận dữ liệu từ DataTable
        $draw = $_POST['draw'] ?? 0;
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;
        $searchValue = $_POST['search']['value'] ?? '';
        $statu = $_POST['statu'] ?? '';
    
        // Fix lỗi ORDER cột
        $orderColumnIndex = $_POST['order'][0]['column'] ?? 1; // Mặc định cột acTzNumber
        $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');
    
        // Danh sách cột hợp lệ
        $validColumns = ["idbh", "employee", "money", "moneybhxh", "numberbhxh", "daybhxh", "placebhxh", "numberyte", "dayyte", "placeyte", "statu"];
        $orderColumn = $validColumns[$orderColumnIndex] ?? "type";
    
        // Điều kiện lọc dữ liệu
        $where = [
            "AND" => [
                "OR" => [
                    "insurance.employee[~]" => $searchValue,
                    "insurance.placebhxh[~]" => $searchValue,
                    "insurance.placeyte[~]" => $searchValue, 
                    "insurance.numberyte[~]" => $searchValue,
                    "insurance.numberbhxh[~]" => $searchValue,               ]
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderColumn => $orderDir]
        ];
    
        if (!empty($statu)) {
            $where["AND"]["insurance.statu"] = $statu;
        }
    
        // Đếm số bản ghi
        $count = $app->count("insurance", ["AND" => $where["AND"]]);
    
        // Truy vấn danh sách Khung thời gian
        $datas = $app->select('insurance', [
            'idbh', 'employee', 'money', 'moneybhxh', 'numberbhxh', 'daybhxh', 'placebhxh', 'numberyte', 'dayyte', 'placeyte', 'statu'
        ], $where) ?? [];
    
        // Log dữ liệu truy vấn để kiểm tra
        error_log("Fetched insurances Data: " . print_r($datas, true));
    
        // Xử lý dữ liệu đầu ra
        $formattedData = array_map(function($data) use ($app, $jatbi) {
            $moneylabel = number_format($data['money'], 0, '.', ',');
            $moneylabel2 = number_format($data['moneybhxh'], 0, '.', ',');

            return [
                "checkbox" => $app->component("box", ["data" => $data['idbh']]),
                "idbh" => $data['idbh'],
                "employee" => $data['employee'],
                "money" => $moneylabel,
                "moneybhxh" => $moneylabel2,
                "numberbhxh" => $data['numberbhxh'],
                "daybhxh" => $data['daybhxh'],
                "placebhxh" => $data['placebhxh'],
                "numberyte" => $data['numberyte'],
                "dayyte" => $data['dayyte'],
                "placeyte" => $data['placeyte'],
                "statu" => $app->component("status",["url"=>"/insurance-status/".$data['idbh'],"data"=>$data['statu'],"permission"=>['insurance.edit']]),

                "action" => $app->component("action", [
                    "button" => [          
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['insurance.edit'],
                            'action' => ['data-url' => '/insurance-edit?idbh=' . $data['idbh'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['insurance.deleted'],
                            'action' => ['data-url' => '/insurance-deleted?idbh=' . $data['idbh'], 'data-action' => 'modal']
                        ],
                    ]
                ]),                         
            ];
        }, $datas);
    
        // Log dữ liệu đã format trước khi JSON encode
        error_log("Formatted Data: " . print_r($formattedData, true));
    
        // Kiểm tra lỗi JSON
        $response = json_encode([
            "draw" => $draw,
            "recordsTotal" => $count,
            "recordsFiltered" => $count,
            "data" => $formattedData
        ]);
    
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Encode Error: " . json_last_error_msg());
        }
    
        echo $response;
    })->setPermissions(['insurance']);

    //Thêm insurance
    $app->router("/insurance-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm Bảo Hiểm");
        $vars['nv1'] = array_map(function($employee) {
            return implode(' - ', $employee);
        }, $app->select("employee", ["name"]));

        echo $app->render('templates/employee/insurance-post.html', $vars, 'global');
    })->setPermissions(['insurance.add']);
    
    $app->router("/insurance-add", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        // Lấy dữ liệu từ form và kiểm tra XSS
        $idbh = $app->count("insurance") + 1;
        $employee = isset($_POST['employee']) ? $app->xss($_POST['employee']) : '';
        $money = isset($_POST['money']) ? $app->xss($_POST['money']) : '';
        $moneybhxh = isset($_POST['moneybhxh']) ? $app->xss($_POST['moneybhxh']) : '';
        $numberbhxh = isset($_POST['numberbhxh']) ? $app->xss($_POST['numberbhxh']) : '';
        $daybhxh = isset($_POST['daybhxh']) ? $app->xss($_POST['daybhxh']) : '';
        $placebhxh = isset($_POST['placebhxh']) ? $app->xss($_POST['placebhxh']) : '';
        $numberyte = isset($_POST['numberyte']) ? $app->xss($_POST['numberyte']) : '';
        $dayyte = isset($_POST['dayyte']) ? $app->xss($_POST['dayyte']) : '';
        $placeyte = isset($_POST['placeyte']) ? $app->xss($_POST['placeyte']) : '';
        $statu = isset($_POST['statu']) ? $app->xss($_POST['statu']) : '';
        $note = isset($_POST['note']) ? $app->xss($_POST['note']) : '';

        // Kiểm tra dữ liệu đầu vào
        if (empty($employee) || empty($money) || empty($moneybhxh) || empty($numberbhxh) || empty($daybhxh) || empty($placebhxh) || empty($numberyte) || empty($dayyte) || empty($placeyte) || empty($statu)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống các trường bắt buộc")]);
            return;
        }
        $temp = str_replace(',', '', $app->xss($_POST['money'] ?? ''));
        $temp2 = str_replace(',', '', $app->xss($_POST['moneybhxh'] ?? ''));

        try {
            // Dữ liệu để lưu vào database
            $insert = [
                "idbh" => $idbh,
                "employee" => $employee,
                "money" => $temp,
                "moneybhxh" => $temp2,
                "numberbhxh" => $numberbhxh,
                "daybhxh" => $daybhxh,
                "placebhxh" => $placebhxh,
                "numberyte" => $numberyte,
                "dayyte" => $dayyte,
                "placeyte" => $placeyte,
                "statu" => $statu,
                "note" => $note,
            ];
              
            // Ghi log
            $jatbi->logs('insurance', 'insurance-add', $insert);
   
            // Thêm dữ liệu vào database
            $app->insert("insurance", $insert);

            echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
    
        } catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['insurance.add']);

    //Xóa insurance
    $app->router("/insurance-deleted", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa Bảo Hiểm");
        echo $app->render('templates/common/deleted.html', $vars, 'global');
    })->setPermissions(['insurance.deleted']);
    
    $app->router("/insurance-deleted", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        if (!empty($_GET['idbh'])) {
            $idbh = $app->xss($_GET['idbh']);
        } elseif (!empty($_GET['box'])) {
            $idbh = $app->xss($_GET['box']);
        }
        
        try {              
            // Xóa dữ liệu trong database
            if (is_string($idbh)) {
                $idbh = explode(',', $idbh); // Split by comma
                foreach ($idbh as $number) {
                    $app->delete("insurance", ["idbh" => trim($number)]); // Trim to remove extra spaces
                }
            } else {
                $app->delete("insurance", ["idbh" => $idbh]);
            }
            echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
        } catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['insurance.deleted']);

    //Sửa insurance
    $app->router("/insurance-edit", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Sửa insurance");
        $vars['nv1'] = array_map(function($employee) {
            return implode(' - ', $employee);
        }, $app->select("employee", ["name"]));
        $idbh = isset($_GET['idbh']) ? $app->xss($_GET['idbh']) : null;
        if (!$idbh) {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
            return;
        }
        $vars['data'] = $app->get("insurance", "*", ["idbh" => $idbh]);
        $vars ['data']['edit'] = true;
        if ($vars['data']) {
            echo $app->render('templates/employee/insurance-post.html', $vars, 'global');
        } else {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
        }
    })->setPermissions(['insurance.edit']);

    $app->router("/insurance-edit", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);

        // Lấy mã nhân viên từ request
        $idbh = isset($_POST['idbh']) ? $app->xss($_POST['idbh']) : null;
        if (!$idbh) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Mã bảo hiểm không hợp lệ")]);
            return;
        }
    
        // Lấy thông tin nhân viên từ DB
        $data = $app->get("insurance", "*", ["idbh" => $idbh]);
        if (!$data) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy bảo hiểm")]);
            return;
        }
    
        // Kiểm tra dữ liệu đầu vào
        $idbh = $_POST['idbh'];
        $employee = isset($_POST['employee']) ? $app->xss($_POST['employee']) : '';
        $money = isset($_POST['money']) ? $app->xss($_POST['money']) : '';
        $moneybhxh = isset($_POST['moneybhxh']) ? $app->xss($_POST['moneybhxh']) : '';
        $numberbhxh = isset($_POST['numberbhxh']) ? $app->xss($_POST['numberbhxh']) : '';
        $daybhxh = isset($_POST['daybhxh']) ? $app->xss($_POST['daybhxh']) : '';
        $placebhxh = isset($_POST['placebhxh']) ? $app->xss($_POST['placebhxh']) : '';
        $numberyte = isset($_POST['numberyte']) ? $app->xss($_POST['numberyte']) : '';
        $dayyte = isset($_POST['dayyte']) ? $app->xss($_POST['dayyte']) : '';
        $placeyte = isset($_POST['placeyte']) ? $app->xss($_POST['placeyte']) : '';
        $statu = isset($_POST['statu']) ? $app->xss($_POST['statu']) : '';
        $note = isset($_POST['note']) ? $app->xss($_POST['note']) : '';
    
        if (empty($employee) || empty($money) || empty($moneybhxh) || empty($numberbhxh) || empty($daybhxh) || empty($placebhxh) || empty($numberyte) || empty($dayyte) || empty($placeyte) || empty($statu)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống các trường bắt buộc: $idbh")]);
            return;
        }
        $temp = str_replace(',', '', $app->xss($_POST['money'] ?? ''));
        $temp2 = str_replace(',', '', $app->xss($_POST['moneybhxh'] ?? ''));

        // Cập nhật dữ liệu trong database
        $update = [
            "employee" => $employee,
            "money" => $temp,
            "moneybhxh" => $temp2,
            "numberbhxh" => $numberbhxh,
            "daybhxh" => $daybhxh,
            "placebhxh" => $placebhxh,
            "numberyte" => $numberyte,
            "dayyte" => $dayyte,
            "placeyte" => $placeyte,
            "statu" => $statu,
            "note" => $note,
        ];
    
        try {
            $app->update("insurance", $update, ["idbh" => $idbh]);
        
            // Ghi log cập nhật
            $jatbi->logs('insurance', 'insurance-edit', $update);
        
            echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật bảo hiểm thành công")]);
        } catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }

    })->setPermissions(['insurance.edit']);

    //Cấp phép insurance
    $app->router("/insurance-approved", 'GET', function($vars) use ($app, $jatbi) {
        if (isset($_GET['idbh']) && isset($_GET['statu'])) {
            $newStatus = ($_GET['statu'] === "2") ? "1" : "2"; // Toggle status between 1 and 2
            $update = [
                "statu" => $newStatus
            ];
            $app->update("insurance", $update, ["idbh" => $_GET['idbh']]);
            echo $app->render('templates/common/restore.html', $vars, 'global');
        }
    })->setPermissions(['insurance.approved']);

    $app->router("/insurance-approved", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        echo json_encode(["status" => "success", "content" => $jatbi->lang("Cấp phép thành công")]);
    })->setPermissions(['insurance.deleted']);

    $app->router("/insurance-status/{idbh}", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $data = $app->get("insurance","*",["idbh"=>$vars['idbh']]);
        if($data>1){
            if($data>1){
                if($data['statu']==='A'){
                    $status = "D";
                } 
                elseif($data['statu']==='D'){
                    $status = "A";
                }
                $app->update("insurance",["statu"=>$status],["idbh"=>$data['idbh']]);
                $jatbi->logs('insurance','insurance-status',$data);
                echo json_encode(value: ['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
            }
            else {
                echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
            }
        }
        else {
            echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['insurance.edit']);
?>
