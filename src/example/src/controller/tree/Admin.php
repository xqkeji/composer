<?php
namespace xqkeji\app\edu\controller\dept;
use xqkeji\mvc\action\Admin as BaseAdmin;
class Admin extends BaseAdmin
{
	protected $pageSize=0;
	protected $conditions=[
		'parent_id'=>'58514b454a495f524f4f5430',
		'status'=>1,
	];
	protected $order=[
		'left_value'=>'asc',
	];
}
