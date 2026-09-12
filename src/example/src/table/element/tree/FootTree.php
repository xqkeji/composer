<?php
namespace xqkeji\app\{MODULE_NAME}\table\element;
use xqkeji\form\element\ListFoot;
class FootTree extends ListFoot
{
	protected $name = 'list_foot_{TABELE_NAME}';
	protected $el=[
		'@CheckAll',
		'~ToolbarTree',
	];

}

