package com.cardauth.inject.service;

import com.cardauth.inject.model.ApkInfo;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;

import java.nio.file.Path;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * APK 解析器：通过 {@code aapt2 dump badging} 提取包名、Application 类名与 SDK 版本。
 *
 * <p>AndroidManifest.xml 在 APK 中是二进制 AXML 格式，直接解析复杂度高；
 * 复用官方 {@code aapt2} 工具是最稳妥的方案（镜像内随 Android Build Tools 提供）。
 */
@Service
public class ApkParser {

    private static final Logger log = LoggerFactory.getLogger(ApkParser.class);

    private static final Pattern PACKAGE_PATTERN =
            Pattern.compile("package: name='([^']+)'");
    private static final Pattern NAME_ONLY_PATTERN =
            Pattern.compile("name='([^']+)'");
    private static final Pattern MIN_SDK_PATTERN =
            Pattern.compile("sdkVersion:'(\\d+)'");
    private static final Pattern TARGET_SDK_PATTERN =
            Pattern.compile("targetSdkVersion:'(\\d+)'");

    @Value("${apk.aapt2-path:aapt2}")
    private String aapt2Path;

    public ApkInfo parse(Path apkPath) throws Exception {
        ProcessBuilder pb = new ProcessBuilder(aapt2Path, "dump", "badging", apkPath.toString());
        pb.redirectErrorStream(true);
        Process p = pb.start();
        String output = new String(p.getInputStream().readAllBytes());
        int code = p.waitFor();
        if (code != 0) {
            throw new RuntimeException("aapt2解析失败(code=" + code + "): " + output);
        }

        ApkInfo info = new ApkInfo();

        // package: name='com.example.app'
        Matcher m = PACKAGE_PATTERN.matcher(output);
        if (m.find()) {
            info.setPackageName(m.group(1));
        }

        // application: label='App' icon='...' name='com.example.App'
        // aapt2 badging 中 application 行可能不含 name（未自定义 Application 时），
        // 此时回退为 android.app.Application。
        String applicationName = extractApplicationName(output);
        info.setApplicationName(applicationName != null ? applicationName : "android.app.Application");

        // sdkVersion:'14' ... targetSdkVersion:'33'
        m = MIN_SDK_PATTERN.matcher(output);
        if (m.find()) {
            info.setMinSdkVersion(parseIntSafe(m.group(1)));
        }
        m = TARGET_SDK_PATTERN.matcher(output);
        if (m.find()) {
            info.setTargetSdkVersion(parseIntSafe(m.group(1)));
        }

        log.info("APK解析完成: {}", info.getPackageName());
        return info;
    }

    /**
     * 从 aapt2 badging 输出中提取 Application 类名。
     *
     * <p>{@code application-label:'...'} 是应用名而非 Application 类；
     * 真正的 Application 类出现在 {@code application: ... name='xxx'} 行。
     */
    private String extractApplicationName(String output) {
        // 仅在 "application:" 行查找 name='...'；"application-label:" 是显示名，需跳过。
        for (String line : output.split("\\r?\\n")) {
            if (line.startsWith("application:")) {
                Matcher m = NAME_ONLY_PATTERN.matcher(line);
                if (m.find()) {
                    return m.group(1);
                }
            }
        }
        return null;
    }

    private int parseIntSafe(String s) {
        try {
            return Integer.parseInt(s);
        } catch (NumberFormatException e) {
            return 0;
        }
    }
}
