# CardAuth C# SDK

卡密验证平台 C# SDK，基于 .NET Standard 2.0，提供卡密验证、激活、换绑、查询等功能。

## 环境要求

- .NET Standard 2.0+
- .NET Framework 4.6.1+
- .NET Core 2.0+
- .NET 5+

## 安装

```bash
# 使用 NuGet 安装
dotnet add package CardAuthSDK
```

或直接引用项目。

## 快速开始

```csharp
using CardAuthSDK;

// 初始化客户端
var client = new CardAuthClient(
    appKey: "your_app_key",
    appSecret: "your_app_secret",
    baseUrl: "http://your-domain.com"
);

// 卡密验证
var result = await client.VerifyAsync(
    cardNo: "CARD-NO-001",
    deviceFingerprint: "device-fingerprint",
    deviceName: "My PC"
);

if (result.IsSuccess)
{
    Console.WriteLine($"验证成功，到期时间: {result.Data.ExpireTime}");
}
else
{
    Console.WriteLine($"验证失败: {result.Message}");
}
```

## API 文档

### CardAuthClient

#### 构造函数

```csharp
CardAuthClient(string appKey, string appSecret, string baseUrl = "http://localhost")
```

**参数：**
- `appKey`: 应用 Key
- `appSecret`: 应用密钥
- `baseUrl`: API 基础地址，默认为 `http://localhost`

#### VerifyAsync

验证卡密。

```csharp
Task<ApiResponse<CardVerifyResult>> VerifyAsync(string cardNo, string deviceFingerprint = "", string deviceName = "")
```

#### ActivateAsync

激活卡密。

```csharp
Task<ApiResponse<CardVerifyResult>> ActivateAsync(string cardNo, string deviceFingerprint, string deviceName = "")
```

#### RebindAsync

换绑设备。

```csharp
Task<ApiResponse<object>> RebindAsync(string cardNo, string oldDevice, string newDevice, string deviceName = "")
```

#### QueryAsync

查询卡密信息。

```csharp
Task<ApiResponse<CardQueryResult>> QueryAsync(string cardNo)
```

#### HeartbeatAsync

发送心跳。

```csharp
Task<ApiResponse<object>> HeartbeatAsync(string cardNo, string deviceFingerprint)
```

#### OnlineCountAsync

获取在线人数。

```csharp
Task<ApiResponse<OnlineCountResult>> OnlineCountAsync()
```

#### AnnouncementAsync

获取系统公告。

```csharp
Task<ApiResponse<AnnouncementResult>> AnnouncementAsync()
```

## 数据模型

### ApiResponse\<T\>

```csharp
public class ApiResponse<T>
{
    public int Code { get; set; }
    public string Message { get; set; }
    public T Data { get; set; }
    public long Timestamp { get; set; }
    public bool IsSuccess { get; }
}
```

### CardVerifyResult

```csharp
public class CardVerifyResult
{
    public int CardId { get; set; }
    public int CardType { get; set; }
    public string CardTypeText { get; set; }
    public int Status { get; set; }
    public string StatusText { get; set; }
    public string ExpireTime { get; set; }
    public int RemainingDuration { get; set; }
    public int BindDeviceCount { get; set; }
    public int BindLimit { get; set; }
    public bool IsPermanent { get; set; }
    public bool IsSoftExpired { get; set; }
}
```

## 签名算法

SDK 内部自动处理签名，签名算法如下：

1. 构造签名串：`METHOD + PATH + TIMESTAMP + NONCE + BODY`
2. 使用 HMAC-SHA256 算法，以 `appSecret` 为密钥对签名串进行加密
3. 签名结果为十六进制字符串（小写）

**请求头：**
- `X-AppKey`: 应用 Key
- `X-Timestamp`: Unix 时间戳（秒）
- `X-Nonce`: 随机字符串（至少8位）
- `X-Sign`: 签名结果

## 错误码

| 错误码 | 说明 |
|--------|------|
| 0 | 成功 |
| 4001 | AppKey无效/签名错误 |
| 4002 | 时间戳无效/已过期 |
| 4003 | Nonce无效/重复 |
| 4004 | IP不在白名单 |
| 4101 | 卡密不存在 |
| 4102 | 卡密未激活 |
| 4103 | 卡密已到期 |
| 4104 | 卡密已封禁 |
| 4105 | 卡密已作废 |
| 4106 | 设备绑定超限 |
| 4107 | 应用已停用 |

## 完整示例

参见 [examples/Program.cs](examples/Program.cs)

## 许可证

MIT License
