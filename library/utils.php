<?php


/**
 * The root of all errors.
 */
class Error extends Exception
{

    /**
     * Create a new error
     *
     * @param string $message the error message
     * @param int $errno one of E_ERROR, E_WARNING, etc.
     * @param string $file the path to the file in which the error occurred
     * @param int $line the line number in the file at which the error occurred
     */
    public function __construct($message = null, $errno = null, $file = null, $line = null)
    {
        parent::__construct($message, $errno);
        if ($file !== null)
            $this->file = $file;
        if ($line !== null)
            $this->line = $line;
    }

    /**
     * Modify the error message.
     *
     * @param string $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

}

/**
 * TypeError
 */
class TypeError extends Error
{
    // Empty
}

/**
 * ValueError
 */
class ValueError extends Error
{
    // Empty
}

/**
 * KeyError
 */
class KeyError extends Error
{
    // Empty
}

/**
 * AttributeError
 */
class AttributeError extends Error
{
    // Empty
}

/**
 * IOError
 */
class IOError extends Error
{
    // Empty
}

/**
 * UserError
 */
class UserError extends Error
{
    // Empty
}

/**
 * DataError
 */
class DataError extends Error
{
    // Empty
}

/**
 * DataError
 */
class TimeError extends Error
{
    // Empty
}

/**
 * Error handler.
 */
class ErrorHandler
{

    /**
     * Enables the error handler.
     */
    public static function enable()
    {
        set_error_handler(array('ErrorHandler', 'handleError'));
    }

    /**
     * Disables the error handler
     */
    public static function disable()
    {
        restore_error_handler();
    }

    /**
     * Handles an error
     */
    public static function handleError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        throw new Error($errstr, $errno, $errfile, $errline);
    }

}

class utils
{

    private static $timeZone = 0;
    private static $applyDst = false;

    public static function setTime($timeZone, $applyDst = false)
    {
        self::$timeZone = $timeZone;
        self::$applyDst = $applyDst;
    }

    /**
     * Returns input string converted to UTF-8 (if necessary), with all HTML
     * entities translated to UTF-8 character codes, with the exception of
     * &amp; &lt; &gt; and &quot;
     * 
     * This will accept dirty strings and return a pure string
     *
     * @param string $html the HTML to normalise
     * @param string $encoding the encoding that $html uses
     * @return string
     */
    public static function normaliseString($string, $encoding = 'ISO-8859-1', $isHtml = true)
    {

        // do we need to normalise?  If so, use iconv to do it.
        if ($encoding !== 'UTF-8')
        {
            // to be safe, we try and find similair chars - fixes bug with char 0x96 from MS Word files
            $string = iconv($encoding, 'UTF-8', $string);
        }

        // if we have html, then double escape some key vars, so that entity_decode 
        // leaves them alone
        if ($isHtml)
        {
            $string = strtr($string, array('&amp;' => '&amp;amp;',
                '&lt;' => '&amp;lt;',
                '&gt;' => '&amp;gt;',
                '&quot;' => '&amp;quot;'));
        }

        // Unescape all chars
        $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');

        // Return it
        return $string;
    }

    /**
     * Take a int and convert it into kb, mb, gb, or tb
     * @param int $bytes
     * @return string
     */
    public static function ByteSize($bytes)
    {
        $size = $bytes / 1024;
        if ($size < 1024)
        {
            $size = number_format($size, 2);
            $size .= ' KB';

            return $size;
        }

        if ($size / 1024 < 1024)
        {
            $size = number_format($size / 1024, 2);
            $size .= ' MB';

            return $size;
        }

        if ($size / 1024 / 1024 < 1024)
        {
            $size = number_format($size / 1024 / 1024, 2);
            $size .= ' GB';
            return $size;
        }

        $size = number_format($size / 1024 / 1024 / 1024, 2);
        $size .= ' TB';
        return $size;
    }

    /**
     * Get a remote file from a server, keeping all the useful http headers intact
     * should handle ftp, http and https transparently
     *
     * To use username/password, use http://username:password@url.tld/
     *
     * @param string $url
     * @throws Exception on fail - not found, etc
     * @return array of headers, with array[body] as the main text (css/rss/html etc etc)
     */
    public static function getRemoteFile($url, $ignoreErrors = false, $largeFile = false)
    {
        Debug::writeLine("File:  Getting remote file: $url");

        // if the curl version is available, use it - otherwise, use the phpHttpClient
        if (function_exists('curl_init'))
        {
            $client = new HttpClient('get', $url, $largeFile);
        }
        else
        {
            $client = new phpHttpClient('get', $url);
        }


        $response = $client->execute();
        Debug::writeLine("File:  Finished getting remote file: $url");

        // backward compatibililty.
        // the previous version used fopen, which until php5.3, could not ignore non 200 http status errros.
        // as the HttpClient can get anything, we throw an error if the default ignoreErrors is false
        if (!$ignoreErrors)
        {
            if (isset($response['response_code']) && $response['response_code'] !== 200)
            {
                throw new Exception("Client for $url returned {$response['response_code']}");
            }
        }

        return $response;
    }

    /**
     * returns an array of headers, with array[body] as the main text (css/rss/html etc etc)
     *
     * @param array $headers
     * @param string $body
     * @return array
     */
    static public function parseHttpResponse($headers, $body)
    {
        while ($headers)
        {
            $line = array_shift($headers);

            // if we are dealing with the html header
            if (strpos($line, ':') === false)
            {
                preg_match('#(HTTPS?)/(\d\.\d) (\d\d\d) (\w+)#i', $line, $matches);
                $return['scheme'] = $matches[1];
                $return['http_version'] = $matches[2];
                $return['response_code'] = $matches[3];
                $return['response_text'] = $matches[4];
                continue;
            }

            $pos = strpos($line, ':');
            $header = substr($line, 0, $pos);
            $value = trim(substr($line, $pos + 1, strlen($line) - $pos));

            // if we have a content type, do we have a char set encoding?
            if (strtolower($header) === 'content-type')
            {
                $return += self::parseContentType($value);
                continue;
            }
            $return[strtolower($header)] = $value;
        }


        // if we don't have a charset, let's parse the data to see if we can find a meta tag or a rss content type
        if (!isset($return['charset']))
        {
            // html
            if (strpos($body, 'http-equiv') !== false)
            {
                preg_match('#<meta http-equiv="Content-Type"\s?content="([^"]+)"#iU', $body, $htmlMatches);
                $ct = self::parseContentType($htmlMatches[1]);
                $return += $ct;
            }
            elseif (strpos($body, 'encoding=') !== false) // rss
            {

                preg_match('/encoding="([^"]+)"/i', $body, $rssMatches);
                $return['charset'] = $rssMatches[1];
            }
        }

        // if no charset for HTTP/* text, then it should be assumed to be ISO-8859-1
        if (!isset($return['charset']))
        {
            $return['charset'] = 'ISO-8859-1';
        }

        // set the body as well
        $return['body'] = $body;

        // return it
        return $return;
    }

    /**
     * Parse a string for a valid content-type and charset
     * @param string $contentType
     * @return array
     */
    public static function parseContentType($contentType)
    {
        // do we have a separator?
        if (strpos($contentType, ';') !== false)
        {
            list($type, $char) = explode(';', $contentType);
            // return both
            return array('content-type' => trim($type), 'charset' => trim(str_replace('charset=', '', $char)));
        }
        return array('content-type' => $contentType);
    }

    /**
     * use the tinyurl.com api to create a tiny url
     *
     * @param  string $url
     * @return string
     */
    public static function getTinyUrl($url)
    {
        $url = "http://tinyurl.com/api-create.php?url=$url";

        $body = self::getRemoteFile($url);

        return $body['body'];
    }

    /**
     * Generate a random password
     * @param int $length
     * @return string
     */
    public static function randomPassword($length = 8)
    {
        $pw = '';
        for ($i = 0; $i < $length; $i++)
        {
            $rnum = mt_rand(0, 51);
            if ($rnum < 26)
            {
                $pw.=chr(65 + $rnum);
            }
            else
            {
                $pw.=chr(97 - 26 + $rnum);
            }
        }

        return $pw;
    }

    
    /**
     * Format a delta
     *
     * @param int $delta
     * @return string
     */
    public static function formatDelta($delta)
    {
        if ($delta < 0.001)
        {
            return $delta * 1000;
        }
        else
        {
            return round($delta, 4);
        }
    }

    /**
     * Format a date and apply a timezone and day light saving times correction if required
     *
     * @param int $timestamp
     * @param string $format
     * @param int $timezone
     * @param boolean $DST
     * @return date
     */
    public static function formatDate($timestamp, $format='longtime')
    {
        switch ($format)
        {
        case 'long':
            $type = 'l jS F Y';
            break;
        case 'longtime':
            $type = 'l jS F Y g:ia';
            break;
        case 'time':
            $type = 'g:ia';
            break;
        case 'short':
            $type = 'jS M y';
            break;
        case 'rfc':
            $type = 'r';
            break;
        case 'display':
            $type = 'd | M | Y';
            break;
        }
        $tzdate = 0;
        if (self::$timeZone != 0)
        {
            $tzdate = self::$timeZone * 3600;
        }
        if (self::$applyDst)
        {
            $tzdate = $tzdate + 3600;
        }
        return gmdate($type, $timestamp + $tzdate);
    }

    /**
     * Get a file from the file systen
     *
     * @param string $filename
     * @return string
     */
    public static function getFile($filename)
    {

        if (!file_exists($filename))
        {
            throw new Exception("File not found: $filename");
        }
        if (!$contents = file_get_contents($filename))
        {
            throw new Exception("Can not read $filename!");
        }
        return $contents;
    }

    /**
     * write a file to the file system
     *
     * @param string $filename
     * @param string $contents
     * @return string
     */
    public static function writeFile($filename, $contents)
    {

        if (!$f = @fopen($file, 'w'))
        {
            throw new Exception("File is not writeable, check your permissions: $file");
        }
        fwrite($f, $contents);
        fclose($f);
    }

    /**
     * Make a string javascript safe
     *
     * @param string $string
     * @return string
     */
    public static function makeStringJSSafe($string)
    {
        $string = str_replace("'", "\'", $string);
        $string = self::removeLineBreaks($string);
        $string = str_replace("<", "\<", $string);
        return $string;
    }

    /**
     * Remove all line breaks from a string
     * @param string $string
     * @return string
     */
    public static function removeLineBreaks($string)
    {
        $string = str_replace("\r\n", " ", $string);
        $string = str_replace("\r", " ", $string);
        $string = str_replace("\n", " ", $string);
        return $string;
    }

    /**
     * Truncate a string
     *
     * @param string $string
     * @param int $length
     * @param string $position of either end, space or middle
     * @return string
     */
    public static function truncateString($string, $length, $position = 'end')
    {
        // if the string is less than the length of the string, return it in total
        if (strlen($string) <= $length)
        {
            return $string;
        }

        switch ($position)
        {
        case 'end':
            $string = substr($string, 0, $length) . '... ';
            break;
        case 'space':
            
            // find the first space that is before the string
            $spacepos = strpos($string, ' ', $length - 5);
            
            if ($spacepos === false)
            {
                $spacepos = $length - 3;
            }
            
            $string = substr($string, 0, $spacepos) . '...';
            
            break;
        case 'middle':
            $start = substr($string, 0, $length - 13);
            $end = substr($string, -10);
            $string = "$start...$end";
            break;
        }

        return $string;
    }

    /**
     * Force a string into A-Z,a-z,_ only
     *
     * @param string $string
     * @return string
     */
    public static function forceAlpha($string)
    {
        $str = str_replace(' ', '_', $string);
        $str = preg_replace('#[^\w_]#', '', $str);
        return $str;
    }

    /**
     * Identify a file extension and return it
     *
     * @param string $filename
     * @return string
     */
    public static function getFileExtension($filename)
    {
        return substr(strrchr($filename, '.'), 1);
    }

    /**
     * http://www.linuxjournal.com/article/9585
     * Validate an email
     */
    public static function checkEmail($email)
    {
        $atIndex = strrpos($email, "@");

        if (strpos($email, '@') === false)
            return "No @ character found in $email.";

        $domain = substr($email, $atIndex + 1);

        $local = substr($email, 0, $atIndex);

        $localLen = strlen($local);
        $domainLen = strlen($domain);
        if ($localLen < 1 || $localLen > 64)
        {
            // local part length exceeded
            return 'The length of the local part of the email was either to small, or to long';
        }
        else if ($domainLen < 1 || $domainLen > 255)
        {
            // domain part length exceeded
            return 'The length of the domain part of the address was either to small, or to long';
        }
        else if ($local[0] == '.' || $local[$localLen - 1] == '.')
        {
            // local part starts or ends with '.'
            return 'The local part of the eamil starts or ends with a "."';
        }
        else if (preg_match('/\\.\\./', $local))
        {
            // local part has two consecutive dots
            return 'The local part has two consecutive dots inside it';
        }
        else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain))
        {
            // character not valid in domain part
            return "There was an invalid character in the domain part of the email address";
        }
        else if (preg_match('/\\.\\./', $domain))
        {
            // domain part has two consecutive dots
            return 'The domain part has two consecutive dots inside it';
        }
        else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\", "", $local)))
        {
            // character not valid in local part unless 
            // local part is quoted
            if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\", "", $local)))
            {
                return "There was an invalid character in the local part of the email address, that was not quoted";
            }
        }
        if (!(checkdnsrr($domain, "MX") || checkdnsrr($domain, "A")))
        {
            // domain not found in DNS
            return "There was no valid DNS lookup found for $email - please check you spelt it correctly.";
        }

        return true;
    }

    /**
     * Generate a random id
     *
     * @return unknown
     */
    public static function getRandomId()
    {
        return md5(uniqid(rand(), true));
    }


    /**
     *  Turn the results of preg_replace_callback with a url
     * @param array $matches
     * @return string
     */
    private static function stringToUrl($matches)
    {
        $url = $matches[1];
        $url = str_replace("\"", "&quot", $url);
        $url = str_replace(" ", "%20", $url);
        $text = self::truncateString($matches[2], 30);
        return "<a href=\"$url\" target=\"_blank\">$text</a>";
    }

    /**
     * Do both the explode and filter in a single command - for convenience
     * @param string $seperator 
     * @param string $string 
     * @param string $f 
     * @return filtered array
     */
    public static function explodeAndFilter($seperator, $string, $f = 'strlen')
    {
        return array_filter(explode($seperator, $string), $f);
    }

    public static function UTF8ord($c)
    {
        $len = strlen($c);
        $index = 0;
        $bytes = 0;

        $h = ord($c{$index});

        if ($h <= 0x7F)
        {
            $bytes = 1;
            return $h;
        }
        else if ($h < 0xC2)
        {
            return false;
        }
        else if ($h <= 0xDF && $index < $len - 1)
        {
            $bytes = 2;
            return ($h & 0x1F) << 6 | (ord($c{$index + 1}) & 0x3F);
        }
        else if ($h <= 0xEF && $index < $len - 2)
        {
            $bytes = 3;
            return ($h & 0x0F) << 12 | (ord($c{$index + 1}) & 0x3F) << 6
                | (ord($c{$index + 2}) & 0x3F);
        }
        else if ($h <= 0xF4 && $index < $len - 3)
        {
            $bytes = 4;
            return ($h & 0x0F) << 18 | (ord($c{$index + 1}) & 0x3F) << 12
                | (ord($c{$index + 2}) & 0x3F) << 6
                | (ord($c{$index + 3}) & 0x3F);
        }
        else
        {
            return false;
        }
    }

}

/**
 * A basic map of extensions to mimetypes
 */
class MimeType
{

    static private $mimeTypes = array('gif' => 'image/gif',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'bmp' => 'image/x-ms-bmp',
        'doc' => 'application/msword',
        'xls' => 'application/vnd.ms-excel',
        'csv' => 'text/csv',
        'zip' => 'application/zip',
        'mp3' => 'audio/mpeg',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pdf' => 'application/pdf',
        'text' => 'text/plain',
        'txt' => 'text/plain',
        'html' => 'text/html',
        'htm' => 'text/html',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'xml' => 'text/xml',
        'css' => 'text/css',
        'rss' => 'application/rss+xml',
        'epub' => 'application/epub+zip',
        'lit' => 'application/x-ms-reader',
        'mobi' => 'application/x-mobipocket-ebook',
        'prc' => 'application/x-mobipocket',
        'php' => 'application/x-httpd-php'
    );

    /**
     * Get a mimetype from a filename
     * 
     * @param string $filename
     * @return text 
     */
    static public function getMimeType($filename)
    {
        $extension = utils::getFileExtension($filename);

        if (!isset(self::$mimeTypes[$extension]))
        {
            throw new error("A file type for $extension has not been set.");
        }

        return self::$mimeTypes[$extension];
    }
}
