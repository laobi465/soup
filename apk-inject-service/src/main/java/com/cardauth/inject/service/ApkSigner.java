package com.cardauth.inject.service;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;

import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.concurrent.TimeUnit;

/**
 * APK 签名器：调用 Android Build Tools 的 {@code apksigner} 完成 v1+v2+v3 签名。
 *
 * <p>使用平台统一 keystore（生产环境通过环境变量/挂载替换占位证书）。
 */
@Service
public class ApkSigner {

    private static final Logger log = LoggerFactory.getLogger(ApkSigner.class);

    @Value("${apk.apksigner-path:apksigner}")
    private String apksignerPath;

    public void sign(Path input, Path output, String keystorePath,
                     String keystorePassword, String alias) throws Exception {
        if (!Files.exists(Path.of(keystorePath))) {
            throw new RuntimeException("keystore 不存在: " + keystorePath
                    + "（请配置 apk.keystore-path 指向有效签名证书）");
        }
        Files.deleteIfExists(output);

        // key-pass 与 ks-pass 一致（平台证书通常如此）；如需分离可扩展配置项
        ProcessBuilder pb = new ProcessBuilder(
                apksignerPath, "sign",
                "--ks", keystorePath,
                "--ks-key-alias", alias,
                "--ks-pass", "pass:" + keystorePassword,
                "--key-pass", "pass:" + keystorePassword,
                "--v1-signing-enabled", "true",
                "--v2-signing-enabled", "true",
                "--v3-signing-enabled", "true",
                "--out", output.toString(),
                input.toString()
        );
        pb.redirectErrorStream(true);
        Process p = pb.start();
        String out = new String(p.getInputStream().readAllBytes(), StandardCharsets.UTF_8);
        boolean finished = p.waitFor(5, TimeUnit.MINUTES);
        if (!finished) {
            p.destroyForcibly();
            throw new RuntimeException("apksigner 签名超时（5 分钟）: " + out);
        }
        int code = p.exitValue();
        if (code != 0) {
            throw new RuntimeException("apksigner 签名失败(code=" + code + "): " + out);
        }
        log.info("APK签名完成: {}", output);
    }
}
