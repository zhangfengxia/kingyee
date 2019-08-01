<?php

use Illuminate\Support\Facades\Redirect;
/**
 * 医脉通服务类
 */
class Medlive
{


    public function __construct()
    {
    }

    const MEDLIVE_GET_USER_INFO_API = 'http://api.medlive.cn/user/get_user_info.php';//获取用户信息的接口
    const MEDLIVE_USER_LOGIN_CHECK_API = 'http://api.medlive.cn/user/user_login_check.php';
    const MEDLIVE_USER_FORCE_LOGIN_API = 'http://www.medlive.cn/force_login.php?';//用户强制登录入口
    const MEDLIVE_USER_SHARE_OTHER_API = 'http://activity.medlive.cn/invitation/weixin?from=mr_task';//用户邀请的任务的链接
    const MEDLIVE_USER_AUTH_API = 'http://m.medlive.cn/certify?url=';//用户认证任务的跳转链接
    const ALL_TASK_MAP = [
        'auth' => self::MEDLIVE_USER_AUTH_API,
        'share' => self::MEDLIVE_USER_SHARE_OTHER_API,
        'force_login' => self::MEDLIVE_USER_FORCE_LOGIN_API,
        'get_user_info' => self::MEDLIVE_GET_USER_INFO_API,
    ];



    /**
     * 用户ID加密
     */
    protected function _hashUser($user, $downloadKey = '')
    {
        if (empty($user)) {
            return '0';
        }
        $crc = intval(sprintf('%u', crc32($downloadKey . "asdfwrew.USER_SEED")));
        $hash = $crc - $user;
        $hash2 = sprintf('%u', crc32($hash . 'werhhs.USER_SEED2'));
        $k1 = substr($hash2, 0, 3);
        $k2 = substr($hash2, -2);
        return $k1 . $hash . $k2;
    }

    /**
     * 与接口通信工具
     *
     * @param string $url
     * @param array $data
     * @param string $method
     * @param int $timeout
     * @return array
     */
    public static function _requestApiByCurl($sUrl, $aData, $sMethod = 'post', $iTimeout = 0)
    {
        $sMethod = strtolower($sMethod);
        $ch = curl_init();
        if ($sMethod == 'get') {
            $sUrl .= '?' . http_build_query($aData);
        }
        curl_setopt($ch, CURLOPT_URL, $sUrl);
        if ($sMethod == 'post') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $aData);
        }
        $iTimeout = intval($iTimeout);
        if ($iTimeout) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $iTimeout);
        }
        ob_start();
        curl_exec($ch);
        $sOut = ob_get_clean();
        curl_close($ch);

        return json_decode($sOut, true);
    }

    /**
     * 通过医脉通id获取hashid与checkid
     * */
    public static function _getHashAndCheckById($uid, $sEnv = 'linux')
    {
        if ($sEnv == 'linux') {
            $sHashId = self::getHashidOrCheckid($uid, self::HASH_EXT_KEY_HASHID);
            $sCheckId = self::getHashidOrCheckid($uid, self::HASH_EXT_KEY_CHECKID);
        } else {
            $sHashId = self::getWinHashidOrCheckid($uid, self::HASH_EXT_KEY_HASHID);
            $sCheckId = self::getWinHashidOrCheckid($uid, self::HASH_EXT_KEY_CHECKID);
        }
        return array('hashid' => $sHashId, 'checkid' => $sCheckId);
    }

    /**
     * 通过接口获取当前用户id用户信息
     */
    public static function _getUserinfo($uid, $sEnv = 'linux')
    {
        $url = self::MEDLIVE_GET_USER_INFO_API;
        $aData = self::_getHashAndCheckById($uid, $sEnv);
        $result = self::_requestApiByCurl($url, $aData, 'get');
        return $result;
    }

    /**phpdoc风格，直接生成api
     * 用户强制登录医脉通
     * @param int $uid 医脉通id
     * @param string $redirect_uri 回调地址
     * @return string url
     * */
    public static function _doForceLogin($uid, $redirect_uri)
    {
        $aInfo = self::_getHashAndCheckById($uid);
        $aInfo['url'] = urlencode($redirect_uri);
        return Redirect::to(self::MEDLIVE_USER_FORCE_LOGIN_API . 'hashid=' . $aInfo['hashid'] . '&checkid=' . $aInfo['checkid'] . '&url=' . urlencode($redirect_uri));
    }

    public static function _getUserinfoByhash($sHashId, $sCheckId)
    {
        $url = self::MEDLIVE_GET_USER_INFO_API;
        $result = self::_requestApiByCurl($url, array('hashid' => $sHashId, 'checkid' => $sCheckId), 'get');
        return $result;
    }

    public static function _getUserid($user_name, $password)
    {
        $url = self::MEDLIVE_USER_LOGIN_CHECK_API;
        $result = self::_requestApiByCurl($url, array('user_name' => $user_name, 'password' => $password), 'POST');
        return $result;
    }

    //获取hashid和checkid 通过不同的key值
    public static function getHashidAndCheckid($uid)
    {
        $sHashId = self::getHashidOrCheckid($uid, self::HASH_EXT_KEY_HASHID);
        $sCheckId = self::getHashidOrCheckid($uid, self::HASH_EXT_KEY_CHECKID);
        return array($sHashId, $sCheckId);
    }

    //Linux系统 获取hashid和checkid 通过不同的key值
    public static function getHashidOrCheckid($user, $downloadKey = '')
    {
        if (empty($user)) {
            return '0';
        }
        $crc = intval(sprintf('%u', crc32($downloadKey . "asdfwrew.USER_SEED")));
        $hash = $crc - $user;
        $hash2 = sprintf('%u', crc32($hash . 'werhhs.USER_SEED2'));
        $k1 = substr($hash2, 0, 3);
        $k2 = substr($hash2, -2);
        return $k1 . $hash . $k2;
    }

    //windows系统 获取hashid和checkid 通过不同的key值 31730733268915, 332380283569776
    public static function getWinHashidOrCheckid($user, $downloadKey = '')
    {
        if (empty($user)) {
            return '0';
        }
        $crc = intval(sprintf('%u', crc32($downloadKey . "asdfwrew.USER_SEED")));
        $hashNum = 3949085137;
        if ($downloadKey == 'dasfgfsdbz') {
            $hashNum = 308446624;
        } else if ($downloadKey == 'hiewrsbzxc') {
            $hashNum = 3803949632;
        }
        $hash = $crc - $user;
        $hash = $hashNum - $user;
        $hash2 = sprintf('%u', crc32($hash . 'werhhs.USER_SEED2'));
        $k1 = substr($hash2, 0, 3);
        $k2 = substr($hash2, -2);
        return $k1 . $hash . $k2;
    }


}