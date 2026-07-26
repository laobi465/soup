# CardAuth VB.NET SDK

卡密验证平台 VB.NET SDK，提供卡密验证、激活、换绑、查询等功能。

## 环境要求

- .NET Standard 2.0+ / .NET Framework 4.6.1+ / .NET Core 2.0+ / .NET 5+
- Visual Studio 2017+ 或 Rider

## 安装

### 方式一：直接引用项目

1. 将 `CardAuthSDK` 文件夹复制到你的解决方案中
2. 在 Visual Studio 中右键解决方案 → 添加 → 现有项目
3. 选择 `CardAuthSDK.vbproj`
4. 在你的项目中添加对 `CardAuthSDK` 的引用

### 方式二：NuGet（待发布）

```bash
Install-Package CardAuthSDK
```

## 快速开始

```vb
Imports CardAuthSDK

Module Program
    Sub Main()
        ' 初始化客户端
        Dim client As New CardAuthClient(
            "your_app_key",
            "your_app_secret",
            "http://your-domain.com"
        )

        ' 卡密验证
        Dim result = client.VerifyAsync(
            "CARD-NO-001",
            "device-fingerprint",
            "My PC"
        ).Result

        If result.IsSuccess Then
            Console.WriteLine("验证成功: " & result.Data.StatusText)
        Else
            Console.WriteLine("验证失败: " & result.Message)
        End If
    End Sub
End Module
```

## API 文档

### 初始化

```vb
Dim client As New CardAuthClient(appKey, appSecret, baseUrl)
```

**参数：**
- `appKey`: 应用 Key
- `appSecret`: 应用密钥
- `baseUrl`: API 基础地址（默认 `http://localhost`）

### 卡密验证

```vb
Dim result As ApiResponse(Of CardVerifyResult) =
    Await client.VerifyAsync(cardNo, deviceFingerprint, deviceName)
```

验证卡密有效性。

**参数：**
- `cardNo`: 卡密编号
- `deviceFingerprint`: 设备指纹（可选）
- `deviceName`: 设备名称（可选）

### 卡密激活

```vb
Dim result As ApiResponse(Of CardVerifyResult) =
    Await client.ActivateAsync(cardNo, deviceFingerprint, deviceName)
```

激活卡密并绑定设备。

**参数：**
- `cardNo`: 卡密编号
- `deviceFingerprint`: 设备指纹
- `deviceName`: 设备名称（可选）

### 设备换绑

```vb
Dim result As ApiResponse(Of Object) =
    Await client.RebindAsync(cardNo, oldDevice, newDevice, deviceName)
```

更换卡密绑定的设备。

**参数：**
- `cardNo`: 卡密编号
- `oldDevice`: 旧设备指纹
- `newDevice`: 新设备指纹
- `deviceName`: 设备名称（可选）

### 卡密查询

```vb
Dim result As ApiResponse(Of CardQueryResult) =
    Await client.QueryAsync(cardNo)
```

查询卡密详细信息。

**参数：**
- `cardNo`: 卡密编号

### 心跳上报

```vb
Dim result As ApiResponse(Of Object) =
    Await client.HeartbeatAsync(cardNo, deviceFingerprint)
```

发送心跳保持在线状态。

**参数：**
- `cardNo`: 卡密编号
- `deviceFingerprint`: 设备指纹

### 获取在线人数

```vb
Dim result As ApiResponse(Of OnlineCountResult) =
    Await client.OnlineCountAsync()
```

获取当前在线用户数。

### 获取系统公告

```vb
Dim result As ApiResponse(Of AnnouncementResult) =
    Await client.AnnouncementAsync()
```

获取系统公告信息。

## 返回数据格式

所有接口返回 `ApiResponse(Of T)` 对象：

```vb
Public Class ApiResponse(Of T)
    Public Property Code As Integer
    Public Property Message As String
    Public Property Data As T
    Public Property Timestamp As Long
    Public ReadOnly Property IsSuccess As Boolean
End Class
```

## 签名算法

SDK 内部自动处理签名，签名算法如下：

1. 构造签名串：`METHOD + PATH + TIMESTAMP + NONCE + BODY`
2. 使用 HMAC-SHA256 算法，以 `app_secret` 为密钥对签名串进行加密
3. 签名结果为十六进制字符串（小写）

**请求头：**
- `X-AppKey`: 应用 Key
- `X-Timestamp`: Unix 时间戳（秒）
- `X-Nonce`: 随机字符串（16位）
- `X-Sign`: 签名结果

## 错误码

| 错误码 | 说明 |
|--------|------|
| 0 | 成功 |
| -1 | 请求失败/网络错误 |
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

参见 [examples/Program.vb](examples/Program.vb)

## 注意事项

1. 请妥善保管 AppSecret，不要在客户端代码中硬编码
2. 建议在服务端进行敏感操作，密钥不要暴露给前端
3. 时间戳使用 Unix 时间戳（秒级）
4. Nonce 使用 16 位随机字符串
5. 所有 API 调用均为异步方法，建议使用 `Async/Await`

## 许可证

MIT License
