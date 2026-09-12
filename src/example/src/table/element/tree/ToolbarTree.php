<?php
namespace xqkeji\app\{MODULE_NAME}\table\element;
use xqkeji\form\element\Td;
class ToolbarTree extends Td
{
    protected $name = "list-toolbar-tree";
    protected $attrs = [
        'colspan' => 99,
        'style' => 'text-align:left;',
    ];
    protected $el = [
        [
            '$TableDiv',
            'name' => 'list-toolbar-content',
            'attrs' => [
                'class' => 'd-flex',
            ],
            'el' => [
                [
					'$button',
					'name'=>'add',
					'attrs'=>[
						'id'=>'xq-add',
						'class'=>'btn btn-primary xq-add',
						'data-bs-toggle'=>'tooltip',
						'data-bs-placement'=>'top',
						'data-bs-trigger'=>'hover',
						'data-bs-html'=>'true',
						'title'=>'没选中时，添加顶级{中文名}；<br/>有选中时，添加子{中文名}。',
						'value'=>'添加',
					],
				]
            ],
        ]
    ];
}
