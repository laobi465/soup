import base64
import hashlib
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad
from typing import Optional


class AesHelper:
    @staticmethod
    def _get_key(key: str) -> bytes:
        return hashlib.sha256(key.encode("utf-8")).digest()

    @staticmethod
    def encrypt(data: str, key: str) -> str:
        key_bytes = AesHelper._get_key(key)
        iv = AES.new(key_bytes, AES.MODE_CBC).iv
        cipher = AES.new(key_bytes, AES.MODE_CBC, iv)
        padded_data = pad(data.encode("utf-8"), AES.block_size)
        encrypted = cipher.encrypt(padded_data)
        return base64.b64encode(iv + encrypted).decode("utf-8")

    @staticmethod
    def decrypt(encrypted_data: str, key: str) -> Optional[str]:
        try:
            key_bytes = AesHelper._get_key(key)
            data = base64.b64decode(encrypted_data)
            iv_length = AES.block_size
            if len(data) < iv_length:
                return None
            iv = data[:iv_length]
            encrypted = data[iv_length:]
            cipher = AES.new(key_bytes, AES.MODE_CBC, iv)
            decrypted = cipher.decrypt(encrypted)
            unpadded = unpad(decrypted, AES.block_size)
            return unpadded.decode("utf-8")
        except Exception:
            return None


def generate_sign(method: str, path: str, timestamp: str, nonce: str, body: str, app_secret: str) -> str:
    sign_string = method + path + timestamp + nonce + body
    return hmac_sha256(sign_string, app_secret)


def hmac_sha256(data: str, key: str) -> str:
    return hmac.new(
        key.encode("utf-8"),
        data.encode("utf-8"),
        hashlib.sha256
    ).hexdigest()


import hmac
