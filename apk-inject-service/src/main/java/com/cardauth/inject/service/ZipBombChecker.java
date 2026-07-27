package com.cardauth.inject.service;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Service;

import java.io.IOException;
import java.nio.file.Path;
import java.util.Enumeration;
import java.util.zip.ZipEntry;
import java.util.zip.ZipFile;

/**
 * ZIP 炸弹检测器。
 *
 * <p>遍历 zip 条目，累计未压缩总大小与压缩比，超阈值则拒绝：
 * <ul>
 *   <li>未压缩总大小 &gt; {@link #MAX_UNCOMPRESSED_SIZE}（500MB）</li>
 *   <li>整体压缩比 &gt; {@link #MAX_COMPRESSION_RATIO}（100:1）</li>
 *   <li>单个条目压缩比 &gt; {@link #MAX_ENTRY_COMPRESSION_RATIO}（100:1）</li>
 * </ul>
 *
 * <p>同时校验文件魔数（ZIP 文件以 {@code PK\x03\x04} 开头）。
 */
@Service
public class ZipBombChecker {

    private static final Logger log = LoggerFactory.getLogger(ZipBombChecker.class);

    /** 未压缩总大小上限：500MB */
    private static final long MAX_UNCOMPRESSED_SIZE = 500L * 1024 * 1024;

    /** 整体压缩比上限：100:1 */
    private static final int MAX_COMPRESSION_RATIO = 100;

    /** 单个条目压缩比上限：100:1 */
    private static final int MAX_ENTRY_COMPRESSION_RATIO = 100;

    /** ZIP 文件魔数：PK\x03\x04 */
    private static final byte[] ZIP_MAGIC = new byte[]{0x50, 0x4B, 0x03, 0x04};

    /**
     * 校验文件是否为合法 ZIP/APK 且非 ZIP 炸弹。
     *
     * @param filePath 待校验的文件路径
     * @throws Exception 校验失败（魔数错误或疑似 ZIP 炸弹）
     */
    public void check(Path filePath) throws Exception {
        // 1. 校验 ZIP 魔数（只读前 4 字节，不全量入内存）
        try (java.io.RandomAccessFile raf = new java.io.RandomAccessFile(filePath.toFile(), "r")) {
            if (raf.length() < 4) {
                throw new RuntimeException("文件过小，不是有效的 APK/ZIP 文件");
            }
            byte[] header = new byte[4];
            raf.readFully(header);
            if (header[0] != ZIP_MAGIC[0] || header[1] != ZIP_MAGIC[1]
                    || header[2] != ZIP_MAGIC[2] || header[3] != ZIP_MAGIC[3]) {
                throw new RuntimeException("文件魔数错误，不是有效的 APK/ZIP 文件（期望 PK\\x03\\x04）");
            }
        }

        // 2. 遍历 zip 条目，检测 ZIP 炸弹
        long totalCompressed = 0;
        long totalUncompressed = 0;

        try (ZipFile zipFile = new ZipFile(filePath.toFile())) {
            Enumeration<? extends ZipEntry> entries = zipFile.entries();
            while (entries.hasMoreElements()) {
                ZipEntry entry = entries.nextElement();
                long compressed = entry.getCompressedSize();
                long uncompressed = entry.getSize();

                // 跳过目录条目
                if (entry.isDirectory()) {
                    continue;
                }

                // 单个条目压缩比检测
                if (compressed > 0 && uncompressed > 0) {
                    int ratio = (int) (uncompressed / compressed);
                    if (ratio > MAX_ENTRY_COMPRESSION_RATIO) {
                        throw new RuntimeException(String.format(
                                "疑似 ZIP 炸弹：条目 %s 压缩比 %d:1 超过上限 %d:1",
                                entry.getName(), ratio, MAX_ENTRY_COMPRESSION_RATIO));
                    }
                }

                totalCompressed += compressed;
                totalUncompressed += uncompressed;

                // 未压缩总大小检测
                if (totalUncompressed > MAX_UNCOMPRESSED_SIZE) {
                    throw new RuntimeException(String.format(
                            "疑似 ZIP 炸弹：未压缩总大小 %d 字节超过上限 %d 字节（%dMB）",
                            totalUncompressed, MAX_UNCOMPRESSED_SIZE,
                            MAX_UNCOMPRESSED_SIZE / (1024 * 1024)));
                }
            }
        }

        // 3. 整体压缩比检测
        if (totalCompressed > 0 && totalUncompressed > 0) {
            int overallRatio = (int) (totalUncompressed / totalCompressed);
            if (overallRatio > MAX_COMPRESSION_RATIO) {
                throw new RuntimeException(String.format(
                        "疑似 ZIP 炸弹：整体压缩比 %d:1 超过上限 %d:1（压缩 %d 字节，未压缩 %d 字节）",
                        overallRatio, MAX_COMPRESSION_RATIO,
                        totalCompressed, totalUncompressed));
            }
        }

        double ratio = totalCompressed > 0 ? (double) totalUncompressed / totalCompressed : 0;
        log.info("ZIP炸弹检测通过: 未压缩总大小 {} 字节，压缩比 {} :1", totalUncompressed, String.format("%.1f", ratio));
    }
}
