# 代码审查问题修复 - 验证清单

## 安全密钥与默认值
- [ ] JWT 密钥无硬编码默认值，未配置时启动失败
- [ ] AES 密钥无硬编码默认值，未配置时启动失败
- [ ] AppSecret 解密失败不回退到 bcrypt hash
- [ ] .example.env APP_DEBUG 默认为 false
- [ ] docker-compose 数据库密码使用强制环境变量

## 权限系统接线
- [ ] merchant 路由组已绑定 PermissionMiddleware
- [ ] admin 路由 PermissionMiddleware 传入正确权限参数
- [ ] DataPermissionMiddleware 已注册到 merchant 路由
- [ ] 子账号查询按 app_ids 过滤数据
- [ ] 子角色权限从数据库 sub_roles 读取

## 卡密安全与日志
- [ ] API 日志中 card_no 为脱敏格式（前4后4中间*）
- [ ] device_fingerprint 哈希后记录
- [ ] 枚举不存在卡号会触发爆破计数
- [ ] 未激活卡 verify 不计爆破
- [ ] Nonce 使用 Redis SETNX 原子操作
- [ ] 限流使用 Redis INCR 原子操作
- [ ] 限流计数在业务执行前

## 发卡业务
- [ ] cards 表有 card_no_encrypted 字段
- [ ] 新生成卡密写入 card_no_encrypted
- [ ] 发卡能正确返回卡密明文
- [ ] orders 表有 buyer_ip 和 device_id 字段
- [ ] 下单时写入 buyer_ip 和 device_id
- [ ] 购买 N 份返回 N 张卡密
- [ ] 库存扣减 N 份
- [ ] quantity 单次上限 100 份
- [ ] 禁用商户店铺不能下单
- [ ] agent_id 不由前端直传
- [ ] 限购查询使用正确字段名

## 支付与资金并发
- [ ] 余额支付使用行锁
- [ ] 支付回调使用行锁且幂等
- [ ] processOrderPaid 在事务内
- [ ] 库存扣减使用 DEC 原子操作
- [ ] 佣金 D+1 结算使用条件更新保证幂等
- [ ] 退款扣回佣金校验余额
- [ ] 订单号使用安全随机数

## 架构与部署
- [ ] Nginx 生产配置 /api/ 只有 fastcgi_pass
- [ ] Nginx 配置包含安全响应头
- [ ] SecurityHeadersMiddleware 全局中间件存在
- [ ] 注册接口有限流中间件
- [ ] AdminMenuSeeder 与 permission.php 权限一致
- [ ] 卡密导入弹窗不使用 dangerouslyUseHTMLString
- [ ] 卡密导入使用 el-dialog + el-form

## 其他高优先级修复
- [ ] CaihongPay 开启 SSL 验证
- [ ] CaihongPay 签名使用 hash_equals
- [ ] 登录风控按 username+ip 双维度
- [ ] 卡密激活/续费/绑定使用事务+行锁
- [ ] 前端 token 存储在 sessionStorage
- [ ] 卡密列表统计使用 GROUP BY
- [ ] CSV 导出有 UTF-8 BOM

## 最终验证
- [ ] 所有 PHP 文件语法检查通过
- [ ] 前端 npm run build 成功
- [ ] 代码成功推送到 GitHub main
- [ ] 无 PHP 语法错误
- [ ] 无新增运行时错误
