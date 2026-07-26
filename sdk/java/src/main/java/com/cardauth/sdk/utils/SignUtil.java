package com.cardauth.sdk.utils;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.security.InvalidKeyException;
import java.security.NoSuchAlgorithmException;
import java.util.UUID;

public class SignUtil {

    private static final String HMAC_SHA256 = "HmacSHA256";

    public static String generateNonce() {
        return UUID.randomUUID().toString().replace("-", "").substring(0, 16);
    }

    public static String getTimestamp() {
        return String.valueOf(System.currentTimeMillis() / 1000);
    }

    public static String hmacSha256(String data, String key) {
        try {
            Mac mac = Mac.getInstance(HMAC_SHA256);
            SecretKeySpec secretKey = new SecretKeySpec(
                    key.getBytes(StandardCharsets.UTF_8),
                    HMAC_SHA256
            );
            mac.init(secretKey);
            byte[] hashBytes = mac.doFinal(data.getBytes(StandardCharsets.UTF_8));
            StringBuilder sb = new StringBuilder();
            for (byte b : hashBytes) {
                sb.append(String.format("%02x", b));
            }
            return sb.toString();
        } catch (NoSuchAlgorithmException | InvalidKeyException e) {
            throw new RuntimeException("HMAC-SHA256签名失败", e);
        }
    }

    public static String sign(String method, String path, String timestamp, String nonce, String body, String appSecret) {
        String signString = method.toUpperCase() + path + timestamp + nonce + body;
        return hmacSha256(signString, appSecret);
    }
}
