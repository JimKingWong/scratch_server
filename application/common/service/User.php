<?php

namespace app\common\service;

use app\admin\model\AdminData;
use app\common\model\UserInfo;
use app\common\model\Wallet;
use think\Db;
use think\Validate;

/**
 * 用户服务层
 */
class User extends Base
{
    protected $model = null;

    public function __construct()
    {
        parent::__construct();

        $this->model = new \app\common\model\User();
    }
    /**
     * 用户信息
     */
    public function userinfo()
    {
        $userinfo = $this->auth->getUserinfo();

        $retval = [
            'userinfo'              => $userinfo,
        ];
        $this->success(__('请求成功'), $retval);
    }
    
    public function extractIdFromUrl($url) 
    {
        // 解码HTML实体和URL编码
        $url = html_entity_decode(urldecode($url));
        
        // 尝试从查询参数或Fragment中提取 invite_code
        $inviteCode = '';
        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);
    
        // 检查常规查询参数
        if ($query) {
            parse_str($query, $params);
            $inviteCode = $params['invite_code'] ?? '';
        }
    
        // 检查Fragment中的参数（如 #/?invite_code=xxx）
        if (!$inviteCode && $fragment) {
            if (preg_match('/invite_code=([^&]+)/', $fragment, $matches)) {
                $inviteCode = $matches[1];
            }
        }
    
        // 最后尝试全局正则匹配
        if (!$inviteCode && preg_match('/invite_code=([^&]+)/', $url, $matches)) {
            $inviteCode = $matches[1];
        }
    
        // 过滤非ASCII字符（只保留字母、数字、下划线等）
        if ($inviteCode) {
            $inviteCode = preg_replace('/[^\x20-\x7E]/', '', $inviteCode); // 移除非ASCII字符
            // 或者更严格：只保留字母和数字
            // $inviteCode = preg_replace('/[^a-zA-Z0-9]/', '', $inviteCode);
        }
    
        return $inviteCode ?: '';
    }
    

    /**
     * 注册
     */
    public function register()
    {
        $username = $this->request->post('username');
        $password = $this->request->post('password');
        $repassword = $this->request->post('repassword');
        $email = $this->request->post('email') ?? $username . "hoho@gmail.com";;
        $mobile = $this->request->post('mobile') ?? "18888888888";;
        $invite_code = $this->request->post('invite_code');
        $url = $this->request->post('url');
        \think\Log::record($url, 'URL');
        \think\Log::record($username, 'username');
        $invite_code = $this->extractIdFromUrl($url);

        // if(!$invite_code) {
        //     $invite_code = $this->extractIdFromUrl($url);
        // }
        // 验证用户名密码是否为空
        if(!$username || !$password){
            $this->error(__('无效参数'));
        }

        // 验证两次密码是否一致
        if(!$repassword || $password != $repassword){
            $this->error(__('两次输入密码不一致'));
        }

        // 站点来源
        $origin = $this->origin ?? 'localhost';

        $ip = $_SERVER["REMOTE_ADDR"];
        $real_ip = false;
        if(isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
            $real_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        }
        if($real_ip) $ip = $real_ip;

        // 同ip最多创建三个账号
        $ipCount = $this->model->where('joinip', $ip)->where('origin', $origin)->count();
        if($ipCount >= 3){
            $this->error(__('您只允许从同一个IP创建3个帐户'));
        }

        // 管理员信息模型
        $adminDataModel = new AdminData();

        // 上级用户
        $admin_id       = 0;
        $parent_id      = 0;
        $parent_id_str  = '';
        $be_invite_code = '';

        // 代理邀请码长度
        $agent_code_length = config('system.agent_code_length');
        // 用户邀请码长度
        $user_code_length = config('system.user_code_length');

        if($invite_code){
            // 通过邀请码长度查询是代理还是用户邀请
            $length = strlen($invite_code);
            if($length == $agent_code_length){
                $agent = $adminDataModel->where('invite_code', $invite_code)->find();

                if($agent){
                    $admin_id = $agent->admin_id;
                    $be_invite_code = $agent->invite_code;
                }
            }else if(strlen($invite_code) == $user_code_length){
                // 通过邀请码查询用户
                $parent = $this->model->where('invite_code', $invite_code)->find();
                // dd($parent);
                if($parent){
                    // 同步上级代理信息
                    $admin_id = $parent->admin_id;
                    // 上级用户ID
                    $parent_id = $parent->id;
                    // 上级的关系链
                    $parent_id_str = $this->model::parentIdStr($parent);
                    // 上级邀请码
                    $be_invite_code = $parent->invite_code;

                    // 邀请码是用户的邀请码，增加邀请人数
                    $parent->userdata->invite_num += 1;
                    
                    // 掉绑设置
                    if($parent->usersetting->unbind_status == 1){
                        $countChild = $this->model->where('parent_id', $parent->id)->count();
                        
                        // 默认70%，意味着下级有30人后，会有30%掉绑，写80就有 20%掉绑
                        if($countChild >= 30){
                            $rand = rand(0, 100);
                            if($rand > $parent->usersetting->unbind_rate){
                                $parent_id      = 0;
                                $parent_id_str  = '';
                                // 掉绑不加人数
                                $parent->userdata->invite_num -= 1;
                            }
                            
                        }
                        // dd($countChild);
                    }
                }
            }
        }
        
        // 补充参数注册
        $extend = [
            'admin_id'        => $admin_id,
            'parent_id'       => $parent_id,
            'parent_id_str'   => $parent_id_str,
            'be_invite_code'  => $be_invite_code,
            'origin'          => $origin,
            'invite_code'     => createInviteCode($user_code_length), // 管理员6位, 用户8位
        ];
        
        Db::startTrans();
        try{
            $ret = $this->auth->register($username, $password, $email, $mobile, $extend);
            
            if(!empty($parent)){
                // 邀请码是用户的邀请码，增加邀请人数
                if($parent->userdata->save() === false){
                    $ret = false;
                }
            }
            
            if($ret != false){
                Db::commit();
            }

        }catch(\Exception $e){
            
            Db::rollback();
            $this->error($e->getMessage());
        }

        if(!$ret){
            $this->error($this->auth->getError());
        }

        $data = ['userinfo' => $this->auth->getUserinfo()];
        $this->success(__('登录成功'), $data);
        
    }

    /**
     * 登录
     */
    public function login()
    {
        $account = $this->request->post('account');
        $password = $this->request->post('password');
        if(!$account || !$password){
            $this->error(__('无效参数'));
        }
        $ret = $this->auth->login($account, $password, $this->origin);
        if($ret){
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('登录成功'), $data);
        }else{
            $this->error($this->auth->getError());
        }
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        if (!$this->request->isPost()) {
            $this->error(__('无效参数'));
        }
        $this->auth->logout();
        $this->success(__('注销成功'));
    }

    /**
     * 编辑钱包
     */
    public function editwallet()
    {
        $id             = $this->request->post('id', 0);
        $name           = $this->request->post('name');
        $phoneNumber    = $this->request->post('phone_number');
        $chavePix       = $this->request->post('chave_pix');
        $pix            = $this->request->post('pix');
        $cpf            = $this->request->post('cpf'); // CPF
        $areaCode       = $this->request->post('area_code', '+55');
        $pixType        = $this->request->post('pix_type', 'CPF');
        $isDefault      = $this->request->post('is_default', 0);
        
        if(!$name || !$phoneNumber || !$chavePix || !$cpf || !$pix || !$areaCode){
            // 缺少参数
            $this->error(__('Parâmetro ausente'));
        }

        // 过滤字符
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber); // 过滤非数字字符
        $cpf = preg_replace('/[^0-9]/', '', $cpf); // 过滤非数字字符
        $pix = preg_replace('/[^0-9a-zA-Z]/', '', $pix); // 过滤非数字和字母字符
        
        // dd($phoneNumber);
        $regex = '/^[1-9]{2}9\d{8}$/'; // 11 98888-8888
        if(!preg_match($regex, $phoneNumber)){
            $this->error(__('电话号码无效'));
        }

        if($chavePix == 'PIX_PHONE'){
            $pix = $areaCode . $phoneNumber; // PIX é o número de telefone
        }

        $user = $this->auth->getUser();
        $where['user_id'] = $user->id;
        $where['id'] = $id;
        $wallet = Wallet::where($where)->find();


        // 查询钱包数量
        $walletList = Wallet::where('user_id', $user->id)->select();

        if(count($walletList) >= 3 && !$id){
            $this->error(__('已超过 3 个限制。'));
        }
        // 如果当前设置默认
        if($isDefault == 1){
            foreach($walletList as $v){
                // 其他的都设置为非默认
                $v->is_default = 0;
                $v->save();
            }
        }

        if(!$wallet){
            $result = Wallet::create([
                'admin_id'      => $user->admin_id,
                'user_id'       => $user->id,
                'name'          => $name,
                'phone_number'  => $phoneNumber,
                'area_code'     => $areaCode,
                'chave_pix'     => $chavePix,
                'pix'           => $pix,
                'cpf'           => $cpf,
                'pix_type'      => $pixType,
                'is_default'    => $isDefault,
            ]);
            
        }else{
            $result = $wallet->save([
                'name'          => $name,
                'phone_number'  => $phoneNumber,
                'area_code'     => $areaCode,
                'chave_pix'     => $chavePix,
                'pix'           => $pix,
                'cpf'           => $cpf,
                'pix_type'      => $pixType,
                'is_default'    => $isDefault,
            ]);
        }
        // dd($result);
        if($result === false){
            $this->error(__('修改失败'));
        }
        $this->success(__('修改成功'));
    }

    /**
     * 设置密码
     */
    public function setPassword()
    {
        $password = $this->request->post('pay_password');
        $repassword = $this->request->post('re_pay_password');

        $data = [
            'pay_password' => $password,
        ];

        $validate = new Validate([
            'pay_password' => 'require|min:6'
        ]);
        if(!$validate->check($data)){
            $this->error(__('提现密码不能为空，且长度不能小于6位'));
        }

        if($password != $repassword){
            $this->error(__('两次输入密码不一致'));
        }

        $user = $this->auth->getUser();

        if($user->pay_password){
            $this->error(__('提现密码已设置'));
        }

        $user->pay_password = $password;
        $result = $user->save();

        if($result === false){
            $this->error(__('设置提现密码失败'));
        }

        $this->success(__('设置提现密码成功'));
    }

    /**
     * 校验提现密码
     */
    public function checkPassword()
    {
        $password = $this->request->post('pay_password');

        $user = $this->auth->getUser();

        if(!$user->pay_password){
            $this->error(__('提现密码未设置'));
        }

        if($password != $user->pay_password){
            $this->error(__('提现密码错误'));
        }

        $this->success(__('校验成功'));
    }

    /**
     * 用户资料
     */
    public function profile()
    {
        $user = $this->auth->getUser();
        
        if(!$user->userinfo){
            $userinfo = new UserInfo();
            $userinfo->admin_id    = $user->admin_id;
            $userinfo->user_id     = $user->id;
            $userinfo->email       = $user->email;
            $userinfo->mobile      = $user->mobile;
            $userinfo->save();

        }else{
            $userinfo = $user->userinfo;
        }

        $userinfo->level = $user->level;
        $userinfo->username = $user->username;
        $retval = [
            'userinfo'  => $userinfo,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 编辑用户资料
     */
    public function editProfile()
    {
        $email      = $this->request->post('email');
        $whatsapp   = $this->request->post('whatsapp');
        $facebook   = $this->request->post('facebook');
        $telegram   = $this->request->post('telegram');
        $line       = $this->request->post('line');
        $twitter    = $this->request->post('twitter');
        $birthday   = $this->request->post('birthday');

         // 生日必须大于等于18岁
        $birthday_time = strtotime($birthday);
        if(time() - $birthday_time < 18 * 365 * 24 * 60 * 60){
            $this->error(__('生日必须大于等于18岁'));
        }

        if($email && !Validate::is($email, 'email')){
            $this->error(__('邮箱格式不正确'));
        }

        $user = $this->auth->getUser();

        // 用户信息
        $userinfo = $user->userinfo;

        if($userinfo){

            // 一旦填写生日，不可修改
            if($birthday && !$userinfo->birthday){
                $userinfo->birthday = $birthday;
            }

            $userinfo->email       = $email;
            $userinfo->whatsapp    = $whatsapp;
            $userinfo->facebook    = $facebook;
            $userinfo->telegram    = $telegram;
            $userinfo->line        = $line;
            $userinfo->twitter     = $twitter;
            $userinfo->save();

            $userinfo->level = $user->level;
        }
        
        $retval = [
            'userinfo'  => $userinfo,
        ];
        $this->success(__('修改成功'), $retval);
    }

    /**
     * 用户层级
     */
    public function rank()
    {
        $rank = $this->request->get('rank');
        if(!in_array($rank, [1, 2 ,3])) $rank = 1;

        $search_user_id = $this->request->get('search_user_id');
        $where = [];
        if($search_user_id != ''){
            $where['id'] = $search_user_id;
        }

        $users = $this->model::getSubUsers($this->auth->id, 1, '', $where);

        // 根据传入的层级，返回相应的用户列表
        $list = $users[$rank] ?? [];

        // 用户数
        $num = count($list);

        // 充值人数
        $firstRechargeNum = 0;
        
        // 有效用户数
        $goodNum = 0;

        $totalBetMoney = 0;
        $totalRechargeMoney = 0;

        foreach($list as $val){
            if($val['is_first_recharge'] == 1){
                $firstRechargeNum ++;
            }
            if($val['is_valid'] == 1){
                $goodNum ++;
            }
            $totalBetMoney += $val['total_bet'];
            $totalRechargeMoney += $val['total_recharge'];
        }
        // 计算平均充值金额
        $averageRecharge = $firstRechargeNum > 0
            ? bcdiv($totalRechargeMoney, $firstRechargeNum, 2)
            : 0;

        $total = [
            'num'                   => $num,
            'first_recharge_num'    => $firstRechargeNum,
            'good_num'              => $goodNum,
            'total_bet_money'       => $totalBetMoney,
            'total_recharge_money'  => $totalRechargeMoney,
            'average_recharge'      => $averageRecharge,
        ];

        $retval = [
            'list'      => $list,
            'total'     => $total,
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 奖金数据
     */
    public function bonus()
    {
        $user = $this->auth->getUser();

        $recharge = db('recharge')->where('user_id', $user->id)->field('id,order_no,money,status,paytime,createtime')->select();
        foreach($recharge as $key=>$val){
            $val['status_text'] = $val['status'] == 0 ? __('未支付') : __('已支付');
            $val['flag'] = 'recharge';
            $recharge[$key] = $val;
        }

        $withdraw = db('withdraw')->where('user_id', $user->id)->field('id,order_no,money,real_money,status,paytime,createtime')->select();
        $arr = [__('审核中'), __('提现成功'), __('拒绝'), __('提现失败'), __('异常')];
        foreach($withdraw as $key=>$val){
            $val['status_text'] = $arr[$val['status']];
            $val['flag'] = 'withdraw';
            $withdraw[$key] = $val;
        }

        $record = array_merge($recharge, $withdraw);
        
        usort($record, function($a, $b) {
            // 如果createtime不存在，则使用paytime
            $timeA = isset($a['createtime']) ? $a['createtime'] : $a['paytime'];
            $timeB = isset($b['createtime']) ? $b['createtime'] : $b['paytime'];
            
            // 降序排序（最新的在前）
            if ($timeA == $timeB) {
                return 0;
            }
            return ($timeA > $timeB) ? -1 : 1;
            
            // 如果需要升序排序（最旧的在前），使用下面的代码
            // return ($timeA < $timeB) ? -1 : 1;
        });

        $retval = [
            'money'             => number_format($user->money),
            'freeze_money'      => number_format($user->money - $user->bonus),
            'bonus'             => number_format($user->bonus),
            'record'            => $record,
            'recharge'          => $recharge,
            'withdraw'          => $withdraw,
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 重置密码
     *
     * @ApiMethod (POST)
     * @param string $oldpassword 旧密码
     * @param string $newpassword 新密码
     * @param string $surepassword 确认新密码
     */
    public function resetpwd()
    {
        $user = $this->auth->getUser();
        $oldpassword = $this->request->post("oldpassword");
        $newpassword = $this->request->post("newpassword");
        $surepassword = $this->request->post("surepassword");
        if (!$newpassword || !$surepassword || !$oldpassword) {
            $this->error(__('无效参数'));
        }

        if ($newpassword != $surepassword) {
            $this->error(__('两次输入密码不一致'));
        }
       
        //模拟一次登录
        $this->auth->direct($user->id);
        $ret = $this->auth->changepwd($newpassword, $oldpassword, false);
        if ($ret) {
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 个人信息修改
     */
    public function personinfo()
    {
        $nickname = $this->request->post("nickname");
        $email = $this->request->post("email");

        if (!$nickname && !$email) {
            $this->error(__('无效参数'));
        }

        $user = $this->auth->getUser();
        $user->nickname = $nickname;
        $user->email = $email;
        $user->save();

        $this->success(__('修改成功'));
    }
}