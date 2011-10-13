<?php

class httpApplication
{
    private $requestHandler;

    
    /**
     * Assign a handling class that will run the current page
     *
     * @param class $handler
     */
    public function __construct($handler)
    {
        $this->requestHandler = $handler;
    }
    
    
    /**
     * Run a http application request
     */
    public function run()
    {
        // get the current http request
        $request = httpRequest::getRequest();
        
        // send some vars to the log
        if (Debug::isOpen())
        {
            $method = $request->method;
            $page = $request->fullPath;
            Debug::writeLine("REQ: $method request for $page");
            
            if ($method == 'post')
            {
                foreach ($_POST as $key => $value)
                {
                    if (!is_array($value))
                    {
                        if (strlen($value)> 255)
                            $value = substr($value,0,252) . '...';
                    }
                    else
                        $value = "array";
                    Debug::writeLine("POST: $key - $value");
                }
            }
        }
        
        try 
        {
            ob_start();

            $response = $this->requestHandler->handleRequest($request);
        }
        catch (Exception $e)
        {
            $html = '<pre>' . get_class($e);
            $message = $e->getMessage();
            if ($message !== null && $message !== '')
                $html .= ': ' . "<span>$message</span>";
            $html .= "\n\n" . '    in ' . $e->getFile() . ' at line ' . $e->getLine();
            $html .= "\n\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            $response = new HttpResponse(500, $html);
            $response->setNoCache();
        }
       
        // Check output buffer
        $data = ob_get_contents();

        // Assert output buffer active
        if ($data === false)
        {
            throw new Error('no output buffer?');
        }
 
         // Only send the response if there were no PHP errors
        if ($data === '')
        {
            // Send the response
            $response->send();
        }

        $status = $response->getStatus();
        Debug::writeLine("DBG: Sent $status");
        
    }
}



class httpResponse
{
    private $httpCode = array(100 => "HTTP/1.1 100 Continue",
                              101 => "HTTP/1.1 101 Switching Protocols",
                              200 => "HTTP/1.1 200 OK",
                              201 => "HTTP/1.1 201 Created",
                              202 => "HTTP/1.1 202 Accepted",
                              203 => "HTTP/1.1 203 Non-Authoritative Information",
                              204 => "HTTP/1.1 204 No Content",
                              205 => "HTTP/1.1 205 Reset Content",
                              206 => "HTTP/1.1 206 Partial Content",
                              300 => "HTTP/1.1 300 Multiple Choices",
                              301 => "HTTP/1.1 301 Moved Permanently",
                              302 => "HTTP/1.1 302 Found",
                              303 => "HTTP/1.1 303 See Other",
                              304 => "HTTP/1.1 304 Not Modified",
                              305 => "HTTP/1.1 305 Use Proxy",
                              307 => "HTTP/1.1 307 Temporary Redirect",
                              400 => "HTTP/1.1 400 Bad Request",
                              401 => "HTTP/1.1 401 Unauthorized",
                              402 => "HTTP/1.1 402 Payment Required",
                              403 => "HTTP/1.1 403 Forbidden",
                              404 => "HTTP/1.1 404 Not Found",
                              405 => "HTTP/1.1 405 Method Not Allowed",
                              406 => "HTTP/1.1 406 Not Acceptable",
                              407 => "HTTP/1.1 407 Proxy Authentication Required",
                              408 => "HTTP/1.1 408 Request Time-out",
                              409 => "HTTP/1.1 409 Conflict",
                              410 => "HTTP/1.1 410 Gone",
                              411 => "HTTP/1.1 411 Length Required",
                              412 => "HTTP/1.1 412 Precondition Failed",
                              413 => "HTTP/1.1 413 Request Entity Too Large",
                              414 => "HTTP/1.1 414 Request-URI Too Large",
                              415 => "HTTP/1.1 415 Unsupported Media Type",
                              416 => "HTTP/1.1 416 Requested range not satisfiable",
                              417 => "HTTP/1.1 417 Expectation Failed",
                              500 => "HTTP/1.1 500 Internal Server Error",
                              501 => "HTTP/1.1 501 Not Implemented",
                              502 => "HTTP/1.1 502 Bad Gateway",
                              503 => "HTTP/1.1 503 Service Unavailable",
                              504 => "HTTP/1.1 504 Gateway Time-out");
    private $status = '';
    private $data = '';
    private $statusText = '';
    private $suppressFormatting = false;
    
    private $cookies = array();
    private $headers = array();
    
    private $isCacheable = true;
    private $isCompressible = true;
    private $time = 0;
    
    public function __construct($status,$data = null,$suppressFormatting = false)
    {
        $this->status = $status;
        $this->data = $data;
        $this->suppressFormatting = $suppressFormatting;

        if (!array_key_exists($status,$this->httpCode))
            throw new Exception("Status code $status does not exist");
        $this->statusText = $this->httpCode[$status];

        Debug::writeLine("DBG: Received $this->statusText");
        if ($status === 500)
        {
            Debug::writeLine($data);
        }
        
        $this->time = time();
        
        // set is cacheable to false for anything other than a standard get response;
        if ($status !== 200)
            $this->isCacheable = false;
    }


    public function setStatus($status)
    {
        $this->status = $status;
        $this->statusText = $this->httpCode[$status];
    }
    
    /**
     * record a header for later
     *
     * @param string $header
     * @param string $value
     */
    public function setHeader($header,$value)
    {
        $this->headers[$header]=$value;
    }

    public function getHeader($header)
    {
        if (isset($this->headers[$header]))
            return $this->headers[$header];

        return null;
    }
    
    
    /**
     * Set the cache to expire instantly
     *
     */
    public function setNoCache()
    {
        // set http 1.1 no cache
        $this->setHeader('Cache-Control','no-cache, must-revalidate');
        $this->setHeader('Pragma', 'no-cache');
        $this->setHeader('Expires','-1'); // Date in the past
        
        $this->isCacheable = false;
    }
    
    /**
     * Set if the response is compressible 
     *
     */
    public function setCompressible($bool)
    {
        $this->isCompressible = $bool;
    }
    
    
    /**
     * Set a cookie
     * To delete a cookie, send expires as 0
     *
     * @param string $name
     * @param string $value
     * @param time $expires
     * @param string $path
     * @param string $domain
     */
    public function setCookie($name,$value,$expires = null, $path = '/', $domain = null)
    {
	
        $cookie = 'set-cookie: '.urlencode($name) . '=' . urlencode($value);
        
        // add an expiry ?
        if ($expires != null)
        {
            $cookie .= '; expires='.date("r",$expires);
        }
        
        // add the path
        $cookie .= "; path=" . str_replace('%2F', '/', urlencode($path));
        
        // set the domain
        if ($domain !== null)
        {
            $cookie .= "; domain=$domain";
        }
        debug::writeline("COK: $cookie");
        $this->cookies[$name] = $cookie;
        
    }
    
  
    /**
     * Send the site
     *
     */
    public function send()
    {
        // send the status text first
        header($this->statusText);
        
        // set a default content type
        if (!isset($this->headers['Content-Type']))
            $this->headers['Content-Type'] = 'text/html';
        
        $page = null;
        
        // create a page with a standard title
        if ($this->status !== 304 && $this->status !== 200)
        {
            $title = str_replace("HTTP/1.1 $this->status ",'',$this->statusText);
            $page = $this->createxHtml($title);
            
        }
        
        // check what sort of response we are sending
        switch($this->status)
        {
        case 200:
        case 304:
            break;
            
        case 301:
        case 302:
        case 303:
        case 307:
            $this->setHeader("Location",$this->data);
            $body = '<p>See <a href="'.$this->data.'">'.$this->data.'</a></p>';
            $this->data = str_replace('<data>',$body,$page);
            break;          
            
        case 401:
        case 500:
            if (!$this->suppressFormatting)
            {
                $body = '<p>'.$this->data.'</p>';
                $this->data = str_replace('<data>',$body,$page);
            }
            break;
        case 404:
            $body = '<p>The requested file has not been found</p>';
            $this->data = str_replace('<data>',$body,$page);
            break;        
        default:
            $this->data = str_replace('<data>','',$page);
        }
        
        // can we send a gzip body ?
        $request = httpRequest::getRequest();

        // Set all the out going headers
        foreach ($this->headers as $header => $value)
            header("$header: $value");

        // set the cookies
        foreach ($this->cookies as $name=>$cookie)
        {
            header($cookie,false);
            Debug::writeLine("CKY: $cookie");
        }

        // if we are sending a not-modified, return now, the rest is superflous
        if ($this->status == 304)
            return;
        
        // get the body
        $data = $this->data;

        // add some god-awful stuff for ie6
        if (!$this->isCacheable)
        {
            $nocache = '<meta http-equiv="Pragma" content="no-cache" /><meta http-equiv="Expires" content="-1" />';
            $data = str_replace('<head>',"<head>$nocache",$data);
        }
        
        // can we encode it?
        if (strpos($request->accept_encoding,'gzip') !== false || strpos($request->accept_encoding,'*') !== false && $this->isCompressible === true)
        {
            $this->setHeader("Content-Encoding","gzip");
            $data = gzencode($data);
        }

        // add the content length
        $this->setHeader('Content-Length',strlen($data));
        $this->setHeader('X-Shakespearean-Insult',$this->getInsult());
        
        // Set all the out going headers
        foreach ($this->headers as $header => $value)
            header("$header: $value");
            
        // we don't send data for a head request
        if (strtolower($request->method) === 'head')   
            exit;
        
        // send the data
        print $data;
        
    }
    
    
    /**
     * Create a basic xhtml page to return for errors etc.
     *
     * @param string $title
     * @return string
     */
    private function createxHtml($title)
    {
        $result = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">' .
                  '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">' .
                  '<head>' .
                  "<title>$title</title></head><body><h1>$title</h1><data></body></html>";
        return $result;
    }
    
    
    /**
     * Get the current status code
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }
    
    
    /**
     * Is this response cacheable?
     *
     * @return boolean
     */
    public function isCacheable()
    {
        return $this->isCacheable;
    }
    
    
    /**
     * get the content type that has been set
     *
     * @return string
     */
    public function getContentType()
    {
        if (isset($this->headers['Content-Type']))
            return $this->headers['Content-Type'];
        return null;
    }
    
    /**
     * Get the content body
     *
     * @return string
     */
    public function getContent()
    {
        return $this->data;
    }

    
    /**
     * Set the content of a cached item
     *
     * @param string $content
     * 
     */
    public function setContent($content)
    {
        $this->data = $content;
    }
    
    
    /**
     * Return the current time of the object
     * This may come from a serialise
     *
     * @return int
     */
    public function getTime()
    {
        return $this->time;
    }
    
    /**
     * Make sure we serialise the correct variables only
     *
     * @return array
     */
    function __sleep()
    {
        Debug::writeLine("CHC: Serialised object created");
        return array('status', 'data', 'statusText','cookies', 'headers', 'isCacheable', 'time');
        
    }


    /**
     *  Get a Shakespearean insult
     * @return string
     */
    private function getInsult()
    {
        $titles = array("Sire","Knave","Sir","Squire","My Liege","Your Majesty","Fop",
                        "Woman","Old Man","Lord","Peasant","Serf","Get thee gone","Fie",
                        "Begone","From thy visage I presume","I faith, nuncle","Tis said that",
                        "Tis plain to mine eye","Fool","Marry, I faith");

        $firsts = array("artless","bawdy","beslubbering","bootless","churlish",
                        "cockered","clouted","craven","currish","dankish","dissembling",
                        "droning","errant","fawning","fobbing","froward","frothy","gleeking",
                        "goatish","gorbellied","impertinent","infectious","jarring",
                        "loggerheaded","lumpish","mammering","mangled","mewling","paunchy",
                        "pribbling","puking","puny","quailing","rank","reeky","roguish","ruttish",
                        "saucy","spleeny","spongy","surly","tottering","unmuzzled","vain",
                        "venomed","villainous","warped","wayward","weedy","yeasty","");

        $seconds = array("base-court","bat-fowling","beef-witted","beetle-headed",
                         "boil-brained","clapper-clawed","clay-brained","common-kissing",
                         "crook-pated","dismal-dreaming","dizzy-eyed","doghearted",
                         "dread-bolted","earth-vexing","elf-skinned","fat-kidneyed",
                         "fen-sucked","flap-mouthed","fly-bitten","folly-fallen","fool-born",
                         "full-gorged","guts-griping","half-faced","hasty-witted","hedge-born",
                         "hell-hated","idle-headed","ill-breeding","ill-nurtured","knotty-pated",
                         "milk-livered","motley-minded","onion-eyed","plume-plucked","pottle-deep",
                         "pox-marked","reeling-ripe","rough-hewn","rude-growing","rump-fed",
                         "shard-borne","sheep-biting","spur-galled","swag-bellied","tardy-gaited",
                         "tickle-brained","toad-spotted","urchin-snouted","weather-bitten");

        $thirds = array("apple-john","baggage","barnacle","bladder","boar-pig",
                        "bugbear","bum-bailey","canker-blossom","clack-dish","clotpole",
                        "coxcomb","codpiece","death-token","dewberry","flap-dragon",
                        "flax-wench","flirt-gill","foot-licker","fustilarian","giglet",
                        "gudgeon","haggard","harpy","hedge-pig","horn-beast","hugger-mugger",
                        "jolthead","lewdster","lout","maggot-pie","malt-worm","mammet","measle",
                        "minnow","miscreant","moldwarp","mumble-news","nut-hook","pigeon-egg",
                        "pignut","puttock","pumpion","ratsbane","scut","skainsmate","strumpet",
                        "varlet","vassal","whey-face","wagtail");
        
        $title = $titles[rand() % count($titles)];
        $first = $firsts[rand() % count($firsts)];
        $second = $seconds[rand() % count($seconds)];
        $third = $thirds[rand() % count($thirds)];

        $n = '';
        try
        {
            $letter = $first[0];
        }
        catch (Exception $e)
        {
            return '';
        }
        switch ($first[0])
        {
        case 'a':
        case 'e':
        case 'i':
        case 'o':
        case 'u':
            $n = 'n';
            break;
        }

        return "$title, thou art nought but a$n $first $second $third.";
    }
}

/**
 * Easy interface to the standard vars that are passed with the request
 */

class httpRequest
{
    private $headers = array();
    
    public function __construct()
    {
        $this->scheme = 'http';
        
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === "on")
            $this->scheme = 'https';
        
        if (isset($_SERVER['SERVER_NAME']))
            $this->serverName = $_SERVER['SERVER_NAME'];

        // for NGINX that doesn't pass a correct server name
        if (strpos($this->serverName,'*') !== false && isset($_SERVER['HTTP_HOST']))
        {
            $this->serverName = $_SERVER['HTTP_HOST'];

            // check for a port in the HTTP_HOST
            if (strpos($this->serverName,':') !== false)
            {
                // and throw it away
                list($server,$post) = split(':',$this->serverName);
                $this->serverName = $server;
            }
        }    
        
        if (isset($_SERVER['SERVER_PORT']))
            $this->serverPort = $_SERVER['SERVER_PORT'];
            
        if (isset($_SERVER['REQUEST_METHOD']))
            $this->method = strtolower($_SERVER['REQUEST_METHOD']);
            
        if (isset($_SERVER['SERVER_PROTOCOL']))
            $this->protocol = $_SERVER['SERVER_PROTOCOL'];
            
        if (isset($_SERVER['SCRIPT_NAME']))
        {
            if (isset($_SERVER['REQUEST_URI']))
            {
                if ($_SERVER['SCRIPT_NAME'] === '' || strpos($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME']) === 0)
                {
                    $this->scriptPath = $_SERVER['SCRIPT_NAME'];
                }
                else
                {
                    $index = strpos($_SERVER['REQUEST_URI'], '?');
                    if ($index === false)
                        $this->scriptPath = $_SERVER['REQUEST_URI'];
                    else
                        $this->scriptPath = substr($_SERVER['REQUEST_URI'], 0, $index);
                }
            }
            else
            {
                $this->scriptPath = $_SERVER['SCRIPT_NAME'];
            }
        }

        if (isset($_SERVER['HTTP_USER_AGENT']))
        {
            $this->userAgent = $_SERVER['HTTP_USER_AGENT'];
        }

        if (isset($_SERVER['PATH_INFO']))
            $this->pathInfo = $_SERVER['PATH_INFO'];
        else
            $this->pathInfo = $_SERVER['DOCUMENT_URI'];
            
        if (isset($_SERVER['QUERY_STRING']))
            $this->queryString = $_SERVER['QUERY_STRING'];

        $this->uri = $this->pathInfo;
        if ($this->queryString !== null && $this->queryString !=='')
            $this->uri .= "?$this->queryString";
            
        if (isset($_SERVER['PHP_AUTH_USER']))
            $this->username = $_SERVER['PHP_AUTH_USER'];
            
        if (isset($_SERVER['PHP_AUTH_PW']))
            $this->password = $_SERVER['PHP_AUTH_PW'];
            
        if (isset($_SERVER['REMOTE_ADDR']))
            $this->ipAddress = $_SERVER['REMOTE_ADDR'];
        
        $fullpath = $this->pathInfo;
        if ($this->queryString !== null && $this->queryString !== '')
            $fullpath .= '?'.$this->queryString;
        
        $this->fullPath = $fullpath;
       
        // if we're not using apache ... bail out
        if (!function_exists('apache_request_headers'))
        {
            if(isset($_SERVER['HTTP_IF_NONE_MATCH']))
            {
                $this->if_none_match = $_SERVER['HTTP_IF_NONE_MATCH'];
            }
            if(isset($_SERVER['HTTP_ACCEPT_ENCODING']))
            {
                $this->accept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'];
            }
            return;
        }

        // parse all the remaining headers
        $headers = apache_request_headers();
        
        foreach ($headers as $key => $value)
        {
            $key = str_replace('-','_',$key);
            $key = strtolower($key);
            $this->$key = $value;
            
            // Get the actual hostname used in the request
            if (strtolower($key) == 'host' && $value !== null && $value !== '')
            {
                $this->hostname = $value;
            }
        }
        
    }
    
    
    /**
     * static interface to this class
     *
     * @return unknown
     */
    public static function getRequest()
    {
        return new self;
    }
    
    
    /**
     * get a variables contents
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->headers[$name]))
            return $this->headers[$name];
        return null;
        
    }

    
    /**
     * Set a variable
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name,$value)
    {
        // deal with any nulls
        if ($value == null && isset($this->headers[$name]))
        {
            unset($this->headers[$name]);
        }
        else if ($value == null)
        { 
            return;
        }
        
        $this->headers[$name] = $value;
        
        return;
    }
}



/**
 *
 * 	Access and clean any incoming http data
 * 	
 *  Additionally, these is a method to check html through the "tidy" extension.  If this doesn't exist,
 *  the string is returned untouched.  If available, the string will be tidied, and will return an array
 *  on an unfixable error.  
*/

class httpData
{
    private static $tidyConfig=array('indent'=>true,
                                     'output-xhtml'=>true,
                                     'wrap'=>'200',
                                     'enclose-text'=>true,
                                     'show-body-only'=>true,
                                     'show-warnings'=>false,
                                     "clean" => false,
                                     "drop-proprietary-attributes" => true,
                                     "drop-font-tags" => true,
                                     "drop-empty-paras" => true,
                                     "hide-comments" => true,
                                     'quote-nbsp'=>false,
                                     "join-classes" => true,
                                     "join-styles" => true);

    /**
     * Query strings should only be used for non volatile data, and should be used sparingly.
     * 
     * @param string $name
     * @return string
     */
    public static function getQueryAsInt($name)
    {
        return self::getData($name,$_GET,'string','int');
    }


    public static function getQueryAsString($name)
    {
        return self::getData($name,$_GET,'string');
    }


    public static function getQueryAsBool($name)
    {
        return self::getData($name,$_GET,'bool');
    }


    public static function getRawQuery($name)
    {
        return isset($_GET[$name]) ? $_GET[$name] : null;
    }

    
    public static function getPostAsString($name)
    {
        return self::getData($name,$_POST,'string');
    }

    
    public static function getPostAsInt($name)
    {
        return self::getData($name,$_POST,'int');
    }


    /**
     *  NB: To parse a BBCode string, set allowed tags to "bbcode"
     * otherwise it will be done as html
     *
     * @param string $name
     * @param string $allowedTags all - will allow any html, <p><b> etc will allow those tags and bbcode will parse the string
     * @return string
     */
    public static function getPostAsHtml($name,$allowedTags = 'all', $tidyHtml = true)
    {
        $html = self::getData($name, $_POST, $allowedTags, $tidyHtml);
        $html = html_entity_decode($html,ENT_NOQUOTES,'UTF-8');
        return $html;
    }


    public static function getPostAsMinimalHtml($name)
    {
        $data = self::getData($name, $_POST, 'string');
        return MinimalHtmlCleanser::clean($data);

    }


    public static function getPostAsBool($name)
    {
        $data = self::getData($name,$_POST,'bool');
        if ($data === null)
            return false;
        return $data;
    }

    public static function getRawPost($name)
    {
        return isset($_POST[$name]) ? $_POST[$name] : null;
    }

    public static function getCookieAsString($name)
    {
        return self::getData($name,$_COOKIE,'string');
    }

    public static function getCookieAsInt($name)
    {
        return self::getData($name,$_COOKIE,'int');
    }

    public static function getRawCookie($name)
    {
        return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
    }

    public static function getFileAsString($name)
    {
        if (!isset($_FILES[$name]))
            return null;
        
        try
        {
            return file_get_contents($_FILES[$name]['tmp_name']);
        }
        catch (Exception $e)
        {
            throw new exception(join("\n",$_FILES[$name]));
        }
    }

    public static function getRawFile($name)
    {
        return (isset($_FILES[$name])) ? $_FILES[$name] : null;
    }

    public static function getFileAsHtml($name)
    {
        if (!isset($_FILES[$name]))
            return null;

        $file = file_get_contents($_FILES[$name]['tmp_name']);

        $file = strip_tags($file,'<p><i><b><u><a><hr><center><br><blockquote><strike><s><em>');

        return self::checkAndCleanHtml($file);
    }

    public static function getFilenameAsString($name)
    {
        if (!isset($_FILES[$name]))
            return null;

        return strval($_FILES[$name]['name']);
    }
    public static function getTmpFilenameAsString($name)
    {
        if (!isset($_FILES[$name]))
            return null;

        return strval($_FILES[$name]['tmp_name']);
    }



    /**
     *  Extract the array[key] data, and return it cast to the correct type
     *
     * @param string $key The key to look for in the data
     * @param array $array the array holding the raw data
     * @param string $type the return type
     * @return <type>
     */
    private static function getData($key,$array,$type,$tidyHtml = true)
    {
        if (isset($array[$key]))
        {
            $value = $array[$key];

            if (is_array($value))
                $value = self::getArrayData($value, $type, $tidyHtml);
            else
                $value = self::cast($value, $type, $tidyHtml);

            return $value;
        }
        return null;
    }


    /**
     *  Take an array and cast the elements to the correct type
     * Note, this will recursviely search.
     * @param array $value
     * @param string $type
     * @return array
     */
    private static function getArrayData($value,$type, $tidyHtml)
    {
        // iff we have an array, we need to loop through it
        if (is_array($value))
        {

            foreach ($value as $k =>$v)
            {
                // if we have a recursive array, we recurse the call
                if (is_array($v))
                    $value[$k] = self::getArrayData($k,$type);
                else // casit it to the correct type
                    $value[$k] = self::cast($v,$type);
            }
        }
        else // cast
            $value = self::cast($value,$type);

        return $value;
    }


    /**
     * Cast a value to the correct type
     * @param string $value
     * @param string $type
     * @return mixed
     */
    private static function cast($value,$type, $tidyHtml = false)
    {
        // just in case
        if (get_magic_quotes_gpc())
        {
            $value = stripslashes($value);
        }

        // remove all spaces, this is form data
        $value = trim($value);

        // cast to the correct type
        switch($type)
        {
        case 'int':
            return intval($value);
        case 'string':
            return strval(trim($value));
        case 'bool':
            if ($value == 1 || $value == 'yes' || $value == 'true')
                return true;
            return false;
        case 'bbcode': // a string is bb code, so we can parse it here
            return trim(utils::parseBBCode($value));
        case 'all': // allow all html
            if ($tidyHtml)
            {
                return self::checkAndCleanHtml($value);
            }
            else
            {
                return $value;
            }
        default: // remove the html we don't allow
            if ($tidyHtml)
            {
                return self::checkAndCleanHtml(strip_tags($value,$type));
            }
            else
            {
                return strip_tags($value, $type);
            }
        }
    }


    /**
     * Check that the HTML is valid according to the Tidy plugin
     * @param string $html 
     * @return string
     */
    public static function checkAndCleanHtml($html,$encoding = 'utf8')
    {
        // if tidy is not installed, return the html as is
        if (!function_exists('tidy_parse_string'))
            return $html;

        // new tidy clase
        $tidy = new tidy;

        // parse the string, using the options above
        $tidy->parseString($html,self::$tidyConfig,$encoding);

        // clean and repair
        $tidy->cleanRepair();

        // if we have an error, format the string and return it
        if ($tidy->errorBuffer)
        {
            throw new Exception(self::htmlErrors($tidy->errorBuffer,$html));
        }
        return (string)$tidy;
                                
    }


    /**
     * Deal with html errors from the tidy function
     *
     * @param string $errors
     * @param string $html
     * @return string
     */
    private static function htmlErrors($errors,$html)
    {
        // split the html into an array of lines
        $html = explode("\n",$html);
        
        $tidy_errors = array();
        // split the errors into useable parts.
        preg_match_all('/^(?:line (\d+) column (\d+) - )?(\S+): (?:\[((?:\d+\.?){4})]:)?(.*?)$/m', $errors, $tidy_errors, PREG_SET_ORDER);

        $errorChunk = '';
        
        // loop through the errors and format them
        for ($i=0;$i<count($tidy_errors);$i++)
        {
            $line = intval($tidy_errors[$i][1]);
            $column = intval($tidy_errors[$i][2]);
            $error = htmlspecialchars($tidy_errors[$i][5]);
            $errorLine = htmlspecialchars($html[$line -1]);
            $errorChunk .= "<pre><p>Error: $error<br />$errorLine<br />".str_repeat('_',$column).'^</p></pre>';
        }
        
        // return errors
        return $errorChunk;
    }
}


/**
 * HTTP CLIENT lib
 *
 *Library to mimic a HTTP request to any site, of GET, POST, DELETE or PUT
 *
 * Handles files as well as normal post forms.
 *
 * Usage:
 *
 * $client = new phpHttpClient('get','http://www.google.com/search');
 * // set some form values
 * $client->q = 'text';
 * $client->foo = 'bar'
 * // get the page from google
 * $response = $client->execute();
 *
 * Will return character set of UTF=8, transposed if necessary.
 *
 */

class phpHttpClient
{
    // the default method
    private $method = 'POST';

    // the resource/path
    private $path = '';

    // Address to open a socket to
    private $host = '';

    // the full http request
    private $request = null;

    // the boundary for a multi part form
    private $boundary = null;

    // default setting
    private $headers = array('Accept'=>'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                             'Accept-Charset'=>'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
                             'Connection'=>'close',
                             'User-Agent'=>'phplib-HttpClient/1.1');

    // store the response
    private $responseHeaders = null;
    private $responseBody = null;

    // holds the form variables
    private $params = array();

    // any files that are being send
    private $files = array();

    // if we have a large file, we get rid of the time outs
    private $largeFile = false;

    // if the server returns a 301, 302 with a Location header, should the client make a new request to that url?
    // NB:  This will do a complete new request, but will change a POST etc to a GET, as per RFC!
    private $followRedirects = false;
    private $redirectLevel = 0;


    /**
     *
     * @param string $method  POST|GET|PUT etc
     * @param string $url an absolute URL - http://www.foo.co.uk/foo/bar/?foo=bar
     * @param int $redirectLevel
     * @param bool $largeFile
     *
     */
    public function __construct($method, $url, $redirectLevel = 0, $largeFile = false)
    {


        $res = preg_match('~http://(.*?)/(.*)$~', $url, $matches);

        if ($res == 0)
        {
            throw new Error("$url is not a URL");
        }
        $host = $matches[1];
        $path = '/' . $matches[2];

        $this->method = strtoupper($method);
        $this->path = $path;
        $this->host = $host;
        $this->redirectLevel = $redirectLevel;

        if ($redirectLevel > 4)
        {
            throw new Exception("Max number of redirects followed.");
        }

    }


    /**
     * Buold the http request
     * @return string
     */
    private function build()
    {
        //Debug::writeLine('CLT: Building request');
        if ($this->request !== null)
        {
            return $this->request;
        }

        // buld the request
        switch($this->method)
        {
        case 'GET':
            $queryString = http_build_query($this->params);
            // only add if we need to
            if ($queryString != '')
            {
                $seperator = (strpos($this->path,'?') !== false) ? '&' : '?';
                $this->path = $this->path . $seperator . $queryString;
            }
            $body = '';
            break;

        case 'POST':
        case 'PUT':

            // if we don't have any files - we do a simple request.
            if (count($this->files) === 0)
            {
                $body = http_build_query($this->params);
                $this->headers['Content-Type']= 'application/x-www-form-urlencoded';
            }
            else
            {
                $body = '';
                // add the form elements
                foreach ($this->params as $key => $value)
                {
                    $body .= $this->getBoundary()."\r\n";
                    $body .= "Content-Disposition: form-data; name=\"$key\"\r\n\r\n";
                    $body .= $value;
                    $body .= "\r\n";
                }

                // add the files
                foreach ($this->files as $file)
                {
                    $body .= "$file\r\n\r\n";
                }
            }

            // set the content length
            $this->headers['Content-Length']= strlen($body);
            break;
        default:
            $body = '';
            break;
        }

        // get the current request
        $baseRequest = HttpRequest::getCurrentRequest();

        // set a referrer
        if (!isset($this->headers['Referer']))
            $this->headers['Referer'] = $baseRequest->getRequestUri();


        // build the request:
        $request = $this->method.' '.$this->path . ' ' . "HTTP/1.1\r\n";
        $request .= "Host: ". $this->host."\r\n";

        // set all the other headers
        foreach ($this->headers as $key => $value)
        {
            $request.= "$key: $value\r\n";
        }

        // add the body
        $request .= "\r\n$body";

        // set the request
        $this->request = $request;

        // return it
        return $request;

    }


    /**
     * To string - return the whole request
     * @return string
     */
    public function __toString()
    {
        $b = $this->build();
        $h = $this->host;

        return "<pre>$h\n\n$b</pre>";
    }


    /**
     * Execute the query
     * @return string
     */
    public function execute()
    {
        $request = $this->build();
        $path = $this->path;

        // open a port
        // throw a useful error on failure
        //Debug::writeLine('CLT: Opening port');
        try
        {
            $fd = fsockopen($this->host,80,$errno, $errstr);
        }
        catch (Exception $e)
        {
            throw new Exception("Opening $this->host failed: ".$e->getMessage());
        }

        if ($fd === false || $fd === null)
            throw new Error('Fsockopen returned an invalid pointer');

        stream_set_timeout($fd,5);
        stream_set_blocking($fd,0);

        //Debug::writeLine('CLT: Sending content');
        // send the content
        fwrite($fd,$request);

        //get the response
        $data = '';
        //Debug::writeLine('CLT: Waiting for response');

        /*
         * feof will never return true if the server doesn't use connection: close, OR returns chunked data
         * So we check the headers to find what mode we are in, and get the data we need.
         */
        
        $eof = false; 
        $inHeaders = true;
        $chunkedMode = false;
        $headers = '';
        

        while(!$eof)
        {
            // check to see if we've timed out
            // get the meta data
            $meta = stream_get_meta_data($fd);

            // if we've timed out, 
            if ($meta['timed_out'] === true)
            {
                // close the connection
                fclose($fd);

                // throw an exception so partial data streams aren't set
                throw new Exception("Getting feed for $this->url failed - socket timeout");
            }

            // The first few lines will be headers, until a blank line is returned 
            if ($inHeaders)
            {
                //get the line - cookie lines can be long
                $tmpdata = fgets($fd, 1024);

                // if, with all CRLFs removed, we have an empty string, we can exit the mode
                if (trim($tmpdata) === '')
                    $inHeaders = false;

                // keep a seperate record of the headers
                $headers .= $tmpdata; 
                    
                // check if we have a content-length string
                if (stripos($tmpdata,'Content-Length') === 0)
                {
                    // split the header on the seperator and check and trim the value
                    list($name,$value) = split(':',$tmpdata);
                    $length = intval(trim($value));
                    //Debug::writeLine("CLT: Fetching $length data");
                }

                // check to see if we are operating in chunked mode
                if (stripos($tmpdata,'Transfer-Encoding') === 0)
                {
                    list($name,$value) = split(':',$tmpdata);
                    $mode = strtolower(trim($value));
                    // check that it is actuall chunked
                    if ($mode === 'chunked')
                    {
                        $chunkedMode = true;
                        //Debug::writeLine("CLT: Entering chunked mode");
                    }
                }
            }
            else if ($chunkedMode)
            {
                //debug::writeLine("CLT: Getting a hex chunk");
                $line = fgets($fd, 10);
            
                // There is an optional whitespace and CRLF between implementations, so we need ot ensure we're not looking at an empty string
                while(trim($line) === '')
                    $line = fgets($fd,10);
                
                $chunk = hexdec(trim($line));
                //debug::writeLine("CLT: Chunk is ".trim($line)." = $chunk long");
                // last chunk always has a zero length
                if ($chunk === 0)
                {
                    $eof = true;
                    // add an empty string
                    $tmpdata = '';
                }
                else // not eof, then we get the rest of the data that we need.
                {
                    //debug::writeLine("CLT: Getting data");
                    $tmpdata = '';
                    $len = 0;
                    // when the chunk needed is greater than 8096 bites, we can't get it in one swoop
                    // so we need to check what size we have so far, get more, until the end.
                    while ($len < $chunk)
                    {
                        if (($len + 2056) < $chunk) 
                            $l = 2056;
                        else
                            $l = $chunk - $len;
                            
                        //debug::writeLine("CLT: Getting $l of $chunk, got $len so far");
                        $tmpdata .= fread($fd,$l); 
                        $len = strlen($tmpdata);
                        //debug::writeLine("CLT: Got $l of $chunk, got $len so far");
                    }
                    //debug::writeLine("CLT: Got data");
                }
            }
            else // we just need to get a large bite of the data
            {
                $tmpdata = fread($fd,2048);
            }
            
            // concatante the tmp data
            $data .= $tmpdata;

            // we might have already reached the end with a chunk
            if (isset($length))
            {
                $bodyLength = strlen($data) - strlen($headers);
                $eof = ($bodyLength >= $length); // length - length of headers
            }

            // check for end of feed as well
            if ($eof === false)
                $eof = feof($fd);

        }

        // close it
        fclose($fd);

        //Debug::writeLine('CLT: Parseing headers');
        
        $data = Utils::parseHttpResponse($data);

        // set the headers
        $this->responseHeaders = $data;

        // transliterate the text
        $data['body'] = Utils::normaliseString($data['body'],$data['charset']);

        $this->responseBody = $data['body'];

        // handle redirects
        // Only if we get a "moved" response code, location exists, and it's been requested

        if ($this->followRedirects)
        {
            Debug::writeLine('CLT: Folowing redirect');
            switch($data['response_code'])
            {
            case 301:
            case 302:
            case 303:
            case 307:
                if (!isset($data['location']))
                    break;

                $location = $data['location'];

                $baseUrl = '';

                // check that we've recieved a full url, if not set the base to the
                if (strpos($location,'http://') === false)
                    $baseUrl .= $this->host;

                // check that we've not got a relative response
                if (substr($location,0,1) !== '/')
                {
                    $baseUrl .= $this->path;

                    // as we're appending, we need to ensure we're adding a /
                    if (substr($this->path,-1,1) !== '/')
                        $baseUrl .= '/';
                }

                // set the full url
                $url = $baseUrl.$location;

                $client = new HttpClient('get',$url,$this->redirectLevel++);
                return $client->execute();
                break;
            }

        }

        $data['original_host'] = $this->host;
        $data['original_path'] = $this->path;

        // return the data
        return $data;

    }


    /**
     * Get the returned body
     * @return string
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }


    /**
     * Get the returned headers
     * @return string
     */
    public function getResponseHeaders()
    {
        return $this->responseHeaders;
    }


    /**
     * Add a header to the http request
     * @param string $name
     * @param string $value
     */
    public function setHeader($name,$value)
    {
        $this->headers[$name] = $value;
    }


    /**
     * Should the client follow HTTP 301,302 responses, showing where an item has moved to?
     * @param bool $bool
     */
    public function followRedirects($bool)
    {
        $this->followRedirects = $bool;
    }


    /**
     * Add a file to the post or put
     * @param string $filePath
     * @param string $formName
     */
    public function addFile($filePath,$formName)
    {

        // check we're in the right mode
        switch($this->method)
        {
        case 'POST':
        case 'PUT':
            break;
        default:
            throw new Exception("You can only send files in POST or PUT mode");
            break;
        }

        // get the name of the file itself
        $fileName = basename($filePath);

        // get the file contents.
        $fileData = file_get_contents($filePath);

        // get a boundary for the file
        $boundary = $this->getBoundary();

        $file = $boundary;
        $file .= "Content-Disposition: form-data; name=\"$formName\"; filename=\"$fileName\"\r\n";
        $file .= "Content-Type: " .MimeType::getMimeType($fileName) ."\r\n\r\n\r\n";
        $file .= $fileData;

        // set the header
        if (!isset($this->headers['Content-Type']))
            $this->headers['Content-Type'] = "multipart/form-data; Boundary=$boundary";

        // save the file for later use
        $this->files[] = $file;
    }


    /**
     * Create and return a boundary marker for a multipart
     * @return string
     */
    private function getBoundary()
    {
        if ($this->boundary === null)
        {
            $this->boundary = '----------------'.Utils::randomToken();
        }

        return $this->boundary;
    }


    /**
     * Set some form values
     * @param string $key
     * @param string $value
     */
    public function __set($key,$value)
    {
        if ($value === null && isset($params[$key]))
        {
            unset($params[$key]);
            return;
        }

        $this->params[$key] = $value;
    }


    /**
     * Return a form item
     * @param string $key
     */
    public function __get($key)
    {
        if (isset($this->params[$key]))
            return $this->params[$key];

        return null;
    }
}


class HttpClient
{
    // http settings
    private $method;
    private $host;
    private $path;
    private $type;

    // default setting
    private $headers = array('Accept'=>'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                             'Accept-Charset'=>'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
                             'User-Agent'=>'phplib-HttpClient/1.1');

    private $followRedirects = false;

    private $params = array();

    private $largeFile = false;

    // store the response
    private $responseHeaders = null;
    private $responseBody = null;

    public function __construct($method,$url, $largeFile = false)
    {

        $res = preg_match('~(http|ftp)://(.*?)/(.*)$~', $url, $matches);
        if ($res == 0)
        {
            throw new Error("$url is not a URL");
        }
        $type = $matches[1];
        $host = $matches[2];
        $path = '/' . $matches[3];
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->host = "$type://$host";
        $this->type = $type;
        $this->largeFile = $largeFile;

    }


    /**
     * Execute the query 
     * @return array
     */
    public function execute()
    {
        // init curl.  This really should have an object interface.
        $curl = curl_init();
        
        // do we follow the redirects ?
        curl_setopt($curl,CURLOPT_FOLLOWLOCATION,$this->followRedirects);


        // set a base url
        $url = "$this->host$this->path";
        // check for other things
        switch($this->method)
        {
        case 'POST':
            // set the post option.  Seems redundant.
            curl_setopt($curl,CURLOPT_POST,true);
            // set all the post fields
            curl_setopt($curl,CURLOPT_POSTFIELDS,$this->params);

            // don't send an Expect header
            // Not exactly a bug, but it does cause a lot of problem with servers that aren't set up to
            // deal with continue headers and multi-stage posts
            $this->headers['Expect']='';

            break;
        case 'PUT':
            curl_setopt($curl,CURLOPT_PUT,true);
            break;
        case 'HEAD':
            // more logic from php - nobody should be CUROPT_HEAD
            curl_setopt($curl,CURLOPT_NOBODY,true);
            break;
        case 'GET':
            // set the timeouts, so it should be a maximum of 7.99 seconds, 3.99 to connect and 4 to run.
            if (!$this->largeFile)
            {
                curl_setopt($curl,CURLOPT_CONNECTTIMEOUT,4);
                curl_setopt($curl,CURLOPT_TIMEOUT,4);
            }
            // deal with any options as a query string
            if (count($this->params) > 0)
            {
                // add the correct seperator
                $sep = (strpos($url,'?') === false) ? '?' : '&';
                $url .= $sep.http_build_query($this->params);
            }
            break;
        default:
            // set to DELETE or any more obscure header
            curl_setopt($curl,CURLOPT_CUSTOMREQUEST,$this->method);
            break;
        }
        
        // open the url we are going to.
        curl_setopt($curl,CURLOPT_URL,$url);

        // if we're looking at getting a ftp file, we need to turn passiive off
        if ($this->type === 'ftp')
        {
            curl_setopt($curl,CURLOPT_FTP_USE_EPSV,false);
        }

        // explicitly close the connection, as our servers seem to have a problem
        // with php and open connections
        curl_setopt($curl,CURLOPT_FORBID_REUSE,true);
       
        // for debug purposes, we need to be able to get what we send back, and to do that...
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);

        // get back the headers as well     
        curl_setopt($curl, CURLOPT_HEADER, true);

        // ensure we get the data back to us as a var
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
        // set the default headers, joining them into a single line
        $headers = array();
        foreach ($this->headers as $key => $value)
            $headers[] = "$key: $value";
        
        curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);

        // grab URL and pass it to the browser
        $data = curl_exec($curl);
        
        // check that we got everything back
        if ($data === false)
        {
            $error = curl_error($curl);
            throw new Exception("$url failed. $error");
        }
        // close cURL resource, and free up system resources
        curl_close($curl);
        
        $d = new parseResponse($data,$url);
        $data = $d->getResponse();

        // set the headers
        $this->responseHeaders = $data;
        $this->responseBody = $data['body'];
        
        // set some extra vars
        $data['original_host'] = $this->host;
        $data['original_path'] = $this->path;
        
        // return the data
        return $data;

    }

    /**
     * get just the headers
     * @return string
     */
    public function getResponseHeaders()
    {
        return $this->responseHeaders;
    }

    /**
     * Get just the html 
     * @return string
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }
    
    
    /**
     * Add a header to the http request
     * @param string $name
     * @param string $value
     */
    public function setHeader($name,$value)
    {
        $this->headers[$name] = $value;
    }
    
    
    /**
     * Should the client follow HTTP 301,302 responses, showing where an item has moved to?
     * @param bool $bool
     */
    public function followRedirects($bool)
    {
        $this->followRedirects = $bool;
    }
    
    
    /**
     * Add a file to the post or put
     * @param string $filePath
     * @param string $formName
     */
    public function addFile($filePath,$formName)
    {
        
        if ($this->method === 'GET')
        {
            throw new Exception("Can not add a file to a GET request");
        }

        // by putting the @ symbol in front of the filrpath, curl seems to know what to do with it.
        $this->$formName = "@$filePath";
    }
    
    
    /**
     * Set some form values
     * @param string $key
     * @param string $value
     */
    public function __set($key,$value)
    {
        if ($value === null && isset($params[$key]))
        {
            unset($params[$key]);
            return;
        }

        $this->params[$key] = $value;
    }
    
    
    /**
     * Return a form item
     * @param string $key
     */
    public function __get($key)
    {
        if (isset($this->params[$key]))
            return $this->params[$key];

        return null;
    }

}


/**
 * Parse a response from either a ftp server or a http server, and place the data into a keyed array
 */
class parseResponse
{
    private $data = array();
    private $string = null;

    public function __construct($string, $url)
    {
        $this->string = $string;
        // first, see if we have a HTTP response
        if (strpos($string,'HTTP') !== false)
        {
            $this->parseHttpResponse();
            $this->normalise();
        }
        // next, see if we have an xml file
        else if (strpos($string,'<?xml') === 0)
        {
            $this->body = $string;
            $this->getContentTypeFromUrl($url);

            // if we can't work it out, we'll 
            if ($this->content_type === null)
                $this->content_type = 'text/xml';

            // try and get the encoding
            $this->getEncodingFromString();

            // and normalise!
            $this->normalise();
        }
        else // a file that has been ftpd and isn't xml
        {
            $this->getContentTypeFromUrl($url);
            $this->body = $string;
        }
       
    }


    /**
     * Get the content type from the filename, if possible, 
     * @param string $url 
     */
    private function getContentTypeFromUrl($url)
    {
        try
        {
            $this->content_type = MimeType::getMimeType($url);
        }
        catch (Exception $e)
        {
            // do nothing
        }
    }


    /**
     * Get the data that has been parsed.
     * @return array
     */
    public function getResponse()
    {
        // if we have a _ in a header name, we duplicate it with a - in the name, so that response-code and response_code both work.
        foreach ($this->data as $key => $value)
        {
            $key = str_replace('_','-',$key);
            $this->data[$key] = $value;
        }
        return $this->data;
    }


    /**
     * Normalise the string to UTF-8 and update any references 
     * @return string
     */
    private function normalise()
    {
        // don't bother changing anything
        if ($this->encoding === 'UTF-8')
            return;
        
        // Hmm, here's an idea - let's only transliterate text based files... and not image based
        if (strpos($this->content_type,'text/') === false && strpos($this->content_type,'xml') === false)
            return;
        // as we're only calling this on XML or RSS, we run it through the html transliterator, so &amp; etc are not touched.
        $this->body = Utils::normaliseString($this->body,$this->encoding);

        $this->original_encoding = $this->encoding;
        $this->encoding = 'UTF-8';

        // now we need to update the strings, to represent the fact that they are now UTF-8.
        $this->updateEncodingString();
    }
    
    
    /**
     * If we've converted something, we need to update the string itself. 
     */
    private function updateEncodingString()
    {
        $string = $this->body;
        // change any text so that it now says that it is UTF-8
        if (strpos($string,'http-equiv') !== false)
        {
            $string = preg_replace('#<meta http-equiv="Content-Type"\s?content="([^"]+)"#iU','<meta http-equiv="Content-Type" content="'.$this->content_type.';charset=UTF-8"/>',$string);
        }
        else if (strpos($string,'encoding=') !== false)
        {
            $string = preg_replace('/encoding=([\'|\"])(.*)\\1/iU','encoding="UTF-8"',$string);
        }
        $this->body = $string;
    }
    

    /**
     * Parse a HTTP response with headers 
     */
    private function parseHttpResponse()
    {
        $break = strpos($this->string,"\r\n\r\n");

        $headers = substr($this->string,0,$break);
        $body = ltrim(substr($this->string,$break)); 
        
        $lines = explode("\n",$headers);

        foreach ($lines as $line)
        {

            // if we are dealing with the html header
            if (strpos($line,':') === false)
            {
                preg_match('#(HTTPS?)/(\d\.\d) (\d\d\d) (\w+)#i',$line,$matches);
                $this->scheme = $matches[1];
                $this->http_version = $matches[2];
                $this->response_code = intval($matches[3]);
                $this->response_text = $matches[4];
                continue;
            }

            $pos = strpos($line,':');
            // get the first part of the line, lower case it and replace - with _ for php var names
            $header = str_replace('-','_',strtolower(substr($line,0,$pos)));

            // get the rest of the line
            $value = trim(substr($line,$pos+1,strlen($line)-$pos));

            // if we have a content type, do we have a char set encoding?
            if ($header === 'content_type')
            {
                $this->parseContentType($value);
                continue;
            }
            $this->$header = $value;
        }

        // with the headers removed, the rest is the body
        $this->body = $body;
        
        // if we STILL don't have a charset, try and guess it from the string
        $this->getEncodingFromString();
    }
    
    
    /**
     * Break a contentType header into content type and encoding 
     * @param string $contentType 
     */
    private function parseContentType($contentType)
    {
        // do we have a separator?
        if (strpos($contentType,';') !== false)
        {
            list($type,$char) = split(';',$contentType);
            // return both
            $this->encoding = trim(str_replace('charset=','',$char));
            $this->content_type = trim($type);
        }
    }


    /**
     * Try an get an encoding from a string 
     * @return string
     */
    private function getEncodingFromString()
    {
        // if we already have an encoding bail out
        if ($this->encoding !== null)
            return;

        $body = $this->body;
        if (strpos($body,'http-equiv') !== false && stripos($body,'"content-type') !== false)
        {
            preg_match('#<meta http-equiv="Content-Type"\s?content="([^"]+)"#iU',$body,$htmlMatches);
            $this->parseContentType($htmlMatches[1]);
        }
        elseif (strpos($body,'encoding=') !== false) // rss
        {
            preg_match('/encoding=([\"|\'])(.*)\\1/iU',$body,$rssMatches);
            $this->encoding  = $rssMatches[2];
        }
        
        // if no charset for text, then it should be assumed to be ISO-8859-1
        $this->encoding = 'ISO-8859-1';
        
    }


    /**
     * Magic set 
     * @param string $key 
     * @param mixed $value 
     */
    public function __set($key,$value)
    {
        if ($value === null && isset($this->data[$key]))
        {
            unset($this->data[$key]);
        }
        else if ($value !== null)
            $this->data[$key] = $value;
    }


    /**
     * Magic get 
     * @param string $key 
     * @return Mixed
     */
    public function __get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }
}

/**
 * HTTP request handler
 */
interface HttpRequestHandler
{
    /**
     * Handle a single HTTP request
     *
     * @param HttpRequest $request the request
     * @return HttpResponse the reponse
     */
    public function handleRequest($request);
}
?>
