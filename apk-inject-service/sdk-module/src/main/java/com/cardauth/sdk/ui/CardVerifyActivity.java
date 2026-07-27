package com.cardauth.sdk.ui;

import android.app.Activity;
import android.content.Context;
import android.content.pm.ApplicationInfo;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.Bundle;
import android.os.Looper;
import android.os.Handler;
import android.text.InputType;
import android.util.Log;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import com.cardauth.sdk.CardAuthClient;
import com.cardauth.sdk.model.ApiResponse;
import com.cardauth.sdk.model.CardVerifyResult;

/**
 * 卡密验证 UI（简化版）。
 *
 * <p>由 {@code KamiProxyApplication} 在校验失败时拉起（或由宿主在需要时启动）。
 * 界面极简：一个卡密输入框 + 一个“验证”按钮 + 一行结果提示，
 * 全部用代码构建（不依赖宿主资源，避免 R.id 冲突）。
 *
 * <p>配置（appKey/appSecret/baseUrl）从 AndroidManifest 的 meta-data 读取，
 * 与 {@code KamiProxyApplication} 保持一致。
 */
public class CardVerifyActivity extends Activity {

    private static final String TAG = "KamiVerifyUI";
    private static final String META_APP_KEY = "kami_app_key";
    private static final String META_APP_SECRET = "kami_app_secret";
    private static final String META_BASE_URL = "kami_base_url";

    private EditText cardInput;
    private TextView resultText;
    private Button verifyButton;
    private final Handler uiHandler = new Handler(Looper.getMainLooper());

    private String appKey;
    private String appSecret;
    private String baseUrl;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        readMetaData(this);

        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setGravity(Gravity.CENTER);
        root.setPadding(48, 48, 48, 48);

        TextView title = new TextView(this);
        title.setText("卡密验证");
        title.setTextSize(20f);
        title.setGravity(Gravity.CENTER);
        title.setPadding(0, 0, 0, 32);

        cardInput = new EditText(this);
        cardInput.setHint("请输入卡密");
        cardInput.setInputType(InputType.TYPE_CLASS_TEXT);
        cardInput.setSingleLine(true);

        verifyButton = new Button(this);
        verifyButton.setText("验证");
        verifyButton.setOnClickListener(v -> doVerify());

        resultText = new TextView(this);
        resultText.setTextSize(14f);
        resultText.setPadding(0, 32, 0, 0);
        resultText.setGravity(Gravity.CENTER);

        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.WRAP_CONTENT);

        root.addView(title, lp);
        root.addView(cardInput, lp);
        root.addView(verifyButton, lp);
        root.addView(resultText, lp);

        setContentView(root);
    }

    private void readMetaData(Context ctx) {
        try {
            ApplicationInfo ai = ctx.getPackageManager()
                    .getApplicationInfo(ctx.getPackageName(), PackageManager.GET_META_DATA);
            if (ai.metaData != null) {
                appKey = ai.metaData.getString(META_APP_KEY, "");
                appSecret = ai.metaData.getString(META_APP_SECRET, "");
                baseUrl = ai.metaData.getString(META_BASE_URL, "");
            }
        } catch (Exception e) {
            Log.e(TAG, "Failed to read meta-data", e);
        }
    }

    private void doVerify() {
        String cardNo = cardInput.getText() == null ? "" : cardInput.getText().toString().trim();
        if (cardNo.isEmpty()) {
            toast("请输入卡密");
            return;
        }
        if (appKey == null || appKey.isEmpty() || baseUrl == null || baseUrl.isEmpty()) {
            resultText.setText("SDK 配置缺失（appKey/baseUrl 未设置）");
            return;
        }

        verifyButton.setEnabled(false);
        resultText.setText("验证中...");

        final String fingerprint = Build.FINGERPRINT + "_" + Build.SERIAL;
        final String deviceName = Build.MODEL;
        final String card = cardNo;

        new Thread(() -> {
            CardAuthClient client = new CardAuthClient(appKey, appSecret, baseUrl);
            final ApiResponse<CardVerifyResult> result = client.verify(card, fingerprint, deviceName);
            uiHandler.post(() -> {
                verifyButton.setEnabled(true);
                if (result.isSuccess() && result.getData() != null) {
                    CardVerifyResult d = result.getData();
                    resultText.setText("验证成功\n状态: " + d.getStatusText()
                            + "\n到期: " + (d.isPermanent() ? "永久" : d.getExpireTime()));
                    toast("验证成功");
                    // 验证通过，结束验证界面
                    finish();
                } else {
                    resultText.setText("验证失败: " + result.getMessage());
                    toast("验证失败");
                }
            });
        }).start();
    }

    private void toast(String msg) {
        Toast.makeText(this, msg, Toast.LENGTH_SHORT).show();
    }
}
