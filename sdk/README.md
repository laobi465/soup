# CardAuth SDK 总览

卡密验证平台多语言 SDK 集合，帮助开发者快速接入卡密验证服务。

## 支持的语言

| 语言 | 目录 | 最低版本 | 状态 |
|------|------|----------|------|
| Python | [python/](python/) | Python 3.7+ | 已完成 |
| C# | [csharp/](csharp/) | .NET Standard 2.0 | 已完成 |
| Java | [java/](java/) | Java 8+ | 已完成 |
| VB.NET | [vbnet/](vbnet/) | .NET Standard 2.0 | 已完成 |
| 易语言 | [easy/](easy/) | 易语言 5.9+ | 已完成 |

## 统一设计模式

所有 SDK 遵循统一的设计模式：

### 1. 初始化

```python
# Python
client = CardAuthClient(app_key, app_secret, base_url)
```

```csharp
// C#
var client = new CardAuthClient(appKey, appSecret, baseUrl);
```

```java
// Java
CardAuthClient client = new CardAuthClient(appKey, appSecret, baseUrl);
```

```vb
' VB.NET
Dim client As New CardAuthClient(appKey, appSecret, baseUrl)
```

```易语言
' 易语言
初始化SDK (app_key, app_secret, base_url)
```

### 2. 调用方法

```python
# Python
result = client.verify(card_no, device_fingerprint, device_name)
```

```csharp
// C#
var result = await client.VerifyAsync(cardNo, deviceFingerprint, deviceName);
```

```java
// Java
ApiResponse<CardVerifyResult> result = client.verify(cardNo, deviceFingerprint, deviceName);
```

```vb
' VB.NET
Dim result = Await client.VerifyAsync(cardNo, deviceFingerprint, deviceName)
```

```易语言
' 易语言
结果 ＝ 卡密验证 (card_no, device_fingerprint, device_name)
```

### 3. 返回结果

所有 SDK 返回统一的响应格式：

```json
{
    "code": 0,
    "message": "success",
    "data": {
        // 具体数据
    },
    "timestamp": 1700000000
}
```

## API 列表

所有 SDK 均提供以下 API：

| 方法名 | 功能 | 说明 |
|--------|------|------|
| verify | 卡密验证 | 验证卡密有效性 |
| activate | 卡密激活 | 激活卡密并绑定设备 |
| rebind | 设备换绑 | 更换卡密绑定的设备 |
| query | 卡密查询 | 查询卡密详细信息 |
| heartbeat | 心跳上报 | 发送心跳保持在线状态 |
| onlineCount | 在线人数 | 获取当前在线用户数 |
| announcement | 系统公告 | 获取系统公告信息 |

## 签名算法

所有 SDK 使用统一的签名算法：

1. 构造签名串：`METHOD + PATH + TIMESTAMP + NONCE + BODY`
2. 使用 HMAC-SHA256 算法，以 `app_secret` 为密钥对签名串进行加密
3. 签名结果为十六进制字符串（小写）

### 请求头

| 请求头 | 说明 |
|--------|------|
| X-AppKey | 应用 Key |
| X-Timestamp | Unix 时间戳（秒） |
| X-Nonce | 随机字符串（至少8位） |
| X-Sign | 签名结果 |

### 示例

签名串示例：

```
POST/api/v1/card/verify1700000000abcdef1234567890{"card_no":"CARD-001"}
```

## 错误码

| 错误码 | 说明 |
|--------|------|
| 0 | 成功 |
| -1 | 请求失败/网络错误 |
| 4001 | AppKey无效/签名错误 |
| 4002 | 时间戳无效/已过期 |
| 4003 | Nonce无效/重复 |
| 4004 | IP不在白名单 |
| 4005 | 请求限流 |
| 4101 | 卡密不存在 |
| 4102 | 卡密未激活 |
| 4103 | 卡密已到期 |
| 4104 | 卡密已封禁 |
| 4105 | 卡密已作废 |
| 4106 | 设备绑定超限 |
| 4107 | 应用已停用 |

## 快速开始

### 第一步：获取 AppKey 和 AppSecret

1. 登录商户后台
2. 进入「应用管理」页面
3. 创建应用，获取 AppKey 和 AppSecret

### 第二步：选择 SDK

根据你的开发语言选择对应的 SDK，参考各 SDK 的 README 文档进行接入。

### 第三步：调用 API

初始化客户端后，即可调用卡密验证等 API。

## 常见问题

### Q: 签名验证失败怎么办？

A: 请检查以下几点：
1. AppKey 和 AppSecret 是否正确
2. 时间戳是否为 Unix 时间戳（秒级），与服务器时间差不超过 5 分钟
3. Nonce 是否为随机字符串，且每次请求都不同
4. 签名串构造是否正确，注意 METHOD 为大写
5. 请求体是否与签名时的 body 完全一致

### Q: 如何处理网络错误？

A: SDK 会捕获网络异常并返回 code=-1 的错误响应，建议业务代码中增加重试机制。

### Q: 支持 AES 加密吗？

A: 目前 SDK 暂未内置 AES 加密功能，如需使用请参考 API 文档自行实现。

### Q: 易语言 SDK 如何使用？

A: 易语言 SDK 为模块文件，需要配合「精易模块」等第三方模块使用，具体请参考易语言 SDK 的 README。

## 技术支持

- 官方文档：[docs.cardauth.com](https://docs.cardauth.com)
- 问题反馈：在 GitHub 提交 Issue
- 商务合作：contact@cardauth.com

## 许可证

MIT License
