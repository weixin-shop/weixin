<?php 
	namespace WX\Model;

	use Think\Model;

	class WeiApiModel extends Model{

		// 默认不走表
		protected $autoCheckFields  =   false;

		//微信全局票据
		public  $access_token       =   '';

		//微信api根路径
	    private $apiURL    = 'https://api.weixin.qq.com/cgi-bin';

	    //微信媒体文件根路径
	    private $mediaURL  = 'http://file.api.weixin.qq.com/cgi-bin';

	    // 微信二维码根路径

	    private $qrcodeURL = 'https://mp.weixin.qq.com/cgi-bin';

	    private $requestCodeURL = 'https://open.weixin.qq.com/connect/oauth2/authorize';

	    private $oauthApiURL = 'https://api.weixin.qq.com/sns';

	    private $openid='';

	    public $weixin_ip_list = array();

	    public $return = array();  

		//初始化微信api模型类模型类
		protected function _initialize() {

			$this->get_access_token();
		}


		/**
	    *获取数据库中的access_token
    	* @return $this
	    */
		public function get_access_token(){

			// 查找微信配置文件中数据
			$res   =  M('config')->order('id desc')->find();

			// 如果没有配置项则获取配置项
			if (is_null($res) || $res['over_time']<time()) {

					// 设置 access_token
					$this->set_access_token();

			}else{

				// 判断表中的appid 和 appsecret 是否和配置文件中的是否一致
				if($res['appid']==C('WX_APPID') && $res['appsecret'] == C('WX_APPSECRET')){

					// 返回 access_token
					$this->access_token    =     $res['access_token'];

				}else{

					$this->set_access_token();

				}
			}
			return $this;
		}

		/**
	    *保存access_token把access_token缓存起来
    	* method   GET
    	* @return $this
	    */
		private function set_access_token(){
				// 获取appid
				$data['appid']    	    =   C('WX_APPID');
				// 获取appsecret
				$data['appsecret']      =   C('WX_APPSECRET');
				// 请求微信服务器获取 access_token 的url
				$url=$this->apiURL.'/token?grant_type=client_credential&appid='.$data['appid'].'&secret='.$data['appsecret'];
				// 通过curl 获取 返回的 json数据
				$return                 =   $this->https_request($url);
				//将json转为对象
				$result                 =   json_decode($return, true);
				// 获取 access_token
				$data['access_token']   =   $result["access_token"];
				// 异常检查
				if(is_null($data['access_token'])){
					throw new \Exception('获取access失败 错误码：'.$data['errcode']);
					exit;
				}
				// 获取更新时间
				$data['update_time']    =   time();
				// 获取过期时间
				$data['over_time']     	=   $data['update_time'] + $result["expires_in"];
				// 添加到配置文件中去
				$add=M('config')->add($data);
				// 如果添加成功 返回access_token;
				if ($add) {
					$this->access_token    =     $data['access_token'];
				}else{
					throw new \Exception('保存access失败！请手动保存'.$data['access_token']);
					exit;
				}
				return $this;
		}


	    /**
	    *获取微信服务器IP的接口路径 
    	* @param  method   GET
    	* @return $this
	    */
		public function get_weixin_ip_list(){
			// 接口路径
			$url    =    $this->apiURL .'/getcallbackip?access_token='.$this->access_token;
			// 回调
			$return =    $this->https_request($url);
			// 获取ip列表
			$this->weixin_ip_list=json_decode($return)->ip_list;

			return $this;

		}

		/**
	     * 创建自定义菜单
	     * @param  array $button 符合规则的菜单数组，规则参见微信手册
	     */
	    //创建菜单
	    public function create_menu($data)
	    {
	        $url = $this->apiURL."/menu/create?access_token=".$this->access_token;

	        $res = $this->https_request($url, $data);

	        $this->return['create_menu'] = $res;      //json_decode($res, true);   //{"errcode":0,"errmsg":"ok"}

	        return $this;
	    }

	     /**
	     * 获取所有的自定义菜单
	     */
	    public function get_menu(){

	    	$url = $this->apiURL."/menu/get?access_token=".$this->access_token;

	        $res = $this->https_request($url);

	        $this->return['get_menu'] = $res ;     

	        return $this;

	    }

	     /**
	     * 删除自定义菜单
	     */
	    public function delete_menu(){

	    	$url = $this->apiURL."/menu/delete?access_token=".$this->access_token;

	        $res = $this->https_request($url);

	        $this->return['delete_menu'] = $res;      

	        return $this;
	    }
		/**
	    * 上传多媒体文件 
	    * 暂时不能用
    	* @param  $type   类型
    	* @param  $file   文件名
    	* @return $this
	    */
    	public function upload_media($type, $file){
    		// 获取当前运行的根路径
    		$dirname=dirname($_SERVER["SCRIPT_FILENAME"]);
    		// 获取到上传文件的url
        	$data = array("media"  => '@'.$dirname.'/media'.$type.'/'.$file);
        	// 接口路径
        	$url = $this->mediaURL."/media/upload?access_token=".$this->access_token."&type=".$type;
        	// 通过接口获取结果
        	$res = $this->https_request($url, $data);   //{"type":"TYPE","media_id":"MEDIA_ID","created_at":123456789}
        	// 把返回来的json转为对象
        	$res = json_decode($res, true);
   			//存放在$this->return中
    		$this->return['media']=$res;
        	// 返回本对象
        	return $this;
    	}


	    /**
	     * 获取媒体资源下载地址
	     * 注意：视频资源不允许下载
	     * @param  string $media_id                        媒体资源id
	     * @return $this->return['upload_media']           媒体资源下载地址
	     */
	    public function download_media($media_id){
	    	// 通过媒体id 获取到下载路径	
			$url = $this->mediaURL.'/media/get?access_token='.$this->access_token.'&media_id='.$media_id;
	        // 放入到$this->return 中
	       	$this->return['download_media']=$url;
	       	// 返回本对象
	       	return $this;
	    }


	    // 为openid赋值
	    public function openid($openid){
	    	$this->openid=$openid;
	    	return $this;
	    }

	    // 发送文本消息
		public function send_text($content){

			$array['content']=$content;

			$this->send_massage($array,'text');

			return  $this;

		}

		// 发送图片消息
		public function send_image($media_id){

			$array['media_id']=$media_id;

			$this->send_massage($array,'image');

			return  $this;

		}
		// 发送录音消息
		public function send_voice($media_id){

			$array['media_id']=$media_id;

			$this->send_massage($array,'voice');

			return  $this;

		}

		// 发送视频消息
		public function send_video($video){

			$this->send_massage($video,'video');

			return  $this;
		}

		// 发送音乐消息
		public function send_music($music){

			//music
			// $array["title"] ;
			// $array["musicurl"];
			// $array["hqmusicurl"];
			// $array["description"];
			// $array["thumb_media_id"];

			$this->send_massage($music,'music');

			return  $this;
		}

		// 发送图文消息
		public function send_news($news){

			// $array["articles"][0]['title']
			// $array["articles"][0]['description']
			// $array["articles"][0]['url']
			// $array["articles"][0]['picurl']

			$array["articles"]=$news;

			$this->send_massage($array,'news');

			return  $this;
		}

	    // 发送消息方法
	    public function send_massage($array,$type='text'){

	    	$data=json_encode($array,JSON_UNESCAPED_UNICODE);

	    	$url = $this->apiURL.'/message/custom/send?access_token='.$this->access_token;

	    	$post_data ='{ "touser": "'.$this->openid.'", "msgtype": "'.$type.'", "'.$type.'": '.$data.'}';

	    	$this->return['send_massage'][$type] = $this->https_request($url,$post_data); 

	    }


	    // 获取 tricket的 方法 
	    public function get_ticket($param,$seconds,$getcode=true){

	    	// 只限永久
	    	if (is_string($param)) {
	    		if (strlen($param)<64) {
	    			$data['action_info']["scene"]["scene_str"]=$param;
	    			$seconds = null;
	    		}else{
					throw new \Exception('字符必须小于64个字符长度');
					exit;
	    		}

	    	}else if (is_numeric($param)){
	    		// 永久/临时
	    		$data['action_info']["scene"]["scene_id"]=$param;

	    	}else if (is_array($param)) {
	    		if (isset($param['id']) && isset($param['str'])) {
	    			$data['action_info']["scene"]["scene_str"]=$param['str'];
	    			$data['action_info']["scene"]["scene_id"]=$param['id'];
	    			$seconds = null; 
	    		}else{
					throw new \Exception('请传入正确的数据');
					exit;
	    		}

	    	}else{
					throw new \Exception('参数类型不正确');
					exit;
	    	}


	    	if (isset($seconds) &&  is_numeric($seconds)  && $seconds<=1800) {

	    		$data['expire_seconds']= $seconds;

	    		$data['action_name']  =  "QR_SCENE";
	    		
	    	}else{
	    		
	    		$data['action_name']  =  "QR_LIMIT_STR_SCENE";

	    		if (isset($data['action_info']["scene"]["scene_id"]) && $data['action_info']["scene"]["scene_id"]<1 && $data['action_info']["scene"]["scene_id"]>100000) {
	    			throw new \Exception('scene_id目前参数只支持1--100000');
					exit;
	    		}
	    		
	    	}

	    	$data=json_encode($data);

	    	$url  = $this->apiURL.'/qrcode/create?access_token='.$this->access_token;


	    	$data=$this->https_request($url, $data);

	    	if ($getcode){
	    		return $this->get_code($data);
	    	}else{

	    		return $data;
	    	}
	    }

	    public function get_code($tricket_data){


	    	$tricket_data= json_decode($tricket_data);

	    	$tricket = $tricket_data->ticket;

	    	if (is_null($tricket)) {
	    		throw new \Exception('无法获取二维码, errcode:'.$tricket_data->errcode);
				exit;
	    	}

			$url = $this->qrcodeURL.'/showqrcode?ticket='.$tricket;

			return $url;
	    }

	    public function shorturl($long_url){

	    	$url  =  $this->apiURL.'/shorturl?access_token='.$this->access_token;
	    	$data['action']='long2short';
	    	$data['long_url']=$long_url;

	    	$data=json_encode($data);

	    	$data=$this->https_request($url,$data);

	    	$data=json_decode($data);

	    	$url=$data->short_url;

	    	if (is_null($url)) {
	    		$link='<a href="'.$long_url.'">访问该链接</a>';
	    		echo $link;
	    		throw new \Exception('长连接转化错误, errcode:'.$data->errcode);
				exit;
	    	}
	    	
	    	return $url;

	    }
 
		/**
	     * https请求（支持GET和POST）
	     * @param  string $url         请求接口的URL
	     * @param         $data        POST请求的数据
	     * @return string $output      接口返回的json数据 
	     */
		protected function https_request($url, $data = null){
		        $curl = curl_init();
		        curl_setopt($curl, CURLOPT_URL, $url);
		        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		        if (!empty($data)){
		            curl_setopt($curl, CURLOPT_POST, 1);
		            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		        }
		        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		        $output = curl_exec($curl);
		        curl_close($curl);
		        return $output;
		}

	}