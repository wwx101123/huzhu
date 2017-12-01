<?php
namespace app\index\Controller;
use app\common\model\User;
use think\Config;
use think\Controller;
use think\Db;
use think\Request;
use think\Session;

class Login extends Controller {
    function _initialize() {
        parent::_initialize();
        $this->jihua();
        if (is_mobile()) {
            $this->redirect('/mobile/index/index');
        }
    }
    /**
     * 登录页加载
     * 登录验证
     * @return [type] [description]
     */
    public function index() {
        $this->is_login();
        if (Request::instance()->isPost()) {
            $request = request()->post();
            //判断不为空
            if ($request['user'] == "") {$this->error('用户名不能为空');}
            if ($request['user_pass'] == "") {$this->error('登录密码不能为空');}
            if ($request['code'] == "") {$this->error('验证码不能为空');}
            //验证器
            $validate = validate('Verify');
            if (!$validate->check($request)) {
                $this->error($validate->getError());
            }
            $info = User::where('user', $request['user'])->field('id,user,salt,status')->find();
            if ($info == '') {
                $this->error('用户账号有误，或不存在');
            }
            if ($info['status'] == 0) {
                $this->error('您的账号未激活');
            } elseif ($info['status'] == 3 || $info['status'] == 4 || $info['status'] == 5 || $info['status'] == 6) {
                $this->error('您的账号被冻结,请联系网站管理员');
            }
            if ($info == '') {
                $this->error('用户账号有误，或不存在');
            }
            $user_pass = md5(md5($request['user_pass'] . $info->salt));
            $infp = User::where(array('user' => $request['user'], 'user_pass' => $user_pass))->value('id');
            if ($infp == "") {
                $this->error('密码错误');
            }
            Session::set('uname', $request['user']);
            Session::set('uid', $info['id']);
            Session::set('log_time', time());
            $this->success('登录成功', '/index/index/index');
        }
        return $this->fetch();
    }
    /**
     * 退出登录
     * 清楚session()
     */
    public function logout() {
        Session::delete('uname');
        Session::delete('uid');
        Session::delete('log_time');
        $this->redirect('/index/login/index');
    }
    public function forget() {
        $request = request()->post();
        //手机验证码
        if ($request['note_code'] == "") {$this->error('短信验证码不能为空');}
        if ($request['note_code'] != session('note_code')) {
            $this->error('短信验证码不正确');
        }
        $info = Db::name('user')->where('phone', $request['phone'])->find();
        if ($info) {
            return array(
                'code' => 1,
                'msg' => '可以重置',
                'id' => $info['id'],
            );
        } else {
            $this->error('账户信息不存在');
        }
    }
    public function forgetcl() {
        $request = request()->post();
        $info = Db::name('user')->where('id', $request['id'])->find();
        if (!$info) {
            $this->error('非法操作');
        }
        if ($request['user_pass'] == "") {
            $this->error('新登录密码不能是空');
        }
        if ($request['pass1'] != $request['user_pass']) {
            $this->error('两次输入密码不相同');
        }
        $validate = validate('Verify');
        if (!$validate->check($request)) {
            $this->error($validate->getError());
        }
        $user_pass = md5(md5($request['user_pass'] . $info['salt']));
        if ($user_pass == $request['user_pass']) {
            $this->success("重置成功");
        }
        $rel = Db::name('user')->where('id', $request['id'])->setfield('user_pass', $user_pass);
        if ($rel) {
            $this->success('重置成功', 'index');
        } else {
            $this->error('重置失败');
        }
    }
    public function register() {
        $id = input('get.id');
        $info = DB::name('user')->where('id', $id)->field('id,user')->find();
        if (Request::instance()->isPost()) {
            $request = request()->post();
            //手机验证码
            if ($request['note_code'] != session('note_code')) {
                $this->error('短信验证码不正确');
            }
            //判断不为空
            if ($request['note_code'] == "") {$this->error('短信验证码不能为空');}
            if ($request['user'] == "") {$this->error('用户名为空');}
            if ($request['theme'] == "") {$this->error('真实姓名为空');}
            if ($request['phone'] == "") {$this->error('手机号不能为空');}
            if ($request['user_pass'] == "") {$this->error('登录密码不能为空');}
            if ($request['second_pass'] == "") {$this->error('二级密码不能为空');}
            //重复密码
            if ($request['user_pass'] != $request['pass1']) {$this->error('两次输入登录密码不相同');}
            if ($request['second_pass'] != $request['pass2']) {$this->error('两次输入二级密码不相同');}
            //验证器
            $validate = validate('Verify');
            if (!$validate->check($request)) {
                $this->error($validate->getError());
            }
            $info1 = $this->user->where('user', $request['user'])->find();
            if ($info1) {
                $this->error('该用户已经注册过了');
            }
            $info2 = $this->user->where('phone', $request['phone'])->find();
            if ($info2) {
                $this->error('该手机号已经注册过了');
            }
            $info3 = $this->user->where('theme', $request['theme'])->find();
            if ($info3) {
                $this->error('该姓名已经注册过了');
            }
            $path = $this->user->where(array('id' => $request['pid']))->value('path');
            if ($path != '') {
                $allpath = $path . ',' . $request['pid'];
            } else {
                $allpath = 0;
            }
            $salt = GetRandStr(4);
            $this->user->data([
                'user' => $request['user'],
                'pid' => $request['pid'],
                'phone' => $request['phone'],
                'theme' => $request['theme'],
                'add_time' => time(),
                'salt' => $salt,
                'user_pass' => md5(md5($request['user_pass'] . $salt)),
                'second_pass' => md5(md5($request['second_pass'] . $salt)),
                'path' => $allpath,
            ]);
            $rel = $this->user->save();
            if ($rel) {
                $this->success('注册成功', 'index');
            } else {
                $this->error('注册失败，请联系管理员');
            }
        }
        if (!$info) {
            $this->error('非法操作');
        }
        $this->assign('user', $info);
        return $this->fetch();
    }
    /**
     * 发送手机验证码
     * @return [type] [description]
     */
    public function sendSMS() {
        $mobile = input('get.mobile');
        $random = mt_rand(1000, 9999);
        $content = "您的验证码为：" . $random;
        session('note_code', $random);
        session('code_time', time());
        $data = $this->note_code($mobile, $content);
        return $data;
    }
    /**
     * 后台登入前台
     */
    public function admin_login() {

        if (input('get.id')) {
            $id = input('get.id');
        } else {
            $this->error('非法操作');
        }
        $info = User::where('id', $id)->value('user');
        Session::set('uname', $info);
        Session::set('uid', $id);
        Session::set('log_time', time());
        $this->redirect('/index/index/index');
    }
    /**
     * 是否登录
     * 是 跳转到首页
     * @return boolean [description]
     */
    protected function is_login() {
        if (session('uid')) {
            $this->redirect('/index/index/index');
        }
    }

    protected function note_code($mobile, $content) {
        header('Content-Type:text/html;charset=utf8');
        $userid = '';
        $account = Config::get('smsaccount');
        $password = Config::get('smspassword');
        $password = md5($password);
        $password = ucfirst($password);
        $content = '【团圆联盟】尊敬的会员你好,' . $content;
        $gateway = "http://114.113.154.5/sms.aspx?action=send&userid={$userid}&account={$account}&password={$password}&mobile={$mobile}&content={$content}&sendTime=";
        $result = file_get_contents($gateway);
        $xml = simplexml_load_string($result);
        if ($xml->returnstatus == 'Faild') {
            return array(
                'status' => 'failed',
                'msg' => '系统错误,发送失败',
            );
        }
        return array(
            'status' => 'success',
            'msg' => '短信已发送',
        );
    }
    /**
     * 计划任务
     * @return [type] [description]
     */
    protected function jihua() {
        $where1 = array(
            'zt' => 3,
            'status' => 0,
            'end_time' => array('lt', time()),
            'delete' => 0,
        );
        $list1 = Db::name('ppdd')->where($where1)->select();
        if ($list1) {
            foreach ($list1 as $k => $v) {
                $money = $v['jb'] / 2 + $v['jb'] / 10;
                $uid = DB::name('user')->where('user', $v['p_user'])->value('id');
                DB::name('user')->where('user', $v['p_user'])->setInc('static_wallet', $money);
                //记录
                $data = array(
                    'uid' => $uid,
                    'jb' => $money,
                    'add_time' => $v['end_time'],
                    'type' => 1,
                );
                DB::name('userget')->insert($data);
                DB::name("ppdd")->where('id', $v['id'])->setfield('status', 1);
            }
        }
        $where2 = array(
            'zt' => 3,
            'status' => 1,
            'last_time' => array('lt', time()),
            'delete' => 0,
        );
        $list2 = Db::name('ppdd')->where($where2)->select();
        if ($list2) {
            foreach ($list2 as $k => $v) {
                $money = $v['jb'] / 2 + $v['jb'] / 10;
                $uid = DB::name('user')->where('user', $v['p_user'])->value('id');
                DB::name('user')->where('user', $v['p_user'])->setInc('static_wallet', $money);
                //记录
                $data = array(
                    'uid' => $uid,
                    'jb' => $money,
                    'add_time' => $v['last_time'],
                    'type' => 2,
                );
                DB::name('userget')->insert($data);
                DB::name("ppdd")->where('id', $v['id'])->setfield('zt', 4);
            }
        }
        $where3 = array(
            'zt' => 1,
            'delete' => 0,
            'cold' => 0,
            'add_time' => array('lt', time() - 21600),
        );
        $list3 = Db::name('ppdd')->where($where3)->select();
        if ($list3) {
            foreach ($list3 as $k => $v) {
                $data = array(
                    'cold' => 1,
                    'cold_time' => time(),
                );
                Db::name('ppdd')->where('id', $v['id'])->update($data);
                $datb = array(
                    'status' => 3,
                    'cold_time' => time(),
                );
                Db::name('user')->where('user', $v['p_user'])->update($datb);
            }
        }
        $where4 = array(
            'zt' => 2,
            'delete' => 0,
            'pay_time' => array('lt', time() - 14400),
            'cold' => 0,
            'ts_zt' => 0,
        );
        $list4 = Db::name('ppdd')->where($where4)->select();
        if ($list4) {
            foreach ($list4 as $k => $v) {
                $data = array(
                    'cold' => 2,
                    'cold_time' => time(),
                );
                Db::name('ppdd')->where('id', $v['id'])->update($data);
                $datb = array(
                    'status' => 4,
                    'cold_time' => time(),
                );
                Db::name('user')->where('user', $v['g_user'])->update($datb);
            }
        }
    }
}
