package com.cardauth.inject.service;

import com.cardauth.inject.model.ApkInfo;
import org.springframework.stereotype.Service;

import java.util.Map;

/**
 * 加固（壳）检测器。
 *
 * <p>通过识别 AndroidManifest 中已注册的 Application 类名前缀，判断是否使用了已知加固方案。
 * 命中即拒绝注入——对加固 APK 重打包通常会破坏壳的完整性，且无法生效。
 */
@Service
public class ShellDetector {

    /**
     * 已知加固方案的 Application 类名特征 → 方案名称。
     *
     * <p>Map.of 最多 10 个键值对，此处恰好 7 个。
     */
    private static final Map<String, String> SHELL_APPLICATIONS = Map.of(
            "com.stub.StubApp", "360加固",
            "com.tencent.StubShell.TxAppEntry", "腾讯乐固",
            "com.tencent.tinker.loader.app.TinkerApplication", "腾讯Tinker(非加固)",
            "com.bangclesdk.appwrapper.AppWrapper", "梆梆安全",
            "com.alibaba.moblie.secguard.ATraceApplication", "阿里聚安全",
            "com.sfx.shell.SfxApp", "爱加密",
            "com.secneo.apkwrapper.AW", "娜迦加固"
    );

    public boolean isProtected(ApkInfo info) {
        String appName = info.getApplicationName();
        if (appName == null) {
            return false;
        }
        for (String key : SHELL_APPLICATIONS.keySet()) {
            if (appName.contains(key)) {
                return true;
            }
        }
        return false;
    }

    public String getShellName(ApkInfo info) {
        String appName = info.getApplicationName();
        if (appName == null) {
            return "未知加固";
        }
        for (Map.Entry<String, String> entry : SHELL_APPLICATIONS.entrySet()) {
            if (appName.contains(entry.getKey())) {
                return entry.getValue();
            }
        }
        return "未知加固";
    }
}
