<?php
    class SimpleApi
    {
        private $title;
        private $version = "1.0";
        private $response = array();
        

            // Protected == intened for modules
        protected $response_format = null; // null == Autodetect
        protected $headers; 
        protected $callmode;
        protected $get,$post,$request,$server;
       
        protected $calls,$keys;

        public function __construct($title = "Untitled API")
        {
            $this->title = $title;
            
            if (php_sapi_name() == "cli") 
                $this->callmode="cli";
            else 
                $this->callmode="web";
        
            $this->request_type = $_SERVER["REQUEST_METHOD"];
            $this->request_https = ($_SERVER["HTTPS"] == "on");

            $this->get = $_GET; unset($_GET);
            $this->post = $_POST; unset($_POST);
            $this->server = $_SERVER; unset($_SERVER);
            $this->request = $_REQUEST; unset($_REQUEST);
        
            $this->headers = getallheaders();
        }

        public function __destruct()
        {
            switch ($this->response_format)
            {
                case "json-pretty":
                    print "<pre>";
                    print json_encode($this->response,JSON_PRETTY_PRINT);
                case "json":
                    print json_encode($this->response);
                    break;
                case "array":
                default:
                    print "<pre>";
                    print_R($this->response);
            }
        }

        public function error($title, $message, $code)
        {
            $tpl = new SimpleTpl();
            $tpl->debug("true");
            $tpl->assign("title",$this->title);
            $tpl->assign("version",$this->version);
            $tpl->assign("calls",$this->calls);
            $tpl->assign("code",400);
            $tpl->assign("codetext","Invalid request");
            $tpl->display("error.tpl");
        }

        public function registerCall($route, $name,$class,$method)
        {
            $this->calls[]=array("name"=>$name,"route"=>$route,"class"=>$class,"method"=>$method);
            
            end($this->calls);
            return key($this->calls);
        }

        public function registerKey($key, $capabilities, $meta = null)
        {
            $this->keys[]=array("key"=>$key,"capability"=>$capabilities, "meta"=>$meta);

            end($this->keys);
            return key($this->keys);
        }

        public function authKey($key)
        {
            $this->currentKey = $key;
        }

        public function authHas($capability, $override_key = null)
        {
            if ($override_key == null)
                $k = $this->currentKey;
            else
                $k = $override_key;

            foreach ($this->keys as $key)
            {
                if ($key["key"] == $k)
                    if ($key["capability"] == "*")
                        return true;
                    else if (in_array($capability,explode(" ",$key["capability"])))
                        return true;
                    else 
                        false;
            }
        }

        public function authRequire($capability)
        {
            if (!$this->authHas($capability))
            {
                die("ERROR: ACCESS DENIED");
            }
            return true;
        }

        public function getCallByRoute($route)
        {
            foreach ($this->calls as $call)
            {
                # Check for excact match
                if ($call["route"] == $route)
                    return $call;
             
                # Check for root of catchall
                if ($call["route"] == $route."/*")
                    return $call;

                # Check for catchall
                if ($call["route"][strlen($call["route"])-1] == "*")                                # Call-route = catchall ? (ends in *)
                        if ((substr($route,0,strlen($call["route"])-1)."*") == $call["route"])      # Does the first part of request-route match call-route?
                            return $call;
            }
            return false;
        }

        public function run()
        {
            $route = "/".$this->server["REDIRECT_ROUTE"];
            $this->uri_tokkens = preg_split("/\//",substr($route,1));
            if ($call=$this->getCallByRoute($route))
            {
                if (method_exists($call["class"],$call["method"]))
                    $call["class"]->$call["method"]($this);
            } else {
                print "ERROR: Unknown route";
            }
//            $this->error("No module requested","The request was not wellformed, please verify syntax.",400);
        }
        public function response($a = null, $b = null)
        {
            if (($a == null) and ($b == null))
                return $this->response;

            else if (is_array($a))
                $this->response = array_merge($this->response, $a);

            else if ($b == null)
                return $this->response[$a];

            else if (is_array($b))
                if (is_array($this->response[$a]))
                    $this->response[$a] = array_merge($this->response[$a], $b);
                else 
                    $this->response[$a] = $b;

            else
                $this->response[$a] = $b;

        }
    }

    include("SimpleTpl/SimpleTpl.class.php");




   # http_response_code(400);
    

    class AuthID extends SimpleApi
    {
        public function __construct()
        {
            parent::__construct("LeanDNS AuthID");
        
   #         $this->registerCall("/test","authUser",$this,"");
  #          $this->registerCall("/nisse/*","auth nisse",$this,"");
            $this->registerCall("/nisse/*","root",$this,"test");
           

            $this->registerKey("C9YlxBM1","can_copy",   array(  "username"  =>  "Charlige Root",
                                                                "phone"     =>  "+4541282808"));

            $this->registerKey("5LN4YEOt","can_read",   array(  "username"  =>  "Lizzie Olsen",
                                                                "phone"     =>  "+45121212121"));


        }

        public function test($core)
        {
            $core->response("r",array("aresult"=>"onayk")); 
            $core->response("r",array("bresult"=>"onayk")); 
            $core->response(array("result"=>"onayk")); 
            $core->response(array("riesult"=>"onayk")); 
        
            $core->response_format = "json";
        }
    }


    $myApi = new AuthID();
    $myApi->authKey("C9YlxBM1");

    $myApi->authRequire("can_cope");
    
    print "Running";
    $myApi->run();
?>

