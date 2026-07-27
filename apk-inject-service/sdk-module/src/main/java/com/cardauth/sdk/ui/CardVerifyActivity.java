package com.cardauth.sdk.ui;

import android.app.Activity;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.text.InputType;
import android.util.Log;
import android.view.Gravity;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import com.cardauth.sdk.KamiProxyApplication;
import com.cardauth.sdk.model.ApiResponse;
import com.cardauth.sdk.model.CardVerifyResult;

/**
 * 卡密验证 UI（Task 8 / I6 实装）。
 *
 * <p>由 {@link KamiProxyApplication} 的 {@code CardVerifyLifecycle} 在首个 Activity 启动时
 * 拉起（若卡密未校验）。界面极简：卡密输入框 + 验证按钮 + 结果提示，
 * 全部用代码构建（不依赖宿主资源，避免 R.id 冲突）。
 *
 * <p><b>鉴权模式</b>（Task 2 / Task 3）：本 Activity 不再自行读取 app_secret 或构造
 * {@code CardAuthClient}，而是委托给 {@link KamiProxyApplication#verifyCard}，由 Application
 * 用已换取的 JWT 通过 Bearer 鉴权调用卡密验证接口（app_secret 永不落地 APK）。
 */
public class CardVerifyActivity extends Activity {

    private static final String TAG = "KamiVerifyUI";

    private EditText cardInput;
    private TextView resultText;
    private Button verifyButton;
    private final Handler uiHandler = new Handler(Looper.getMainLooper());

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

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

    private void doVerify() {
        String cardNo = cardInput.getText() == null ? "" : cardInput.getText().toString().trim();
        if (cardNo.isEmpty()) {
            toast("请输入卡密");
            return;
        }

        // 获取 Application（应为 KamiProxyApplication）
        android.app.Application app = getApplication();
        if (!(app instanceof KamiProxyApplication)) {
            resultText.setText("SDK 初始化异常（Application 不是 KamiProxyApplication）");
            return;
        }
        KamiProxyApplication kamiApp = (KamiProxyApplication) app;

        verifyButton.setEnabled(false);
        resultText.setText("验证中...");

        final String fingerprint = Build.FINGERPRINT + "_" + Build.SERIAL;
        final String deviceName = Build.MODEL;
        final String card = cardNo;

        // 委托给 Application 的 verifyCard（使用 JWT Bearer 鉴权，不依赖 app_secret）
        new Thread(() -> {
            final ApiResponse<CardVerifyResult> result = kamiApp.verifyCard(card, fingerprint, deviceName);
            uiHandler.post(() -> {
                verifyButton.setEnabled(true);
                if (result.getCode() == 0 && result.getData() != null) {
                    CardVerifyResult d = result.getData();
                    resultText.setText("验证成功\n状态: " + d.getStatusText()
                            + "\n到期: " + (d.isPermanent() ? "永久" : d.getExpireTime()));
                    toast("验证成功");
                    // 验证通过，结束验证界面，回到主 Activity
                    finish();
                } else {
                    resultText.setText("验证失败: " + result.getMessage());
                    toast("验证失败");
                }
            });
        }).start();
    }

    @Override
    public void onBackPressed() {
        // 禁止返回键跳过卡密校验（防止绕过）
        toast("请先完成卡密验证");
    }

    private void toast(String msg) {
        Toast.makeText(this, msg, Toast.LENGTH_SHORT).show();
    }
}
