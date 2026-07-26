from cardauth_sdk import CardAuthClient


def main():
    app_key = "your_app_key"
    app_secret = "your_app_secret"
    base_url = "http://localhost"

    client = CardAuthClient(app_key, app_secret, base_url)

    card_no = "TEST-CARD-001"
    device_fingerprint = "device-abc-123"
    device_name = "My PC"

    print("=== 卡密查询 ===")
    result = client.query(card_no)
    print(f"结果: {result}")
    print()

    print("=== 卡密激活 ===")
    result = client.activate(card_no, device_fingerprint, device_name)
    print(f"结果: {result}")
    print()

    print("=== 卡密验证 ===")
    result = client.verify(card_no, device_fingerprint, device_name)
    print(f"结果: {result}")
    print()

    print("=== 心跳 ===")
    result = client.heartbeat(card_no, device_fingerprint)
    print(f"结果: {result}")
    print()

    print("=== 在线人数 ===")
    result = client.online_count()
    print(f"结果: {result}")
    print()

    print("=== 系统公告 ===")
    result = client.announcement()
    print(f"结果: {result}")


if __name__ == "__main__":
    main()
