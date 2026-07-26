import hashlib
import hmac
import json
import time
import uuid
from typing import Dict, Any, Optional

import requests


class CardAuthClient:
    def __init__(self, app_key: str, app_secret: str, base_url: str = "http://localhost"):
        self.app_key = app_key
        self.app_secret = app_secret
        self.base_url = base_url.rstrip("/")
        self.timeout = 15

    def _generate_nonce(self) -> str:
        return uuid.uuid4().hex[:16]

    def _sign(self, method: str, path: str, timestamp: str, nonce: str, body: str) -> str:
        sign_string = method + path + timestamp + nonce + body
        signature = hmac.new(
            self.app_secret.encode("utf-8"),
            sign_string.encode("utf-8"),
            hashlib.sha256
        ).hexdigest()
        return signature

    def _request(self, method: str, path: str, data: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
        url = self.base_url + path
        timestamp = str(int(time.time()))
        nonce = self._generate_nonce()
        
        body = json.dumps(data, separators=(",", ":")) if data else ""
        
        sign = self._sign(method.upper(), path, timestamp, nonce, body)
        
        headers = {
            "X-AppKey": self.app_key,
            "X-Timestamp": timestamp,
            "X-Nonce": nonce,
            "X-Sign": sign,
            "Content-Type": "application/json"
        }
        
        try:
            if method.upper() == "GET":
                response = requests.get(url, headers=headers, params=data, timeout=self.timeout)
            else:
                response = requests.post(url, headers=headers, data=body, timeout=self.timeout)
            
            response.raise_for_status()
            return response.json()
        except requests.RequestException as e:
            return {
                "code": -1,
                "message": f"请求失败: {str(e)}",
                "data": None,
                "timestamp": int(time.time())
            }

    def verify(self, card_no: str, device_fingerprint: str = "", device_name: str = "") -> Dict[str, Any]:
        data = {
            "card_no": card_no,
            "device_fingerprint": device_fingerprint,
            "device_name": device_name
        }
        return self._request("POST", "/api/v1/card/verify", data)

    def activate(self, card_no: str, device_fingerprint: str, device_name: str = "") -> Dict[str, Any]:
        data = {
            "card_no": card_no,
            "device_fingerprint": device_fingerprint,
            "device_name": device_name
        }
        return self._request("POST", "/api/v1/card/activate", data)

    def rebind(self, card_no: str, old_device: str, new_device: str, device_name: str = "") -> Dict[str, Any]:
        data = {
            "card_no": card_no,
            "old_device": old_device,
            "new_device": new_device,
            "device_name": device_name
        }
        return self._request("POST", "/api/v1/card/rebind", data)

    def query(self, card_no: str) -> Dict[str, Any]:
        data = {
            "card_no": card_no
        }
        return self._request("POST", "/api/v1/card/query", data)

    def heartbeat(self, card_no: str, device_fingerprint: str) -> Dict[str, Any]:
        data = {
            "card_no": card_no,
            "device_fingerprint": device_fingerprint
        }
        return self._request("POST", "/api/v1/card/heartbeat", data)

    def online_count(self) -> Dict[str, Any]:
        return self._request("GET", "/api/v1/card/online-count")

    def announcement(self) -> Dict[str, Any]:
        return self._request("GET", "/api/v1/app/announcement")
