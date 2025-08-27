<?php
// 包含数据库连接文件
require_once 'db_connect.php';

try {
    // 创建settings表
    $sql = "
    CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        key_name VARCHAR(100) NOT NULL UNIQUE,
        value TEXT,
        type VARCHAR(20) DEFAULT 'text',
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );
    ";
    query($sql);
    
    // 创建form_fields表
    $sql = "
    CREATE TABLE IF NOT EXISTS form_fields (
        id INT AUTO_INCREMENT PRIMARY KEY,
        field_name VARCHAR(100) NOT NULL,
        label VARCHAR(100) NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'text',
        placeholder VARCHAR(255),
        required BOOLEAN DEFAULT false,
        options TEXT,
        order_index INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );
    ";
    query($sql);
    
    // 创建submissions表
    $sql = "
    CREATE TABLE IF NOT EXISTS submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data LONGTEXT NOT NULL,
        ip_address VARCHAR(50),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'processed') DEFAULT 'pending'
    );
    ";
    query($sql);
    
    // 创建admin表
    $sql = "
    CREATE TABLE IF NOT EXISTS admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL,
        role ENUM('superadmin', 'admin') DEFAULT 'admin',
        status ENUM('active', 'inactive') DEFAULT 'active',
        last_login TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );
    ";
    query($sql);
    
    // 添加默认管理员账户（用户名：admin，密码：admin123）
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "
    INSERT INTO admin (username, password, email, role, status)
    SELECT 'admin', '$hashedPassword', 'admin@example.com', 'superadmin', 'active'
    WHERE NOT EXISTS (SELECT 1 FROM admin WHERE username = 'admin');
    ";
    query($sql);
    
    // 添加默认表单字段
    $defaultFields = [
        ['name' => 'name', 'label' => '姓名', 'type' => 'text', 'placeholder' => '请输入您的姓名', 'required' => 1, 'order' => 1],
        ['name' => 'email', 'label' => '邮箱', 'type' => 'email', 'placeholder' => '请输入您的邮箱', 'required' => 1, 'order' => 2],
        ['name' => 'phone', 'label' => '电话', 'type' => 'tel', 'placeholder' => '请输入您的电话', 'required' => 1, 'order' => 3],
        ['name' => 'message', 'label' => '留言', 'type' => 'textarea', 'placeholder' => '请输入您的留言内容', 'required' => 0, 'order' => 4]
    ];
    
    foreach ($defaultFields as $field) {
        $sql = "
        INSERT INTO form_fields (field_name, label, type, placeholder, required, order_index)
        SELECT :field_name, :label, :type, :placeholder, :required, :order_index
        WHERE NOT EXISTS (SELECT 1 FROM form_fields WHERE field_name = :field_name_check);
        ";
        query($sql, [
            ':field_name' => $field['name'],
            ':field_name_check' => $field['name'],
            ':label' => $field['label'],
            ':type' => $field['type'],
            ':placeholder' => $field['placeholder'],
            ':required' => $field['required'],
            ':order_index' => $field['order']
        ]);
    }
    
    // 添加默认系统设置
    $defaultSettings = [
        ['key' => 'site_title', 'value' => '信息收集系统', 'type' => 'text', 'desc' => '网站标题'],
        ['key' => 'site_description', 'value' => '这是一个基于PHP的信息收集网站系统', 'type' => 'text', 'desc' => '网站描述'],
        ['key' => 'background_api', 'value' => 'https://picsum.photos/1920/1080', 'type' => 'text', 'desc' => '背景图API地址'],
        ['key' => 'success_message', 'value' => '提交成功！感谢您的反馈，我们会尽快与您联系。', 'type' => 'text', 'desc' => '提交成功提示信息'],
        ['key' => 'redirect_delay', 'value' => '3', 'type' => 'number', 'desc' => '自动跳转延迟（秒）'],
        ['key' => 'smtp_host', 'value' => '', 'type' => 'text', 'desc' => 'SMTP服务器地址'],
        ['key' => 'smtp_port', 'value' => '587', 'type' => 'number', 'desc' => 'SMTP服务器端口'],
        ['key' => 'smtp_username', 'value' => '', 'type' => 'text', 'desc' => 'SMTP用户名'],
        ['key' => 'smtp_password', 'value' => '', 'type' => 'password', 'desc' => 'SMTP密码'],
        ['key' => 'smtp_encryption', 'value' => 'tls', 'type' => 'select', 'desc' => 'SMTP加密方式'],
        ['key' => 'email_from', 'value' => '', 'type' => 'text', 'desc' => '发件人邮箱'],
        ['key' => 'email_to', 'value' => '', 'type' => 'text', 'desc' => '收件人邮箱'],
        ['key' => 'email_subject', 'value' => '新的表单提交', 'type' => 'text', 'desc' => '邮件主题'],
        ['key' => 'cache_enabled', 'value' => '0', 'type' => 'checkbox', 'desc' => '启用缓存'],
        ['key' => 'cache_time', 'value' => '3600', 'type' => 'number', 'desc' => '缓存时间（秒）']
    ];
    
    foreach ($defaultSettings as $setting) {
        $sql = "
        INSERT INTO settings (key_name, value, type, description)
        SELECT :key_name, :value, :type, :description
        WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = :key_name_check);
        ";
        query($sql, [
            ':key_name' => $setting['key'],
            ':key_name_check' => $setting['key'],
            ':value' => $setting['value'],
            ':type' => $setting['type'],
            ':description' => $setting['desc']
        ]);
    }
    
    echo "数据库初始化成功！\n";
    echo "默认管理员账户：admin / admin123\n";
    echo "请在首次登录后修改密码以确保安全。";
    
} catch (PDOException $e) {
    die('数据库初始化失败: ' . $e->getMessage());
} catch (Exception $e) {
    die('初始化过程中出现错误: ' . $e->getMessage());
}
?>