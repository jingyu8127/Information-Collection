<?php
// 包含系统配置文件
require_once '../config.php';

// 记录退出日志
if (isset($_SESSION['admin_id'])) {
    logAction('管理员退出登录', '管理员 ' . $_SESSION['admin_username'] . ' 从IP地址 ' . getClientIp() . ' 退出系统');
    
    // 清除管理员会话
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_role']);
    unset($_SESSION['last_login']);
    
    // 可选：完全销毁会话
    session_destroy();
}

// 重定向到登录页面
header('Location: login.php');
exit;