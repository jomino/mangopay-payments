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

}