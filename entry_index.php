<?php
/**
 * 企业微信管理系统 - 根目录入口引导
 * 
 * 1Panel 的站点根目录为 /www/sites/qywx.example.com/index，
 * 实际应用入口在 public/ 子目录中。
 * 此文件将请求转发到 public/index.php
 */

// 直接引入真正的入口文件
require __DIR__ . '/public/index.php';
