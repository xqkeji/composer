<?php
namespace xqkeji\app\{MODULE_NAME}\controller\{CONTROLLER_NAME};
use xqkeji\mvc\Action;
use xqkeji\mvc\builder\Model;
use xqkeji\app\{MODULE_NAME}\model\{MODEL_NAME};
use xqkeji\App;
class Add extends Action
{

	public function run()
	{
		$view=$this->view;
		$view->disable();
		$request=$this->request;
		
		$modelName=$this->modelName;
		$model=Model::getModel($modelName);
		
        if($request->isPost()) 
		{
			$data=$request->getPut();
			if(empty($data['pid']))
			{
				$pid={MODEL_NAME}::ROOT_NODE;
			}
			else
			{
				$pid=$data['pid'];
			}
			$model->setAttr('parent_id',$pid);
			$model->setAttr('name','{CONTROLLER_NAME}');
			$model->setAttr('status',1);

			$model->save();
			$catid=(string)$model->getKey();
			$name=$model->getAttr('name');
			$content='<td><input type="checkbox" id="id_'.$catid.'" name="'.
			$catid .'[id]" value="' .$catid .'" class="form-check-input"></td>'.
			'<td style="text-align:left;"><input type="text" id="name_' .
			$catid . '" name="' . $catid . '[name]" value="' .$name. '" class="form-control" style="width:300px;" required="1" ></td>'.
			'<td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="status_' . $catid . '" name="' . $catid . '[status]" checked=""></div></td>'.
			'<input type="button" value="编辑" class="btn btn-primary btn-sm xq-edit" style="margin-right:5px;">' .
			'<input type="button"  value="删除" class="btn btn-danger btn-sm xq-delete" style="margin-right:5px;">' .
			'</td>';
			$result=[
				'id'=>(string)$model->getKey(),
				'name'=>'{CONTROLLER_NAME}',
				'depth'=>$model->getAttr('depth'),
				'is_leaf'=>$model->isLeaf(),
				'pid'=>$data['pid'],
				'status'=>1,
				'content'=>$content,
			];
			echo json_encode($result);
			exit(0);
		}
		else
		{
			throw new \Exception(App::t("no request data"),500);
		}
		
	}
}