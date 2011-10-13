<?php

/**
 * Debug class 
 *
 */
class Debug
{
    private static $lines = '';
    private static $fd = null;
    private static $isOpen = false;
    private static $buffer = true;
    private static $showMemory = false;
    private static $showExplain = false;
    
    /** Show we show CPU usage? */
    private static $showCpu = false;

    /** First utime for total */
    private static $firstUtime = 0;

    /** Last utime for delta */
    private static $lastUtime = 0;
  
    private static $db = null;
    
    /** set some statistic gathering variables **/
    private static $profiledPoints = array();
    private static $profilePoint = array();
    private static $startTime = 0;

    public static function setDB($db)
    {
        self::$db = $db;
    }
   
    public static function setExplain($bool)
    {
        self::$showExplain = $bool;
    }

    public static function showExplain()
    {
        return self::$showExplain;
    }

    public static function setBuffer($bool)
    {
        self::$buffer = $bool;

    }

    public static function getBuffer()
    {
        if (self::showExplain() && self::$db !== null)
        {
            self::$db->explainAll();
        }
        self::writeLine(self::getStatistics());
        return self::$lines;
    }


    public static function setMemory($bool)
    {
        self::$showMemory = $bool;
    }
    
   
    /**
     * Set a profiling point so that overall statistics can be used 
     * @param string $name 
     */
    public static function startProfile($name)
    {
        // if we are not profileing the cpu (live machines etc) then return
        if (!self::$showCpu)
            return;

        self::$profilePoint[$name] = array(self::getCpuUserTime(),microtime(true));
        self::writeLine("DBG: starting profile for $name");
    }
    
    
    /**
     * Take a stop command, and set the statistics 
     * @param string $name 
     */
    public static function stopProfile($name)
    {
        // if we are not profileing the cpu (live machines etc) then return
        if (!self::$showCpu)
            return;

        // check that we have a point
        if (!isset(self::$profilePoint[$name]))
            self::writeLine("DBG: Stop profile called for $name without a corresponding startProfile() being used");

        // calculate the delta
        $cpu = self::getCpuUserTime();
        $cpudelta = $cpu - self::$profilePoint[$name][0];

        $time = microtime(true);
        $timedelta = round($time - self::$profilePoint[$name][1],3) * 1000;

        $line = "$name took {$cpudelta}ms cpu time and {$timedelta}ms real time";

        // store the point for a summary later
        self::$profiledPoints[] = $line;

        // print it in place as well
        self::writeLine($line);
    }
    
    
    /**
     * Sets whether the CPU lines should be shown
     *
     * This is disabled by default
     *
     * @param boolean $use true to enable, false to disable
     */
    public static function setCpuUse($use)
    {
        if ($use)
        { 
            self::$firstUtime = self::$lastUtime = self::getCpuUserTime();
        }
        self::$showCpu = $use;
    }



    /**
     * Open a debug session, writing to the specified file
     *
     * @param string $path
     */
    public static function open($path = null, $buffer = true)
    {
        // if we have a null, we output to std output by default
        if ($path === null)
            $path = 'php://stdout';
        
        // set the state of the buffer
        self::$buffer = $buffer;

        // check that we're not trying to open a directory
        if (is_dir($path))
            throw new Error("You can not open a directory!");

        // do we have a file?  If so, append, otherwise open a new one    
        if (file_exists($path))
            $fd = fopen($path,'a');
        else 
            $fd = fopen($path,'w');
        
        // have we had any success?
        if ($fd === false)
            throw new Error("Could not open $path");
        
        // remember the open  
        self::$fd = $fd;    

        // set that we're open
        self::$isOpen = true;
        
        // write a few lines
        self::writeLine(str_repeat('-',20));
        self::writeLine('DBG: Opened debug log');
        
        // auto close it
        register_shutdown_function(array('Debug', 'close'));
               
    }
    

    /**
     * Store a line for the log file
     * The output is buffered.
     * Note: If the session is not opened, this is a no-op routine.
     *
     * @param string $line
     * @param string $type
     */
    public static function writeLine($line)
    {
        // if we're not open, return
        if (!self::$isOpen)
            return;

        // remove all control chracters
        $line = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f-\xa0]/', '', $line);  
                    
        // get the micro time
        list($microtime, $second) = explode(' ',microtime());

        // set the current time
        $time = date("Y/m/d H:i:s",intval($second)) . substr($microtime, 1, 4);

        // if we want a memory print out, we can show it here.
        if (self::$showMemory && function_exists('memory_get_usage'))
        {
            $current = number_format(memory_get_usage()).'b';

            if (!isset($timestamp))
                $timestamp = '';

            $line = "Memory: $current: $line";
        }
        
        // If we want a CPU print out, we can show it here.
        if (self::$showCpu)
        {
            $utime = self::getCpuUserTime();
            $delta = $utime - self::$lastUtime;
            self::$lastUtime = $utime;

            // in order to be able to read down a column easily, we add a pad character
            if (strlen($delta) === 0)
                $delta = "0$delta";

            $line = "CPU: {$delta}ms: $line";
        }

        // format the line
        $line = "$time: $line\n";
        
        if (self::$buffer)
        {
            // add to the output buffering
            self::$lines .= $line;
        }
        else
            self::write($line);
    }
    
    
    /**
     * close the log file and write the buffer to the file system
     */
    public static function close()
    {
        // if we're not open, we return
        if (!self::$isOpen)
            return;

        if (self::showExplain() && self::$db !== null)
        {
            self::writeLine("SQL:\n".self::$db->explainAll());
        }

        // add a closing line
        self::writeLine(self::getStatistics());
        self::writeLine('DBG: closed debug stream');   
        self::writeLine(str_repeat('-',20));
        
        // write it
        self::write();
    }
    
    
    /**
     * Write the buffer to the log file
     */
    public static function write($line='')
    {
         
        if (self::$buffer)
        {
            // write the buffer
            fwrite(self::$fd, self::$lines);
            // close it
            fclose(self::$fd);
        }
        else
        {
            fwrite(self::$fd, $line);
            fflush(self::$fd);
        }
        
    }
    
    
    /**
     * Return the current state of the debugging
     *
     * @return boolean
     */
    public static function isOpen()
    {
        return self::$isOpen;
    }


    /**
     * Add the memory peak useage to the site 
     */
    public static function getPeakMemoryUsage()
    {
        // if we can get the peak useage, we can show that as well.
        if (function_exists('memory_get_peak_usage') && self::$showMemory)
        {
            return number_format(memory_get_peak_usage());
        }
        return 0;
    }
    
    
    /**
     * Returns the amount of CPU user time used since the process started.
     *
     * @return int the time in milliseconds, or zero if posix_times() is unavailable
     */
    public static function getCpuUserTime()
    {
        if (!function_exists('posix_times'))
        {
            return 0;
        }

        // N.B. we assume that clock_t is 100th of a sec
        $times = posix_times();
        return $times['utime'] * 10;
    }
    
    
    /**
     * get the cpu, time, and memory use for the page 
     * @param bool $explain 
     * @return string
     */
    private static function getStatistics()
    {
        $response = "\n";

        // get any profile points
        if (self::$showCpu)
        {
            if (count(self::$profiledPoints) > 0)
            {
                $response .= join("\n",self::$profiledPoints);
                $response .= "\n";
            }
            // get the total cpu time
            $utime = self::getCpuUserTime();
            $total = $utime - self::$firstUtime;
            $response .= "Total CPU usage - {$total}ms\n";
        }
        
        // get the peak memory use
        $response .= "Peak memory use ".self::getPeakMemoryUsage() ."b\n";

        // get the total time
        $generation_time = round((microtime(true) - self::$startTime),3);

        $response .= "Total time {$generation_time}s\n";

        return $response;
    }
}
