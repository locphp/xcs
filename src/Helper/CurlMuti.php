<?php

namespace Xcs\Helper;

class CurlMuti {

    /**
     * @param $urls
     * @param string $data
     * @param array $httphead
     * @param string $charset
     * @return array
     */
    function send($urls, $data = '', $httphead = array(), $charset = 'UTF-8') {
        //创建多个curl语柄
        $mhandle = curl_multi_init();

        foreach ($urls as $key => $url) {
            $conn[$key] = curl_init($url);
            if (false !== stripos($url, "https://")) {
                curl_setopt($conn[$key], CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($conn[$key], CURLOPT_SSL_VERIFYHOST, false);
            }
            //设置超时时间
            curl_setopt($conn[$key], CURLOPT_TIMEOUT, 30);
            curl_setopt($conn[$key], CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
            //HTTp定向级别
            curl_setopt($conn[$key], CURLOPT_MAXREDIRS, 7);
            //这里不要header，加块效率
            curl_setopt($conn[$key], CURLOPT_HEADER, 0);
            // 302 redirect
            curl_setopt($conn[$key], CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($conn[$key], CURLOPT_RETURNTRANSFER, 1);
            $heads = array();
            foreach ($httphead as $k => $v) {
                $heads[] = $k . ': ' . $v;
            }
            unset($k);
            curl_setopt($conn[$key], CURLOPT_HTTPHEADER, $heads);
            /* 设置请求头部 */
            if (!empty($data)) {
                if (is_array($data)) {
                    if (isset($data['__file'])) {
                        $data[$data['__file']] = class_exists('\CURLFile', false) ? new \CURLFile($data[$data['__file']]) : '@' . $data[$data['__file']];
                        unset($data['__file']);
                        $poststr = $data;
                    } else {
                        $poststr = http_build_query($data);
                    }
                } else {
                    $poststr = trim($data);
                    $httphead['Content-Length'] = strlen($poststr);
                }
                curl_setopt($conn[$key], CURLOPT_POST, true);
                curl_setopt($conn[$key], CURLOPT_POSTFIELDS, $poststr);
            } else {
                curl_setopt($conn[$key], CURLOPT_HTTPGET, true);
            }
            curl_multi_add_handle($mhandle, $conn[$key]);
        }

        // 执行批处理句柄
        $active = null;
        do {
            //当无数据，active=true
            $mrc = curl_multi_exec($mhandle, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);//当正在接受数据时

        //当无数据时或请求暂停时，active=true
        while ($active && $mrc == CURLM_OK) {
            do {
                $mrc = curl_multi_exec($mhandle, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }

        $res = array();
        foreach ($urls as $key => $url) {
            if (0 != curl_errno($conn[$key])) {
                $res[$key]['http_code'] = 0;
                $res[$key]['body'] = null;
            } else {
                //获取Http code
                $res[$key]['http_code'] = curl_getinfo($conn[$key], CURLINFO_HTTP_CODE);
                //获得返回body信息
                $http_body = curl_multi_getcontent($conn[$key]);
                if ('UTF-8' != $charset) {
                    $http_body = self::convert_encode(strtoupper($charset), 'UTF-8', $http_body);
                }
                $res[$key]['body'] = $http_body;
            }
            //获取返回http信息
            $conn[$key]['http_info'] = curl_getinfo($conn[$key]);
            //关闭语柄
            curl_close($conn[$key]);
            //释放资源
            curl_multi_remove_handle($mhandle, $conn[$key]);
        }

        curl_multi_close($mhandle);
        return $res;
    }

    /**
     * @param $in
     * @param $out
     * @param $string
     * @return mixed|string
     */
    private static function convert_encode($in, $out, $string) { // string change charset return string
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($string, $out, $in);
        } elseif (function_exists('iconv')) {
            return iconv($in, $out . '//IGNORE', $string);
        } else {
            return $string;
        }
    }
}