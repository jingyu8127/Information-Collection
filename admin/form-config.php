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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMessage = '表单验证失败，请重试';
    } else {
        // 处理字段更新
        if (isset($_POST['update_fields'])) {
            $fieldIds = $_POST['field_id'] ?? [];
            $labels = $_POST['label'] ?? [];
            $types = $_POST['type'] ?? [];
            $requireds = $_POST['required'] ?? [];
            $statuses = $_POST['status'] ?? [];
            $placeholders = $_POST['placeholder'] ?? [];
            $options = $_POST['options'] ?? [];
            
            // 开启事务
            beginTransaction();
            $hasError = false;
            
            try {
                // 更新每个字段
                foreach ($fieldIds as $index => $fieldId) {
                    $sql = "UPDATE form_fields SET 
                            label = :label, 
                            type = :type, 
                            required = :required, 
                            status = :status, 
                            placeholder = :placeholder, 
                            options = :options 
                            WHERE id = :id";
                    
                    $params = [
                        ':label' => $labels[$index] ?? '',
                        ':type' => $types[$index] ?? 'text',
                        ':required' => isset($requireds[$fieldId]) ? 1 : 0,
                        ':status' => isset($statuses[$fieldId]) ? 'active' : 'inactive',
                        ':placeholder' => $placeholders[$index] ?? '',
                        ':options' => $options[$index] ?? '',
                        ':id' => $fieldId
                    ];
                    
                    if (!execute($sql, $params)) {
                        $hasError = true;
                        break;
                    }
                }
                
                if (!$hasError) {
                    commitTransaction();
                    $successMessage = '表单字段配置已成功更新';
                    logAction('更新表单字段配置', '更新了表单字段设置');
                } else {
                    rollbackTransaction();
                    $errorMessage = '更新失败，请稍后重试';
                }
            } catch (Exception $e) {
                rollbackTransaction();
                $errorMessage = '操作过程中发生错误：' . $e->getMessage();
            }
        }
        
        // 处理排序更新
        if (isset($_POST['update_order'])) {
            $orderIds = $_POST['order_ids'] ?? [];
            
            if (!empty($orderIds)) {
                // 开启事务
                beginTransaction();
                $hasError = false;
                
                try {
                    // 更新排序
                    foreach ($orderIds as $orderIndex => $fieldId) {
                        $sql = "UPDATE form_fields SET order_index = :order_index WHERE id = :id";
                        
                        if (!execute($sql, [':order_index' => $orderIndex, ':id' => $fieldId])) {
                            $hasError = true;
                            break;
                        }
                    }
                    
                    if (!$hasError) {
                        commitTransaction();
                        $successMessage = '字段排序已成功更新';
                        logAction('更新表单字段排序', '调整了表单字段显示顺序');
                    } else {
                        rollbackTransaction();
                        $errorMessage = '排序更新失败，请稍后重试';
                    }
                } catch (Exception $e) {
                    rollbackTransaction();
                    $errorMessage = '操作过程中发生错误：' . $e->getMessage();
                }
            }
        }
        
        // 处理添加新字段
        if (isset($_POST['add_field'])) {
            $label = $_POST['new_label'] ?? '';
            $type = $_POST['new_type'] ?? 'text';
            $required = isset($_POST['new_required']) ? 1 : 0;
            $status = isset($_POST['new_status']) ? 'active' : 'inactive';
            $placeholder = $_POST['new_placeholder'] ?? '';
            $options = $_POST['new_options'] ?? '';
            
            if (empty($label)) {
                $errorMessage = '字段标签不能为空';
            } else {
                $sql = "INSERT INTO form_fields (label, field_name, type, required, status, placeholder, options, order_index)
                        VALUES (:label, :field_name, :type, :required, :status, :placeholder, :options, :order_index)";
                
                // 生成唯一字段名
                $fieldName = strtolower(str_replace([' ', '-', '_'], '_', $label));
                $fieldName = preg_replace('/[^a-z0-9_]/', '', $fieldName);
                
                // 确保字段名唯一
                $counter = 1;
                $originalFieldName = $fieldName;
                while (getSingle("SELECT id FROM form_fields WHERE field_name = :field_name", [':field_name' => $fieldName])) {
                    $fieldName = $originalFieldName . '_' . $counter;
                    $counter++;
                }
                
                // 获取最大排序索引
                $maxOrderIndex = getSingle("SELECT MAX(order_index) as max_order FROM form_fields")['max_order'] ?? -1;
                $orderIndex = $maxOrderIndex + 1;
                
                $params = [
                    ':label' => $label,
                    ':field_name' => $fieldName,
                    ':type' => $type,
                    ':required' => $required,
                    ':status' => $status,
                    ':placeholder' => $placeholder,
                    ':options' => $options,
                    ':order_index' => $orderIndex
                ];
                
                if (execute($sql, $params)) {
                    $successMessage = '新字段已成功添加';
                    logAction('添加表单字段', '添加了字段: ' . $label);
                } else {
                    $errorMessage = '添加字段失败，请稍后重试';
                }
            }
        }
        
        // 处理删除字段
        if (isset($_POST['delete_field']) && is_numeric($_POST['delete_field'])) {
            $fieldId = intval($_POST['delete_field']);
            
            // 检查是否有提交数据依赖此字段
            $hasDependencies = getSingle("SELECT COUNT(*) as count FROM submissions WHERE data LIKE :field_name", 
                                [':field_name' => '%"' . getSingle("SELECT field_name FROM form_fields WHERE id = :id", [':id' => $fieldId])['field_name'] . '"%']);
            
            if ($hasDependencies['count'] > 0) {
                $errorMessage = '该字段已有提交数据，无法删除';
            } else {
                // 先删除字段
                $fieldInfo = getSingle("SELECT * FROM form_fields WHERE id = :id", [':id' => $fieldId]);
                
                if (execute("DELETE FROM form_fields WHERE id = :id", [':id' => $fieldId])) {
                    // 调整剩余字段的排序
                    $sql = "UPDATE form_fields SET order_index = order_index - 1 WHERE order_index > :deleted_order";
                    execute($sql, [':deleted_order' => $fieldInfo['order_index']]);
                    
                    $successMessage = '字段已成功删除';
                    logAction('删除表单字段', '删除了字段: ' . $fieldInfo['label']);
                } else {
                    $errorMessage = '删除字段失败，请稍后重试';
                }
            }
        }
        
        // 处理提交设置更新
        if (isset($_POST['update_submit_settings'])) {
            $thankYouMessage = $_POST['thank_you_message'] ?? '';
            $autoRedirect = isset($_POST['auto_redirect']) ? 1 : 0;
            $redirectTime = intval($_POST['redirect_time'] ?? 3);
            $successTitle = $_POST['success_title'] ?? '提交成功';
            
            // 验证重定向时间
            if ($redirectTime < 1 || $redirectTime > 60) {
                $redirectTime = 3;
            }
            
            // 更新设置
            updateSetting('thank_you_message', $thankYouMessage);
            updateSetting('auto_redirect', $autoRedirect);
            updateSetting('redirect_time', $redirectTime);
            updateSetting('success_title', $successTitle);
            
            $successMessage = '提交设置已成功更新';
            logAction('更新提交设置', '修改了表单提交后的显示设置');
        }
    }
}

// 获取表单字段列表（按排序索引排序）
$formFields = getAll("SELECT * FROM form_fields ORDER BY order_index ASC");

// 获取提交设置
$thankYouMessage = getSetting('thank_you_message', '感谢您的提交，我们将尽快与您联系！');
$autoRedirect = getSetting('auto_redirect', 1);
$redirectTime = getSetting('redirect_time', 3);
$successTitle = getSetting('success_title', '提交成功');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>表单配置 - <?php echo escape($siteTitle); ?></title>
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
        
        /* 字段项拖拽样式 */
        .sortable-item {
            transition: all 0.2s ease;
            cursor: grab;
        }
        
        .sortable-item:active {
            cursor: grabbing;
        }
        
        .sortable-item.dragging {
            opacity: 0.5;
            transform: rotate(2deg);
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
                        <a href="form-config.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg sidebar-item-active">
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
                        表单配置
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
                                <span class="text-sm font-medium text-gray-800">表单配置</span>
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
                
                <!-- 字段管理卡片 -->
                <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">字段管理</h3>
                        <div class="text-sm text-gray-500">
                            <i class="fa fa-info-circle mr-1"></i>
                            拖拽字段可调整显示顺序
                        </div>
                    </div>
                    
                    <form method="POST" id="fieldsForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="update_fields" value="1">
                        
                        <!-- 字段列表 -->
                        <div class="space-y-6 mb-6" id="fieldsContainer">
                            <?php if (empty($formFields)): ?>
                                <div class="text-center py-12 text-gray-500">
                                    <i class="fa fa-table text-4xl mb-3"></i>
                                    <p class="text-lg">暂无表单字段</p>
                                    <p class="text-sm mt-1">请使用下方的表单添加新字段</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($formFields as $field): ?>
                                    <div class="sortable-item bg-gray-50 rounded-lg p-5 border border-gray-200 hover:border-primary/50 transition-all-300 relative">
                                        <div class="absolute top-4 left-4 cursor-grab text-gray-400 hover:text-primary">
                                            <i class="fa fa-bars"></i>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 pl-8">
                                            <!-- 字段信息 -->
                                            <div class="md:col-span-1">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    字段标签
                                                </label>
                                                <input 
                                                    type="text" 
                                                    name="label[]" 
                                                    value="<?php echo escape($field['label']); ?>" 
                                                    class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                                    placeholder="输入字段标签"
                                                    required
                                                >
                                                <input type="hidden" name="field_id[]" value="<?php echo $field['id']; ?>">
                                                <p class="text-xs text-gray-500 mt-1">
                                                    字段名: <?php echo $field['field_name']; ?>
                                                </p>
                                            </div>
                                            
                                            <!-- 字段类型 -->
                                            <div class="md:col-span-1">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    字段类型
                                                </label>
                                                <select 
                                                    name="type[]" 
                                                    class="form-select w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                                    data-field-id="<?php echo $field['id']; ?>"
                                                    onchange="toggleFieldOptions(this)"
                                                >
                                                    <option value="text" <?php echo ($field['type'] === 'text') ? 'selected' : ''; ?>>文本输入</option>
                                                    <option value="textarea" <?php echo ($field['type'] === 'textarea') ? 'selected' : ''; ?>>多行文本</option>
                                                    <option value="email" <?php echo ($field['type'] === 'email') ? 'selected' : ''; ?>>邮箱</option>
                                                    <option value="tel" <?php echo ($field['type'] === 'tel') ? 'selected' : ''; ?>>电话</option>
                                                    <option value="number" <?php echo ($field['type'] === 'number') ? 'selected' : ''; ?>>数字</option>
                                                    <option value="date" <?php echo ($field['type'] === 'date') ? 'selected' : ''; ?>>日期</option>
                                                    <option value="select" <?php echo ($field['type'] === 'select') ? 'selected' : ''; ?>>下拉选择</option>
                                                    <option value="radio" <?php echo ($field['type'] === 'radio') ? 'selected' : ''; ?>>单选按钮</option>
                                                    <option value="checkbox" <?php echo ($field['type'] === 'checkbox') ? 'selected' : ''; ?>>复选框</option>
                                                </select>
                                            </div>
                                            
                                            <!-- 占位符 -->
                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    占位文本
                                                </label>
                                                <input 
                                                    type="text" 
                                                    name="placeholder[]" 
                                                    value="<?php echo escape($field['placeholder']); ?>" 
                                                    class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                                    placeholder="占位提示文本"
                                                >
                                            </div>
                                        </div>
                                        
                                        <!-- 选项配置 (仅对特定类型字段显示) -->
                                        <div class="mt-4 pl-8" id="options-container-<?php echo $field['id']; ?>" style="display: <?php echo in_array($field['type'], ['select', 'radio', 'checkbox']) ? 'block' : 'none'; ?>">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                选项设置 (每行一个选项)
                                            </label>
                                            <textarea 
                                                name="options[]" 
                                                class="form-textarea w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none min-h-[80px]"
                                                placeholder="选项1
选项2
选项3">
                                                <?php echo escape($field['options']); ?>
                                            </textarea>
                                            <p class="text-xs text-gray-500 mt-1">
                                                每行输入一个选项，使用英文逗号分隔选项值和显示文本（如：value,显示文本）
                                            </p>
                                        </div>
                                        
                                        <!-- 字段属性 -->
                                        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 pl-8">
                                            <div class="flex items-center">
                                                <input 
                                                    type="checkbox" 
                                                    id="required-<?php echo $field['id']; ?>" 
                                                    name="required[<?php echo $field['id']; ?>]" 
                                                    class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300 rounded"
                                                    <?php echo ($field['required']) ? 'checked' : ''; ?>
                                                >
                                                <label for="required-<?php echo $field['id']; ?>" class="ml-2 text-sm text-gray-700">
                                                    必填字段
                                                </label>
                                            </div>
                                            
                                            <div class="flex items-center">
                                                <input 
                                                    type="checkbox" 
                                                    id="status-<?php echo $field['id']; ?>" 
                                                    name="status[<?php echo $field['id']; ?>]" 
                                                    class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300 rounded"
                                                    <?php echo ($field['status'] === 'active') ? 'checked' : ''; ?>
                                                >
                                                <label for="status-<?php echo $field['id']; ?>" class="ml-2 text-sm text-gray-700">
                                                    启用字段
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <!-- 操作按钮 -->
                                        <div class="absolute top-4 right-4 flex gap-2">
                                            <button type="button" class="delete-field-btn p-2 text-gray-500 hover:text-red-500 transition-all-300" data-id="<?php echo $field['id']; ?>">
                                                <i class="fa fa-trash-o"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 保存按钮 -->
                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-all-300 flex items-center gap-2">
                                <i class="fa fa-save"></i>
                                <span>保存字段配置</span>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- 添加新字段卡片 -->
                <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6">添加新字段</h3>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="add_field" value="1">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- 字段标签 -->
                            <div>
                                <label for="new_label" class="block text-sm font-medium text-gray-700 mb-1">
                                    字段标签 <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="new_label"
                                    name="new_label" 
                                    class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                    placeholder="输入字段标签"
                                    required
                                >
                            </div>
                            
                            <!-- 字段类型 -->
                            <div>
                                <label for="new_type" class="block text-sm font-medium text-gray-700 mb-1">
                                    字段类型 <span class="text-red-500">*</span>
                                </label>
                                <select 
                                    id="new_type"
                                    name="new_type" 
                                    class="form-select w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                    onchange="toggleNewFieldOptions(this)"
                                >
                                    <option value="text">文本输入</option>
                                    <option value="textarea">多行文本</option>
                                    <option value="email">邮箱</option>
                                    <option value="tel">电话</option>
                                    <option value="number">数字</option>
                                    <option value="date">日期</option>
                                    <option value="select">下拉选择</option>
                                    <option value="radio">单选按钮</option>
                                    <option value="checkbox">复选框</option>
                                </select>
                            </div>
                            
                            <!-- 占位文本 -->
                            <div>
                                <label for="new_placeholder" class="block text-sm font-medium text-gray-700 mb-1">
                                    占位文本
                                </label>
                                <input 
                                    type="text" 
                                    id="new_placeholder"
                                    name="new_placeholder" 
                                    class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                    placeholder="占位提示文本"
                                >
                            </div>
                            
                            <!-- 保留空列以保持布局平衡 -->
                            <div>
                                <div class="h-10"></div>
                            </div>
                        </div>
                        
                        <!-- 新字段选项配置 -->
                        <div id="new-options-container" style="display: none;">
                            <label for="new_options" class="block text-sm font-medium text-gray-700 mb-1">
                                选项设置 (每行一个选项)
                            </label>
                            <textarea 
                                id="new_options"
                                name="new_options" 
                                class="form-textarea w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none min-h-[80px]"
                                placeholder="选项1
选项2
选项3">
                            </textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                每行输入一个选项，使用英文逗号分隔选项值和显示文本（如：value,显示文本）
                            </p>
                        </div>
                        
                        <!-- 新字段属性 -->
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                            <div class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    id="new_required" 
                                    name="new_required" 
                                    class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300 rounded"
                                >
                                <label for="new_required" class="ml-2 text-sm text-gray-700">
                                    必填字段
                                </label>
                            </div>
                            
                            <div class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    id="new_status" 
                                    name="new_status" 
                                    class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300 rounded"
                                    checked
                                >
                                <label for="new_status" class="ml-2 text-sm text-gray-700">
                                    启用字段
                                </label>
                            </div>
                        </div>
                        
                        <!-- 添加按钮 -->
                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-all-300 flex items-center gap-2">
                                <i class="fa fa-plus-circle"></i>
                                <span>添加新字段</span>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- 提交设置卡片 -->
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6">提交设置</h3>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="update_submit_settings" value="1">
                        
                        <!-- 成功标题 -->
                        <div>
                            <label for="success_title" class="block text-sm font-medium text-gray-700 mb-1">
                                成功页面标题
                            </label>
                            <input 
                                type="text" 
                                id="success_title"
                                name="success_title" 
                                value="<?php echo escape($successTitle); ?>" 
                                class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                placeholder="提交成功"
                            >
                        </div>
                        
                        <!-- 感谢信息 -->
                        <div>
                            <label for="thank_you_message" class="block text-sm font-medium text-gray-700 mb-1">
                                感谢信息 (富文本)
                            </label>
                            <textarea 
                                id="thank_you_message"
                                name="thank_you_message" 
                                class="form-textarea w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none min-h-[200px]"
                                placeholder="感谢您的提交，我们将尽快与您联系！">
                                <?php echo $thankYouMessage; ?>
                            </textarea>
                        </div>
                        
                        <!-- 自动跳转设置 -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    id="auto_redirect" 
                                    name="auto_redirect" 
                                    class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300 rounded"
                                    <?php echo ($autoRedirect) ? 'checked' : ''; ?>
                                    onchange="toggleRedirectTime(this)"
                                >
                                <label for="auto_redirect" class="ml-2 text-sm font-medium text-gray-700">
                                    启用自动跳转
                                </label>
                            </div>
                            
                            <div id="redirect_time_container" style="display: <?php echo ($autoRedirect) ? 'block' : 'none'; ?>">
                                <label for="redirect_time" class="block text-sm font-medium text-gray-700 mb-1">
                                    跳转时间 (秒)
                                </label>
                                <input 
                                    type="number" 
                                    id="redirect_time"
                                    name="redirect_time" 
                                    value="<?php echo $redirectTime; ?>" 
                                    min="1" 
                                    max="60" 
                                    class="form-input w-24 px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                >
                            </div>
                        </div>
                        
                        <!-- 保存按钮 -->
                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-all-300 flex items-center gap-2">
                                <i class="fa fa-save"></i>
                                <span>保存提交设置</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <!-- 隐藏的删除表单 -->
    <form id="deleteFieldForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <input type="hidden" name="delete_field" id="delete_field_id">
    </form>
    
    <!-- 排序更新表单 -->
    <form id="orderForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <input type="hidden" name="update_order" value="1">
        <input type="hidden" name="order_ids" id="order_ids">
    </form>
    
    <!-- JavaScript -->
    <script>
        // 初始化TinyMCE富文本编辑器
        tinymce.init({
            selector: '#thank_you_message',
            plugins: 'advlist autolink lists link image charmap print preview anchor searchreplace visualblocks code fullscreen insertdatetime media table paste code help wordcount',
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            menubar: 'file edit view insert format tools table help',
            height: 200,
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
            
            // 初始化拖拽排序功能
            const fieldsContainer = document.getElementById('fieldsContainer');
            const sortableItems = document.querySelectorAll('.sortable-item');
            
            if (fieldsContainer && sortableItems.length > 0) {
                let draggedItem = null;
                let currentDropTarget = null;
                
                // 为每个可拖拽项添加事件监听
                sortableItems.forEach(item => {
                    // 开始拖拽
                    item.addEventListener('dragstart', function(e) {
                        draggedItem = this;
                        setTimeout(() => {
                            this.classList.add('dragging');
                        }, 0);
                    });
                    
                    // 结束拖拽
                    item.addEventListener('dragend', function() {
                        this.classList.remove('dragging');
                        draggedItem = null;
                        currentDropTarget = null;
                        
                        // 重置所有项的背景色
                        sortableItems.forEach(i => {
                            i.style.backgroundColor = '';
                        });
                        
                        // 更新排序
                        updateFieldOrder();
                    });
                    
                    // 拖拽经过
                    item.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        
                        // 阻止默认行为以允许放置
                        if (draggedItem !== this) {
                            // 计算应该放置在上方还是下方
                            const rect = this.getBoundingClientRect();
                            const y = e.clientY - rect.top;
                            const halfHeight = rect.height / 2;
                            
                            if (y < halfHeight) {
                                // 放置在当前项上方
                                this.style.borderTop = '2px solid #3B82F6';
                                this.style.borderBottom = '';
                            } else {
                                // 放置在当前项下方
                                this.style.borderBottom = '2px solid #3B82F6';
                                this.style.borderTop = '';
                            }
                            
                            currentDropTarget = this;
                        }
                    });
                    
                    // 拖拽离开
                    item.addEventListener('dragleave', function() {
                        this.style.borderTop = '';
                        this.style.borderBottom = '';
                        
                        if (currentDropTarget === this) {
                            currentDropTarget = null;
                        }
                    });
                    
                    // 放置
                    item.addEventListener('drop', function(e) {
                        e.preventDefault();
                        
                        if (draggedItem !== this) {
                            const rect = this.getBoundingClientRect();
                            const y = e.clientY - rect.top;
                            const halfHeight = rect.height / 2;
                            
                            if (y < halfHeight) {
                                // 放置在当前项上方
                                fieldsContainer.insertBefore(draggedItem, this);
                            } else {
                                // 放置在当前项下方
                                fieldsContainer.insertBefore(draggedItem, this.nextSibling);
                            }
                        }
                        
                        // 重置边框
                        this.style.borderTop = '';
                        this.style.borderBottom = '';
                    });
                    
                    // 设置为可拖拽
                    item.setAttribute('draggable', 'true');
                });
            }
            
            // 更新字段排序
            function updateFieldOrder() {
                const orderIds = [];
                const sortableItems = document.querySelectorAll('.sortable-item');
                
                sortableItems.forEach(item => {
                    const fieldId = item.querySelector('input[name="field_id[]"]').value;
                    orderIds.push(fieldId);
                });
                
                // 提交排序表单
                const orderForm = document.getElementById('orderForm');
                const orderIdsInput = document.getElementById('order_ids');
                
                if (orderForm && orderIdsInput) {
                    orderIdsInput.value = orderIds.join(',');
                    orderForm.submit();
                }
            }
            
            // 切换字段选项显示
            window.toggleFieldOptions = function(selectElement) {
                const fieldId = selectElement.getAttribute('data-field-id');
                const optionsContainer = document.getElementById('options-container-' + fieldId);
                const fieldType = selectElement.value;
                
                if (optionsContainer) {
                    if (['select', 'radio', 'checkbox'].includes(fieldType)) {
                        optionsContainer.style.display = 'block';
                    } else {
                        optionsContainer.style.display = 'none';
                    }
                }
            };
            
            // 切换新字段选项显示
            window.toggleNewFieldOptions = function(selectElement) {
                const optionsContainer = document.getElementById('new-options-container');
                const fieldType = selectElement.value;
                
                if (optionsContainer) {
                    if (['select', 'radio', 'checkbox'].includes(fieldType)) {
                        optionsContainer.style.display = 'block';
                    } else {
                        optionsContainer.style.display = 'none';
                    }
                }
            };
            
            // 切换重定向时间设置显示
            window.toggleRedirectTime = function(checkbox) {
                const redirectTimeContainer = document.getElementById('redirect_time_container');
                
                if (redirectTimeContainer) {
                    redirectTimeContainer.style.display = checkbox.checked ? 'block' : 'none';
                }
            };
            
            // 删除字段按钮
            const deleteFieldBtns = document.querySelectorAll('.delete-field-btn');
            const deleteFieldForm = document.getElementById('deleteFieldForm');
            const deleteFieldId = document.getElementById('delete_field_id');
            
            deleteFieldBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const fieldId = this.getAttribute('data-id');
                    if (confirm('确定要删除这个字段吗？如果已有提交数据，将无法删除。')) {
                        deleteFieldId.value = fieldId;
                        deleteFieldForm.submit();
                    }
                });
            });
            
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