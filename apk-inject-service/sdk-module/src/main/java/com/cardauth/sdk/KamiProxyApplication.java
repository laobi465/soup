package com.cardauth.sdk;

import com.cardauth.sdk.model.ApiResponse;
import com.cardauth.sdk.model.CardVerifyResult;

import android.app.Application;
import android.content.Context;
import android.content.pm.ApplicationInfo;
import android.content.pm.PackageManager;
import android.os.Bundle;
import android.util.Log;

import java.lang.reflect.Field;
import java.lang.reflect.Method;
import java.util.List;

/**
 * 注入用代理 Application（Task 6 核心）。
 *
 * <p>注入流水线会把目标 APK 的 {@code <application android:name>} 替换为本类
 * （见 {@code ManifestModifier#KAMI_PROXY_APPLICATION}），并把原 Application 类名与
 * SDK 配置写入 meta-data：
 * <ul>
 *   <li>{@code kami_original_application} —— 原 Application 全限定名</li>
 *   <li>{@code kami_app_key} / {@code kami_app_secret} / {@code kami_base_url} —— 卡密校验配置</li>
 * </ul>
 *
 * <p>启动流程：
 * <ol>
 *   <li>{@link #attachBaseContext(Context)}：读取 meta-data，反射调用 MultiDex.install
 *       （不硬依赖 AndroidX，保证最大兼容性）；</li>
 *   <li>{@link #onCreate()}：反射创建并替换回原 Application（ActivityThread.mInitialApplication /
 *       mAllApplications），再异步发起卡密校验。</li>
 * </ol>
 */
public class KamiProxyApplication extends Application {

    private static final String TAG = "KamiProxy";
    private static final String META_ORIGINAL_APP = "kami_original_application";
    private static final String META_APP_KEY = "kami_app_key";
    private static final String META_APP_SECRET = "kami_app_secret";
    private static final String META_BASE_URL = "kami_base_url";

    private Application originalApplication;
    private String appKey;
    private String appSecret;
    private String baseUrl;

    @Override
    protected void attachBaseContext(Context base) {
        super.attachBaseContext(base);
        Log.i(TAG, "KamiProxyApplication attachBaseContext");

        // 读取 meta-data 配置
        readMetaData(base);

        // MultiDex 支持（确保能加载到 kami-sdk dex）
        try {
            Class<?> multidex = Class.forName("androidx.multidex.MultiDex");
            Method install = multidex.getMethod("install", Context.class);
            install.invoke(null, base);
        } catch (Exception e) {
            // 如果没有 MultiDex 库，尝试手动加载
            Log.w(TAG, "MultiDex not available, skipping");
        }
    }

    @Override
    public void onCreate() {
        super.onCreate();
        Log.i(TAG, "KamiProxyApplication onCreate");

        // 创建并替换为原 Application
        try {
            replaceWithOriginalApplication();
        } catch (Exception e) {
            Log.e(TAG, "Failed to replace application", e);
        }

        // 启动卡密校验（异步，不阻塞启动）
        startCardVerification();
    }

    private void readMetaData(Context ctx) {
        try {
            ApplicationInfo ai = ctx.getPackageManager()
                .getApplicationInfo(ctx.getPackageName(), PackageManager.GET_META_DATA);
            Bundle metaData = ai.metaData;
            if (metaData != null) {
                appKey = metaData.getString(META_APP_KEY, "");
                appSecret = metaData.getString(META_APP_SECRET, "");
                baseUrl = metaData.getString(META_BASE_URL, "");
            }
        } catch (Exception e) {
            Log.e(TAG, "Failed to read meta-data", e);
        }
    }

    private void replaceWithOriginalApplication() throws Exception {
        if (appKey == null || appKey.isEmpty()) {
            Log.w(TAG, "No original application class specified");
            return;
        }

        // 获取原 Application 类名（从 meta-data）
        String originalAppClass = "";
        try {
            ApplicationInfo ai = getPackageManager()
                .getApplicationInfo(getPackageName(), PackageManager.GET_META_DATA);
            originalAppClass = ai.metaData.getString(META_ORIGINAL_APP, "");
        } catch (Exception e) {
            Log.e(TAG, "Failed to get original app class", e);
            return;
        }

        if (originalAppClass.isEmpty() || originalAppClass.equals("android.app.Application")) {
            Log.i(TAG, "No custom Application, using default");
            return;
        }

        // 反射创建原 Application 实例
        Class<?> clazz = Class.forName(originalAppClass);
        originalApplication = (Application) clazz.newInstance();

        // 调用原 Application 的 attachBaseContext
        Method attach = Application.class.getDeclaredMethod("attach", Context.class);
        attach.setAccessible(true);
        attach.invoke(originalApplication, getBaseContext());

        // 调用原 Application 的 onCreate
        originalApplication.onCreate();

        // 反射替换 ActivityThread 中的 Application 引用
        replaceActivityThreadApplication(originalAppClass);

        Log.i(TAG, "Original application replaced: " + originalAppClass);
    }

    private void replaceActivityThreadApplication(String originalAppClass) {
        try {
            // 获取 ActivityThread
            Class<?> activityThreadClass = Class.forName("android.app.ActivityThread");
            Method currentActivityThread = activityThreadClass.getMethod("currentActivityThread");
            Object activityThread = currentActivityThread.invoke(null);

            // 替换 mInitialApplication
            Field mInitialApp = activityThreadClass.getDeclaredField("mInitialApplication");
            mInitialApp.setAccessible(true);
            mInitialApp.set(activityThread, originalApplication);

            // 替换 mAllApplications
            Field mAllApps = activityThreadClass.getDeclaredField("mAllApplications");
            mAllApps.setAccessible(true);
            @SuppressWarnings("unchecked")
            List<Application> allApps = (List<Application>) mAllApps.get(activityThread);
            for (int i = allApps.size() - 1; i >= 0; i--) {
                if (allApps.get(i) == this) {
                    allApps.set(i, originalApplication);
                }
            }

            // 替换 LoadedApk 中的 mApplication
            Field mPackages = activityThreadClass.getDeclaredField("mPackages");
            mPackages.setAccessible(true);
            // ... 复杂的反射替换逻辑

        } catch (Exception e) {
            Log.e(TAG, "Failed to replace in ActivityThread", e);
        }
    }

    private void startCardVerification() {
        // 在新线程中执行卡密校验
        new Thread(() -> {
            try {
                CardAuthClient client = new CardAuthClient(appKey, appSecret, baseUrl);
                // 获取设备指纹
                String fingerprint = getDeviceFingerprint();
                String deviceName = android.os.Build.MODEL;

                // 调用 verify 接口
                ApiResponse<CardVerifyResult> result = client.verify("", fingerprint, deviceName);

                if (result.getCode() == 200) {
                    Log.i(TAG, "Card verification passed");
                } else {
                    Log.w(TAG, "Card verification failed: " + result.getMessage());
                    // TODO: 弹出卡密输入 UI
                }
            } catch (Exception e) {
                Log.e(TAG, "Card verification error", e);
            }
        }).start();
    }

    private String getDeviceFingerprint() {
        return android.os.Build.FINGERPRINT + "_" + android.os.Build.SERIAL;
    }
}
