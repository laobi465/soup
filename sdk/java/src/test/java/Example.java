package com.cardauth.sdk;

import com.cardauth.sdk.model.ApiResponse;
import com.cardauth.sdk.model.CardVerifyResult;
import com.google.gson.Gson;

public class Example {

    public static void main(String[] args) {
        String appKey = "your_app_key";
        String appSecret = "your_app_secret";
        String baseUrl = "http://localhost";

        CardAuthClient client = new CardAuthClient(appKey, appSecret, baseUrl);
        Gson gson = new Gson();

        String cardNo = "TEST-CARD-001";
        String deviceFingerprint = "device-abc-123";
        String deviceName = "My PC";

        System.out.println("=== 卡密查询 ===");
        ApiResponse<CardVerifyResult> queryResult = client.query(cardNo);
        printResult(queryResult, gson);
        System.out.println();

        System.out.println("=== 卡密激活 ===");
        ApiResponse<CardVerifyResult> activateResult = client.activate(cardNo, deviceFingerprint, deviceName);
        printResult(activateResult, gson);
        System.out.println();

        System.out.println("=== 卡密验证 ===");
        ApiResponse<CardVerifyResult> verifyResult = client.verify(cardNo, deviceFingerprint, deviceName);
        printResult(verifyResult, gson);
        System.out.println();

        System.out.println("=== 心跳 ===");
        ApiResponse<Object> heartbeatResult = client.heartbeat(cardNo, deviceFingerprint);
        printResult(heartbeatResult, gson);
        System.out.println();

        System.out.println("=== 在线人数 ===");
        ApiResponse<?> onlineResult = client.onlineCount();
        printResult(onlineResult, gson);
        System.out.println();

        System.out.println("=== 系统公告 ===");
        ApiResponse<?> announcementResult = client.announcement();
        printResult(announcementResult, gson);
    }

    private static void printResult(ApiResponse<?> result, Gson gson) {
        System.out.println("Code: " + result.getCode());
        System.out.println("Message: " + result.getMessage());
        if (result.getData() != null) {
            System.out.println("Data: " + gson.toJson(result.getData()));
        }
    }
}
