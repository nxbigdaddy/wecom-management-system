<?php
/**
 * 企业微信消息加解密（AES-256-CBC + SHA1 签名）
 * 
 * 用于处理企业微信回调消息：
 * - URL 验证（首次配置回调 URL）
 * - 消息解密（接收回调事件）
 * - 消息加密（回复消息）
 * 
 * AES-256-CBC 模式：
 * - Key: Base64 解码后的 EncodingAESKey（32字节）
 * - IV: Key 的前 16 字节
 * - Padding: PKCS7
 * 
 * 消息结构（解密后）：
 * - 16 字节随机数
 * - 4 字节消息长度（网络字节序）
 * - 消息明文 XML
 * - ReceiveID（corpid 或 suiteId）
 */

namespace Wxwork\Core;

class Crypt
{
    /** @var string 回调 Token（用于签名） */
    private string $token;

    /** @var string AES Key（43 位 Base64 编码） */
    private string $encodingAesKey;

    /** @var string 接收方 ID（corpid 或 suiteId） */
    private string $receiveId;

    /** @var int AES block size */
    const BLOCK_SIZE = 32;

    /**
     * @param string $token          Token
     * @param string $encodingAesKey EncodingAESKey（43 位）
     * @param string $receiveId      corpid 或 suiteId
     */
    public function __construct(string $token, string $encodingAesKey, string $receiveId)
    {
        $this->token = YOUR_SECRET;
        $this->encodingAesKey = $encodingAesKey;
        $this->receiveId = $receiveId;

        // 自动补全 43 位（用户可能填了 42 位，末尾补 =）
        if (strlen($this->encodingAesKey) === 42) {
            $this->encodingAesKey .= '=';
        }
    }

    /**
     * URL 验证（GET 请求）
     * 企业微信首次配置回调 URL 时，会发 GET 请求验证
     * 
     * @param string $msgSignature URL 参数 msg_signature
     * @param string $timestamp    时间戳
     * @param string $nonce        随机数
     * @param string $echostr      加密的随机字符串
     * @return string 解密后的 echostr（明文）
     * @throws CryptException
     */
    public function verifyUrl(string $msgSignature, string $timestamp, string $nonce, string $echostr): string
    {
        // 1. 验证签名
        $signature = $this->getSignature($timestamp, $nonce, $echostr);
        if ($signature !== $msgSignature) {
            throw new CryptException('URL 验证签名失败');
        }

        // 2. 解密
        $result = $this->decrypt($echostr);

        // 3. 返回明文 echoStr
        return $result['message'];
    }

    /**
     * 解密消息（POST 请求中的加密消息体）
     * 
     * @param string $msgSignature URL 参数 msg_signature
     * @param string $timestamp    时间戳
     * @param string $nonce        随机数
     * @param string $postData     POST 请求体（完整的加密 XML）
     * @return array ['xml' => 明文 XML 字符串]
     * @throws CryptException
     */
    public function decryptMsg(string $msgSignature, string $timestamp, string $nonce, string $postData): array
    {
        // 1. 从 XML 中提取 <Encrypt> 内容
        $xml = simplexml_load_string($postData, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            throw new CryptException('XML 解析失败');
        }

        $encrypt = (string)$xml->Encrypt;
        if (empty($encrypt)) {
            throw new CryptException('XML 中未找到 Encrypt 字段');
        }

        // 2. 验证签名
        $signature = $this->getSignature($timestamp, $nonce, $encrypt);
        if ($signature !== $msgSignature) {
            throw new CryptException('消息签名验证失败');
        }

        // 3. 解密
        $result = $this->decrypt($encrypt);

        return [
            'xml' => $result['message'],
        ];
    }

    /**
     * 加密消息（回复给企业微信）
     * 
     * @param string $replyXml  明文的回复 XML
     * @param string $timestamp 时间戳
     * @param string $nonce     随机数
     * @return string 加密后的 XML 字符串
     * @throws CryptException
     */
    public function encryptMsg(string $replyXml, string $timestamp, string $nonce): string
    {
        // 1. 构造明文：16字节随机数 + 4字节长度 + 消息 + receiveId
        $random = $this->getRandomStr(16);
        $msgLen = pack('N', strlen($replyXml));
        $plaintext = $random . $msgLen . $replyXml . $this->receiveId;

        // 2. PKCS7 补位
        $plaintext = self::pkcs7Pad($plaintext);

        // 3. AES-256-CBC 加密
        $aesKey = base64_decode($this->encodingAesKey . '=');
        if ($aesKey === false || strlen($aesKey) !== 32) {
            throw new CryptException('EncodingAESKey 解码失败，不是有效的 32 字节 Key');
        }

        $iv = substr($aesKey, 0, 16);
        $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new CryptException('AES 加密失败');
        }

        // 4. Base64 编码
        $encrypt = base64_encode($encrypted);

        // 5. 生成签名
        $signature = $this->getSignature($timestamp, $nonce, $encrypt);

        // 6. 构造加密 XML
        $xml = sprintf(
            '<xml><Encrypt><![CDATA[%s]]></Encrypt><MsgSignature><![CDATA[%s]]></MsgSignature><TimeStamp>%s</TimeStamp><Nonce><![CDATA[%s]]></Nonce></xml>',
            $encrypt,
            $signature,
            $timestamp,
            $nonce
        );

        return $xml;
    }

    /**
     * 核心解密方法
     * 
     * @param string $encrypted Base64 编码的密文
     * @return array ['message' => 明文, 'receiveid' => corpid]
     * @throws CryptException
     */
    private function decrypt(string $encrypted): array
    {
        // 1. Base64 解码
        $ciphertext = base64_decode($encrypted);
        if ($ciphertext === false) {
            throw new CryptException('Base64 解码失败');
        }

        // 2. AES-256-CBC 解密
        $aesKey = base64_decode($this->encodingAesKey . '=');
        if ($aesKey === false || strlen($aesKey) !== 32) {
            throw new CryptException('EncodingAESKey 解码失败');
        }

        $iv = substr($aesKey, 0, 16);
        $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            throw new CryptException('AES 解密失败');
        }

        // 3. 去除 PKCS7 补位
        try {
            $plaintext = self::pkcs7Unpad($plaintext);
        } catch (\RuntimeException $e) {
            throw new CryptException('PKCS7 去补位失败: ' . $e->getMessage());
        }

        // 4. 提取结构：16字节随机数 + 4字节消息长度 + 消息 + receiveId
        if (strlen($plaintext) < 20) {
            throw new CryptException('解密后内容太短');
        }

        // $random = substr($plaintext, 0, 16);
        $msgLen = unpack('N', substr($plaintext, 16, 4));
        $msgLen = $msgLen[1];

        $message = substr($plaintext, 20, $msgLen);
        $receiveId = substr($plaintext, 20 + $msgLen);

        return [
            'message'   => $message,
            'receiveid' => $receiveId,
        ];
    }

    /**
     * 生成签名
     * SHA1(sort(token, timestamp, nonce, encrypt))
     * 
     * @param string $timestamp
     * @param string $nonce
     * @param string $encrypt  加密消息体
     * @return string 40 位 SHA1 十六进制字符串
     */
    private function getSignature(string $timestamp, string $nonce, string $encrypt): string
    {
        $params = [$this->token, $timestamp, $nonce, $encrypt];
        sort($params, SORT_STRING);
        return sha1(implode('', $params));
    }

    /**
     * PKCS7 补位
     */
    public static function pkcs7Pad(string $text): string
    {
        $blockSize = self::BLOCK_SIZE;
        $pad = $blockSize - (strlen($text) % $blockSize);
        $padChar = chr($pad);
        return $text . str_repeat($padChar, $pad);
    }

    /**
     * PKCS7 去除补位
     */
    public static function pkcs7Unpad(string $text): string
    {
        $len = strlen($text);
        if ($len === 0) {
            throw new \RuntimeException('空文本');
        }

        $pad = ord($text[$len - 1]);

        if ($pad <= 0 || $pad > self::BLOCK_SIZE) {
            throw new \RuntimeException('非法补位值: ' . $pad);
        }

        // 验证补位正确性
        for ($i = $len - $pad; $i < $len; $i++) {
            if (ord($text[$i]) !== $pad) {
                throw new \RuntimeException('补位校验失败');
            }
        }

        return substr($text, 0, $len - $pad);
    }

    /**
     * 生成指定长度的随机字符串
     */
    private function getRandomStr(int $length): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $result = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }
        return $result;
    }
}
