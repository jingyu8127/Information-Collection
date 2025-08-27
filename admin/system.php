<?php
// 包含系统配置文件
require_once '../config.php';

// 验证管理员访问权限
adminAccess();

// 获取网站标题
$siteTitle = getSetting('site_title', '信息收集网站系统');

// 初始化消息变量
$successMessage = '';
$errorMessage = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMessage = '表单验证失败，请重试';
    } else {
        // 处理网站信息更新
        if (isset($_POST['update_site_info'])) {
            $siteTitleNew = $_POST['site_title'] ?? '';
            $siteDescription = $_POST['site_description'] ?? '';
            $siteKeywords = $_POST['site_keywords'] ?? '';
            $siteFooter = $_POST['site_footer'] ?? '';
            
            // 更新设置
            updateSetting('site_title', $siteTitleNew);
            updateSetting('site_description', $siteDescription);
            updateSetting('site_keywords', $siteKeywords);
            updateSetting('site_footer', $siteFooter);
            
            $successMessage = '网站信息已成功更新';
            logAction('更新网站信息', '修改了网站基本信息');
        }
        
        // 处理管理员密码更新
        if (isset($_POST['update_admin_password'])) {
            // 检查是否为演示站
            if (defined('IS_DEMO_SITE') && IS_DEMO_SITE === true) {
                // 记录操作日志
                logAction('演示站密码修改尝试', '管理员 ' . ($_SESSION['admin_username'] ?? '未知') . ' 尝试修改密码，但演示站模式已禁用此功能');
                
                // 将密码恢复为默认值（假设默认密码是'admin123'）
                $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
                query("UPDATE admin SET password = ?", [$defaultPassword]);
                
                // 清除管理员会话
                unset($_SESSION['admin_id']);
                unset($_SESSION['admin_username']);
                unset($_SESSION['admin_role']);
                unset($_SESSION['last_login']);
                
                // 销毁会话
                session_destroy();
                
                // 设置退出原因cookie，以便在登录页面显示
                setcookie('logout_reason', '演示站无法执行此操作', time() + 30, '/');
                
                // 重定向到登录页面
                header('Location: login.php');
                exit;
            }
            
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // 验证当前密码
            $adminId = $_SESSION['admin_id'];
            $admin = getSingle("SELECT * FROM admin WHERE id = ?", [$adminId]);
            
            if (!password_verify($currentPassword, $admin['password'])) {
                $errorMessage = '当前密码不正确';
            } else if (empty($newPassword)) {
                $errorMessage = '新密码不能为空';
            } else if (strlen($newPassword) < 6) {
                $errorMessage = '新密码长度不能少于6位';
            } else if ($newPassword !== $confirmPassword) {
                $errorMessage = '两次输入的新密码不一致';
            } else {
                // 更新密码
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                query("UPDATE admin SET password = ? WHERE id = ?", [$hashedPassword, $adminId]);
                
                $successMessage = '管理员密码已成功更新';
                logAction('更新管理员密码', '修改了管理员账户密码');
            }
        }
        
        // 处理数据导出
        if (isset($_POST['export_data'])) {
            $exportType = $_POST['export_type'] ?? 'all';
            $exportDateFrom = $_POST['export_date_from'] ?? '';
            $exportDateTo = $_POST['export_date_to'] ?? '';
            
            // 构建导出SQL查询
            $query = "SELECT * FROM submissions";
            $params = [];
            
            if ($exportType === 'date_range' && !empty($exportDateFrom) && !empty($exportDateTo)) {
                $query .= " WHERE created_at BETWEEN ? AND ?";
                $params = [$exportDateFrom . ' 00:00:00', $exportDateTo . ' 23:59:59'];
            } else if ($exportType === 'pending') {
                $query .= " WHERE status = 'pending'";
            } else if ($exportType === 'processed') {
                $query .= " WHERE status = 'processed'";
            }
            
            $query .= " ORDER BY created_at DESC";
            
            // 获取数据
            $submissions = getAll($query, $params);
            
            if (!empty($submissions)) {
                // 设置CSV文件头
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="submissions_export_' . date('Ymd_His') . '.csv"');
                
                // 打开输出流
                $output = fopen('php://output', 'w');
                
                // 设置UTF-8 BOM以确保Excel正确识别编码
                fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
                
                // 写入CSV表头
                $headers = ['ID', '提交时间', '姓名', '邮箱', '电话', '留言内容', 'IP地址', '状态'];
                fputcsv($output, $headers);
                
                // 写入数据行
                foreach ($submissions as $submission) {
                    $data = json_decode($submission['data'], true);
                    $row = [
                        $submission['id'],
                        $submission['created_at'],
                        $data['name'] ?? '',
                        $data['email'] ?? '',
                        $data['phone'] ?? '',
                        $data['message'] ?? '',
                        $submission['ip_address'],
                        $submission['status'] === 'pending' ? '待处理' : '已处理'
                    ];
                    fputcsv($output, $row);
                }
                
                // 关闭输出流
                fclose($output);
                exit;
            } else {
                $errorMessage = '没有找到可导出的数据';
            }
        }
        
        // 处理数据清理
        if (isset($_POST['clean_data'])) {
            $cleanType = $_POST['clean_type'] ?? 'all';
            $cleanDateBefore = $_POST['clean_date_before'] ?? '';
            
            if ($cleanType === 'date_before' && !empty($cleanDateBefore)) {
                // 清理指定日期之前的数据
                $result = query("DELETE FROM submissions WHERE created_at < ?", [$cleanDateBefore . ' 00:00:00']);
                $affectedRows = $result->rowCount();
                
                $successMessage = '已成功清理 ' . $affectedRows . ' 条数据';
                logAction('清理历史数据', '清理了 ' . $affectedRows . ' 条 ' . $cleanDateBefore . ' 之前的数据');
            } else if ($cleanType === 'processed') {
                // 清理已处理的数据
                $result = query("DELETE FROM submissions WHERE status = 'processed'");
                $affectedRows = $result->rowCount();
                
                $successMessage = '已成功清理 ' . $affectedRows . ' 条已处理数据';
                logAction('清理已处理数据', '清理了 ' . $affectedRows . ' 条已处理的表单提交数据');
            } else if ($cleanType === 'all') {
                // 清理所有数据
                $result = query("DELETE FROM submissions");
                $affectedRows = $result->rowCount();
                
                $successMessage = '已成功清理全部 ' . $affectedRows . ' 条数据';
                logAction('清理全部数据', '清理了全部表单提交数据');
            }
        }
        
        // 处理清空缓存
        if (isset($_POST['clear_cache'])) {
            // 清空所有缓存
            $cacheDir = '../cache/';
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            
            $successMessage = '系统缓存已成功清空';
            logAction('清空系统缓存', '手动清空了系统所有缓存文件');
        }
    }
}

// 获取当前网站设置
$siteTitleCurrent = getSetting('site_title', '信息收集网站系统');
$siteDescription = getSetting('site_description', '这是一个基于PHP的信息收集网站系统');
$siteKeywords = getSetting('site_keywords', 'PHP,信息收集,');
$siteFooter = getSetting('site_footer', '© ' . date('Y') . ' PHP全栈网站系统 版权所有');

// 获取系统信息
// 获取MySQL版本
$mysqlVersion = getSingle("SELECT VERSION() as version")['version'] ?? '未知';

// 获取提交总数
$totalSubmissions = getSingle("SELECT COUNT(*) as count FROM submissions")['count'] ?? 0;

// 获取待处理提交数
$pendingSubmissions = getSingle("SELECT COUNT(*) as count FROM submissions WHERE status = 'pending'")['count'] ?? 0;

$systemInfo = [
    'php_version' => phpversion(),
    'server_os' => PHP_OS,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
    'mysql_version' => $mysqlVersion,
    'system_version' => SYSTEM_VERSION,
    'total_submissions' => $totalSubmissions,
    'pending_submissions' => $pendingSubmissions,
    'total_views' => getSetting('total_views', 0),
    'last_login' => $_SESSION['last_login'] ?? '首次登录'
];

// 生成时间选择器的最小和最大日期
$minDate = getSingle("SELECT MIN(DATE(created_at)) as min_date FROM submissions")['min_date'] ?? date('Y-m-d', strtotime('-1 year'));
$maxDate = date('Y-m-d');
$defaultCleanDate = date('Y-m-d', strtotime('-3 months'));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - <?php echo escape($siteTitle); ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    
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
        
        /* 系统信息卡片样式 */
        .system-info-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .system-info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* 数据清理警告样式 */
        .warning-alert {
            border-left: 4px solid #F59E0B;
        }
        
        /* 安全提醒样式 */
        .security-tip {
            position: relative;
            padding-left: 28px;
        }
        
        .security-tip:before {
            content: '\f023';
            font-family: 'FontAwesome';
            position: absolute;
            left: 0;
            top: 0;
            font-size: 16px;
            color: #F59E0B;
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
                        <a href="email-settings.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:text-white">
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
                        <a href="system.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg sidebar-item-active">
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
                        系统设置
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
                                <span class="text-sm font-medium text-gray-800">系统设置</span>
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
                
                <!-- 系统信息卡片 -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- PHP版本 -->
                    <div class="bg-white rounded-xl p-6 shadow-sm system-info-card">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center text-primary">
                                <i class="fa fa-code text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">PHP版本</h3>
                                <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo escape($systemInfo['php_version']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 服务器操作系统 -->
                    <div class="bg-white rounded-xl p-6 shadow-sm system-info-card">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center text-green-600">
                                <i class="fa fa-server text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">服务器操作系统</h3>
                                <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo escape($systemInfo['server_os']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 数据库版本 -->
                    <div class="bg-white rounded-xl p-6 shadow-sm system-info-card">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center text-purple-600">
                                <i class="fa fa-database text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">MySQL版本</h3>
                                <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo escape($systemInfo['mysql_version']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 系统版本 -->
                    <div class="bg-white rounded-xl p-6 shadow-sm system-info-card">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600">
                                <i class="fa fa-cogs text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">系统版本</h3>
                                <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo SYSTEM_VERSION; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 网站信息设置 -->
                <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">网站信息</h3>
                        <span class="px-3 py-1 bg-blue-100 text-blue-600 rounded-full text-xs font-medium">基础设置</span>
                    </div>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="update_site_info" value="1">
                        
                        <!-- 网站标题 -->
                        <div>
                            <label for="site_title" class="block text-sm font-medium text-gray-700 mb-1">
                                网站标题 <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="site_title"
                                name="site_title" 
                                value="<?php echo escape($siteTitleCurrent); ?>"
                                class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                placeholder="请输入网站标题"
                                required
                            >
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fa fa-info-circle mr-1"></i>
                                网站的主要标题，将显示在浏览器标签和首页
                            </p>
                        </div>
                        
                        <!-- 网站描述 -->
                        <div>
                            <label for="site_description" class="block text-sm font-medium text-gray-700 mb-1">
                                网站描述
                            </label>
                            <textarea 
                                id="site_description"
                                name="site_description" 
                                class="form-textarea w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none min-h-[80px]"
                                placeholder="请输入网站描述">
                                <?php echo escape($siteDescription); ?>
                            </textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fa fa-info-circle mr-1"></i>
                                网站的简短描述，用于SEO和元标签
                            </p>
                        </div>
                        
                        <!-- 网站关键词 -->
                        <div>
                            <label for="site_keywords" class="block text-sm font-medium text-gray-700 mb-1">
                                网站关键词
                            </label>
                            <input 
                                type="text" 
                                id="site_keywords"
                                name="site_keywords" 
                                value="<?php echo escape($siteKeywords); ?>"
                                class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                placeholder="请输入网站关键词，用逗号分隔"
                            >
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fa fa-info-circle mr-1"></i>
                                用于SEO优化的关键词，多个关键词用逗号分隔
                            </p>
                        </div>
                        
                        <!-- 网站页脚 -->
                        <div>
                            <label for="site_footer" class="block text-sm font-medium text-gray-700 mb-1">
                                网站页脚
                            </label>
                            <input 
                                type="text" 
                                id="site_footer"
                                name="site_footer" 
                                value="<?php echo escape($siteFooter); ?>"
                                class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                placeholder="请输入网站页脚信息"
                            >
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fa fa-info-circle mr-1"></i>
                                显示在网站底部的版权信息
                            </p>
                        </div>
                        
                        <!-- 保存按钮 -->
                        <div class="pt-4 flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-all-300 flex items-center gap-2">
                                <i class="fa fa-save"></i>
                                <span>保存网站信息</span>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- 管理员账户设置 -->
                <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">管理员账户</h3>
                        <span class="px-3 py-1 bg-green-100 text-green-600 rounded-full text-xs font-medium">安全设置</span>
                    </div>
                    
                    <div class="mb-6 security-tip text-sm text-gray-600">
                        为了账户安全，建议定期更改密码，并确保密码包含字母、数字和特殊字符
                    </div>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="update_admin_password" value="1">
                        
                        <!-- 当前密码 -->
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">
                                当前密码 <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="password" 
                                id="current_password"
                                name="current_password" 
                                class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                placeholder="请输入当前密码"
                                required
                            >
                        </div>
                        
                        <!-- 新密码 -->
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
                                新密码 <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="password" 
                                id="new_password"
                                name="new_password" 
                                class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                placeholder="请输入新密码（至少6位）"
                                required
                            >
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fa fa-info-circle mr-1"></i>
                                密码长度至少6位，建议包含字母、数字和特殊字符
                            </p>
                        </div>
                        
                        <!-- 确认新密码 -->
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                                确认新密码 <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="password" 
                                id="confirm_password"
                                name="confirm_password" 
                                class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                placeholder="请再次输入新密码"
                                required
                            >
                        </div>
                        
                        <!-- 保存按钮 -->
                        <div class="pt-4 flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-all-300 flex items-center gap-2">
                                <i class="fa fa-key"></i>
                                <span>更新密码</span>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- 数据导出 -->
                <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">数据导出</h3>
                        <span class="px-3 py-1 bg-purple-100 text-purple-600 rounded-full text-xs font-medium">数据管理</span>
                    </div>
                    
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h4 class="text-md font-medium text-gray-700 mb-2">导出选项</h4>
                        
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="export_data" value="1">
                            
                            <!-- 导出类型 -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">
                                    导出范围
                                </label>
                                <div class="space-y-3">
                                    <!-- 全部数据 -->
                                    <div class="flex items-center">
                                        <input 
                                            type="radio" 
                                            id="export_type_all"
                                            name="export_type" 
                                            value="all" 
                                            class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300"
                                            checked
                                        >
                                        <label for="export_type_all" class="ml-2 text-sm font-medium text-gray-700">
                                            导出全部数据
                                        </label>
                                    </div>
                                    
                                    <!-- 待处理数据 -->
                                    <div class="flex items-center">
                                        <input 
                                            type="radio" 
                                            id="export_type_pending"
                                            name="export_type" 
                                            value="pending" 
                                            class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300"
                                        >
                                        <label for="export_type_pending" class="ml-2 text-sm font-medium text-gray-700">
                                            仅导出待处理数据
                                        </label>
                                    </div>
                                    
                                    <!-- 已处理数据 -->
                                    <div class="flex items-center">
                                        <input 
                                            type="radio" 
                                            id="export_type_processed"
                                            name="export_type" 
                                            value="processed" 
                                            class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300"
                                        >
                                        <label for="export_type_processed" class="ml-2 text-sm font-medium text-gray-700">
                                            仅导出已处理数据
                                        </label>
                                    </div>
                                    
                                    <!-- 日期范围 -->
                                    <div class="flex items-center">
                                        <input 
                                            type="radio" 
                                            id="export_type_date_range"
                                            name="export_type" 
                                            value="date_range" 
                                            class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300"
                                        >
                                        <label for="export_type_date_range" class="ml-2 text-sm font-medium text-gray-700">
                                            按日期范围导出
                                        </label>
                                    </div>
                                    
                                    <!-- 日期选择器 (默认隐藏) -->
                                    <div id="date_range_container" class="ml-6 mt-2 space-y-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- 开始日期 -->
                                        <div>
                                            <label for="export_date_from" class="block text-sm font-medium text-gray-700 mb-1">
                                                开始日期
                                            </label>
                                            <input 
                                                type="date" 
                                                id="export_date_from"
                                                name="export_date_from" 
                                                class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                                min="<?php echo escape($minDate); ?>"
                                                max="<?php echo escape($maxDate); ?>"
                                                value="<?php echo escape($minDate); ?>"
                                            >
                                        </div>
                                        
                                        <!-- 结束日期 -->
                                        <div>
                                            <label for="export_date_to" class="block text-sm font-medium text-gray-700 mb-1">
                                                结束日期
                                            </label>
                                            <input 
                                                type="date" 
                                                id="export_date_to"
                                                name="export_date_to" 
                                                class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                                min="<?php echo escape($minDate); ?>"
                                                max="<?php echo escape($maxDate); ?>"
                                                value="<?php echo escape($maxDate); ?>"
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 导出按钮 -->
                            <div class="pt-4 flex justify-end">
                                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-all-300 flex items-center gap-2">
                                    <i class="fa fa-download"></i>
                                    <span>导出CSV数据</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- 数据清理 -->
                <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">数据清理</h3>
                        <span class="px-3 py-1 bg-red-100 text-red-600 rounded-full text-xs font-medium">危险操作</span>
                    </div>
                    
                    <!-- 警告提示 -->
                    <div class="warning-alert bg-yellow-50 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fa fa-exclamation-triangle text-yellow-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700 font-medium">警告：数据清理操作不可恢复！</p>
                                <p class="text-xs text-yellow-600 mt-1">
                                    请确保已备份重要数据，清理后将无法恢复已删除的表单提交记录
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h4 class="text-md font-medium text-gray-700 mb-2">清理选项</h4>
                        
                        <form method="POST" class="space-y-6" onsubmit="return confirmCleanup();">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="clean_data" value="1">
                            
                            <!-- 清理类型 -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">
                                    清理范围
                                </label>
                                <div class="space-y-3">
                                    <!-- 已处理数据 -->
                                    <div class="flex items-center">
                                        <input 
                                            type="radio" 
                                            id="clean_type_processed"
                                            name="clean_type" 
                                            value="processed" 
                                            class="h-4 w-4 text-red-500 focus:ring-red-500/20 border-gray-300"
                                            checked
                                        >
                                        <label for="clean_type_processed" class="ml-2 text-sm font-medium text-gray-700">
                                            清理已处理数据
                                        </label>
                                    </div>
                                    
                                    <!-- 指定日期之前的数据 -->
                                    <div class="flex items-center">
                                        <input 
                                            type="radio" 
                                            id="clean_type_date_before"
                                            name="clean_type" 
                                            value="date_before" 
                                            class="h-4 w-4 text-red-500 focus:ring-red-500/20 border-gray-300"
                                        >
                                        <label for="clean_type_date_before" class="ml-2 text-sm font-medium text-gray-700">
                                            清理指定日期之前的数据
                                        </label>
                                    </div>
                                    
                                    <!-- 日期选择器 (默认隐藏) -->
                                    <div id="clean_date_container" class="ml-6 mt-2">
                                        <div>
                                            <label for="clean_date_before" class="block text-sm font-medium text-gray-700 mb-1">
                                                清理此日期之前的数据
                                            </label>
                                            <input 
                                                type="date" 
                                                id="clean_date_before"
                                                name="clean_date_before" 
                                                class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                                min="<?php echo escape($minDate); ?>"
                                                max="<?php echo escape($maxDate); ?>"
                                                value="<?php echo escape($defaultCleanDate); ?>"
                                            >
                                        </div>
                                    </div>
                                    
                                    <!-- 全部数据 -->
                                    <div class="flex items-center">
                                        <input 
                                            type="radio" 
                                            id="clean_type_all"
                                            name="clean_type" 
                                            value="all" 
                                            class="h-4 w-4 text-red-500 focus:ring-red-500/20 border-gray-300"
                                        >
                                        <label for="clean_type_all" class="ml-2 text-sm font-medium text-gray-700">
                                            清理全部数据 <span class="text-red-500">(极其危险)</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 清理按钮 -->
                            <div class="pt-4 flex justify-end">
                                <button type="submit" class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-all-300 flex items-center gap-2">
                                    <i class="fa fa-trash-o"></i>
                                    <span>执行数据清理</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- 系统维护 -->
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">系统维护</h3>
                        <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">维护工具</span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- 清空缓存 -->
                        <div class="border border-gray-200 rounded-lg p-6">
                            <div class="flex items-start gap-4 mb-4">
                                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center text-purple-600">
                                    <i class="fa fa-refresh"></i>
                                </div>
                                <div>
                                    <h4 class="text-md font-medium text-gray-800">清空系统缓存</h4>
                                    <p class="text-xs text-gray-500 mt-1">
                                        清理系统临时文件和缓存，提高系统性能
                                    </p>
                                </div>
                            </div>
                            
                            <form method="POST" class="flex justify-end">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="clear_cache" value="1">
                                <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-all-300 flex items-center gap-2">
                                    <i class="fa fa-broom"></i>
                                    <span>清空缓存</span>
                                </button>
                            </form>
                        </div>
                        
                        <!-- 系统信息 -->
                        <div class="border border-gray-200 rounded-lg p-6">
                            <div class="flex items-start gap-4 mb-4">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600">
                                    <i class="fa fa-info-circle"></i>
                                </div>
                                <div>
                                    <h4 class="text-md font-medium text-gray-800">系统信息</h4>
                                    <p class="text-xs text-gray-500 mt-1">
                                        查看系统详细信息和统计数据
                                    </p>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">服务器软件:</span>
                                    <span class="font-medium text-gray-700"><?php echo escape($systemInfo['server_software']); ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">总提交量:</span>
                                    <span class="font-medium text-gray-700"><?php echo number_format($systemInfo['total_submissions']); ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">待处理提交:</span>
                                    <span class="font-medium text-amber-600"><?php echo number_format($systemInfo['pending_submissions']); ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">总访问量:</span>
                                    <span class="font-medium text-gray-700"><?php echo number_format($systemInfo['total_views']); ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">上次登录:</span>
                                    <span class="font-medium text-gray-700"><?php echo escape($systemInfo['last_login']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- JavaScript -->
    <script>
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
            
            // 处理导出日期范围显示/隐藏
            const exportTypeRadios = document.querySelectorAll('input[name="export_type"]');
            const dateRangeContainer = document.getElementById('date_range_container');
            
            exportTypeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'date_range') {
                        dateRangeContainer.style.display = 'grid';
                    } else {
                        dateRangeContainer.style.display = 'none';
                    }
                });
            });
            
            // 初始化时检查导出日期范围是否应该显示
            const exportTypeDateRange = document.getElementById('export_type_date_range');
            if (exportTypeDateRange && exportTypeDateRange.checked) {
                dateRangeContainer.style.display = 'grid';
            } else {
                dateRangeContainer.style.display = 'none';
            }
            
            // 处理清理日期选择器显示/隐藏
            const cleanTypeRadios = document.querySelectorAll('input[name="clean_type"]');
            const cleanDateContainer = document.getElementById('clean_date_container');
            
            cleanTypeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'date_before') {
                        cleanDateContainer.style.display = 'block';
                    } else {
                        cleanDateContainer.style.display = 'none';
                    }
                });
            });
            
            // 初始化时检查清理日期选择器是否应该显示
            const cleanTypeDateBefore = document.getElementById('clean_type_date_before');
            if (cleanTypeDateBefore && cleanTypeDateBefore.checked) {
                cleanDateContainer.style.display = 'block';
            } else {
                cleanDateContainer.style.display = 'none';
            }
            
            // 数据清理确认函数
            window.confirmCleanup = function() {
                const cleanType = document.querySelector('input[name="clean_type"]:checked').value;
                let confirmMessage = '';
                
                if (cleanType === 'processed') {
                    confirmMessage = '确定要清理所有已处理的数据吗？此操作不可恢复！';
                } else if (cleanType === 'date_before') {
                    const date = document.getElementById('clean_date_before').value;
                    confirmMessage = '确定要清理 ' + date + ' 之前的所有数据吗？此操作不可恢复！';
                } else if (cleanType === 'all') {
                    confirmMessage = '警告！您即将清理所有数据，此操作不可恢复！\n请确认您已备份重要数据！\n\n确定要继续吗？';
                }
                
                // 二次确认
                if (confirm(confirmMessage)) {
                    if (cleanType === 'all') {
                        return confirm('最后确认：清理全部数据将导致所有表单提交记录丢失，确定要继续吗？');
                    }
                    return true;
                }
                return false;
            };
            
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