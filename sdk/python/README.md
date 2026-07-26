# CardAuth Python SDK

卡密验证平台 Python SDK，提供卡密验证、激活、换绑、查询等功能。

## 安装

```bash
pip install -r requirements.txt
```

或者直接将 `cardauth_sdk` 目录复制到你的项目中。

## 快速开始

```python
from cardauth_sdk import CardAuthClient

# 初始化客户端
client = CardAuthClient(
    app_key="your_app_key",
    app_secret="your_app_secret",
    base_url="http://your-domain.com"
)

# 卡密验证
result = client.verify(
    card_no="CARD-NO-001",
    device_fingerprint="device-fingerprint",
    device_name="My PC"
)
print(result)
```

## API 文档

### CardAuthClient

#### 初始化

```python
CardAuthClient(app_key, app_secret, base_url="http://localhost")
```

**参数：**
- `app_key` (str): 应用 Key
- `app_secret` (str): 应用密钥
- `base_url` (str): API 基础地址，默认为 `http://localhost`

#### verify(card_no, device_fingerprint="", device_name="")

验证卡密。

**参数：**
- `card_no` (str): 卡密编号
- `device_fingerprint` (str): 设备指纹
- `device_name` (str): 设备名称

**返回：**
```python
{
    "code": 0,
    "message": "success",
    "data": {
        "card_id": 1,
        "card_type": 1,
        "card_type_text": "月卡",
        "status": 1,
        "status_text": "已激活",
        "expire_time": "2024-12-31 23:59:59",
        "remaining_duration": 2592000,
        "bind_device_count": 1,
        "bind_limit": 3,
        "is_permanent": false,
        "is_soft_expired": false
    },
    "timestamp": 1700000000
}
```

#### activate(card_no, device_fingerprint, device_name="")

激活卡密。

**参数：**
- `card_no` (str): 卡密编号
- `device_fingerprint` (str): 设备指纹（必填）
- `device_name` (str): 设备名称

#### rebind(card_no, old_device, new_device, device_name="")

换绑设备。

**参数：**
- `card_no` (str): 卡密编号
- `old_device` (str): 旧设备指纹
- `new_device` (str): 新设备指纹
- `device_name` (str): 设备名称

#### query(card_no)

查询卡密信息。

**参数：**
- `card_no` (str): 卡密编号

#### heartbeat(card_no, device_fingerprint)

发送心跳。

**参数：**
- `card_no` (str): 卡密编号
- `device_fingerprint` (str): 设备指纹

#### online_count()

获取在线人数。

#### announcement()

获取系统公告。

## 签名算法

SDK 内部自动处理签名，签名算法如下：

1. 构造签名串：`METHOD + PATH + TIMESTAMP + NONCE + BODY`
2. 使用 HMAC-SHA256 算法，以 `app_secret` 为密钥对签名串进行加密
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

参见 [examples/example.py](examples/example.py)

## 许可证

MIT License
