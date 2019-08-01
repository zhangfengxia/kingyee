<?php
/**
 * Created by PhpStorm.
 * User: wangxiaomei
 * Date: 2019/7/8
 * Time: 17:21
 */

namespace App\Services;

use App\Http\Middleware\BindRelative;
use App\Models\MR_Remote\OpenIdBind;
use App\Models\Task\TaskUser;
use Illuminate\Support\Facades\Input;

/**
 * 医脉通服务类
 */
class MedliveService extends BaseService
{

    protected $MRService;

    public function __construct(MRService $MRService)
    {
        $this->MRService = $MRService;
    }

    const HASH_EXT_KEY_HASHID = 'dasfgfsdbz';
    const HASH_EXT_KEY_CHECKID = 'hiewrsbzxc';
    const MEDLIVE_GET_USER_INFO_API = 'http://api.medlive.cn/user/get_user_info.php';
    const MEDLIVE_USER_LOGIN_CHECK_API = 'http://api.medlive.cn/user/user_login_check.php';
    const MEDLIVE_USER_FORCE_LOGIN_API = 'http://www.medlive.cn/force_login.php?';
    const MEDLIVE_GOLD_DEAL_TYPE_ID = 1293; //医脉通麦粒交易类型id
    const MEDLIVE_GOLD_DEAL_TOKEN = 'KGKCCJXXQQOCWEIC';//医脉通麦粒交易TOKEN
    const MEDLIVE_GIFT_CATE_LIST = 'http://gift.medlive.cn/api/gift_cate_list_with_item_v2.do';//TODO:医脉通礼品列表接口 正式
    const MEDLIVE_GOLD_RECORD = 'http://gift.medlive.cn/api/pay_record_gold_new_list.do';//todo:麦粒明细接口
    const TASK_TYPE = 3;
    const ALL_TASK_MAP = [
        'auth' => 'http://m.medlive.cn/certify?url=',//用户认证任务的跳转链接
        'share' => 'http://activity.medlive.cn/invitation/weixin?from=mr_task'//用户邀请的任务的链接
    ];
    const  AUTH_RESOURCE = 'new_mr_wx';

    /**
     * 获取礼品列表
     ***/
    public function getGiftCateList()
    {
        $result = data_get(self::sendRequest('GET', self::MEDLIVE_GIFT_CATE_LIST), 'data_list');
        return $result;
    }

    /**
     * 获取用户麦粒明细信息
     * @param int $iMeduid 医脉通id
     * @param int $time 开始日期
     ***/
    public function getPayRecordGoldList($iMeduid, $time)
    {
        $result = data_get(self::sendRequest('GET', self::MEDLIVE_GOLD_RECORD, ['userid' => $iMeduid, 'from_date' => $time]), 'data_list');
        return $result;
    }


    /**
     *添加或者更新用户信息
     * @param int $iMeduid 医脉通id
     * @param array $aMedliveUserInfo 医脉通用户信息
     * @param array $aWxUserInfo 微信用户信息
     * @param model $model 模型类
     * @param int $type 活动类型
     */
    public function getOrganizeUserInfo($iMeduid, $aMedliveUserInfo, $aWxUserInfo, $model, $type = null)
    {
        $aWxUserInfo['user_id'] = $iMeduid;
        $aWxUserInfo['ymtname'] = $aMedliveUserInfo['data']['name'];
        $aWxUserInfo['ymtnick'] = $aMedliveUserInfo['data']['nick'];
        $aWxUserInfo['user_profile'] = $aMedliveUserInfo['data']['thumb'];
        $aWxUserInfo['reg_time'] = $aMedliveUserInfo['data']['reg_time'];
        if ($type && $type == BaseService::TASK_CENTER_ACTIVITY_TYPE) {
            //任务中心
            $aWxUserInfo['maili'] = $aMedliveUserInfo['data']['maili'];
        } elseif ($type && $type == BaseService::OLD_AND_NEW_ACTIVITY_TYPE) {
            //老带新
            !empty($aMedliveUserInfo['data']['company']) ? $aWxUserInfo['user_hospital'] = end($aMedliveUserInfo['data']['company']) : $aWxUserInfo['user_hospital'] = '';
            !empty($aMedliveUserInfo['data']['profession']) ? $aWxUserInfo['user_depart'] = end($aMedliveUserInfo['data']['profession']) : $aWxUserInfo['user_depart'] = '';
            $aWxUserInfo['user_carclass'] = $aMedliveUserInfo['data']['carclass'];
        }
        if (empty($aMedliveUserInfo['data']['is_certifing']) || (!empty($aMedliveUserInfo['data']['is_certifing']) && $aMedliveUserInfo['data']['is_certifing'] == 'N')) {
            $aWxUserInfo['is_certifing'] = 0;
        } else {
            $aWxUserInfo['is_certifing'] = 1;
        }
        !empty($aMedliveUserInfo['data']['certify_flg']) ? $aWxUserInfo['certify_flg'] = 1 : $aWxUserInfo['certify_flg'] = 0;
        self::addOrUpdateUserInfo($aWxUserInfo, $model);
    }

    /**
     *添加或者更新用户信息
     * @param array $aUserInfo 用户信息
     * @param model $model 模型类
     */
    public function addOrUpdateUserInfo($aUserInfo, $model)
    {
        $oInfo = $model::getInfoBySel($aUserInfo['openid'], 'openid');
        if (empty($oInfo)) {
            return $model::addUserInfo($aUserInfo);
        } else {
            return $model::updateUserInfoByField($aUserInfo['openid'], 'openid', $aUserInfo);
        }
    }


    /**
     *医脉通强制登录
     * @param string $openid 微信openid
     */
    public function forceLogin($openid)
    {
        //强制登录
        $bIsLogin = Input::get('hashid');
        if (empty($bIsLogin)) {
            return self::_doForceLogin(session('medlive_id'), url()->full());
        }
    }


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
        return redirect(self::MEDLIVE_USER_FORCE_LOGIN_API . 'hashid=' . $aInfo['hashid'] . '&checkid=' . $aInfo['checkid'] . '&url=' . urlencode($redirect_uri));
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

    /**
     * 医脉通积分处理接口
     *参数说明:
     * @param action：执行的操作，积分处理为“dealScore”
     * @param score：分值。受积分规则控制的交易类别，可不传递此值
     * @param gold：麦粒值。受积分规则控制的交易类别，可不传递此值
     * @param uid：用户医脉通号
     * @param typeid：交易类别id，对应p2p_pay_type表的值
     * @param bizid：业务id (如评论记录的主键)
     ***/
    public static function getDealCommunicate($log_id, $medilveId, $gold)
    {
        $url = config('activity.api.communicate');
        $aData = array('action' => 'dealGold', 'gold' => $gold, 'uid' => $medilveId, 'typeid' => self::MEDLIVE_GOLD_DEAL_TYPE_ID, 'bizid' => $log_id, 'token' => self::MEDLIVE_GOLD_DEAL_TOKEN);
        $result = self::_requestApiByCurl($url, $aData, 'get');
        $res = data_get($result, 'data_list', false);
        !$res ? $msg = '执行失败,' . $result['err_msg'] : $msg = '执行成功';
        $baseurl = storage_path() . '/logs/';
        if (!file_exists($baseurl)) {
            mkdir($baseurl, 0775, true);
        }
        \Log::channel('maililog')->info("[" . '时间:' . date("Y-m-d H:i:s", time()) . '，邀请人医脉通id:' . $medilveId . ',增加麦粒值:' . $gold . '，交易类别typeid：' . self::MEDLIVE_GOLD_DEAL_TYPE_ID . ',业务bizid:' . $log_id . ',Token' . self::MEDLIVE_GOLD_DEAL_TOKEN . ',结果:' . $msg . "]\n");
        return $res;
    }

    //******aes/cbc/pkcs5padding/128加解密******
    public static function aesEncrypt($data, $iv = '', $key = '4lsLmywJLPMiWkLo')
    {
        $enc_iv = self::aesRandom(16);//随机生成16位iv
        if ($iv) {
            $enc_iv = $iv;
        }
        $method = 'AES-128-CBC';
        $encrypt = openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $enc_iv);
        return base64_encode($enc_iv . $encrypt);//iv拼密串之后base64
    }

    public static function aesDecrypt($data, $key = '4lsLmywJLPMiWkLo')
    {
        try {
            $data = base64_decode(rawurldecode($data)); // urlencode解码
            $iv = substr($data, 0, 16);    // 加密方式目前写死了AES-128-CBC，所以固定去前16位作为IV
            $data = substr($data, 16);     // 获取数据
            return openssl_decrypt($data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv); //使用openssl进行解密
        } catch (\Exception $exception) {
            // 处理解密失败的逻辑
        }
        return false;
    }

    public static function aesRandom($length, $chars = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ')
    {
        $hash = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $hash .= $chars[mt_rand(0, $max)];
        }
        return $hash;
    }

    /**
     * 获取医脉通用户需要完成的任务
     * @param int $iMedUid 医脉通id
     * @param string $sBackUrl 回调地址
     * @return array
     * */
    public function getMedTaskByMeduid($iMedUid, $sBackUrl)
    {
        return [
            'share_task' => self::MEDLIVE_SHARE_TASK,
            'auth_task' => self::MEDLIVE_AUTH_TASK . urlencode($sBackUrl),
        ];
    }

    public function getNotLoginMedTaskByMeduid($sBackUrl)
    {
        return [
            'auth_task' => self::MEDLIVE_AUTH_TASK . urlencode($sBackUrl),
        ];
    }


}