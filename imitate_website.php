<?php
    error_reporting(0);
    header('Access-Control-Allow-Origin: *');
    header('Content-type: application/json; charset=utf-8');
    
    define('DATA_PATH', str_replace('\\', '/', realpath(dirname(__FILE__).'/')).'/imitate_website_data/');
    if (!is_dir(DATA_PATH)) {
        mkdir(DATA_PATH);
    }
    
    class PHPZip {
        private $ctrl_dir     = array();
        private $datasec      = array();
        var $fileList = array();
        public function visitFile($path)
        {
            global $fileList;
            $path = str_replace("\\", "/", $path);
            $fdir = dir($path);
        
            while(($file = $fdir->read()) !== false)
            {
                if($file == '.' || $file == '..'){ continue; }
        
                $pathSub    = preg_replace("*/{2,}*", "/", $path."/".$file);  // 替换多个反斜杠
                $fileList[] = is_dir($pathSub) ? $pathSub."/" : $pathSub;
                if(is_dir($pathSub)){ $this->visitFile($pathSub); }
            }
            $fdir->close();
            return $fileList;
        }
        
        
        private function unix2DosTime($unixtime = 0)
        {
            $timearray = ($unixtime == 0) ? getdate() : getdate($unixtime);
    
            if($timearray['year'] < 1980)
            {
                $timearray['year']    = 1980;
                $timearray['mon']     = 1;
                $timearray['mday']    = 1;
                $timearray['hours']   = 0;
                $timearray['minutes'] = 0;
                $timearray['seconds'] = 0;
            }
    
            return (  ($timearray['year'] - 1980) << 25)
                    | ($timearray['mon'] << 21)
                    | ($timearray['mday'] << 16)
                    | ($timearray['hours'] << 11)
                    | ($timearray['minutes'] << 5)
                    | ($timearray['seconds'] >> 1);
        }
        
        
        var $old_offset = 0;
        private function addFile($data, $filename, $time = 0)
        {
            $filename = str_replace('\\', '/', $filename);
    
            $dtime    = dechex($this->unix2DosTime($time));
            $hexdtime = '\x' . $dtime[6] . $dtime[7]
                      . '\x' . $dtime[4] . $dtime[5]
                      . '\x' . $dtime[2] . $dtime[3]
                      . '\x' . $dtime[0] . $dtime[1];
            eval('$hexdtime = "' . $hexdtime . '";');
    
            $fr       = "\x50\x4b\x03\x04";
            $fr      .= "\x14\x00";
            $fr      .= "\x00\x00";
            $fr      .= "\x08\x00";
            $fr      .= $hexdtime;
            $unc_len  = strlen($data);
            $crc      = crc32($data);
            $zdata    = gzcompress($data);
            $c_len    = strlen($zdata);
            $zdata    = substr(substr($zdata, 0, strlen($zdata) - 4), 2);
            $fr      .= pack('V', $crc);
            $fr      .= pack('V', $c_len);
            $fr      .= pack('V', $unc_len);
            $fr      .= pack('v', strlen($filename));
            $fr      .= pack('v', 0);
            $fr      .= $filename;
    
            $fr      .= $zdata;
    
            $fr      .= pack('V', $crc);
            $fr      .= pack('V', $c_len);
            $fr      .= pack('V', $unc_len);
    
            $this->datasec[] = $fr;
            $new_offset      = strlen(implode('', $this->datasec));
    
            $cdrec  = "\x50\x4b\x01\x02";
            $cdrec .= "\x00\x00";
            $cdrec .= "\x14\x00";
            $cdrec .= "\x00\x00";
            $cdrec .= "\x08\x00";
            $cdrec .= $hexdtime;
            $cdrec .= pack('V', $crc);
            $cdrec .= pack('V', $c_len);
            $cdrec .= pack('V', $unc_len);
            $cdrec .= pack('v', strlen($filename) );
            $cdrec .= pack('v', 0 );
            $cdrec .= pack('v', 0 );
            $cdrec .= pack('v', 0 );
            $cdrec .= pack('v', 0 );
            $cdrec .= pack('V', 32 );
    
            $cdrec .= pack('V', $this->old_offset );
            $this->old_offset = $new_offset;
    
            $cdrec .= $filename;
            $this->ctrl_dir[] = $cdrec;
        }
        
        var $eof_ctrl_dir = "\x50\x4b\x05\x06\x00\x00\x00\x00";
        private function file()
        {
            $data    = implode('', $this->datasec);
            $ctrldir = implode('', $this->ctrl_dir);
    
            return   $data
                   . $ctrldir
                   . $this->eof_ctrl_dir
                   . pack('v', sizeof($this->ctrl_dir))
                   . pack('v', sizeof($this->ctrl_dir))
                   . pack('V', strlen($ctrldir))
                   . pack('V', strlen($data))
                   . "\x00\x00";
        }
        
        public function Zip($dir, $saveName)
        {
            if(@!function_exists('gzcompress')){ return; }
    
            ob_end_clean();
            $filelist = $this->visitFile($dir);
            if(count($filelist) == 0){ return; }
    
            foreach($filelist as $file)
            {
                if(!file_exists($file) || !is_file($file)){ continue; }
                
                $fd       = fopen($file, "rb");
                $content  = @fread($fd, filesize($file));
                fclose($fd);

                // 1.删除$dir的字符(./folder/file.txt删除./folder/)
                // 2.如果存在/就删除(/file.txt删除/)
                $file = substr($file, strlen($dir));
                if(substr($file, 0, 1) == "\\" || substr($file, 0, 1) == "/"){ $file = substr($file, 1); }
                
                $this->addFile($content, $file);
            }
            $out = $this->file();
    
            $fp = fopen($saveName, "wb");
            fwrite($fp, $out, strlen($out));
            fclose($fp);
        }
    }
    
    function delete_dir($path) {
        if (is_dir($path)) {
            $file = scandir($path);
            if (count($file) > 2) {
                foreach ($file as $value) {
                    if ($value != '.' && $value != '..') {
                        if (is_dir($path.$value)) {
                            delete_dir($path.$value.'/');
                        } else {
                            unlink($path.$value);
                        }
                    }
                }
            }
        }
        return rmdir($path);
    }
    
    function is_https() {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off') {
            return true;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            return true;
        } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) != 'off') {
            return true;
        }
        return false;
    }
    
    function return_result($information, $state=200) {
    	$result = array(
    		'state'=>$state,
    		'information'=>$information
    	);
    	exit(stripslashes(json_encode($result, JSON_UNESCAPED_UNICODE)));
    }
    
    $url = $_GET['url'];
    if (empty($url)) {
    	return_result('参数错误', 100);
    }
    exec('wget -c -r -p -P '.DATA_PATH.' -np --no-check-certificate -k '.$url);
    if (is_https()) {
        $local_url = 'https://';
    } else {
        $local_url = 'http://';
    }
    $local_url = $local_url.$_SERVER['HTTP_HOST'].dirname($_SERVER['REQUEST_URI']);
    if (strstr($url, 'http://') or strstr($url, 'https://')) {
        $local_url = dirname($local_url);
    } else {
        if (substr($url, -1) == '/') {
            $url = rtrim($url, '/');
        }
    }
    $local_url = $local_url.'/imitate_website_data/';
    $imitate_domain = parse_url($url)['host'];
    if (empty($imitate_domain)) {
        $imitate_domain = $url;
    }
    $new_imitate_domain = $imitate_domain.'-'.time();
    rename(DATA_PATH.$imitate_domain, DATA_PATH.$new_imitate_domain);
    $zip = new PHPZip();
    $zip->Zip(DATA_PATH.$new_imitate_domain, DATA_PATH.$new_imitate_domain.'.zip');
    delete_dir(DATA_PATH.$new_imitate_domain.'/');
    $information = array(
        // 'preview_url'=>$local_url.$new_imitate_domain.'/',
    	'download_url'=>$local_url.$new_imitate_domain.'.zip'
    );
    return_result($information);
?>
