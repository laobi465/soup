package com.cardauth.sdk;

import com.cardauth.sdk.model.ApiResponse;
import com.cardauth.sdk.model.CardVerifyResult;
import com.cardauth.sdk.utils.SignUtil;
import com.google.gson.Gson;
import com.google.gson.reflect.TypeToken;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.Map;

public class CardAuthClient {

    private final String appKey;
    private final String appSecret;
    private final String baseUrl;
    private final Gson gson;
    private static final int TIMEOUT = 15000;

    public CardAuthClient(String appKey, String appSecret, String baseUrl) {
        this.appKey = appKey;
        this.appSecret = appSecret;
        this.baseUrl = baseUrl.endsWith("/") ? baseUrl.substring(0, baseUrl.length() - 1) : baseUrl;
        this.gson = new Gson();
    }

    private <T> ApiResponse<T> sendRequest(String method, String path, Map<String, Object> data, Class<T> dataClass) {
        try {
            String timestamp = SignUtil.getTimestamp();
            String nonce = SignUtil.generateNonce();
            String body = data != null ? gson.toJson(data) : "";
            String sign = SignUtil.sign(method, path, timestamp, nonce, body, appSecret);

            URL url = new URL(baseUrl + path);
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod(method.toUpperCase());
            conn.setConnectTimeout(TIMEOUT);
            conn.setReadTimeout(TIMEOUT);
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setRequestProperty("X-AppKey", appKey);
            conn.setRequestProperty("X-Timestamp", timestamp);
            conn.setRequestProperty("X-Nonce", nonce);
            conn.setRequestProperty("X-Sign", sign);

            if (data != null && !"GET".equalsIgnoreCase(method)) {
                conn.setDoOutput(true);
                try (OutputStream os = conn.getOutputStream()) {
                    byte[] input = body.getBytes(StandardCharsets.UTF_8);
                    os.write(input, 0, input.length);
                }
            }

            StringBuilder response = new StringBuilder();
            try (BufferedReader br = new BufferedReader(
                    new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8))) {
                String line;
                while ((line = br.readLine()) != null) {
                    response.append(line);
                }
            }

            return gson.fromJson(response.toString(),
                    TypeToken.getParameterized(ApiResponse.class, dataClass).getType());

        } catch (Exception e) {
            ApiResponse<T> errorResponse = new ApiResponse<>();
            errorResponse.setCode(-1);
            errorResponse.setMessage("请求失败: " + e.getMessage());
            errorResponse.setData(null);
            errorResponse.setTimestamp(System.currentTimeMillis() / 1000);
            return errorResponse;
        }
    }

    public ApiResponse<CardVerifyResult> verify(String cardNo, String deviceFingerprint, String deviceName) {
        Map<String, Object> data = new HashMap<>();
        data.put("card_no", cardNo);
        data.put("device_fingerprint", deviceFingerprint);
        data.put("device_name", deviceName);
        return sendRequest("POST", "/api/v1/card/verify", data, CardVerifyResult.class);
    }

    public ApiResponse<CardVerifyResult> activate(String cardNo, String deviceFingerprint, String deviceName) {
        Map<String, Object> data = new HashMap<>();
        data.put("card_no", cardNo);
        data.put("device_fingerprint", deviceFingerprint);
        data.put("device_name", deviceName);
        return sendRequest("POST", "/api/v1/card/activate", data, CardVerifyResult.class);
    }

    public ApiResponse<Object> rebind(String cardNo, String oldDevice, String newDevice, String deviceName) {
        Map<String, Object> data = new HashMap<>();
        data.put("card_no", cardNo);
        data.put("old_device", oldDevice);
        data.put("new_device", newDevice);
        data.put("device_name", deviceName);
        return sendRequest("POST", "/api/v1/card/rebind", data, Object.class);
    }

    public ApiResponse<CardVerifyResult> query(String cardNo) {
        Map<String, Object> data = new HashMap<>();
        data.put("card_no", cardNo);
        return sendRequest("POST", "/api/v1/card/query", data, CardVerifyResult.class);
    }

    public ApiResponse<Object> heartbeat(String cardNo, String deviceFingerprint) {
        Map<String, Object> data = new HashMap<>();
        data.put("card_no", cardNo);
        data.put("device_fingerprint", deviceFingerprint);
        return sendRequest("POST", "/api/v1/card/heartbeat", data, Object.class);
    }

    public ApiResponse<Map<String, Object>> onlineCount() {
        return sendRequest("GET", "/api/v1/card/online-count", null,
                (Class<Map<String, Object>>) (Class<?>) Map.class);
    }

    public ApiResponse<Map<String, Object>> announcement() {
        return sendRequest("GET", "/api/v1/app/announcement", null,
                (Class<Map<String, Object>>) (Class<?>) Map.class);
    }
}
