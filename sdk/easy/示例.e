版本 1.0.0

本模块为卡密验证平台的易语言SDK封装

.版本 2

.程序集 卡密验证SDK
.程序集变量 m_AppKey, 文本型
.程序集变量 m_AppSecret, 文本型
.程序集变量 m_BaseUrl, 文本型
.程序集变量 m_Nonce, 文本型
.程序集变量 m_Timestamp, 文本型

.子程序 初始化SDK, , 公开, 初始化SDK参数
.参数 AppKey, 文本型
.参数 AppSecret, 文本型
.参数 BaseUrl, 文本型, , 可空，默认为http://localhost

    m_AppKey ＝ AppKey
    m_AppSecret ＝ AppSecret
    .如果真 (BaseUrl ＝ “”)
        BaseUrl ＝ “http://localhost”
    .如果真结束
    m_BaseUrl ＝ 子文本替换 (BaseUrl, “/”, “”, , 1, 假)

.子程序 生成随机串, 文本型, 私有
.参数 长度, 整数型, , 默认为16

    .局部变量 chars, 文本型
    .局部变量 result, 文本型
    .局部变量 i, 整数型
    .局部变量 pos, 整数型

    chars ＝ “abcdef0123456789”
    result ＝ “”
    .计次循环首 (长度, i)
        置随机数种子 ()
        pos ＝ 取随机数 (1, 取文本长度 (chars))
        result ＝ result ＋ 取文本中间 (chars, pos, 1)
    .计次循环尾 ()
    返回 (result)

.子程序 获取时间戳, 文本型, 私有

    .局部变量 time, 长整数型

    time ＝ 取时间戳 ()
    返回 (到文本 (time))

.子程序 HMAC_SHA256, 文本型, 私有
.参数 data, 文本型
.参数 key, 文本型

    ' 此处需要实现HMAC-SHA256加密
    ' 可以使用第三方加密库或自行实现
    返回 (“”)

.子程序 生成签名, 文本型, 私有
.参数 method, 文本型
.参数 path, 文本型
.参数 body, 文本型

    .局部变量 signString, 文本型
    .局部变量 sign, 文本型

    m_Timestamp ＝ 获取时间戳 ()
    m_Nonce ＝ 生成随机串 (16)
    signString ＝ 到大写 (method) ＋ path ＋ m_Timestamp ＋ m_Nonce ＋ body
    sign ＝ HMAC_SHA256 (signString, m_AppSecret)
    返回 (到小写 (sign))

.子程序 发送HTTP请求, 文本型, 私有
.参数 method, 文本型
.参数 path, 文本型
.参数 body, 文本型

    .局部变量 url, 文本型
    .局部变量 sign, 文本型
    .局部变量 result, 文本型

    url ＝ m_BaseUrl ＋ path
    sign ＝ 生成签名 (method, path, body)

    ' 此处需要实现HTTP POST请求
    ' 需要添加请求头:
    ' X-AppKey: m_AppKey
    ' X-Timestamp: m_Timestamp
    ' X-Nonce: m_Nonce
    ' X-Sign: sign
    ' Content-Type: application/json

    返回 (result)

.子程序 卡密验证, 文本型, 公开, 验证卡密
.参数 卡密编号, 文本型
.参数 设备指纹, 文本型
.参数 设备名称, 文本型, , 可空

    .局部变量 body, 文本型

    body ＝ “{” ＋ #引号 ＋ “card_no” ＋ #引号 ＋ “:” ＋ #引号 ＋ 卡密编号 ＋ #引号 ＋ “,”
    body ＝ body ＋ #引号 ＋ “device_fingerprint” ＋ #引号 ＋ “:” ＋ #引号 ＋ 设备指纹 ＋ #引号 ＋ “,”
    body ＝ body ＋ #引号 ＋ “device_name” ＋ #引号 ＋ “:” ＋ #引号 ＋ 设备名称 ＋ #引号 ＋ “}”
    返回 (发送HTTP请求 (“POST”, “/api/v1/card/verify”, body))

.子程序 卡密激活, 文本型, 公开, 激活卡密
.参数 卡密编号, 文本型
.参数 设备指纹, 文本型
.参数 设备名称, 文本型, , 可空

    .局部变量 body, 文本型

    body ＝ “{” ＋ #引号 ＋ “card_no” ＋ #引号 ＋ “:” ＋ #引号 ＋ 卡密编号 ＋ #引号 ＋ “,”
    body ＝ body ＋ #引号 ＋ “device_fingerprint” ＋ #引号 ＋ “:” ＋ #引号 ＋ 设备指纹 ＋ #引号 ＋ “,”
    body ＝ body ＋ #引号 ＋ “device_name” ＋ #引号 ＋ “:” ＋ #引号 ＋ 设备名称 ＋ #引号 ＋ “}”
    返回 (发送HTTP请求 (“POST”, “/api/v1/card/activate”, body))

.子程序 设备换绑, 文本型, 公开, 更换绑定设备
.参数 卡密编号, 文本型
.参数 旧设备指纹, 文本型
.参数 新设备指纹, 文本型
.参数 设备名称, 文本型, , 可空

    .局部变量 body, 文本型

    body ＝ “{” ＋ #引号 ＋ “card_no” ＋ #引号 ＋ “:” ＋ #引号 ＋ 卡密编号 ＋ #引号 ＋ “,”
    body ＝ body ＋ #引号 ＋ “old_device” ＋ #引号 ＋ “:” ＋ #引号 ＋ 旧设备指纹 ＋ #引号 ＋ “,”
    body ＝ body ＋ #引号 ＋ “new_device” ＋ #引号 ＋ “:” ＋ #引号 ＋ 新设备指纹 ＋ #引号 ＋ “,”
    body ＝ body ＋ #引号 ＋ “device_name” ＋ #引号 ＋ “:” ＋ #引号 ＋ 设备名称 ＋ #引号 ＋ “}”
    返回 (发送HTTP请求 (“POST”, “/api/v1/card/rebind”, body))

.子程序 卡密查询, 文本型, 公开, 查询卡密信息
.参数 卡密编号, 文本型

    .局部变量 body, 文本型

    body ＝ “{” ＋ #引号 ＋ “card_no” ＋ #引号 ＋ “:” ＋ #引号 ＋ 卡密编号 ＋ #引号 ＋ “}”
    返回 (发送HTTP请求 (“POST”, “/api/v1/card/query”, body))

.子程序 心跳上报, 文本型, 公开, 发送心跳保活
.参数 卡密编号, 文本型
.参数 设备指纹, 文本型

    .局部变量 body, 文本型

    body ＝ “{” ＋ #引号 ＋ “card_no” ＋ #引号 ＋ “:” ＋ #引号 ＋ 卡密编号 ＋ #引号 ＋ “,”
    body ＝ body ＋ #引号 ＋ “device_fingerprint” ＋ #引号 ＋ “:” ＋ #引号 ＋ 设备指纹 ＋ #引号 ＋ “}”
    返回 (发送HTTP请求 (“POST”, “/api/v1/card/heartbeat”, body))

.子程序 获取在线人数, 文本型, 公开, 获取当前在线人数

    返回 (发送HTTP请求 (“GET”, “/api/v1/card/online-count”, “”))

.子程序 获取系统公告, 文本型, 公开, 获取系统公告

    返回 (发送HTTP请求 (“GET”, “/api/v1/app/announcement”, “”))

.子程序 取时间戳, 长整数型, 私有
.局部变量 时间, 日期时间型

    时间 ＝ 取现行时间 ()
    返回 (取时间戳_秒 (时间))

.子程序 取时间戳_秒, 长整数型, 私有
.参数 时间, 日期时间型

    .局部变量 时间戳, 长整数型
    ' 实现时间戳转换
    返回 (时间戳)

.子程序 到小写, 文本型, 私有
.参数 文本, 文本型

    返回 (文本到小写 (文本))

.子程序 到大写, 文本型, 私有
.参数 文本, 文本型

    返回 (文本到大写 (文本))

.子程序 文本到小写, 文本型, 私有
.参数 文本, 文本型

    ' 实现文本转小写
    返回 (文本)

.子程序 文本到大写, 文本型, 私有
.参数 文本, 文本型

    ' 实现文本转大写
    返回 (文本)
