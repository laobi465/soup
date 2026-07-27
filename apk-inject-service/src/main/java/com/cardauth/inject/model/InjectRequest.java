package com.cardauth.inject.model;

import com.fasterxml.jackson.annotation.JsonProperty;

/**
 * 注入请求体。
 *
 * <p>JSON 字段使用 snake_case，与 PHP 端 {@code ApkInjectJob::callInjectService} 的 payload 对齐：
 * {@code task_id / source_path / app_key / app_secret / base_url}。
 */
public class InjectRequest {

    @JsonProperty("task_id")
    private Long taskId;

    @JsonProperty("source_path")
    private String sourcePath;

    @JsonProperty("app_key")
    private String appKey;

    @JsonProperty("app_secret")
    private String appSecret;

    @JsonProperty("base_url")
    private String baseUrl;

    public Long getTaskId() {
        return taskId;
    }

    public void setTaskId(Long taskId) {
        this.taskId = taskId;
    }

    public String getSourcePath() {
        return sourcePath;
    }

    public void setSourcePath(String sourcePath) {
        this.sourcePath = sourcePath;
    }

    public String getAppKey() {
        return appKey;
    }

    public void setAppKey(String appKey) {
        this.appKey = appKey;
    }

    public String getAppSecret() {
        return appSecret;
    }

    public void setAppSecret(String appSecret) {
        this.appSecret = appSecret;
    }

    public String getBaseUrl() {
        return baseUrl;
    }

    public void setBaseUrl(String baseUrl) {
        this.baseUrl = baseUrl;
    }
}
