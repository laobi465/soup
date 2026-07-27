package com.cardauth.inject.service;

import com.cardauth.inject.model.ApkInfo;
import com.cardauth.inject.model.InjectRequest;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;
import org.w3c.dom.Document;
import org.w3c.dom.Element;
import org.w3c.dom.Node;
import org.w3c.dom.NodeList;

import javax.xml.parsers.DocumentBuilder;
import javax.xml.parsers.DocumentBuilderFactory;
import javax.xml.transform.OutputKeys;
import javax.xml.transform.Transformer;
import javax.xml.transform.TransformerFactory;
import javax.xml.transform.dom.DOMSource;
import javax.xml.transform.stream.StreamResult;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.concurrent.TimeUnit;

/**
 * AndroidManifest 修改器（基于 APKEditor decode/build）。
 *
 * <p>二进制 AXML 无法直接文本替换，故借助 APKEditor：
 * <ol>
 *   <li>{@code java -jar APKEditor.jar d -i input.apk -o decodedDir}（decode 为文本 XML）</li>
 *   <li>用 DOM 修改 AndroidManifest.xml：
 *     <ul>
 *       <li>替换 {@code <application android:name>} 为 {@link #KAMI_PROXY_APPLICATION}；</li>
 *       <li>在 {@code <application>} 内插入 meta-data 保存原 Application 类名与 SDK 配置
 *           （app_key/app_secret/base_url），供 KamiProxyApplication 读取；</li>
 *       <li>若缺少 {@code INTERNET} 权限则补上。</li>
 *     </ul>
 *   </li>
 *   <li>{@code java -jar APKEditor.jar b -i decodedDir -o output.apk}（build 回 APK）</li>
 * </ol>
 *
 * <p>使用非命名空间感知的 DOM，按限定名（qname，如 {@code android:name}）读写属性，
 * 便于在带前缀的 XML 上做精准替换。
 */
@Service
public class ManifestModifier {

    private static final Logger log = LoggerFactory.getLogger(ManifestModifier.class);

    /** 注入用代理 Application 全限定名（Task 6 中实现）。 */
    public static final String KAMI_PROXY_APPLICATION = "com.cardauth.sdk.KamiProxyApplication";

    private static final String ANDROID_NAME = "android:name";
    private static final String ANDROID_VALUE = "android:value";
    private static final String INTERNET_PERMISSION = "android.permission.INTERNET";

    @Value("${apk.apkeditor-path:/app/tools/APKEditor.jar}")
    private String apkEditorPath;

    public void modify(Path inputApk, Path outputApk, ApkInfo apkInfo, InjectRequest request) throws Exception {
        Path decodedDir = inputApk.getParent().resolve("decoded");
        Files.createDirectories(decodedDir);

        // 1. APKEditor decode
        runApkEditor("d", "-i", inputApk.toString(), "-o", decodedDir.toString());

        // 2. 修改 AndroidManifest.xml
        Path manifestPath = decodedDir.resolve("AndroidManifest.xml");
        if (!Files.exists(manifestPath)) {
            throw new RuntimeException("APKEditor decode 后未找到 AndroidManifest.xml: " + manifestPath);
        }
        modifyManifest(manifestPath, apkInfo, request);

        // 3. APKEditor build
        Files.deleteIfExists(outputApk);
        runApkEditor("b", "-i", decodedDir.toString(), "-o", outputApk.toString());

        log.info("Manifest修改完成: {}", outputApk);
    }

    private void modifyManifest(Path manifestPath, ApkInfo apkInfo, InjectRequest request) throws Exception {
        DocumentBuilderFactory factory = DocumentBuilderFactory.newInstance();
        // 关闭 DOCTYPE / 外部实体，防 XXE
        factory.setFeature("http://apache.org/xml/features/disallow-doctype-decl", true);
        factory.setNamespaceAware(false);
        DocumentBuilder builder = factory.newDocumentBuilder();
        Document doc = builder.parse(manifestPath.toFile());

        Element manifest = doc.getDocumentElement();
        Element application = findFirstChildElement(manifest, "application");
        if (application == null) {
            throw new RuntimeException("AndroidManifest.xml 中未找到 <application> 节点");
        }

        // 2a. 捕获原 Application 类名
        String originalApp = application.getAttribute(ANDROID_NAME);
        if (originalApp == null || originalApp.isEmpty()) {
            originalApp = "android.app.Application";
        }

        // 2b. 替换为 KamiProxyApplication
        application.setAttribute(ANDROID_NAME, KAMI_PROXY_APPLICATION);

        // 2c. 插入 meta-data（放在 application 第一个子节点前）
        insertMetaData(doc, application, "kami_original_application", originalApp);
        insertMetaData(doc, application, "kami_app_key", nullToEmpty(request.getAppKey()));
        insertMetaData(doc, application, "kami_app_secret", nullToEmpty(request.getAppSecret()));
        insertMetaData(doc, application, "kami_base_url", nullToEmpty(request.getBaseUrl()));

        // 2d. 补 INTERNET 权限（如缺失）
        ensureInternetPermission(doc, manifest);

        // 2e. 写回
        TransformerFactory tf = TransformerFactory.newInstance();
        Transformer transformer = tf.newTransformer();
        transformer.setOutputProperty(OutputKeys.ENCODING, StandardCharsets.UTF_8.name());
        transformer.setOutputProperty(OutputKeys.OMIT_XML_DECLARATION, "no");
        transformer.setOutputProperty(OutputKeys.INDENT, "no");
        try (var os = Files.newOutputStream(manifestPath)) {
            transformer.transform(new DOMSource(doc), new StreamResult(os));
        }
    }

    private void insertMetaData(Document doc, Element application, String name, String value) {
        Element meta = doc.createElement("meta-data");
        meta.setAttribute(ANDROID_NAME, name);
        meta.setAttribute(ANDROID_VALUE, value);
        Node firstChild = application.getFirstChild();
        if (firstChild != null) {
            application.insertBefore(meta, firstChild);
        } else {
            application.appendChild(meta);
        }
    }

    private void ensureInternetPermission(Document doc, Element manifest) {
        NodeList perms = manifest.getElementsByTagName("uses-permission");
        for (int i = 0; i < perms.getLength(); i++) {
            Element perm = (Element) perms.item(i);
            if (INTERNET_PERMISSION.equals(perm.getAttribute(ANDROID_NAME))) {
                return; // 已存在
            }
        }
        Element perm = doc.createElement("uses-permission");
        perm.setAttribute(ANDROID_NAME, INTERNET_PERMISSION);
        // 插入到 <application> 之前（若有），否则追加到 manifest 末尾
        Element application = findFirstChildElement(manifest, "application");
        if (application != null) {
            manifest.insertBefore(perm, application);
        } else {
            manifest.appendChild(perm);
        }
    }

    private Element findFirstChildElement(Element parent, String localName) {
        NodeList children = parent.getChildNodes();
        for (int i = 0; i < children.getLength(); i++) {
            Node n = children.item(i);
            if (n.getNodeType() == Node.ELEMENT_NODE && n.getNodeName().equals(localName)) {
                return (Element) n;
            }
        }
        return null;
    }

    private String nullToEmpty(String s) {
        return s == null ? "" : s;
    }

    private void runApkEditor(String mode, String... args) throws Exception {
        if (!Files.exists(Path.of(apkEditorPath))) {
            throw new RuntimeException("APKEditor.jar 不存在: " + apkEditorPath
                    + "（请下载 https://github.com/REAndroid/APKEditor 并配置 apk.apkeditor-path）");
        }
        String[] cmd = new String[args.length + 4];
        cmd[0] = "java";
        cmd[1] = "-jar";
        cmd[2] = apkEditorPath;
        cmd[3] = mode;
        System.arraycopy(args, 0, cmd, 4, args.length);

        ProcessBuilder pb = new ProcessBuilder(cmd);
        pb.redirectErrorStream(true);
        Process p = pb.start();
        String out = new String(p.getInputStream().readAllBytes(), StandardCharsets.UTF_8);
        boolean finished = p.waitFor(10, TimeUnit.MINUTES);
        int code = p.exitValue();
        if (!finished) {
            p.destroyForcibly();
            throw new RuntimeException("APKEditor " + mode + " 超时（10 分钟）: " + out);
        }
        if (code != 0) {
            throw new RuntimeException("APKEditor " + mode + " 失败(code=" + code + "): " + out);
        }
    }
}
