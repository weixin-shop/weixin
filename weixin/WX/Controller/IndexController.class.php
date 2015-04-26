<?php
namespace WX\Controller;
use Think\Controller;
use Think\Wechat;
use Think\WechatAuth;
class IndexController extends Controller {
    private $weixin     =   null;

    public function _initialize(){

        $this->weixin   =   new WechatAuth(C('WX_APPID'),C('WX_APPSECRET'),D('Tool')->get_token());
    }

    public function index(){
    	$wechat = new Wechat();
    	$data = $wechat->request();
    	if($data && is_array($data)){
            $Tool=D('Tool');
            // 客户传来的数据进行保存
            $Tool->receive_data($data);

            switch ($data['MsgType']) {
                case 'text':
                    $response=$Tool->reply_text($data);
                break;

                case 'image':
                    $response=$Tool->reply_image($data);
                break;

                case 'voice':
                    $response=$Tool->reply_voice($data);
                break;

                case 'video':
                    $response=$Tool->reply_video($data);
                break;
                
                case 'location':
                    $response=$Tool->reply_location($data);
                break;
                case 'link':
                    $response=$Tool->reply_link($data);
                break;
                case 'event':
                    $response=$Tool->reply_event($data);
                break;
            }
    	    $content = $response['content'];              
            $type    = $response['type'];
            $wechat->response($content, $type);
        }
       
    }


}

