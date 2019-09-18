<?php

namespace Util;

class Tools
{

    public static function cookieGetValue(string $cookie)
    {
        $key_value = explode('=',$cookie);
        return $key_value[1];
    }

    public static function queryGetValues(string $params)
    {
        $raw = [];
        $values = explode('&',$params);
        foreach ($values as $value) {
            $param = explode('=',$value);
            $raw[$param[0]] = urldecode($param[1]);
        }
        return $raw;
    }

    public static function obfuscGetValues(string $params)
    {
        $sep = '...';
        if(strpos($params,'@')){
            $sep .= '@...';
        }
        return substr($params,0,3).$sep.substr($params,-3);
    }

    public static function getTLD(string $domain)
    {
        $sep = '.';
        $d_parts = explode($sep,$domain);
        return $d_parts[sizeof($d_parts)-2].$sep.$d_parts[sizeof($d_parts)-1];
    }

    public static function intGen(int $length)
    {
        $long_num = '';
        for ($i=0; $i < $length ; $i++) { 
            $long_num .= mt_rand(1,9);
        }
        return (int) $long_num;
    }

    public static function getExpiryYears()
    {
        $years = [];
        $this_year = (\Carbon\Carbon::now())->year;
        $count_year = 10;
        for ($i=0; $i < $count_year; $i++) { 
            $years[] = [
                'name' => $this_year + $i,
                'value' => $this_year - 2000
            ];
        }
        return $years;
    }

}