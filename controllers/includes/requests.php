<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $requests = [
        "main"=>[
            "name"=>$jatbi->lang("Chính"),
            "item"=>[
                '/'=>[
                    "menu"=>$jatbi->lang("Trang chủ"),
                    "url"=>'/',
                    "icon"=>'<i class="ti ti-dashboard"></i>',
                    "controllers"=>"controllers/core/main.php",
                    "main"=>'true',
                    "permission" => "",
                ],
            ],
        ],
        "timekeeping"=>[
            "name"=>$jatbi->lang("Chấm công"),
            "item"=>[
                'group-access'=>[
                    "menu"=>$jatbi->lang("Nhóm kiểm soát"),
                    "url"=>'/group-access',
                    "icon"=>'<i class="ti ti-users-group"></i>',
                    "sub"=>[
                        'group-access'    =>[
                            "name"  => $jatbi->lang("Danh sách nhóm"),
                            "router"=> '/control/group-access',
                            "icon"  => '<i class="fas fa-key"></i>',
                        ],
                    ],
                    "controllers"=>"controllers/core/group-access.php",
                    "main"=>'false',
                    "permission"=>[
                        'group-access'          => $jatbi->lang("Nhóm kiểm soát"),
                        'group-access.add'      => $jatbi->lang("Thêm Nhóm kiểm soát"),
                        'group-access.edit'     => $jatbi->lang("Sửa Nhóm kiểm soát"),
                        'group-access.deleted'  => $jatbi->lang("Xóa Nhóm kiểm soát"),
                    ]
                ],
                'record'=>[
                    "menu"=>$jatbi->lang("Danh Sách Ra Vào"),
                    "url"=>'/record',
                    "icon"=>'<i class="ti ti-book "></i>',
                    "controllers"=>"controllers/core/record.php",
                    "main"=>'false',
                    "permission"=>[
                        'record'      =>$jatbi->lang("Hồ sơ"),
                        'record.add'  =>$jatbi->lang("Thêm hồ sơ"),
                        'record.edit' =>$jatbi->lang("Sửa hồ sơ"),
                        'record.deleted'=>$jatbi->lang("Xóa hồ sơ"),
                    ]
                ],
                'employee'=>[
                    "menu"=>$jatbi->lang("Quản lý"),
                    "url"=>'/employee',
                    "icon"=>'<i class="ti ti-layout-dashboard "></i>',
                    "sub"=>[
                        'employee'      =>[
                            "name"  => $jatbi->lang("Nhân viên"),
                            "router"=> '/manager/employee',
                            "icon"  => '<i class="ti ti-user"></i>',
                        ],
                        'face_employee'    =>[
                            "name"  => $jatbi->lang("Khuôn mặt"),
                            "router"=> '/manager/face_employee',
                            "icon"  => '<i class="fas fa-universal-access"></i>',
                            "controllers" => 'controllers/core/face_employee.php',
                        ],
                        'checkinout'      =>[
                            "name"  => $jatbi->lang("Thời gian ra vào"),
                            "router"=> '/manager/checkinout',
                            "icon"  => '<i class="fas fa-universal-access"></i>',
                            "controllers" => 'controllers/core/checkinout.php',

                        ],
                        'timeperiod'      =>[
                            "name"  => $jatbi->lang("Khung thời gian"),
                            "router"=> '/manager/timeperiod',
                            "icon"  => '<i class="fas fa-universal-access"></i>',
                            "controllers" => 'controllers/core/timeperiod.php',
                        ],
                    ],
                    "controllers"=>"controllers/core/employee.php",
                    "main"=>'false',
                    "permission"=>[
                        'employee'      =>$jatbi->lang("Nhân viên"),
                        'employee.add'  =>$jatbi->lang("Thêm Nhân viên"),
                        'employee.edit' =>$jatbi->lang("Sửa Nhân viên"),
                        'employee.deleted'=>$jatbi->lang("Xóa Nhân viên"),
                        'department'    =>$jatbi->lang("Phòng ban"),
                        'department.add'=>$jatbi->lang("Thêm Phòng ban"),
                        'department.edit'=>$jatbi->lang("Sửa Phòng ban"),
                        'department.deleted'=>$jatbi->lang("Xóa Phòng   ban"),
                        'position'      =>$jatbi->lang("Chức vụ"),
                        'position.add'  =>$jatbi->lang("Thêm Chức vụ"),
                        'position.edit' =>$jatbi->lang("Sửa Chức vụ"),
                        'position.deleted'=>$jatbi->lang("Xóa Chức vụ"),
                        'checkinout' => $jatbi->lang("Hồ sơ ra vào"),
                        'checkinout.add' => $jatbi->lang("Thêm Hồ sơ ra vào"),
                        'checkinout.edit' => $jatbi->lang("Sửa Hồ sơ ra vào"),
                        'checkinout.sync' => $jatbi->lang("Đồng Bộ"),
                        'checkinout.deleted' => $jatbi->lang("Xóa Hồ sơ ra vào"),
                        'timeperiod'      =>$jatbi->lang("Khung thời gian"),
                        'timeperiod.add'  =>$jatbi->lang("Thêm Khung thời gian"),
                        'timeperiod.sync' =>$jatbi->lang("Đồng Bộ"),
                        'timeperiod.deleted'=>$jatbi->lang("Xóa Khung thời gian"),
                        'timeperiod.edit'=>$jatbi->lang("Sửa Khung thời gian"),
                        'face_employee' => $jatbi->lang("Khuôn mặt"),
                        'face_employee.add' => $jatbi->lang("Thêm Khuôn mặt"),
                        'face_employee.edit' => $jatbi->lang("Sửa Khuôn mặt"),
                        'face_employee.deleted' => $jatbi->lang("Xóa Khuôn mặt"),
                        'face_employee.deleted.multiple' => $jatbi->lang("Xóa nhiều Khuôn mặt"),
                    ]
                ],
            ],
        ],
        "page"=>[
            "name"=>'Admin',
            "item"=>[
                'users'=>[
                    "menu"=>$jatbi->lang("Người dùng"),
                    "url"=>'/users',
                    "icon"=>'<i class="ti ti-user "></i>',
                    "sub"=>[ 
                        'accounts'      =>[
                            "name"  => $jatbi->lang("Tài khoản"),
                            "router"=> '/users/accounts',
                            "icon"  => '<i class="ti ti-user"></i>',
                        ],
                        'permission'    =>[
                            "name"  => $jatbi->lang("Nhóm quyền"),
                            "router"=> '/users/permission',
                            "icon"  => '<i class="fas fa-universal-access"></i>',
                        ],
                    ],
                    "controllers"=>"controllers/core/users.php",
                    "main"=>'false',
                    "permission"=>[
                        'accounts'=> $jatbi->lang("Tài khoản"),
                        'accounts.add' => $jatbi->lang("Thêm tài khoản"),
                        'accounts.edit' => $jatbi->lang("Sửa tài khoản"),
                        'accounts.deleted' => $jatbi->lang("Xóa tài khoản"),
                        'permission'=> $jatbi->lang("Nhóm quyền"),
                        'permission.add' => $jatbi->lang("Thêm Nhóm quyền"),
                        'permission.edit' => $jatbi->lang("Sửa Nhóm quyền"),
                        'permission.deleted' => $jatbi->lang("Xóa Nhóm quyền"),
                    ]
                ],
                'admin'=>[
                    "menu"=>$jatbi->lang("Quản trị"),
                    "url"=>'/admin',
                    "icon"=>'<i class="ti ti-settings "></i>',
                    "sub"=>[
                        'blockip'   => [
                            "name"  => $jatbi->lang("Chặn truy cập"),
                            "router"    => '/admin/blockip',
                            "icon"  => '<i class="fas fa-ban"></i>',
                        ],
                        'trash'  => [
                            "name"  => $jatbi->lang("Thùng rác"),
                            "router"    => '/admin/trash',
                            "icon"  => '<i class="fa fa-list-alt"></i>',
                        ],
                        'logs'  => [
                            "name"  => $jatbi->lang("Nhật ký"),
                            "router"    => '/admin/logs',
                            "icon"  => '<i class="fa fa-list-alt"></i>',
                        ],
                        'config'    => [
                            "name"  => $jatbi->lang("Cấu hình"),
                            "router"    => '/admin/device-information',
                            "icon"  => '<i class="fa fa-cog"></i>',
                            "req"   => 'modal-url',
                            "controllers" => 'controllers/core/system.php',
                        ],
                    ],
                    "controllers"=>"controllers/core/admin.php",
                    "main"=>'false',
                    "permission"=>[
                        'blockip'       =>$jatbi->lang("Chặn truy cập"),
                        'blockip.add'   =>$jatbi->lang("Thêm Chặn truy cập"),
                        'blockip.edit'  =>$jatbi->lang("Sửa Chặn truy cập"),
                        'blockip.deleted'=>$jatbi->lang("Xóa Chặn truy cập"),
                        'config'        =>$jatbi->lang("Cấu hình"),
                        'logs'          =>$jatbi->lang("Nhật ký"),
                        'trash'          =>$jatbi->lang("Thùng rác"),
                    ]
                ],
            ],
        ],
    ];
    foreach($requests as $request){
        foreach($request['item'] as $key_item =>  $items){
            $setRequest[] = [
                "key" => $key_item,
                "controllers" =>  $items['controllers'],
            ];
            // Thêm controllers từ sub
            if (isset($items['sub']) && is_array($items['sub'])) {
                foreach ($items['sub'] as $sub_key => $sub_item) {
                    if (isset($sub_item['controllers'])) {
                        $setRequest[] = [
                            "key" => $sub_key,
                            "controllers" => $sub_item['controllers'],
                        ];
                    }
                }
            }
            if($items['main']!='true'){
                $SelectPermission[$items['menu']] = $items['permission'];
            }
            if (isset($items['permission']) && is_array($items['permission'])) {
                foreach($items['permission'] as $key_per => $per) {
                    $userPermissions[] = $key_per; 
                }
            }
        }
    }
?>