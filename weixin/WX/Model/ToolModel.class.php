<?php 
namespace WX\Model;

	use Think\Model;
	use Think\WechatAuth;
	//常用工具模块
	class ToolModel extends Model{
		// 默认不走表
		protected $autoCheckFields  =   false;

		// 接收的数据
	    public function receive_data($data){
	        M('receive_'.$data['MsgType'])->add($data);
	    }

	    // IP地址转为城市
	    public function IpToCity($ip){
				$json=file_get_contents('http://ip.taobao.com/service/getIpInfo.php?ip='.$ip);
				$arr=json_decode($json);
	            $city= $arr->data->city;    //城市
				$city = str_replace('市','',$city);
				return $city;
	    }

		public function  reply_text($data){
	        $response['content']=$data['Content'];
	        $response['type']='text';
	        return $response;
	    }

	    public function  reply_image($data){
	        $response['content']='这是一张图片';
	        $response['type']='text';
	        return $response;
	    }

	    public function  reply_voice($data){
	        $response['content']=$data['MediaId'];
	        $response['type']='voice';
	        return $response;
	    }

	    public function  reply_video($data){
	        $response['content']='12312313';
	        $response['type']='text';
	        return $response;
	    }    

	    public function  reply_location($data){
	        $response['content']='12312313';
	        $response['type']='text';
	        return $response;
	    }
	    
	    public function  reply_link($data){
	        $response['content']='12312313';
	        $response['type']='text';
	        return $response;
	    }

	    public function  reply_event($data){
	        $response['content']='12312313';
	        $response['type']='text';
	        return $response;
	    }


	    		/**
	    * 获取access_token, 并对access_token进行缓存
	    * @return string access_token字符串
	    */
		public function get_token(){
			$weixin=new WechatAuth(C('WX_APPID'),C('WX_APPSECRET'));
	        $config   =  M('config')->order('id desc')->find();
	        if (is_null($config) || $config['over_time']<time()) {
	        	$data['appid']=C('WX_APPID');
	        	$data['appsecret']=C('WX_APPSECRET');
				$data['update_time']    =   time();
				$data['over_time']     	=   $data['update_time'] + 7200;
	            $data['access_token']   =   $weixin->getAccessToken()['access_token'];
	        	$add=M('config')->add($data);
	        	if ($add) {
	        		$access_token      =    $data['access_token'];
	        	}else{
	        		throw new \Exception('保存access失败！请手动保存'.$data['access_token']);
	        	}
	        }else{
	            $access_token    =    $config['access_token'] ;
	        }
	        return $access_token;
		}



		/**
	    * 将媒体文件保存到本地
	    * @param  array   media['mediaId']  media['type']  
	    * @param  string  geturl
	    * @param  string  $rootPath defalute './'
	    * @return Boolean  true/false
	    */
		public function save_media($media,$geturl,$rootPath='./'){
			switch ($media['type']){
				case 'image':
					$dir='media/image/';
					$Suffix='.jpg';
					break;
				case 'voice':
					$dir='media/voice/';
					$Suffix='.amr';
					break;
				case 'video':
					$dir='media/video/';
					$Suffix='.mp4';
					break;
				case 'thumb':
					$dir='media/thumb/';
					$Suffix='.jpg';
					break;
			}
			$url=$dir.$media['mediaId'].$Suffix;
			if(file_exists($url)){
				return false;
			}else{
 				file_put_contents($url,file_get_contents($geturl));
 				return true;
			}

		}

		/**
		 * 自定义菜单转为数据格式
		 * @param  array $menu 查询出来的菜单
		 * @return array $data 数据库的数组格式
		 */
		public function menu_to_data($menu){

			$menu=$menu['menu']['button'];
	        $key=0;
	        $Bkey=1;
	        foreach ($menu as $Amenu) {
	            $pid=$Bkey;
	            $data[$key]['id']=$Bkey;
	            $data[$key]['pid']=0;
	            $data[$key]['name']=$Amenu['name'];
	            if (count($Amenu['sub_button'])>0) {
	                foreach ($Amenu['sub_button'] as $Bmenu) {
	                    $key++;
	                    $Bkey++;
	                    $data[$key]['id']     =   $Bkey;
	                    $data[$key]['pid']     =   $pid;
	                    $data[$key]['name']   =   $Bmenu['name'];
	                    $data[$key]['type']   =   $Bmenu['type'];
	                    $data[$key]['key']    =   $Bmenu['key'];
	                    $data[$key]['url']    =   $Bmenu['url'];
	                }
	            }else{
	                $data[$key]['type']=$Amenu['type'];
	                $data[$key]['key']=$Amenu['key'];
	                $data[$key]['url']=$Amenu['url'];
	            }
	            $Bkey++;
	            $key++;
	        }

	        return $data;
		}

		/**
		 * 删除数据库中的菜单
		 * @return array $data 数据库的数组格式
		 */
		public function menu_data_delete(){
			$res=M('menu')->where(1)->delete();
			return $res;
		}

		/**
		 * 跟新自定义菜单状态
		 * @param  array     $menu 获取到的菜单数据
		 * @return Boolean   更新成功失败
		 */
		public function menu_data_update(){

			$menu=$this->weixin->menuGet();

			$data 	= 	$this->menu_to_data($menu);

			if(M('menu')->count()>0){
				$this->menu_data_delete();
			}

	        foreach ($data as $save) {
	            $status=M('menu')->add($save);
	        }

	        if ($status) {
	        	return true;
	        }else{
	        	return false;

	        }
	     
		}

		/**
		 * 数据格式转为自定义菜单
		 * @return array $menu 菜单的数据格式
		 */
		public function data_to_menu(){

			 $data=M('menu')->where(array('pid'=>0))->select();

			 $button=0;
			 foreach ($data as $Amenu) {
					$menu[$button]['name']=$Amenu['name'];
					if(M('menu')->where(array('pid'=>$Amenu['id']))->count()==0){
						$menu[$button]['type']=$res['type'];
						$menu[$button]['key']=$res['key'];
						$menu[$button]['url']=$res['url'];
					}else{
						$Bdata=M('menu')->where(array('pid'=>$Amenu['id']))->select();
						$Bnum=0;
						foreach ($Bdata as $Bmenu) {
							$menu[$button]['sub_button'][$Bnum]['name'] 		=	$Bmenu['name'];
							$menu[$button]['sub_button'][$Bnum]['type'] 		=	$Bmenu['type'];
							$menu[$button]['sub_button'][$Bnum]['key'] 			=	$Bmenu['key'];
							$menu[$button]['sub_button'][$Bnum]['url'] 			=	$Bmenu['url'];
							$menu[$button]['sub_button'][$Bnum]['sub_button'] 	=	array();
							$Bnum++;
						}
					}
					$button++;
			 }

			 return $menu;

		}




	}



 ?>