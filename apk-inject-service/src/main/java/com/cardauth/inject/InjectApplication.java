package com.cardauth.inject;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;

/**
 * APK 云端注入微服务启动类（Task 5）。
 *
 * <p>对外契约（与 PHP 端 {@code ApkInjectJob} 对接）：
 * <ul>
 *   <li>POST /api/v1/inject，请求体 {@code task_id/source_path/app_key/app_secret/base_url}（snake_case）</li>
 *   <li>响应 {@code success/output_path/error}（snake_case）</li>
 * </ul>
 */
@SpringBootApplication
public class InjectApplication {

    public static void main(String[] args) {
        SpringApplication.run(InjectApplication.class, args);
    }
}
