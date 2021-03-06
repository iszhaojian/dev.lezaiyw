<?php
namespace apps\home\ctrl;
use core\lib\conf;
use apps\home\model\cashValue;
use apps\home\model\users;
class alipayCtrl extends \core\icunji{
  public $m;
  public $wap;
  public $cvdb;
  public $udb;
  // 构造方法
  public function _initialize(){
    $this->m = isset($_GET['m']) ? $_GET['m'] : 0;
    $this->wap = isset($_GET['wap']) ? $_GET['wap'] : 0;
    $this->cvdb = new cashValue();
    $this->udb = new users();
  }

  /**
   * 支付宝支付base
   */
  public function index(){
    // Get
    if (IS_GET === true) {

      //商户订单号，商户网站订单系统中唯一订单号，必填
      $out_trade_no = createIn($_SESSION['homeUserinfo']['id']);

      //订单名称，必填
      $subject = '听娱神游约玩账户充值';

      //付款金额，必填
      $total_amount = $this->m;

      //商品描述，可空
      $body = '用户在线约玩的货币';

      //构造参数
      if ($this->wap == 1)
      {
        // 支付宝手机支付接口
        require_once ICUNJI.'/vendor/alipay/wappay/service/AlipayTradeService.php';
        require_once ICUNJI.'/vendor/alipay/wappay/buildermodel/AlipayTradeWapPayContentBuilder.php';
        // 手机支付接口
        $payRequestBuilder = new \AlipayTradeWapPayContentBuilder();
      }
      else
      {
        // 支付宝电脑支付接口
        require_once ICUNJI.'/vendor/alipay/pagepay/service/AlipayTradeService.php';
        require_once ICUNJI.'/vendor/alipay/pagepay/buildermodel/AlipayTradePagePayContentBuilder.php';
        // 电脑支付接口
        $payRequestBuilder = new \AlipayTradePagePayContentBuilder();
      }
      $payRequestBuilder->setBody($body);
      $payRequestBuilder->setSubject($subject);
      $payRequestBuilder->setTotalAmount($total_amount);
      $payRequestBuilder->setOutTradeNo($out_trade_no);


      $aop = new \AlipayTradeService(conf::all('alipay'));

      if ($this->wap == 1)
      {
        /**
         * pagePay 手机支付请求
         * @param $builder 业务参数，使用buildmodel中的对象生成。
         * @param $return_url 同步跳转地址，公网可以访问
         * @param $notify_url 异步通知地址，公网可以访问
         * @return $response 支付宝返回的信息
        */
        $response = $aop->wapPay($payRequestBuilder,conf::get('return_url','alipay'),conf::get('notify_url','alipay'));
        return ;
      }
      else
      {
        /**
         * pagePay 电脑支付请求
         * @param $builder 业务参数，使用buildmodel中的对象生成。
         * @param $return_url 同步跳转地址，公网可以访问
         * @param $notify_url 异步通知地址，公网可以访问
         * @return $response 支付宝返回的信息
        */
        $response = $aop->pagePay($payRequestBuilder,conf::get('return_url','alipay'),conf::get('notify_url','alipay'));
        //输出表单
        see($response);
        die;
      }

    }

  }

  /**
   * 异步回调
   */
  public function asynchronization(){
    $arr=$_POST;
    // 手机端
    if (isHttpsMobile())
    {
      // 支付宝手机支付接口
      require_once ICUNJI.'/vendor/alipay/wappay/service/AlipayTradeService.php';
      require_once ICUNJI.'/vendor/alipay/wappay/buildermodel/AlipayTradeWapPayContentBuilder.php';
    }
    else
    {
      // 支付宝电脑支付接口
      require_once ICUNJI.'/vendor/alipay/pagepay/service/AlipayTradeService.php';
      require_once ICUNJI.'/vendor/alipay/pagepay/buildermodel/AlipayTradePagePayContentBuilder.php';
    }
    $alipaySevice = new \AlipayTradeService(conf::all('alipay'));
    $alipaySevice->writeLog(var_export($_POST,true));
    $result = $alipaySevice->check($arr);
    if($result) {
      //商户订单号
      $out_trade_no = $_POST['out_trade_no'];
      //支付宝交易号
      $trade_no = $_POST['trade_no'];
      //交易状态
      $trade_status = $_POST['trade_status'];
      // 付款总金额
      $total_amount = $_POST['total_amount'];
      if ($trade_status == 'TRADE_SUCCESS') {
        // 订单存在则不执行
        if (!$this->cvdb->getCrow($out_trade_no)) {
          // 获取用户uid
          $uid_str = trim($out_trade_no);
          $uid_len = strlen($uid_str)-1;
          $uid = $uid_str{$uid_len};
          // 组装数据写入充值表
          $data['uid'] = $uid;
          $data['inumber'] = $out_trade_no;
          $data['money'] = $total_amount;
          $data['ctime'] = time();
          $data['type'] = 0;
          $data['status'] = 2;
          if ($this->cvdb->add($data)) {
            // 累加约玩币，约玩值
            $balance = bcmul($total_amount, conf::get('TEST_BALANCE','alipay'), 0);
            $contribution = bcmul($total_amount, conf::get('TEST_CONTRIBUTION','alipay'), 0);
            $uData = $this->udb->getRow($uid);
            $balance = bcadd($uData['balance'], $balance, 0);
            $contribution = bcadd($uData['contribution'], $contribution, 0);
            $upUdata['balance'] = $balance;
            $upUdata['contribution'] = $contribution;
            $this->udb->save($uid,$upUdata);
          }
        }
      }
      echo "success";
    }else {
      //验证失败
      echo "fail";
    }
  }

  /**
   * 同步跳转
   */
  public function synchronization(){
    // if
    if (isHttpsMobile())
    {
      header('Location:/wap/pay/pay');
    }
    else
    {
      header('Location:/recharge/index');
    }
  }

  /**
   * 微信支付
   */
  public function wxPay(){
    // Ajax
    if (IS_AJAX === true) {

      //商户订单号，商户网站订单系统中唯一订单号，必填
      $out_trade_no = createIn($_SESSION['homeUserinfo']['id']);

      //订单名称，必填
      $subject = '听娱神游约玩账户充值';

      //付款金额，必填
      $total_amount = bcmul($this->m, 100, 0);

      // 统一下单
      $jsApiParameters = wxJsapiPay('',$subject,$out_trade_no,$total_amount,$_SESSION['homeUserinfo']['id']);
      echo J($jsApiParameters);
      die;

    }

  }

  // 微信支付回调
  public function notify(){

    //$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
    $xml = file_get_contents("php://input");

    // 这句file_put_contents是用来查看服务器返回的XML数据 测试完可以删除了
    file_put_contents(ICUNJI."/vendor/wxpay/wxlogs/check.log",$xml.PHP_EOL,FILE_APPEND);

    //将服务器返回的XML数据转化为数组
    //$data = json_decode(json_encode(simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA)),true);
    $data = XA($xml);
    // 保存微信服务器返回的签名sign
    $data_sign = $data['sign'];
    // sign不参与签名算法
    unset($data['sign']);
    $sign = makeSign($data);

    // 判断签名是否正确  判断支付状态
    if ( ($sign===$data_sign) && ($data['return_code']=='SUCCESS') && ($data['result_code']=='SUCCESS') ) {
        $result = $data;
        // 这句file_put_contents是用来查看服务器返回的XML数据 测试完可以删除了
        file_put_contents(ICUNJI."/vendor/wxpay/wxlogs/ok.log",$xml.PHP_EOL,FILE_APPEND);

        //获取服务器返回的数据
        $out_trade_no = $data['out_trade_no'];  //订单单号
        $uid = $data['attach'];        //附加参数,选择传递用户uid
        $openid = $data['openid'];          //付款人openID
        $total_amount = $data['total_fee'];    //付款金额

        // 微信支付成功后修改订单状态，订单存在则不执行
        if (!$this->cvdb->getCrow($out_trade_no)) {
          // 组装数据写入充值表
          $data['uid'] = $uid;
          $data['inumber'] = $out_trade_no;
          $data['money'] = $total_amount;
          $data['ctime'] = time();
          $data['type'] = 0;
          $data['status'] = 2;
          if ($this->cvdb->add($data)) {
            // 累加约玩币，约玩值
            $balance = bcmul($total_amount, conf::get('TEST_BALANCE','alipay'), 0);
            $contribution = bcmul($total_amount, conf::get('TEST_CONTRIBUTION','alipay'), 0);
            $uData = $this->udb->getRow($uid);
            $balance = bcadd($uData['balance'], $balance, 0);
            $contribution = bcadd($uData['contribution'], $contribution, 0);
            $upUdata['balance'] = $balance;
            $upUdata['contribution'] = $contribution;
            $this->udb->save($uid,$upUdata);
          }
        }
        $result = true;
    }else{
        $result = false;
    }
    // 返回状态给微信服务器
    if ($result) {
        $str='<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
    }else{
        $str='<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
    }
    echo $str;
    return $result;

  }

}