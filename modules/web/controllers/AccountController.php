<?php

namespace app\modules\web\controllers;

use app\common\services\ConstantMapService;
use app\common\services\UrlService;
use app\models\AppAccessLog;
use app\models\User;
use app\modules\web\controllers\common\BaseController;
use yii\web\Controller;

/**
 * Default controller for the `web` module
 */
class AccountController extends BaseController
{
    public function __construct($id,  $module, array $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->layout = "main";
    }

    /**
     * 账户列表
     */
    public function actionIndex()
    {
        $status = intval($this->get('status', ConstantMapService::$status_default));
        $mix_kw = trim($this->get('mix_kw',''));
        $p = intval($this->get('p', 1)); //当前第几页

        $query = User::find();
        if($status > ConstantMapService::$status_default)
        {
            $query->andWhere(['status' => $status]);
        }
        if($mix_kw != "")
        {
            $where_nickname = ['LIKE', 'nickname', '%' . $mix_kw . '%', false];
            $where_mobile = ['LIKE', 'mobile','%' . $mix_kw . '%', false];
            $query->andWhere(['OR', $where_nickname, $where_mobile]);
        }
        //分页功能，需要两个参数 1：符合条的总记录数 2：每页显示的条数
        $page_size = 50;
        $total_res_count = $query->count();
        $total_page = ceil(($total_res_count / $page_size));

        $list = $query->orderBy(["uid" => SORT_DESC])
            ->offset(($p-1) * $page_size)
            ->limit($page_size)
            ->all();

        return $this->render("index", [
            "list" => $list,
            "status_mapping" => ConstantMapService::$status_mapping,
            'search_conditions' =>[
                'mix_kw' => $mix_kw,
                'status' => $status,
            ],
            'pages' => [
                'total_count' => $total_res_count,
                'page_size' =>$page_size,
                'total_page' =>$total_page,
                'p' => $p,
            ],
        ]);
    }

    /**
     * 账户编辑或者添加
     */
    public function actionSet()
    {
        if(\Yii::$app->request->isGet)
        {
            $id = intval($this->get("id"));
            $info = [];
            if($id)
            {
                $info = User::find()->where(["uid"=>$id])->one();

            }
            return $this->render("set",[
                "info" => $info,
            ]);
        }
        $id = intval($this->post("id","0"));
        $nickname = trim($this->post("nickname", ""));
        $mobile = trim($this->post("mobile", ""));
        $email = trim($this->post("email", ""));
        $login_name = trim($this->post("login_name", ""));
        $login_pwd = $this->post("login_pwd", "");
        $date_now = date("Y-m-d H:i:s");


        if(mb_strlen($nickname,"utf-8") < 1)
        {
            return $this->renderJson([], "请输入符合规范的姓名~~~");
        }
        if(mb_strlen($mobile,"utf-8") != 11)
        {
            return $this->renderJson([], "请输入符合规范的手机号码~~~");
        }
        if(mb_strlen($email,"utf-8") < 1)
        {
            return $this->renderJson([], "请输入符合规范的邮箱号码~~~");
        }
        if(mb_strlen($login_name,"utf-8") < 1)
        {
            return $this->renderJson([], "请输入符合规范的登陆名~~~");
        }
        if(mb_strlen($login_pwd,"utf-8") < 6)
        {
            return $this->renderJson([], "请输入符合规范的登陆密码~~~");
        }

        $has_in = User::find()
            ->where(["nickname" => $nickname])
            ->andWhere(["!=", "uid", $id])
            ->count();
        if($has_in)
        {
            return $this->renderJson([], "该登录名已存在，请换一个试试~~~", -1);
        }

        $info = User::find()->where(["uid" => $id])->one();
        if($info)
        {//编辑
            $model_user = $info;
        } else {//添加
            $model_user = new User();
            $model_user->setSalt();
            $model_user->created_time = $date_now;
        }
        $model_user->nickname = $nickname;
        $model_user->mobile = $mobile;
        $model_user->email = $email;
        $model_user->avatar = ConstantMapService::$default_avatar;
        $model_user->login_name = $login_name;
        if($login_pwd != ConstantMapService::$default_password)
        {
            $model_user->setPassword($login_pwd);
        }
        $model_user->updated_time = $date_now;

        $model_user->save(0);
        return $this->renderJson([], "操作成功~~~");
    }

    /**
     * 账户详情
     */
    public function actionInfo()
    {
        $id = intval($this->get("id", 0));
        $reback_url = UrlService::buildWebUrl("/account/index");
        if(!$id)
        {
            $this->redirect($reback_url);
        }

        $info = User::find()->where(["uid" => $id])->one();
        if(!$info)
        {
            return $this->redirect($reback_url);
        }

        $access_list = AppAccessLog::find()
            ->where(["uid" => $info['uid']])
            ->orderBy(["id" => SORT_DESC])
            ->limit(10)
            ->all();

        return $this->render("info", [
            "info" => $info,
            'access_list' => $access_list,
        ]);
    }

    /**
     * 伪删除 和 恢复账号操作
     */
    public function actionOps(){
        if(!\Yii::$app->request->isPost)
        {
            return $this->renderJson([], "系统繁忙，请稍后再试~~~");
        }
        $uid = intval($this->post("uid",0));
        $act = trim($this->post("act", ""));

        if(!$uid)
        {
            return $this->renderJson([], "请选择要操作的账号~~~");
        }

        if(!in_array($act, ["remove", "recover"]))
        {
            return $this->renderJson([], "操作有误，请重试~~~");
        }

        $user_info = User::find()->where(["uid" => $uid])->one();
        if(!$user_info)
        {
            return $this->renderJson([], "你指定的账号不存在~~~");
        }
        switch($act){
            case "remove":
                $user_info->status = 0;
                break;
            case "recover":
                $user_info->status = 1;
                break;
        }
        $user_info->updated_time = date("Y-m-d H:i:s");
        $user_info->update(0);
        return $this->renderJson([], "操作成功~~~");
    }
}
