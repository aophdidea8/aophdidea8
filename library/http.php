<?php
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
                             'User-Agent'=>'aophd-HttpClient/1.1');

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
            list($type,$char) = explode(';',$contentType);
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