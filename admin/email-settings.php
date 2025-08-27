<?php
// 包含系统配置文件
require_once '../config.php';

// 验证管理员访问权限
adminAccess();

// 获取网站标题
$siteTitle = getSetting('site_title', '信息收集网站系统');

// 处理表单提交
$successMessage = '';
$errorMessage = '';
$testResult = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMessage = '表单验证失败，请重试';
    } else {
        // 处理邮件设置更新
        if (isset($_POST['update_email_settings'])) {
            // 收集SMTP设置
            $smtpHost = $_POST['smtp_host'] ?? '';
            $smtpPort = intval($_POST['smtp_port'] ?? 25);
            $smtpUsername = $_POST['smtp_username'] ?? '';
            $smtpPassword = $_POST['smtp_password'] ?? '';
            $smtpEncryption = $_POST['smtp_encryption'] ?? 'none';
            $fromName = $_POST['from_name'] ?? '';
            $fromEmail = $_POST['from_email'] ?? '';
            $replyToEmail = $_POST['reply_to_email'] ?? '';
            $recipients = $_POST['recipients'] ?? '';
            $subjectTemplate = $_POST['subject_template'] ?? '[新提交] {{site_title}}';
            $bodyTemplate = $_POST['body_template'] ?? '';
            $enableNotifications = isset($_POST['enable_notifications']) ? 1 : 0;
            $emailContentType = $_POST['email_content_type'] ?? 'html';
            
            // 验证必要字段
            if (empty($smtpHost) || empty($fromEmail)) {
                $errorMessage = 'SMTP服务器和发件邮箱不能为空';
            } else {
                // 更新设置
                updateSetting('smtp_host', $smtpHost);
                updateSetting('smtp_port', $smtpPort);
                updateSetting('smtp_username', $smtpUsername);
                // 只有当输入了新密码时才更新
                if (!empty($smtpPassword)) {
                    updateSetting('smtp_password', encryptPassword($smtpPassword));
                }
                updateSetting('smtp_encryption', $smtpEncryption);
                updateSetting('from_name', $fromName);
                updateSetting('from_email', $fromEmail);
                updateSetting('reply_to_email', $replyToEmail);
                updateSetting('recipients', $recipients);
                updateSetting('subject_template', $subjectTemplate);
                updateSetting('body_template', $bodyTemplate);
                updateSetting('enable_notifications', $enableNotifications);
                updateSetting('email_content_type', $emailContentType);
                
                $successMessage = '邮件设置已成功更新';
                logAction('更新邮件设置', '修改了系统邮件配置');
            }
        }
        
        // 处理测试邮件发送
        if (isset($_POST['send_test_email'])) {
            $testEmail = $_POST['test_email'] ?? '';
            
            if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = '请输入有效的测试邮箱地址';
            } else {
                // 获取当前邮件设置
                $smtpHost = getSetting('smtp_host');
                $smtpPort = getSetting('smtp_port', 25);
                $smtpUsername = getSetting('smtp_username');
                $smtpPassword = getSetting('smtp_password');
                $smtpEncryption = getSetting('smtp_encryption', 'none');
                $fromName = getSetting('from_name', $siteTitle);
                $fromEmail = getSetting('from_email');
                
                // 检查必要设置是否存在
                if (empty($smtpHost) || empty($fromEmail)) {
                    $errorMessage = '请先完成邮件基本设置';
                } else {
                    // 尝试发送测试邮件
                    $subject = '测试邮件 - ' . $siteTitle;
                    $body = '<h2>测试邮件</h2><p>这是一封测试邮件，用于验证 ' . $siteTitle . ' 的邮件配置是否正确。</p><p>时间: ' . date('Y-m-d H:i:s') . '</p>';
                    
                    try {
                        // 解密密码
                        $decryptedPassword = !empty($smtpPassword) ? decryptPassword($smtpPassword) : '';
                        
                        // 保存表单中的临时设置用于测试
                        $originalSettings = [
                            'smtp_host' => getSetting('smtp_host'),
                            'smtp_port' => getSetting('smtp_port', 25),
                            'smtp_username' => getSetting('smtp_username'),
                            'smtp_password' => getSetting('smtp_password'),
                            'smtp_encryption' => getSetting('smtp_encryption', 'none'),
                            'email_from' => getSetting('email_from')
                        ];
                        
                        // 更新临时设置
                        updateSetting('smtp_host', $smtpHost);
                        updateSetting('smtp_port', $smtpPort);
                        updateSetting('smtp_username', $smtpUsername);
                        updateSetting('smtp_encryption', $smtpEncryption);
                        updateSetting('email_from', $fromEmail);
                        
                        // 如果用户在表单中输入了新密码，也更新它
                        if (!empty($_POST['smtp_password'])) {
                            updateSetting('smtp_password', encryptPassword($_POST['smtp_password']));
                        }
                        
                        // 使用sendEmail函数发送测试邮件，同时获取详细错误信息
                        $sendError = '';
                        $result = sendEmail($testEmail, $subject, $body, $sendError);
                        
                        // 恢复原始设置
                        foreach ($originalSettings as $key => $value) {
                            updateSetting($key, $value);
                        }
                        
                        if ($result) {
                            $testResult = 'success';
                            $successMessage = '测试邮件已成功发送至 ' . $testEmail;
                            logAction('发送测试邮件', '发送至: ' . $testEmail);
                        } else {
                            $testResult = 'error';
                            $errorMessage = '测试邮件发送失败，请检查邮件设置';
                            if (!empty($sendError)) {
                                $errorMessage .= ' (错误详情: ' . $sendError . ')';
                            }
                            // 同时记录详细错误信息到操作日志
                            logAction('测试邮件发送失败', '目标: ' . $testEmail . ', 错误: ' . $sendError);
                        }
                    } catch (Exception $e) {
                        $testResult = 'error';
                        $errorMessage = '发送测试邮件时发生错误：' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// 获取当前邮件设置
$smtpHost = getSetting('smtp_host');
$smtpPort = getSetting('smtp_port', 25);
$smtpUsername = getSetting('smtp_username');
// 不获取密码，避免显示
$smtpPassword = '';
$smtpEncryption = getSetting('smtp_encryption', 'none');
$fromName = getSetting('from_name', $siteTitle);
$fromEmail = getSetting('from_email');
$replyToEmail = getSetting('reply_to_email');
$recipients = getSetting('recipients');
$subjectTemplate = getSetting('subject_template', '[新提交] {{site_title}}');
$bodyTemplate = getSetting('body_template');
$enableNotifications = getSetting('enable_notifications', 1);
$emailContentType = getSetting('email_content_type', 'html');

// 如果没有默认的邮件模板，设置一个
if (empty($bodyTemplate)) {
    $bodyTemplate = '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>表单提交通知</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1 { color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .content { background-color: #f9f9f9; padding: 20px; border-radius: 5px; margin-top: 20px; }
        .field { margin-bottom: 10px; }
        .field-label { font-weight: bold; display: inline-block; width: 150px; }
        .field-value { display: inline-block; }
        .footer { margin-top: 30px; font-size: 12px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <h1>新的表单提交</h1>
    <div class="content">
        {{form_data}}
    </div>
    <div class="footer">
        <p>此邮件由 {{site_title}} 自动发送</p>
        <p>提交时间: {{submit_time}}</p>
    </div>
</body>
</html>';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>邮件设置 - <?php echo escape($siteTitle); ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <!-- TinyMCE 富文本编辑器 -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    
    <!-- 配置Tailwind自定义主题 -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#6366F1',
                        success: '#10B981',
                        warning: '#F59E0B',
                        danger: '#EF4444',
                        info: '#3B82F6',
                        dark: '#1F2937',
                        light: '#F9FAFB',
                        sidebar: '#1E293B',
                        sidebarItem: '#334155',
                        sidebarItemHover: '#475569',
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    
    <!-- 自定义工具类 -->
    <style type="text/tailwindcss">
        @layer utilities {
            .content-auto {
                content-visibility: auto;
            }
            .sidebar-item-active {
                @apply bg-primary text-white;
            }
            .transition-all-300 {
                transition: all 0.3s ease;
            }
            .scrollbar-hide {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
            .scrollbar-hide::-webkit-scrollbar {
                display: none;
            }
        }
    </style>
    
    <style>
        /* 全局样式 */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* 主容器布局 */
        .main-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* 侧边栏样式 */
        .sidebar {
            width: 240px;
            background-color: #1E293B;
            color: white;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            transition: all 0.3s ease;
        }
        
        /* 主内容区域 */
        .content {
            margin-left: 240px;
            flex: 1;
            transition: all 0.3s ease;
        }
        
        /* 响应式布局 - 小屏幕 */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1000;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .content {
                margin-left: 0;
            }
        }
        
        /* 侧边栏菜单项样式 */
        .sidebar-item {
            transition: all 0.3s ease;
        }
        
        .sidebar-item:hover {
            background-color: #475569;
        }
        
        /* 加载动画 */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .animate-pulse-slow {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        /* 表单控件样式 */
        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        /* 模板预览样式 */
        .template-preview {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            background-color: #f9fafb;
            font-family: monospace;
            font-size: 0.875rem;
            line-height: 1.5;
            overflow-x: auto;
            color: #374151;
        }
        
        .template-variable {
            color: #d97706;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 主容器 -->
    <div class="main-container">
        <!-- 侧边栏 -->
        <aside class="sidebar" id="sidebar">
            <!-- 侧边栏头部 -->
            <div class="p-6 border-b border-gray-700">
                <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                    <i class="fa fa-cogs text-primary"></i>
                    <span>管理中心</span>
                </h1>
            </div>
            
            <!-- 侧边栏菜单 -->
            <nav class="p-4">
                <ul class="space-y-1">
                    <!-- 控制面板 -->
                    <li>
                        <a href="index.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:text-white">
                            <i class="fa fa-dashboard w-5 text-center"></i>
                            <span>控制面板</span>
                        </a>
                    </li>
                    
                    <!-- 数据管理 -->
                    <li>
                        <a href="records.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:text-white">
                            <i class="fa fa-database w-5 text-center"></i>
                            <span>数据管理</span>
                        </a>
                    </li>
                    
                    <!-- 表单配置 -->
                    <li>
                        <a href="form-config.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:text-white">
                            <i class="fa fa-wpforms w-5 text-center"></i>
                            <span>表单配置</span>
                        </a>
                    </li>
                    
                    <!-- 邮件设置 -->
                    <li>
                        <a href="email-settings.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg sidebar-item-active">
                            <i class="fa fa-envelope w-5 text-center"></i>
                            <span>邮件设置</span>
                        </a>
                    </li>
                    
                    <!-- 背景管理 -->
                    <li>
                        <a href="background.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:text-white">
                            <i class="fa fa-picture-o w-5 text-center"></i>
                            <span>背景管理</span>
                        </a>
                    </li>
                    
                    <!-- 系统设置 -->
                    <li>
                        <a href="system.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:text-white">
                            <i class="fa fa-gear w-5 text-center"></i>
                            <span>系统设置</span>
                        </a>
                    </li>
                    
                    <!-- 分隔线 -->
                    <li class="pt-4 pb-2">
                        <div class="border-t border-gray-700"></div>
                    </li>
                    
                    <!-- 退出登录 -->
                    <li>
                        <a href="logout.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:text-white">
                            <i class="fa fa-sign-out w-5 text-center"></i>
                            <span>退出登录</span>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <!-- 侧边栏底部 -->
            <div class="absolute bottom-0 w-full p-4 border-t border-gray-700">
                <div class="text-sm text-gray-400">
                    <p>版本: <?php echo SYSTEM_VERSION; ?></p>
                    <p class="mt-1">© <?php echo date('Y'); ?></p>
                </div>
            </div>
        </aside>
        
        <!-- 主内容区域 -->
        <main class="content">
            <!-- 顶部导航栏 -->
            <header class="bg-white shadow-sm sticky top-0 z-10">
                <div class="flex justify-between items-center p-4">
                    <!-- 移动端菜单按钮 -->
                    <button id="menuToggle" class="lg:hidden text-gray-500 hover:text-primary transition-all-300">
                        <i class="fa fa-bars text-xl"></i>
                    </button>
                    
                    <!-- 页面标题 -->
                    <div class="text-xl font-semibold text-gray-800">
                        邮件设置
                    </div>
                    
                    <!-- 用户信息 -->
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <button class="flex items-center gap-2 text-gray-600 hover:text-primary transition-all-300">
                                <i class="fa fa-bell-o text-xl"></i>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                    <?php echo getSingle("SELECT COUNT(*) as count FROM submissions WHERE status = 'pending'")['count']; ?>
                                </span>
                            </button>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-700">
                                    <?php echo escape($_SESSION['admin_username']); ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?php echo escape($_SESSION['admin_role']); ?>
                                </p>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                                <i class="fa fa-user"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- 页面内容 -->
            <div class="p-6">
                <!-- 面包屑导航 -->
                <nav class="flex mb-6" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="index.php" class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-primary transition-all-300">
                                <i class="fa fa-home mr-2"></i>
                                首页
                            </a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fa fa-chevron-right text-gray-400 text-xs mx-2"></i>
                                <span class="text-sm font-medium text-gray-800">邮件设置</span>
                            </div>
                        </li>
                    </ol>
                </nav>
                
                <!-- 成功/错误消息提示 -->
                <?php if (!empty($successMessage)): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 animate-pulse">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fa fa-check-circle text-green-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-green-700"><?php echo escape($successMessage); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errorMessage)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 animate-pulse">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fa fa-exclamation-circle text-red-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-red-700"><?php echo escape($errorMessage); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- 测试邮件结果 -->
                <?php if (!empty($testResult)): ?>
                    <div class="<?php echo $testResult === 'success' ? 'bg-green-50 border-l-4 border-green-500' : 'bg-red-50 border-l-4 border-red-500'; ?> p-4 mb-6 animate-pulse">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fa fa-<?php echo $testResult === 'success' ? 'check-circle text-green-500' : 'exclamation-circle text-red-500'; ?>"></i>
                            </div>
                            <div class="ml-3">
                                <p class="<?php echo $testResult === 'success' ? 'text-green-700' : 'text-red-700'; ?>">
                                    <?php echo escape($testResult === 'success' ? $successMessage : $errorMessage); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- 邮件设置卡片 -->
                <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6">邮件服务器设置</h3>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="update_email_settings" value="1">
                        
                        <!-- 基础设置 -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- SMTP服务器 -->
                            <div>
                                <label for="smtp_host" class="block text-sm font-medium text-gray-700 mb-1">
                                    SMTP服务器地址 <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="smtp_host"
                                    name="smtp_host" 
                                    value="<?php echo escape($smtpHost); ?>" 
                                    class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                    placeholder="smtp.example.com"
                                    required
                                >
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fa fa-info-circle mr-1"></i>
                                    请输入您的邮件服务器地址
                                </p>
                            </div>
                            
                            <!-- SMTP端口 -->
                            <div>
                                <label for="smtp_port" class="block text-sm font-medium text-gray-700 mb-1">
                                    SMTP端口
                                </label>
                                <input 
                                    type="number" 
                                    id="smtp_port"
                                    name="smtp_port" 
                                    value="<?php echo $smtpPort; ?>" 
                                    min="1" 
                                    max="65535" 
                                    class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                    placeholder="25"
                                >
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fa fa-info-circle mr-1"></i>
                                    常用端口: 25(无加密), 465(SSL), 587(TLS)
                                </p>
                            </div>
                        </div>
                        
                        <!-- 加密方式 -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="smtp_encryption" class="block text-sm font-medium text-gray-700 mb-1">
                                    加密方式
                                </label>
                                <select 
                                    id="smtp_encryption"
                                    name="smtp_encryption" 
                                    class="form-select w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                >
                                    <option value="none" <?php echo ($smtpEncryption === 'none') ? 'selected' : ''; ?>>无</option>
                                    <option value="ssl" <?php echo ($smtpEncryption === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                    <option value="tls" <?php echo ($smtpEncryption === 'tls') ? 'selected' : ''; ?>>TLS</option>
                                </select>
                            </div>
                            
                            <!-- 内容类型 -->
                            <div>
                                <label for="email_content_type" class="block text-sm font-medium text-gray-700 mb-1">
                                    邮件内容类型
                                </label>
                                <select 
                                    id="email_content_type"
                                    name="email_content_type" 
                                    class="form-select w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                >
                                    <option value="html" <?php echo ($emailContentType === 'html') ? 'selected' : ''; ?>>HTML</option>
                                    <option value="text" <?php echo ($emailContentType === 'text') ? 'selected' : ''; ?>>纯文本</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- 认证信息 -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- SMTP用户名 -->
                            <div>
                                <label for="smtp_username" class="block text-sm font-medium text-gray-700 mb-1">
                                    SMTP用户名
                                </label>
                                <input 
                                    type="text" 
                                    id="smtp_username"
                                    name="smtp_username" 
                                    value="<?php echo escape($smtpUsername); ?>" 
                                    class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                    placeholder="用户名或邮箱地址"
                                >
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fa fa-info-circle mr-1"></i>
                                    留空表示不需要认证
                                </p>
                            </div>
                            
                            <!-- SMTP密码 -->
                            <div>
                                <label for="smtp_password" class="block text-sm font-medium text-gray-700 mb-1">
                                    SMTP密码
                                </label>
                                <input 
                                    type="password" 
                                    id="smtp_password"
                                    name="smtp_password" 
                                    class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                    placeholder="输入新密码或留空不修改"
                                >
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fa fa-info-circle mr-1"></i>
                                    留空表示保持现有密码不变
                                </p>
                            </div>
                        </div>
                        
                        <!-- 发件人设置 -->
                        <div class="pt-4 border-t border-gray-200">
                            <h4 class="text-md font-medium text-gray-700 mb-4">发件人设置</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- 发件人名称 -->
                                <div>
                                    <label for="from_name" class="block text-sm font-medium text-gray-700 mb-1">
                                        发件人名称
                                    </label>
                                    <input 
                                        type="text" 
                                        id="from_name"
                                        name="from_name" 
                                        value="<?php echo escape($fromName); ?>" 
                                        class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                        placeholder="您的名称"
                                    >
                                </div>
                                
                                <!-- 发件人邮箱 -->
                                <div>
                                    <label for="from_email" class="block text-sm font-medium text-gray-700 mb-1">
                                        发件人邮箱 <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="email" 
                                        id="from_email"
                                        name="from_email" 
                                        value="<?php echo escape($fromEmail); ?>" 
                                        class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                        placeholder="your@email.com"
                                        required
                                    >
                                </div>
                                
                                <!-- 回复邮箱 -->
                                <div>
                                    <label for="reply_to_email" class="block text-sm font-medium text-gray-700 mb-1">
                                        回复邮箱
                                    </label>
                                    <input 
                                        type="email" 
                                        id="reply_to_email"
                                        name="reply_to_email" 
                                        value="<?php echo escape($replyToEmail); ?>" 
                                        class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                        placeholder="reply@email.com"
                                    >
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fa fa-info-circle mr-1"></i>
                                        留空将使用发件人邮箱
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 收件人设置 -->
                        <div class="pt-4 border-t border-gray-200">
                            <h4 class="text-md font-medium text-gray-700 mb-4">收件人设置</h4>
                            
                            <div>
                                <label for="recipients" class="block text-sm font-medium text-gray-700 mb-1">
                                    通知收件人
                                </label>
                                <textarea 
                                    id="recipients"
                                    name="recipients" 
                                    class="form-textarea w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none min-h-[100px]"
                                    placeholder="每个邮箱地址一行">
                                    <?php echo escape($recipients); ?>
                                </textarea>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fa fa-info-circle mr-1"></i>
                                    每个邮箱地址一行，表单提交后将通知这些邮箱
                                </p>
                            </div>
                        </div>
                        
                        <!-- 通知设置 -->
                        <div class="pt-4 border-t border-gray-200">
                            <h4 class="text-md font-medium text-gray-700 mb-4">通知设置</h4>
                            
                            <div class="flex items-center mb-6">
                                <input 
                                    type="checkbox" 
                                    id="enable_notifications" 
                                    name="enable_notifications" 
                                    class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300 rounded"
                                    <?php echo ($enableNotifications) ? 'checked' : ''; ?>
                                >
                                <label for="enable_notifications" class="ml-2 text-sm font-medium text-gray-700">
                                    启用邮件通知
                                </label>
                            </div>
                            
                            <!-- 邮件主题模板 -->
                            <div>
                                <label for="subject_template" class="block text-sm font-medium text-gray-700 mb-1">
                                    邮件主题模板
                                </label>
                                <input 
                                    type="text" 
                                    id="subject_template"
                                    name="subject_template" 
                                    value="<?php echo escape($subjectTemplate); ?>" 
                                    class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                    placeholder="[新提交] {{site_title}}"
                                >
                                <div class="mt-2 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs text-gray-600 font-medium mb-2">可用变量：</p>
                                    <div class="flex flex-wrap gap-2">
                                        <span class="template-variable px-2 py-1 bg-yellow-50 rounded text-xs">{{site_title}}</span>
                                        <span class="template-variable px-2 py-1 bg-yellow-50 rounded text-xs">{{submit_time}}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 邮件内容模板 -->
                            <div class="mt-6">
                                <label for="body_template" class="block text-sm font-medium text-gray-700 mb-1">
                                    邮件内容模板
                                </label>
                                <textarea 
                                    id="body_template"
                                    name="body_template" 
                                    class="form-textarea w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none min-h-[300px]"
                                    placeholder="邮件内容">
                                    <?php echo escape($bodyTemplate); ?>
                                </textarea>
                                <div class="mt-2 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs text-gray-600 font-medium mb-2">可用变量：</p>
                                    <div class="flex flex-wrap gap-2">
                                        <span class="template-variable px-2 py-1 bg-yellow-50 rounded text-xs">{{site_title}}</span>
                                        <span class="template-variable px-2 py-1 bg-yellow-50 rounded text-xs">{{form_data}}</span>
                                        <span class="template-variable px-2 py-1 bg-yellow-50 rounded text-xs">{{submit_time}}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 保存按钮 -->
                        <div class="pt-4 flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-all-300 flex items-center gap-2">
                                <i class="fa fa-save"></i>
                                <span>保存邮件设置</span>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- 测试邮件发送卡片 -->
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6">测试邮件</h3>
                    
                    <!-- 邮件发送提示信息 -->
                    <div class="p-4 mb-6 bg-blue-50 border-l-4 border-blue-500 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fa fa-info-circle text-blue-500 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-blue-700">
                                    <strong>提示：</strong>当前邮件发送功能为基础实现，主要用于验证SMTP服务器连接是否正常。
                                    系统会自动记录详细的连接测试日志（包括错误代码和错误信息）到服务器错误日志中，帮助排查问题。
                                    <br>
                                    <br>
                                    在实际生产环境中，建议使用PHPMailer等专业的邮件发送库来获得更可靠的SMTP支持和更好的错误处理能力。
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="send_test_email" value="1">
                        
                        <!-- 测试邮箱 -->
                        <div class="max-w-md">
                            <label for="test_email" class="block text-sm font-medium text-gray-700 mb-1">
                                测试邮箱地址 <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="email" 
                                id="test_email"
                                name="test_email" 
                                class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                placeholder="输入要测试的邮箱地址"
                                required
                            >
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fa fa-info-circle mr-1"></i>
                                请输入有效的邮箱地址以测试邮件发送功能
                            </p>
                        </div>
                        
                        <!-- 发送按钮 -->
                        <div class="pt-4 flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-success text-white rounded-lg hover:bg-success/90 transition-all-300 flex items-center gap-2">
                                <i class="fa fa-paper-plane"></i>
                                <span>发送测试邮件</span>
                            </button>
                        </div>
                    </form>
                    
                    <!-- 邮件模板预览 -->
                    <div class="mt-8">
                        <h4 class="text-md font-medium text-gray-700 mb-3">邮件模板预览</h4>
                        <div class="template-preview">
                            <div class="mb-2 font-medium text-gray-700">主题:</div>
                            <div class="mb-4">
                                <?php 
                                    $previewSubject = str_replace(
                                        ['{{site_title}}', '{{submit_time}}'], 
                                        [$siteTitle, date('Y-m-d H:i:s')], 
                                        $subjectTemplate
                                    );
                                    echo escape($previewSubject);
                                ?>
                            </div>
                            <div class="mb-2 font-medium text-gray-700">内容:</div>
                            <div class="whitespace-pre-wrap">
                                <?php 
                                    $previewBody = str_replace(
                                        ['{{site_title}}', '{{submit_time}}'], 
                                        [$siteTitle, date('Y-m-d H:i:s')], 
                                        $bodyTemplate
                                    );
                                    $previewBody = str_replace('{{form_data}}', "<p>表单数据将在这里显示</p>", $previewBody);
                                    echo escape($previewBody);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- JavaScript -->
    <script>
        // 初始化TinyMCE富文本编辑器
        tinymce.init({
            selector: '#body_template',
            plugins: 'advlist autolink lists link image charmap print preview anchor searchreplace visualblocks code fullscreen insertdatetime media table paste code help wordcount',
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            menubar: 'file edit view insert format tools table help',
            height: 300,
            setup: function(editor) {
                editor.on('init', function() {
                    // 编辑器初始化完成后执行
                    editor.getBody().style.fontFamily = 'Inter, system-ui, sans-serif';
                    editor.getBody().style.fontSize = '14px';
                });
            }
        });
        
        // 页面加载完成后执行
        document.addEventListener('DOMContentLoaded', function() {
            // 移动端菜单切换
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
            
            // 点击侧边栏外部关闭侧边栏（移动端）
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 1024 && 
                    !sidebar.contains(event.target) && 
                    !menuToggle.contains(event.target) && 
                    sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            });
            
            // 加密方式选择时自动设置常见端口
            const smtpEncryption = document.getElementById('smtp_encryption');
            const smtpPort = document.getElementById('smtp_port');
            
            if (smtpEncryption && smtpPort) {
                smtpEncryption.addEventListener('change', function() {
                    const selectedValue = this.value;
                    
                    if (selectedValue === 'ssl' && smtpPort.value !== '465') {
                        smtpPort.value = '465';
                    } else if (selectedValue === 'tls' && smtpPort.value !== '587') {
                        smtpPort.value = '587';
                    } else if (selectedValue === 'none' && smtpPort.value !== '25') {
                        smtpPort.value = '25';
                    }
                });
            }
            
            // 内容类型切换时更新模板提示
            const emailContentType = document.getElementById('email_content_type');
            const bodyTemplate = document.getElementById('body_template');
            
            if (emailContentType && bodyTemplate) {
                emailContentType.addEventListener('change', function() {
                    const selectedValue = this.value;
                    
                    if (selectedValue === 'text') {
                        alert('切换到纯文本模式后，HTML标签将不会生效。请确保模板内容适合纯文本格式。');
                        
                        // 如果TinyMCE已初始化，尝试切换到纯文本模式
                        if (window.tinymce && tinymce.editors['body_template']) {
                            // 这里只是提示用户，不做实际切换，因为TinyMCE默认不支持纯文本模式
                        }
                    }
                });
            }
            
            // 窗口大小变化时的响应式调整
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>