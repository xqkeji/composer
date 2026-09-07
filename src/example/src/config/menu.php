<?php
return [
	/*
	//后台管理菜单，按授权入口分组（入口名称需与 acl.php 的入口保持一致，如 admin、member）
	'入口名称'=>[
		'title'=>'入口显示名称',
		'children'=>[
			[
				'url'=>'控制器/动作',
				'title'=>'功能名称',
				'icon'=>'bootstrap-icon图标的类样式',
				'submenu'=>false,//是否子菜单访问链接,如果是，点击后是展开子菜单
			],
		],
	]
	*/
	'admin'=>[
		'title'=>'{MODULE_NAME}管理',
		'children'=>[
			/*
			[
				'url'=>'控制器/动作',
				'title'=>'功能名称',
				'icon'=>'bi bi-list',
			],
			*/
		]
	],
];
