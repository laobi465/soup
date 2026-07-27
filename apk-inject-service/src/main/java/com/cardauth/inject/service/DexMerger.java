package com.cardauth.inject.service;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Service;

import java.io.FileOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.Enumeration;
import java.util.zip.CRC32;
import java.util.zip.ZipEntry;
import java.util.zip.ZipFile;
import java.util.zip.ZipOutputStream;

/**
 * dex 合并器：将 {@code kami-sdk.dex} 作为新的 {@code classes(N+1).dex} 追加到 APK。
 *
 * <p>实现要点（Java {@code ZipOutputStream} 无法原地追加，故采用“复制+新增”重打包）：
 * <ul>
 *   <li>逐条复制源 APK 的所有 zip 条目，<b>保持其原始压缩方式</b>（STORED 的保持 STORED，
 *       如 resources.arsc / .so —— 这是 Android 运行时的硬性要求）；</li>
 *   <li>对 DEFLATED 条目重新压缩（内容不变，后续会重新签名+对齐，无副作用）；</li>
 *   <li>扫描已有 {@code classes*.dex}，取最大编号 +1 作为新 dex 文件名；</li>
 *   <li>对 SDK dex 做魔数校验（{@code dex\n}），避免占位/损坏文件混入。</li>
 * </ul>
 *
 * <p>注意：本步骤会破坏原 APK 签名（新增条目所致），后续步骤会重新 zipalign + 签名。
 */
@Service
public class DexMerger {

    private static final Logger log = LoggerFactory.getLogger(DexMerger.class);

    /** dex 文件魔数前 4 字节：'d','e','x','\n' */
    private static final byte[] DEX_MAGIC_PREFIX = new byte[]{'d', 'e', 'x', '\n'};

    public void mergeSdkDex(Path sourceApk, Path outputApk, String sdkDexPath) throws Exception {
        // 0. 校验 SDK dex 存在且合法
        Path sdkDex = Path.of(sdkDexPath);
        if (!Files.exists(sdkDex)) {
            throw new RuntimeException("kami-sdk.dex 不存在: " + sdkDexPath
                    + "（请先用 Task 6 编译生成）");
        }
        byte[] sdkDexBytes = Files.readAllBytes(sdkDex);
        validateDexMagic(sdkDexBytes, sdkDexPath);

        Files.deleteIfExists(outputApk);

        try (ZipFile zipIn = new ZipFile(sourceApk.toFile());
             OutputStream fos = new FileOutputStream(outputApk.toFile());
             ZipOutputStream zos = new ZipOutputStream(fos)) {

            // 1. 计算最大 dex 编号
            int maxDexNum = 1;
            Enumeration<? extends ZipEntry> entries = zipIn.entries();
            while (entries.hasMoreElements()) {
                ZipEntry e = entries.nextElement();
                String name = e.getName();
                if (name.equals("classes.dex")) {
                    maxDexNum = Math.max(maxDexNum, 1);
                } else if (name.startsWith("classes") && name.endsWith(".dex")) {
                    String numStr = name.substring("classes".length(), name.length() - ".dex".length());
                    try {
                        int num = Integer.parseInt(numStr);
                        maxDexNum = Math.max(maxDexNum, num);
                    } catch (NumberFormatException ignored) {
                        // 非数字后缀的 classesX.dex，跳过
                    }
                }
            }

            // 2. 复制所有原始条目（保持压缩方式）
            entries = zipIn.entries();
            while (entries.hasMoreElements()) {
                ZipEntry src = entries.nextElement();
                if (src.getName().startsWith("META-INF/") && isSignatureFile(src.getName())) {
                    // 丢弃旧签名（新增 dex 后旧签名已失效，apksigner 会重签）
                    continue;
                }
                ZipEntry out = new ZipEntry(src.getName());
                byte[] data = readAll(zipIn, src);

                if (src.getMethod() == ZipEntry.STORED) {
                    out.setMethod(ZipEntry.STORED);
                    out.setSize(data.length);
                    out.setCompressedSize(data.length);
                    CRC32 crc = new CRC32();
                    crc.update(data);
                    out.setCrc(crc.getValue());
                } else {
                    out.setMethod(ZipEntry.DEFLATED);
                }
                // 保留时间戳便于排查
                if (src.getTime() != -1) {
                    out.setTime(src.getTime());
                }
                zos.putNextEntry(out);
                zos.write(data);
                zos.closeEntry();
            }

            // 3. 追加 SDK dex
            int newDexNum = maxDexNum + 1;
            String newDexName = "classes" + newDexNum + ".dex";
            ZipEntry dexEntry = new ZipEntry(newDexName);
            dexEntry.setMethod(ZipEntry.DEFLATED);
            zos.putNextEntry(dexEntry);
            zos.write(sdkDexBytes);
            zos.closeEntry();

            log.info("dex合并完成: 新增 {} ({} 字节)", newDexName, sdkDexBytes.length);
        }
    }

    private void validateDexMagic(byte[] bytes, String path) {
        if (bytes.length < 8) {
            throw new RuntimeException("kami-sdk.dex 体积过小，疑似占位文件: " + path
                    + "（请用 Task 6 生成真实 dex）");
        }
        for (int i = 0; i < DEX_MAGIC_PREFIX.length; i++) {
            if (bytes[i] != DEX_MAGIC_PREFIX[i]) {
                throw new RuntimeException("kami-sdk.dex 魔数校验失败，不是有效 dex: " + path
                        + "（请用 Task 6 生成真实 dex）");
            }
        }
    }

    private boolean isSignatureFile(String name) {
        String lower = name.toLowerCase();
        return lower.endsWith(".mf")
                || lower.endsWith(".rsa")
                || lower.endsWith(".sf")
                || lower.endsWith(".dsa")
                || lower.endsWith(".ec");
    }

    private byte[] readAll(ZipFile zipFile, ZipEntry entry) throws Exception {
        try (InputStream is = zipFile.getInputStream(entry)) {
            return is.readAllBytes();
        }
    }
}
