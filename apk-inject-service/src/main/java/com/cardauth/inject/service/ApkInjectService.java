package com.cardauth.inject.service;

import com.cardauth.inject.model.ApkInfo;
import com.cardauth.inject.model.InjectRequest;
import com.cardauth.inject.model.InjectResponse;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;
import software.amazon.awssdk.core.sync.RequestBody;
import software.amazon.awssdk.services.s3.S3Client;
import software.amazon.awssdk.services.s3.model.GetObjectRequest;
import software.amazon.awssdk.services.s3.model.PutObjectRequest;

import java.nio.file.Files;
import java.nio.file.Path;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;

/**
 * APK 注入流水线编排器。
 *
 * <p>步骤：
 * <ol>
 *   <li>创建临时目录</li>
 *   <li>从 MinIO 下载源 APK</li>
 *   <li>解析 APK（aapt2）</li>
 *   <li>加固检测</li>
 *   <li>dex 合并：追加 kami-sdk.dex 为 classes(N+1).dex</li>
 *   <li>Manifest 修改：替换 Application 为 KamiProxyApplication，保存原类名，补 INTERNET 权限</li>
 *   <li>zipalign 对齐</li>
 *   <li>apksigner 签名（v1+v2+v3）</li>
 *   <li>上传输出 APK 到 MinIO</li>
 * </ol>
 *
 * <p>所有临时文件落在系统临时目录下，处理完成自动清理。
 */
@Service
public class ApkInjectService {

    private static final Logger log = LoggerFactory.getLogger(ApkInjectService.class);

    @Autowired
    private S3Client s3Client;

    @Autowired
    private ApkParser apkParser;

    @Autowired
    private ShellDetector shellDetector;

    @Autowired
    private ZipBombChecker zipBombChecker;

    @Autowired
    private DexMerger dexMerger;

    @Autowired
    private ManifestModifier manifestModifier;

    @Autowired
    private ApkSigner apkSigner;

    @Value("${minio.bucket}")
    private String bucket;

    @Value("${apk.keystore-path}")
    private String keystorePath;

    @Value("${apk.keystore-password}")
    private String keystorePassword;

    @Value("${apk.keystore-alias}")
    private String keystoreAlias;

    @Value("${apk.sdk-dex-path}")
    private String sdkDexPath;

    @Value("${apk.zipalign-path:zipalign}")
    private String zipalignPath;

    public InjectResponse inject(InjectRequest request) {
        InjectResponse response = new InjectResponse();
        List<String> steps = new ArrayList<>();
        Path tempDir = null;

        try {
            // 1. 创建临时目录
            tempDir = Files.createTempDirectory("apk_inject_");
            steps.add("创建临时目录: " + tempDir);

            // 2. 从 MinIO 下载源 APK
            Path sourceApk = tempDir.resolve("source.apk");
            downloadFromMinio(request.getSourcePath(), sourceApk);
            steps.add("下载源APK: " + request.getSourcePath());

            // 2.5 APK 安全校验：ZIP 魔数 + ZIP 炸弹检测（在解析前拦截恶意文件）
            zipBombChecker.check(sourceApk);
            steps.add("APK安全校验: 魔数+ZIP炸弹检测通过");

            // 3. 解析 APK
            ApkInfo apkInfo = apkParser.parse(sourceApk);
            steps.add("解析APK: package=" + apkInfo.getPackageName()
                    + ", application=" + apkInfo.getApplicationName()
                    + ", minSdk=" + apkInfo.getMinSdkVersion()
                    + ", targetSdk=" + apkInfo.getTargetSdkVersion());

            // 4. 加固检测
            if (shellDetector.isProtected(apkInfo)) {
                String shellName = shellDetector.getShellName(apkInfo);
                steps.add("加固检测: 发现 " + shellName);
                response.setSuccess(false);
                response.setError("检测到加固(" + shellName + ")，暂不支持");
                response.setSteps(steps);
                return response;
            }
            steps.add("加固检测: 未加固");

            // 5. dex 合并 - 将 kami-sdk.dex 追加到 APK
            Path mergedApk = tempDir.resolve("merged.apk");
            dexMerger.mergeSdkDex(sourceApk, mergedApk, sdkDexPath);
            steps.add("dex合并: kami-sdk.dex 已追加");

            // 6. 修改 AndroidManifest（Application 替换 + meta-data + 权限）
            Path manifestModifiedApk = tempDir.resolve("manifest_modified.apk");
            manifestModifier.modify(mergedApk, manifestModifiedApk, apkInfo, request);
            steps.add("Manifest修改: Application替换为KamiProxyApplication");

            // 7. zipalign
            Path alignedApk = tempDir.resolve("aligned.apk");
            zipalign(manifestModifiedApk, alignedApk);
            steps.add("zipalign对齐完成");

            // 8. 签名（v1+v2+v3）
            Path signedApk = tempDir.resolve("signed.apk");
            apkSigner.sign(alignedApk, signedApk, keystorePath, keystorePassword, keystoreAlias);
            steps.add("APK签名完成(v1+v2+v3)");

            // 9. 上传到 MinIO
            String outputPath = "apk-output/" + request.getTaskId() + "/injected.apk";
            uploadToMinio(signedApk, outputPath);
            steps.add("上传输出APK: " + outputPath);

            response.setSuccess(true);
            response.setOutputPath(outputPath);
            response.setSteps(steps);

        } catch (Exception e) {
            log.error("inject failed, taskId={}", request.getTaskId(), e);
            response.setSuccess(false);
            response.setError(e.getMessage());
            response.setSteps(steps);
        } finally {
            if (tempDir != null) {
                cleanupTempDir(tempDir);
            }
        }

        return response;
    }

    private void downloadFromMinio(String key, Path localPath) {
        GetObjectRequest req = GetObjectRequest.builder()
                .bucket(bucket)
                .key(key)
                .build();
        s3Client.getObject(req, localPath);
    }

    private void uploadToMinio(Path localPath, String key) {
        PutObjectRequest req = PutObjectRequest.builder()
                .bucket(bucket)
                .key(key)
                .build();
        s3Client.putObject(req, RequestBody.fromFile(localPath));
    }

    /**
     * 调用 zipalign 对齐 APK（未压缩条目 4 字节对齐）。
     *
     * <p>参数：-f 强制覆盖输出；-p 对未压缩的 .so 保持页对齐（4KiB）。
     */
    private void zipalign(Path input, Path output) throws Exception {
        Files.deleteIfExists(output);
        ProcessBuilder pb = new ProcessBuilder(
                zipalignPath, "-f", "-p", "4",
                input.toString(), output.toString());
        pb.redirectErrorStream(true);
        Process p = pb.start();
        String out = new String(p.getInputStream().readAllBytes());
        int code = p.waitFor();
        if (code != 0) {
            throw new RuntimeException("zipalign失败(code=" + code + "): " + out);
        }
    }

    private void cleanupTempDir(Path dir) {
        try {
            Files.walk(dir)
                    .sorted(Comparator.reverseOrder())
                    .forEach(p -> {
                        try {
                            Files.delete(p);
                        } catch (Exception ignored) {
                            // 单文件删除失败不影响整体清理
                        }
                    });
        } catch (Exception ignored) {
            // 清理失败不向上抛
        }
    }
}
