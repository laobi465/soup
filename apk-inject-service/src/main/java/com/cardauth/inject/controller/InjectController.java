package com.cardauth.inject.controller;

import com.cardauth.inject.model.InjectRequest;
import com.cardauth.inject.model.InjectResponse;
import com.cardauth.inject.service.ApkInjectService;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import java.util.LinkedHashMap;
import java.util.Map;

/**
 * 注入微服务 HTTP 入口。
 */
@RestController
@RequestMapping("/api/v1")
public class InjectController {

    private static final Logger log = LoggerFactory.getLogger(InjectController.class);

    @Autowired
    private ApkInjectService injectService;

    /**
     * 健康检查。
     */
    @GetMapping("/health")
    public ResponseEntity<Map<String, Object>> health() {
        Map<String, Object> body = new LinkedHashMap<>();
        body.put("status", "UP");
        body.put("timestamp", System.currentTimeMillis());
        return ResponseEntity.ok(body);
    }

    /**
     * 执行 APK 注入。
     *
     * <p>注意：即便内部抛异常，也以 200 + {@code success=false} 返回，
     * 与 PHP 端 {@code ApkInjectJob} 的处理逻辑（按 body.success 分支）保持一致。
     */
    @PostMapping("/inject")
    public ResponseEntity<InjectResponse> inject(@RequestBody InjectRequest request) {
        try {
            InjectResponse response = injectService.inject(request);
            return ResponseEntity.ok(response);
        } catch (Exception e) {
            log.error("inject unexpected error, taskId={}", request.getTaskId(), e);
            InjectResponse response = new InjectResponse();
            response.setSuccess(false);
            response.setError("内部错误: " + e.getMessage());
            return ResponseEntity.ok(response);
        }
    }
}
