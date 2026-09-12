<?php
namespace xqkeji\app\{MODULE_NAME}\table\element;
use xqkeji\form\element\ListItem;
class NameTree extends ListItem
{
	protected $name = 'list_name';
	protected $text = '{中文名称}';
	protected $attrs=[
		'style'=>'width:100%;',
	];
	protected $el = [
		[
			'$text',
			'name'=>'name',
			'attrs'=>[
				'class'=>'form-control',
				'style'=>'width:300px;',
			],
		]
	];
}

