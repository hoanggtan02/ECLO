<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    $app->router("/record", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Hồ sơ");
        // $vars['add'] = '/manager/employee-add';
        // $vars['deleted'] = '/manager/employee-deleted';
        $data = $app->select("record", ["id","personName","personSn"]);
        $vars['data'] = $data; 
        echo $app->render('templates/record/record.html', $vars);
    })->setPermissions(['record']);

    $app->router("/record", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;
        $searchValue = $_POST['search']['value'] ?? '';
        $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
        $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
        // $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';

        $where = [
            "AND" => [
                "OR" => [
                    "record.id[~]" => $searchValue,
                    "record.personName[~]" => $searchValue,
                    "record.personSn[~]" => $searchValue,
                    // "accounts.email[~]" => $searchValue,
                    // "accounts.account[~]" => $searchValue,
                ],
                // "accounts.status[<>]" => $status,
                // "accounts.deleted" => 0,
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderName => strtoupper($orderDir)]
        ];

        $count = $app->count("record");

        // $datas = $app->select("record", ['id', 'personName', 'personSn'], $where) ?? [];

        $app->select("record", 
        // [
        //     "[>]permissions" => ["permission" => "id"]
        //     ], 
            [
            'record.id',
            'record.personName',
            'record.personSn',
            // 'permissions.name (permission)',
            ], $where, function ($data) use (&$datas,$jatbi,$app) {
            $datas[] = [
                "checkbox" => $app->component("box",["data"=>$data['id']]),
                "id" => $data['id'],
                "personName" => $data['personName'],
                "personSn" => $data['personSn'],
                "action" => $app->component("action",[
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            // 'permission' => ['record.deleted'],
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
            "data" => $datas ?? []
        ]);
    })->setPermissions(['record']);

    //mở hộp thoại tìm kiếm khoảng thời gian
    $app->router("/record/record-find", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("tìm hồ sơ khoảng thời gian");
        echo $app->render('templates/record/record-find.html', $vars, 'global');
    })->setPermissions(['record']);

    $app->router("/record/record-find", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        if($app->xss($_POST['startTime'])=='' || $app->xss($_POST['endTime'])==''){
            $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng nhập thời gian")];
        }
        if($app->xss($_POST['startTime']) > $app->xss($_POST['endTime'])){
            $error = ["status"=>"error","content"=>$jatbi->lang("Thời gian bắt đầu phải sớm hơn thời gian kết thúc")];
        }
        if(empty($error)){
            $insert = [
                "startTime"     => $app->xss($_POST['startTime']),
                "endTime"       => $app->xss($_POST['endTime']),
                "personSn"      => $app->xss($_POST['email']),
                "personType"    => $app->xss($_POST['permission']),
                "recordType"    => $app->xss($_POST['phone']),
                "index"         => $app->xss($_POST['gender']),
                "length"        => $app->xss($_POST['birthday']),
                "order"         => password_hash($app->xss($_POST['password']), PASSWORD_DEFAULT),
                "lang"          => $_COOKIE['lang'] ?? 'vi',
            ];
        }


        // elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        //     $error = ['status'=>'error','content'=>$jatbi->lang('Email không đúng')];
        // }
        // if(empty($error)){
        //     $insert = [
        //         "type"          => 1,
        //         "name"          => $app->xss($_POST['name']),
        //         "account"       => $app->xss($_POST['account']),
        //         "email"         => $app->xss($_POST['email']),
        //         "permission"    => $app->xss($_POST['permission']),
        //         "phone"         => $app->xss($_POST['phone']),
        //         "gender"        => $app->xss($_POST['gender']),
        //         "birthday"      => $app->xss($_POST['birthday']),
        //         "password"      => password_hash($app->xss($_POST['password']), PASSWORD_DEFAULT),
        //         "active"        => $jatbi->active(),
        //         "date"          => date('Y-m-d H:i:s'),
        //         "login"         => 'create',
        //         "status"        => $app->xss($_POST['status']),
        //         "lang"          => $_COOKIE['lang'] ?? 'vi',
        //     ];
        //     $app->insert("accounts",$insert);
        //     $getID = $app->id();
        //     $app->insert("settings",["account"=>$getID]);
        //     $directory = 'datas/'.$insert['active'];
        //     mkdir($directory, 0755, true);
        //     if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        //         $imageUrl = $_FILES['avatar'];
        //     }
        //     else {
        //         $imageUrl = 'datas/avatar/avatar'.rand(1,10).'.png';
        //     }
        //     $handle = $app->upload($imageUrl);
        //     $path_upload = 'datas/'.$insert['active'].'/images/';
        //     if (!is_dir($path_upload)) {
        //         mkdir($path_upload, 0755, true);
        //     }
        //     $path_upload_thumb = 'datas/'.$insert['active'].'/images/thumb';
        //     if (!is_dir($path_upload_thumb)) {
        //         mkdir($path_upload_thumb, 0755, true);
        //     }
        //     $newimages = $jatbi->active();
        //     if ($handle->uploaded) {
        //         $handle->allowed        = array('image/*');
        //         $handle->file_new_name_body = $newimages;
        //         $handle->Process($path_upload);
        //         $handle->image_resize   = true;
        //         $handle->image_ratio_crop  = true;
        //         $handle->image_y        = '200';
        //         $handle->image_x        = '200';
        //         $handle->allowed        = array('image/*');
        //         $handle->file_new_name_body = $newimages;
        //         $handle->Process($path_upload_thumb);
        //     }
        //     if($handle->processed ){
        //         $getimage = 'upload/images/'.$newimages;
        //         $data = [
        //             "file_src_name" => $handle->file_src_name,
        //             "file_src_name_body" => $handle->file_src_name_body,
        //             "file_src_name_ext" => $handle->file_src_name_ext,
        //             "file_src_pathname" => $handle->file_src_pathname,
        //             "file_src_mime" => $handle->file_src_mime,
        //             "file_src_size" => $handle->file_src_size,
        //             "image_src_x" => $handle->image_src_x,
        //             "image_src_y" => $handle->image_src_y,
        //             "image_src_pixels" => $handle->image_src_pixels,
        //         ];
        //         $insert = [
        //             "account" => $getID,
        //             "type" => "images",
        //             "content" => $path_upload.$handle->file_dst_name,
        //             "date" => date("Y-m-d H:i:s"),
        //             "active" => $newimages,
        //             "size" => $data['file_src_size'],
        //             "data" => json_encode($data),
        //         ];
        //         $app->insert("uploads",$insert);
        //         $app->update("accounts",["avatar"=>$getimage],["id"=>$getID]);
        //     }
        //     echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công"),"test"=>$imageUrl]);
        //     $jatbi->logs('accounts','accounts-add',$insert);
        // }
        // else {
            echo json_encode($error);
        // }
    })->setPermissions(['record']);

    //mở hộp thoại xòa khoảng thời gian
    $app->router("/record/record-delete", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa hồ sơ khoảng thời gian");
        // $vars['permissions'] = $app->select("permissions","*",["deleted"=>0,"status"=>"A"]);
        // $vars['data'] = [
        //     "status" => 'A',
        //     "permission" => '',
        //     "gender" => '',
        // ];
        echo $app->render('templates/record/record-delete.html', $vars, 'global');
    })->setPermissions(['record.deleted']);