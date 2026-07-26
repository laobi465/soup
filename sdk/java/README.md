# CardAuth Java SDK

卡密验证平台 Java SDK，基于 Java 8+，提供卡密验证、激活、换绑、查询等功能。

## 环境要求

- Java 8 及以上
- Maven 3.6+

## 安装

### Maven

```xml
<dependency>
    <groupId>com.cardauth</groupId>
    <artifactId>cardauth-sdk</artifactId>
    <version>1.0.0</version>
</dependency>
```

### 手动安装

```bash
mvn clean install
```

## 快速开始

```java
import com.cardauth.sdk.CardAuthClient;
import com.cardauth.sdk.model.ApiResponse;
import com.cardauth.sdk.model.CardVerifyResult;

public class Example {
    public static void main(String[] args) {
        // 初始化客户端
        CardAuthClient client = new CardAuthClient(
            "your_app_key",
            "your_app_secret",
            "http://your-domain.com"
        );

        // 卡密验证
        ApiResponse<CardVerifyResult> result = client.verify(
            "CARD-NO-001",
            "device-fingerprint",
            "My PC"
        );

        if (result.isSuccess()) {
            System.out.println("验证成功，到期时间: " + result.getData().getExpireTime());
        } else {
            System.out.println("验证失败: " + result.getMessage());
        }
    }
}
```

## API 文档

### CardAuthClient

#### 构造函数

```java
CardAuthClient(String appKey, String appSecret, String baseUrl)
```

**参数：**
- `appKey`: 应用 Key
- `appSecret`: 应用密钥
- `baseUrl`: API 基础地址

#### verify(String cardNo, String deviceFingerprint, String deviceName)

验证卡密。

#### activate(String cardNo, String deviceFingerprint, String deviceName)

激活卡密。

#### rebind(String cardNo, String oldDevice, String newDevice, String deviceName)

换绑设备。

#### query(String cardNo)

查询卡密信息。

#### heartbeat(String cardNo, String deviceFingerprint)

发送心跳。

#### onlineCount()

获取在线人数。

#### announcement()

获取系统公告。

## 数据模型

### ApiResponse\<T\>

| 字段 | 类型 | 说明 |
|------|------|------|
| code | int | 状态码，0表示成功 |
| message | String | 消息 |
| data | T | 数据 |
| timestamp | long | 时间戳 |
| isSuccess() | boolean | 是否成功 |

### CardVerifyResult

| 字段 | 类型 | 说明 |
|------|------|------|
| cardId | int | 卡密ID |
| cardType | int | 卡密类型 |
| cardTypeText | String | 卡密类型文本 |
| status | int | 状态 |
| statusText | String | 状态文本 |
| expireTime | String | 到期时间 |
| remainingDuration | int | 剩余时长（秒） |
| bindDeviceCount | int | 已绑定设备数 |
| bindLimit | int | 绑定限制数 |
| permanent | boolean | 是否永久 |
| softExpired | boolean | 是否软过期 |

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

参见 [src/test/java/Example.java](src/test/java/Example.java)

## 许可证

MIT License
