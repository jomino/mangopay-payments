<?php

namespace Util;

class Tools
{

    public static function cookieGetValue($cookie)
    {
        $key_value = explode('=',$cookie);
        return $key_value[1];
    }

    public static function queryGetValues($params)
    {
        $raw = [];
        $values = explode('&',$params);
        foreach ($values as $value) {
            $param = explode('=',$value);
            $raw[$param[0]] = urldecode($param[1]);
        }
        return $raw;
    }

}