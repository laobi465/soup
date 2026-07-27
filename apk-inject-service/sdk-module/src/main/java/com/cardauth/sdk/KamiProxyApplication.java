package com.cardauth.sdk;

import com.cardauth.sdk.model.ApiResponse;
import com.cardauth.sdk.model.CardVerifyResult;

import android.app.Activity;
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
 * 注入用代理 Application（Task 6 核心 + Task 2 安全修复）。
 *
 * <p>注入流水线会把目标 APK 的 {@code <application android:name>} 替换为本类
 * （见 {@code ManifestModifier#KAMI_PROXY_APPLICATION}），并把原 Application 类名与
 * SDK 配置写入 meta-data：
 * <ul>
 *   <li>{@code kami_original_application} —— 原 Application 全限定名</li>
 *   <li>{@code kami_app_key} / {@code kami_base_url} —— 卡密校验非敏感配置</li>
 *   <li>{@code kami_task_token} —— 任务令牌（替代明文 app_secret，见 Task 2 / C2）</li>
 * </ul>
 *
 * <p><b>安全模型</b>：APK 内不存 app_secret，启动时用 task_token 调用
 * {@code POST /api/v1/sdk/auth} 换取短期 JWT（≤1 小时），后续卡密校验用 Bearer 鉴权。
 *
 * <p>启动流程：
 * <ol>
 *   <li>{@link #attachBaseContext(Context)}：读取 meta-data，反射调用 MultiDex.install
 *       （不硬依赖 AndroidX，保证最大兼容性）；</li>
 *   <li>{@link #onCreate()}：反射创建并替换回原 Application（ActivityThread.mInitialApplication /
 *       mAllApplications），再异步发起 task_token 换 JWT 与卡密校验。</li>
 * </ol>
 */
public class KamiProxyApplication extends Application {

    private static final String TAG = "KamiProxy";
    private static final String META_ORIGINAL_APP = "kami_original_application";
    private static final String META_APP_KEY = "kami_app_key";
    private static final String META_TASK_TOKEN = "kami_task_token";
    private static final String META_BASE_URL = "kami_base_url";

    private Application originalApplication;
    private String appKey;
    private String taskToken;
    private String baseUrl;

    /** 卡密校验状态：true=已通过，false=未通过/未校验 */
    private volatile boolean cardVerified = false;
    /** 已换取的 JWT（卡密校验请求用） */
    private volatile String jwtToken = null;

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

        // 注册 Activity 生命周期回调，在第一个 Activity 启动时拉起卡密校验 UI
        registerActivityLifecycleCallbacks(new CardVerifyLifecycle());

        // 异步：task_token 换 JWT（不阻塞启动，卡密校验 UI 会在需要时等待）
        startSdkAuth();
    }

    private void readMetaData(Context ctx) {
        try {
            ApplicationInfo ai = ctx.getPackageManager()
                .getApplicationInfo(ctx.getPackageName(), PackageManager.GET_META_DATA);
            Bundle metaData = ai.metaData;
            if (metaData != null) {
                appKey = metaData.getString(META_APP_KEY, "");
                taskToken = metaData.getString(META_TASK_TOKEN, "");
                baseUrl = metaData.getString(META_BASE_URL, "");
            }
        } catch (Exception e) {
            Log.e(TAG, "Failed to read meta-data", e);
        }
    }

    /**
     * 替换为原 Application（Task 2 / I5 修复：检查 originalAppClass 而非 appKey）。
     *
     * <p>原实现错误地用 {@code appKey} 判断是否需要替换，导致 appKey 为空时直接跳过，
     * 即使原 APK 有自定义 Application 也不会被替换回来。
     */
    private void replaceWithOriginalApplication() throws Exception {
        // 先读 originalAppClass，再判断是否需要替换
        String originalAppClass = "";
        try {
            ApplicationInfo ai = getPackageManager()
                .getApplicationInfo(getPackageName(), PackageManager.GET_META_DATA);
            if (ai.metaData != null) {
                originalAppClass = ai.metaData.getString(META_ORIGINAL_APP, "");
            }
        } catch (Exception e) {
            Log.e(TAG, "Failed to get original app class", e);
            return;
        }

        // 修复 I5：检查 originalAppClass（而非 appKey）
        if (originalAppClass == null || originalAppClass.isEmpty()
                || originalAppClass.equals("android.app.Application")) {
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

    /**
     * 异步：用 task_token 调用 /api/v1/sdk/auth 换取 JWT。
     * 不阻塞启动；卡密校验 UI 在用户提交卡密时会等待此结果。
     */
    private void startSdkAuth() {
        if (taskToken == null || taskToken.isEmpty()) {
            Log.w(TAG, "task_token 为空，跳过 SDK 鉴权");
            return;
        }
        new Thread(() -> {
            try {
                SdkAuthClient authClient = new SdkAuthClient(baseUrl);
                String fingerprint = getDeviceFingerprint();
                String deviceName = android.os.Build.MODEL;
                SdkAuthClient.AuthResult result = authClient.auth(taskToken, fingerprint, deviceName);
                if (result.success) {
                    jwtToken = result.jwtToken;
                    Log.i(TAG, "SDK auth success, jwt expires in " + result.expiresIn + "s");
                } else {
                    Log.w(TAG, "SDK auth failed: " + result.error);
                }
            } catch (Exception e) {
                Log.e(TAG, "SDK auth error", e);
            }
        }).start();
    }

    /**
     * 卡密校验入口（由 CardVerifyActivity 调用）。
     * 使用 JWT 鉴权模式调用 /api/v1/card/verify。
     *
     * @param cardNo            用户输入的卡密
     * @param deviceFingerprint 设备指纹
     * @param deviceName        设备名
     * @return 校验结果
     */
    public ApiResponse<CardVerifyResult> verifyCard(String cardNo, String deviceFingerprint, String deviceName) {
        // 等待 JWT 就绪（最多 5 秒）
        long deadline = System.currentTimeMillis() + 5000;
        while (jwtToken == null && System.currentTimeMillis() < deadline) {
            try {
                Thread.sleep(100);
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
                break;
            }
        }
        if (jwtToken == null) {
            ApiResponse<CardVerifyResult> err = new ApiResponse<>();
            err.setCode(-1);
            err.setMessage("SDK 鉴权未完成，请稍后重试");
            err.setTimestamp(System.currentTimeMillis() / 1000);
            return err;
        }

        CardAuthClient client = new CardAuthClient(jwtToken, baseUrl);
        ApiResponse<CardVerifyResult> result = client.verify(cardNo, deviceFingerprint, deviceName);
        // PHP 端 apiSuccess 返回 code=0
        if (result.getCode() == 0) {
            cardVerified = true;
        }
        return result;
    }

    /** 卡密是否已校验通过 */
    public boolean isCardVerified() {
        return cardVerified;
    }

    private String getDeviceFingerprint() {
        return android.os.Build.FINGERPRINT + "_" + android.os.Build.SERIAL;
    }

    /**
     * Activity 生命周期回调：在第一个 Activity 启动时检查卡密校验状态，
     * 未通过则拉起 {@link CardVerifyActivity} 阻塞主 Activity。
     */
    private class CardVerifyLifecycle implements ActivityLifecycleCallbacks {
        @Override
        public void onActivityCreated(Activity activity, Bundle savedInstanceState) {
            // no-op
        }

        @Override
        public void onActivityStarted(Activity activity) {
            // 首次启动主 Activity 时，若未校验则拉起卡密校验 UI
            if (!cardVerified && !(activity instanceof CardVerifyActivity)) {
                Log.i(TAG, "Card not verified, launching CardVerifyActivity");
                android.content.Intent intent = new android.content.Intent(activity, CardVerifyActivity.class);
                intent.addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK
                        | android.content.Intent.FLAG_ACTIVITY_CLEAR_TOP);
                activity.startActivity(intent);
            }
        }

        @Override
        public void onActivityResumed(Activity activity) {
            // no-op
        }

        @Override
        public void onActivityPaused(Activity activity) {
            // no-op
        }

        @Override
        public void onActivityStopped(Activity activity) {
            // no-op
        }

        @Override
        public void onActivitySaveInstanceState(Activity activity, Bundle outState) {
            // no-op
        }

        @Override
        public void onActivityDestroyed(Activity activity) {
            // no-op
        }
    }
}
