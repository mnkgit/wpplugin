<?php
/*
Plugin Name:  WS
Plugin URI: 
Description: This plugin enable web services call. example: http://domain.com/api/your_method_name
Version: 1.0.0
Author: Mukesh Kanzariya
Author URI: 
*/
class iWS {

    public $db;
    public $prefix;
    public $ds;
    public $siteUrl;
    public $pluginPath;
    public $pluginUrl;
    public $Request;

    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->ds = DIRECTORY_SEPARATOR;
        $this->siteUrl = get_option("siteurl");
        $this->pluginPath = plugin_dir_path(__FILE__);
        $this->pluginUrl = plugin_dir_url(__FILE__);
        $this->prefix = $wpdb->prefix;

        add_action('init', array($this, 'init'));
    }

    public function init()
    {
        if (!session_id()) {
            session_start();
        }

        add_filter('rewrite_rules_array', array($this, 'api_rewrites'));
        add_action('template_redirect', array($this, 'template_redirect'));
        add_filter('query_vars', array($this, 'query_vars'));
    }

    public function activation()
    {
        global $wp_rewrite;
        add_filter('rewrite_rules_array', array($this, 'api_rewrites'));
        $wp_rewrite->flush_rules();
    }

    public function deactivation()
    {
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }

    public function api_rewrites($wp_rules)
    {
        $json_api_rules = array(
            "api\$" => 'index.php?api_request=index',
            "api/(.+)\$" => 'index.php?api_request=$matches[1]'
        );
        return array_merge($json_api_rules, $wp_rules);
    }

    public function query_vars($wp_vars)
    {
        $wp_vars[] = 'api_request';
        return $wp_vars;
    }

    public function template_redirect()
    {
        $api_method = get_query_var('api_request');
        if($api_method)
        {
            include_once($this->pluginPath.'request.php');

            //include_once($this->pluginPath.'push-notification.php');

            $this->Request = new APIRequest();

           


            $uri_segments = explode('/', $api_method);
            $method_name = strtolower($uri_segments[0]);
            array_shift($uri_segments);

            try {

                if(!is_callable(array($this->Request, $method_name)))
                {
                    throw new Exception('Method not exist', 404);
                }

                $this->Request->publicMethods || $this->Request->publicMethods = array('*');

                if(!in_array('*', $this->Request->publicMethods) && !in_array($method_name, $this->Request->publicMethods))
                {
                   $this->verify_access_token();
                } else {
                    global $wpdb, $current_user;
                    if(isset($_SERVER['HTTP_TOKEN']) && !empty($_SERVER['HTTP_TOKEN'])){

                        $token = $wpdb->get_row("SELECT user_id,token FROM wp_users_token WHERE token='{$_SERVER['HTTP_TOKEN']}'",ARRAY_A);

                        if(!empty($token)){
                            $current_user = new WP_User($token['user_id']);
                        }
                    }
                }

                $_post_data = json_decode(file_get_contents("php://input"), true);
                if (!empty($_post_data)) {
                    foreach ($_post_data as $key => $val) {
                        $_POST[$key] = $val;
                    }
                }
                $data = call_user_func_array(array($this->Request, $method_name), $uri_segments);
                $this->_response($data);

            } catch(Exception $e)
            {

              //  $this->_response(array("error" => $e->getMessage(), "code" => $e->getCode()),$e->getCode());
                  $this->_response(array("message" => $e->getMessage()),$e->getCode());
            }
        }
        return null;
    }

    public function verify_access_token(){
        global $current_user,$wpdb;
        //$headers = getallheaders();print_r($_SERVER);
        $header_token = $_SERVER['HTTP_TOKEN'];
        if(!empty($header_token)){
            $token = $wpdb->get_row("SELECT user_id,token FROM wp_users_token WHERE token='".$header_token."'",ARRAY_A);
            if(!empty($token)){
                    $id = $token['user_id'];
                    $current_user = new WP_User($id);
                    return true;
            }else{
                throw new Exception('Unauthorized', 401);
            }
        }else{
            throw new Exception('Unauthorized', 401);
        }
    }

    public function _response($data, $status = 200) {
        
        global $wpdb;
        ob_clean();
        header("HTTP/1.1 200 OK");
        header("Content-Type: application/json");
      
        // // check if already token exist , remove it
        // if($data['id'] != ''){
        //     $token = $wpdb->get_row("SELECT user_id,token FROM wp_users_token WHERE user_id = '".$data['id']."'  ",ARRAY_A);

        //     if(!empty($token)){
        //         $res =  $wpdb->delete($wpdb->prefix.'users_token', array('user_id'=>$data['id']));
        //     }
        // }
        // // check if already token exist , remove it

        ## to pass token in header ##
        if($data['id'] != ''){
            
            $app_token = wp_generate_password(16, false, false).time();

            $wpdb->insert($wpdb->prefix.'users_token', array(
                "user_id" => $data['id'],
                "token" => $app_token
            ));
            
            header("Token: ".$app_token);
        }
        ## to pass token in header ##

        if(is_array($data) && isset($data['error']))
        {
          //  #$data['code'] = $data['code'] == '1' ? '0' : $data['code'];
             if($status != 200) {
                $data['status_code'] = $status;
            }
            echo json_encode($data);
        
        } else if(is_string($data)) {
        
            if($status != 200) {
                $data['status_code'] = $status;
            }
            echo json_encode($data);

        } else {
 
            array_walk_recursive($data, function(&$v, $key) {
                if ($v == null && $v !== 0){
                    $v = '';
                }
            });

           if($status != 200) {
                $data['status_code'] = $status;
            }

            echo json_encode( $data);
        }

      //header("Content-Type: application/json");
        http_response_code($status);
        die();
    }

}




 if (!function_exists('http_response_code')) {
        function http_response_code($code = NULL) {

            if ($code !== NULL) {

                switch ($code) {
                    case 100: $text = 'Continue'; break;
                    case 101: $text = 'Switching Protocols'; break;
                    case 200: $text = 'OK'; break;
                    case 201: $text = 'Created'; break;
                    case 202: $text = 'Accepted'; break;
                    case 203: $text = 'Non-Authoritative Information'; break;
                    case 204: $text = 'No Content'; break;
                    case 205: $text = 'Reset Content'; break;
                    case 206: $text = 'Partial Content'; break;
                    case 300: $text = 'Multiple Choices'; break;
                    case 301: $text = 'Moved Permanently'; break;
                    case 302: $text = 'Moved Temporarily'; break;
                    case 303: $text = 'See Other'; break;
                    case 304: $text = 'Not Modified'; break;
                    case 305: $text = 'Use Proxy'; break;
                    case 400: $text = 'Bad Request'; break;
                    case 401: $text = 'Unauthorized'; break;
                    case 402: $text = 'Payment Required'; break;
                    case 403: $text = 'Forbidden'; break;
                    case 404: $text = 'Not Found'; break;
                    case 405: $text = 'Method Not Allowed'; break;
                    case 406: $text = 'Not Acceptable'; break;
                    case 407: $text = 'Proxy Authentication Required'; break;
                    case 408: $text = 'Request Time-out'; break;
                    case 409: $text = 'Conflict'; break;
                    case 410: $text = 'Gone'; break;
                    case 411: $text = 'Length Required'; break;
                    case 412: $text = 'Precondition Failed'; break;
                    case 413: $text = 'Request Entity Too Large'; break;
                    case 414: $text = 'Request-URI Too Large'; break;
                    case 415: $text = 'Unsupported Media Type'; break;
                    case 500: $text = 'Internal Server Error'; break;
                    case 501: $text = 'Not Implemented'; break;
                    case 502: $text = 'Bad Gateway'; break;
                    case 503: $text = 'Service Unavailable'; break;
                    case 504: $text = 'Gateway Time-out'; break;
                    case 505: $text = 'HTTP Version not supported'; break;
                    default:
                        exit('Unknown http status code "' . htmlentities($code) . '"');
                    break;
                }

                $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

                header($protocol . ' ' . $code . ' ' . $text);

                $GLOBALS['http_response_code'] = $code;

            } else {

                $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);

            }

            return $code;

        }
    }

$iWS = new iWS();
register_activation_hook(__FILE__, array($iWS, 'activation'));
register_deactivation_hook(__FILE__, array($iWS, 'deactivation'));
