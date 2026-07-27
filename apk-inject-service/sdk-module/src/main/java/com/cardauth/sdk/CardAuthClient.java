package com.cardauth.sdk;

import com.cardauth.sdk.model.ApiResponse;
import com.cardauth.sdk.model.CardVerifyResult;
import com.cardauth.sdk.utils.SignUtil;

import org.json.JSONException;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.Iterator;
import java.util.Map;

/**
 * 卡密校验客户端（注入 SDK 版本）。
 *
 * <p>相对 {@code /workspace/sdk/java} 下的通用 SDK，本版本做了以下适配以适配注入场景：
 * <ul>
 *   <li>移除 Gson 依赖，改用 Android 自带的 {@code org.json}，避免与宿主 APK 的 Gson 版本冲突，
 *       同时让 kami-sdk.dex 零外部依赖、体积更小；</li>
 *   <li>响应解析改为手动从 {@code JSONObject} 取值并填充 POJO，不再依赖反射反序列化；</li>
 *   <li>支持两种鉴权模式（Task 2 / Task 3）：
 *     <ul>
 *       <li>HMAC 模式（开发者集成）：appKey + appSecret 签名，兼容现有 API；</li>
 *       <li>JWT 模式（注入 SDK）：Bearer token，由 task_token 换取，不依赖 appSecret。</li>
 *     </ul>
 *   </li>
 * </ul>
 */
public class CardAuthClient {

    private final String appKey;
    private final String appSecret;
    private final String baseUrl;
    /** JWT Bearer token（注入 SDK 模式），非空时优先于 HMAC 签名 */
    private final String jwtToken;
    private static final int TIMEOUT = 15000;

    /**
     * HMAC 鉴权构造函数（开发者集成模式，需 appSecret）。
     */
    public CardAuthClient(String appKey, String appSecret, String baseUrl) {
        this.appKey = appKey;
        this.appSecret = appSecret;
        this.jwtToken = null;
        if (baseUrl == null || baseUrl.isEmpty()) {
            this.baseUrl = "";
        } else if (baseUrl.endsWith("/")) {
            this.baseUrl = baseUrl.substring(0, baseUrl.length() - 1);
        } else {
            this.baseUrl = baseUrl;
        }
    }

    /**
     * JWT 鉴权构造函数（注入 SDK 模式，无需 appSecret）。
     *
     * @param jwtToken 由 task_token 通过 {@code POST /api/v1/sdk/auth} 换取的 Bearer token
     * @param baseUrl  平台 API base url
     */
    public CardAuthClient(String jwtToken, String baseUrl) {
        this.appKey = "";
        this.appSecret = null;
        this.jwtToken = jwtToken;
        if (baseUrl == null || baseUrl.isEmpty()) {
            this.baseUrl = "";
        } else if (baseUrl.endsWith("/")) {
            this.baseUrl = baseUrl.substring(0, baseUrl.length() - 1);
        } else {
            this.baseUrl = baseUrl;
        }
    }

    /** 是否使用 JWT 鉴权模式 */
    private boolean isJwtMode() {
        return jwtToken != null && !jwtToken.isEmpty();
    }

    /** 响应解析器：从原始 JSONObject 构造 {@link ApiResponse}。 */
    private interface ResponseParser<T> {
        ApiResponse<T> parse(JSONObject json) throws JSONException;
    }

    private <T> ApiResponse<T> sendRequest(String method, String path, Map<String, Object> data,
                                           ResponseParser<T> parser) {
        try {
            String body = data != null ? toJson(data) : "";

            URL url = new URL(baseUrl + path);
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod(method.toUpperCase());
            conn.setConnectTimeout(TIMEOUT);
            conn.setReadTimeout(TIMEOUT);
            conn.setRequestProperty("Content-Type", "application/json");

            if (isJwtMode()) {
                // JWT 模式（注入 SDK）：Authorization: Bearer <token>
                conn.setRequestProperty("Authorization", "Bearer " + jwtToken);
            } else {
                // HMAC 模式（开发者集成）：appKey + 签名头
                String timestamp = SignUtil.getTimestamp();
                String nonce = SignUtil.generateNonce();
                String sign = SignUtil.sign(method, path, timestamp, nonce, body, appSecret);
                conn.setRequestProperty("X-AppKey", appKey);
                conn.setRequestProperty("X-Timestamp", timestamp);
                conn.setRequestProperty("X-Nonce", nonce);
                conn.setRequestProperty("X-Sign", sign);
            }

            if (data != null && !"GET".equalsIgnoreCase(method)) {
                conn.setDoOutput(true);
                try (OutputStream os = conn.getOutputStream()) {
                    byte[] input = body.getBytes(StandardCharsets.UTF_8);
                    os.write(input, 0, input.length);
                }
            }

            int responseCode = conn.getResponseCode();
            StringBuilder response = new StringBuilder();
            InputStream is = (responseCode >= 200 && responseCode < 400)
                    ? conn.getInputStream() : conn.getErrorStream();
            if (is != null) {
                try (BufferedReader br = new BufferedReader(
                        new InputStreamReader(is, StandardCharsets.UTF_8))) {
                    String line;
                    while ((line = br.readLine()) != null) {
                        response.append(line);
                    }
                }
            }

            String responseStr = response.toString();
            JSONObject json = responseStr.isEmpty() ? new JSONObject() : new JSONObject(responseStr);
            return parser.parse(json);

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
        return sendRequest("POST", "/api/v1/card/verify", data, CardAuthClient::parseCardVerifyResult);
    }

    public ApiResponse<CardVerifyResult> activate(String cardNo, String deviceFingerprint, String deviceName) {
        Map<String, Object> data = new HashMap<>();
        data.put("card_no", cardNo);
        data.put("device_fingerprint", deviceFingerprint);
        data.put("device_name", deviceName);
        return sendRequest("POST", "/api/v1/card/activate", data, CardAuthClient::parseCardVerifyResult);
    }

    public ApiResponse<Object> rebind(String cardNo, String oldDevice, String newDevice, String deviceName) {
        Map<String, Object> data = new HashMap<>();
        data.put("card_no", cardNo);
        data.put("old_device", oldDevice);
        data.put("new_device", newDevice);
        data.put("device_name", deviceName);
        return sendRequest("POST", "/api/v1/card/rebind", data, json -> parseRaw(json));
    }

    public ApiResponse<CardVerifyResult> query(String cardNo) {
        Map<String, Object> data = new HashMap<>();
        data.put("card_no", cardNo);
        return sendRequest("POST", "/api/v1/card/query", data, CardAuthClient::parseCardVerifyResult);
    }

    public ApiResponse<Object> heartbeat(String cardNo, String deviceFingerprint) {
        Map<String, Object> data = new HashMap<>();
        data.put("card_no", cardNo);
        data.put("device_fingerprint", deviceFingerprint);
        return sendRequest("POST", "/api/v1/card/heartbeat", data, json -> parseRaw(json));
    }

    public ApiResponse<Map<String, Object>> onlineCount() {
        return sendRequest("GET", "/api/v1/card/online-count", null, json -> parseMap(json));
    }

    public ApiResponse<Map<String, Object>> announcement() {
        return sendRequest("GET", "/api/v1/app/announcement", null, json -> parseMap(json));
    }

    // ==================== 内部解析工具 ====================

    private static String toJson(Map<String, Object> data) {
        JSONObject obj = new JSONObject();
        for (Map.Entry<String, Object> e : data.entrySet()) {
            try {
                obj.put(e.getKey(), e.getValue());
            } catch (JSONException ignored) {
                // 跳过无法序列化的值
            }
        }
        return obj.toString();
    }

    private static <T> void fillCommon(ApiResponse<T> resp, JSONObject json) throws JSONException {
        resp.setCode(json.optInt("code", -1));
        resp.setMessage(json.optString("message", ""));
        resp.setTimestamp(json.optLong("timestamp", 0));
    }

    private static ApiResponse<CardVerifyResult> parseCardVerifyResult(JSONObject json) throws JSONException {
        ApiResponse<CardVerifyResult> resp = new ApiResponse<>();
        fillCommon(resp, json);
        JSONObject d = json.optJSONObject("data");
        resp.setData(d != null ? toCardVerifyResult(d) : null);
        return resp;
    }

    private static ApiResponse<Object> parseRaw(JSONObject json) throws JSONException {
        ApiResponse<Object> resp = new ApiResponse<>();
        fillCommon(resp, json);
        resp.setData(json.opt("data"));
        return resp;
    }

    private static ApiResponse<Map<String, Object>> parseMap(JSONObject json) throws JSONException {
        ApiResponse<Map<String, Object>> resp = new ApiResponse<>();
        fillCommon(resp, json);
        JSONObject d = json.optJSONObject("data");
        resp.setData(d != null ? toMap(d) : null);
        return resp;
    }

    private static CardVerifyResult toCardVerifyResult(JSONObject d) {
        CardVerifyResult r = new CardVerifyResult();
        r.setCardId(d.optInt("card_id", 0));
        r.setCardType(d.optInt("card_type", 0));
        r.setCardTypeText(d.optString("card_type_text", ""));
        r.setStatus(d.optInt("status", 0));
        r.setStatusText(d.optString("status_text", ""));
        r.setExpireTime(d.optString("expire_time", ""));
        r.setRemainingDuration(d.optInt("remaining_duration", 0));
        r.setBindDeviceCount(d.optInt("bind_device_count", 0));
        r.setBindLimit(d.optInt("bind_limit", 0));
        r.setPermanent(d.optBoolean("is_permanent", false));
        r.setSoftExpired(d.optBoolean("is_soft_expired", false));
        return r;
    }

    private static Map<String, Object> toMap(JSONObject d) {
        Map<String, Object> map = new HashMap<>();
        Iterator<String> it = d.keys();
        while (it.hasNext()) {
            String key = it.next();
            map.put(key, d.opt(key));
        }
        return map;
    }
}
