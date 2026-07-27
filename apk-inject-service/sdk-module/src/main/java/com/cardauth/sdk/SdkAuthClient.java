package com.cardauth.sdk;

import org.json.JSONException;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

/**
 * SDK 鉴权客户端（Task 2 / Task 3 配套）。
 *
 * <p>注入后 APK 启动时，{@link KamiProxyApplication} 用 manifest 中的 {@code kami_task_token}
 * 调用平台 {@code POST /api/v1/sdk/auth} 换取短期 JWT（默认 1 小时），后续卡密校验请求
 * 通过 Bearer token 鉴权。
 *
 * <p><b>安全模型</b>：
 * <ul>
 *   <li>task_token 注入到 APK manifest（非敏感凭证，泄露后只能换取本任务对应的 JWT）；</li>
 *   <li>app_secret 永不落地 APK，仅在 Java 注入微服务内存中用于本次签名流水线；</li>
 *   <li>JWT 有效期 ≤1 小时，payload 含 task_id/app_id/merchant_id/app_key，不含 app_secret。</li>
 * </ul>
 */
public class SdkAuthClient {

    private static final int TIMEOUT = 15000;
    private static final String AUTH_PATH = "/api/v1/sdk/auth";

    private final String baseUrl;

    public SdkAuthClient(String baseUrl) {
        if (baseUrl == null || baseUrl.isEmpty()) {
            this.baseUrl = "";
        } else if (baseUrl.endsWith("/")) {
            this.baseUrl = baseUrl.substring(0, baseUrl.length() - 1);
        } else {
            this.baseUrl = baseUrl;
        }
    }

    /**
     * 用 task_token 换取 JWT。
     *
     * @param taskToken        注入时写入 manifest 的 kami_task_token
     * @param deviceFingerprint 设备指纹
     * @param deviceName       设备名
     * @return 鉴权结果，成功时含 jwt_token / expires_in / app_key / base_url
     */
    public AuthResult auth(String taskToken, String deviceFingerprint, String deviceName) {
        AuthResult result = new AuthResult();
        if (baseUrl.isEmpty()) {
            result.success = false;
            result.error = "base_url 未配置";
            return result;
        }
        if (taskToken == null || taskToken.isEmpty()) {
            result.success = false;
            result.error = "task_token 为空";
            return result;
        }

        HttpURLConnection conn = null;
        try {
            JSONObject body = new JSONObject();
            body.put("task_token", taskToken);
            body.put("device_fingerprint", deviceFingerprint);
            body.put("device_name", deviceName);
            byte[] bodyBytes = body.toString().getBytes(StandardCharsets.UTF_8);

            URL url = new URL(baseUrl + AUTH_PATH);
            conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setConnectTimeout(TIMEOUT);
            conn.setReadTimeout(TIMEOUT);
            conn.setRequestProperty("Content-Type", "application/json; charset=UTF-8");
            conn.setDoOutput(true);
            try (OutputStream os = conn.getOutputStream()) {
                os.write(bodyBytes, 0, bodyBytes.length);
            }

            int code = conn.getResponseCode();
            InputStream is = (code >= 200 && code < 400) ? conn.getInputStream() : conn.getErrorStream();
            StringBuilder resp = new StringBuilder();
            if (is != null) {
                try (BufferedReader br = new BufferedReader(
                        new InputStreamReader(is, StandardCharsets.UTF_8))) {
                    String line;
                    while ((line = br.readLine()) != null) {
                        resp.append(line);
                    }
                }
            }

            String respStr = resp.toString();
            JSONObject json = respStr.isEmpty() ? new JSONObject() : new JSONObject(respStr);
            int bizCode = json.optInt("code", -1);
            // PHP 端 apiSuccess 返回 code=0，apiError 返回非 0 错误码
            if (code == 200 && bizCode == 0) {
                JSONObject data = json.optJSONObject("data");
                if (data != null) {
                    result.success = true;
                    result.jwtToken = data.optString("jwt_token", "");
                    result.expiresIn = data.optInt("expires_in", 0);
                    result.appKey = data.optString("app_key", "");
                    result.baseUrl = data.optString("base_url", baseUrl);
                } else {
                    result.success = false;
                    result.error = "响应缺少 data 字段: " + respStr;
                }
            } else {
                result.success = false;
                result.error = "鉴权失败(code=" + code + ", biz=" + bizCode + "): "
                        + json.optString("message", "");
            }
        } catch (Exception e) {
            result.success = false;
            result.error = "请求失败: " + e.getMessage();
        } finally {
            if (conn != null) {
                conn.disconnect();
            }
        }
        return result;
    }

    /** 鉴权结果。 */
    public static class AuthResult {
        public boolean success;
        public String jwtToken;
        public int expiresIn;
        public String appKey;
        public String baseUrl;
        public String error;
    }
}
