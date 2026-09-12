<?php
namespace xqkeji\app\{MODULE_NAME}\table\element;
use xqkeji\form\element\ListItem;
class EditDeleteTree extends ListItem
{
	protected $name = 'list_edit_delete';
	protected $text = '操作';
	protected $attrs=[
		'style'=>'min-width:200px;',
	];
	protected $el=[
		[
			'$button',
			'name'=>'setting',
			'attrs'=>[
				'id'=>'xq-treegrid-edit',
				'class'=>'btn btn-primary btn-sm xq-edit',
				'style'=>'margin-right:5px;',
				'value'=>'编辑',
			],
		],
		[
			'$button',
			'name'=>'delete',
			'attrs'=>[
				'id'=>'xq-treegrid-delete',
				'class'=>'btn btn-danger btn-sm xq-delete',
				'style'=>'margin-right:5px;',
				'value'=>'删除',
			],
		],
	];
}

