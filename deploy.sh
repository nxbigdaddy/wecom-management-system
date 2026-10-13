# 部署指南
#
# 项目：企业微信管理系统
# 服务器：203.0.113.11
# 路径：/www/sites/qywx.example.com/index
#
# 使用方式（以下命令在你有 SSH 访问权限的机器上执行）：

# ============================================================
# 方式一：手动上传（推荐初次部署）
# ============================================================
# 1. 将本地 <workspace>\wxwork2026\ 下的文件通过 SFTP 上传到服务器
#    - 推荐工具：WinSCP / FileZilla / scp 命令
#    - 目标目录：/www/sites/qywx.example.com/index

# 2. SSH 登录服务器后执行：
ssh root@203.0.113.11 -p 22   # 如果 22 不通，尝试其他端口

cd /www/sites/qywx.example.com/index

# 3. 配置 Nginx（参考 nginx_fix.conf）
cp nginx_fix.conf /etc/nginx/conf.d/qywx.conf
nginx -s reload

# 4. 设置目录权限
chmod -R 755 .
chmod -R 777 logs/ public/uploads/

# 5. 创建数据库
mysql -u root -p < schema.sql

# 6. 编辑配置
vim config.local.php   # 填入 corpid / corpsecret 等

# 7. 访问安装向导
# 浏览器打开 http://qywx.example.com/system/setup
# 按步骤创建管理员账号

# 8. 设置定时任务
crontab /www/sites/qywx.example.com/index/cron.example
# 或手动编辑：crontab -e

# ============================================================
# 方式二：rsync 增量同步（后续更新使用）
# ============================================================
# 在有 SSH 的机器上执行：
# rsync -avz --delete --exclude='config.local.php' --exclude='logs/' --exclude='vendor/' \
#   /path/to/local/wxwork2026/ \
#   root@203.0.113.11:/www/sites/qywx.example.com/index/

# ============================================================
# 服务器路径参考
# ============================================================
# 项目根目录：/www/sites/qywx.example.com/index
# 日志目录：   /www/sites/qywx.example.com/log
# Nginx 配置：/etc/nginx/conf.d/qywx.conf
# PHP 脚本：   /usr/bin/php

# ============================================================
# 更新清单（每次部署前确认）
# ============================================================
echo "=== 更新前检查清单 ==="
echo "[ ] config.local.php 是否正确配置？"
echo "[ ] schema.sql 是否已执行？（首次部署需要）"
echo "[ ] composer dump-autoload 是否已运行？"
echo "[ ] 文件是否已上传到服务器？"
echo "[ ] Nginx 配置是否已更新？"
echo "[ ] 日志目录是否可写？"
echo "[ ] 是否已通过 /system/setup 完成安装？"
