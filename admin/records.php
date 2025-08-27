<?php
// 包含系统配置文件
require_once '../config.php';

// 验证管理员访问权限
adminAccess();

// 获取网站标题
$siteTitle = getSetting('site_title', '信息收集网站系统');

// 处理单个记录查看
$viewRecord = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $recordId = intval($_GET['id']);
    $viewRecord = getSingle("SELECT * FROM submissions WHERE id = :id", [':id' => $recordId]);
    if ($viewRecord) {
        $viewRecord['data'] = json_decode($viewRecord['data'], true);
    }
}

// 处理批量操作
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMessage = '表单验证失败，请重试';
    } else {
        // 处理批量删除
        if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['records'])) {
            $recordIds = implode(',', array_map('intval', $_POST['records']));
            $sql = "DELETE FROM submissions WHERE id IN ($recordIds)";
            
            if (execute($sql) > 0) {
                $successMessage = '选中的记录已成功删除';
                logAction('批量删除记录', '删除了 ' . count($_POST['records']) . ' 条记录');
            } else {
                $errorMessage = '删除失败，请稍后重试';
            }
        }
        
        // 处理批量标记为已处理
        if (isset($_POST['action']) && $_POST['action'] === 'mark_processed' && isset($_POST['records'])) {
            $recordIds = implode(',', array_map('intval', $_POST['records']));
            $sql = "UPDATE submissions SET status = 'processed' WHERE id IN ($recordIds)";
            
            if (execute($sql) > 0) {
                $successMessage = '选中的记录已标记为已处理';
                logAction('批量标记记录', '标记了 ' . count($_POST['records']) . ' 条记录为已处理');
            } else {
                $errorMessage = '操作失败，请稍后重试';
            }
        }
        
        // 处理单条记录删除
        if (isset($_POST['delete_record']) && is_numeric($_POST['delete_record'])) {
            $recordId = intval($_POST['delete_record']);
            $sql = "DELETE FROM submissions WHERE id = :id";
            
            if (execute($sql, [':id' => $recordId]) > 0) {
                $successMessage = '记录已成功删除';
                logAction('删除单条记录', '删除了ID为 ' . $recordId . ' 的记录');
                $viewRecord = null; // 清除查看的记录
            } else {
                $errorMessage = '删除失败，请稍后重试';
            }
        }
    }
}

// 处理搜索和筛选
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// 构建查询条件
$whereConditions = [];
$params = [];

if (!empty($searchTerm)) {
    $whereConditions[] = "data LIKE :search";
    $params[':search'] = '%' . $searchTerm . '%';
}

if (!empty($statusFilter)) {
    $whereConditions[] = "status = :status";
    $params[':status'] = $statusFilter;
}

if (!empty($dateFrom)) {
    $whereConditions[] = "created_at >= :date_from";
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $whereConditions[] = "created_at <= :date_to";
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

// 构建WHERE子句
$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(' AND ', $whereConditions);
}

// 分页设置
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 15; // 每页显示的记录数
$offset = ($page - 1) * $perPage;

// 获取总记录数
$totalRecords = getSingle("SELECT COUNT(*) as count FROM submissions $whereClause", $params)['count'];
$totalPages = max(1, ceil($totalRecords / $perPage));

// 获取记录列表
$records = getAll("SELECT * FROM submissions $whereClause ORDER BY created_at DESC LIMIT :offset, :per_page", array_merge($params, [':offset' => $offset, ':per_page' => $perPage]));

// 格式化记录数据
foreach ($records as &$record) {
    $record['data'] = json_decode($record['data'], true);
}

// 获取字段配置用于数据展示
$formFields = getAll("SELECT * FROM form_fields WHERE status = 'active' ORDER BY order_index ASC");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据管理 - <?php echo escape($siteTitle); ?></title>
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
        
        /* 表格行悬停效果 */
        .table-row:hover {
            background-color: #F1F5F9;
        }
        
        /* 加载动画 */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .animate-pulse-slow {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        /* 搜索框样式 */
        .search-input:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        /* 分页按钮样式 */
        .pagination-link {
            transition: all 0.2s ease;
        }
        
        .pagination-link:hover:not(:disabled) {
            background-color: #E2E8F0;
        }
        
        .pagination-link.active {
            background-color: #3B82F6;
            color: white;
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
                        <a href="records.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg sidebar-item-active">
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
                        数据管理
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
                                <span class="text-sm font-medium text-gray-800">数据管理</span>
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
                
                <!-- 记录详情模态框 -->
                <?php if ($viewRecord): ?>
                    <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
                        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                            <div class="sticky top-0 bg-white p-6 border-b border-gray-200 flex justify-between items-center">
                                <h3 class="text-xl font-bold text-gray-800">记录详情 #<?php echo $viewRecord['id']; ?></h3>
                                <div class="flex gap-3">
                                    <button id="closeDetailBtn" class="text-gray-500 hover:text-gray-700 transition-all-300">
                                        <i class="fa fa-times text-xl"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="p-6">
                                <div class="space-y-6">
                                    <!-- 记录基本信息 -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <p class="text-sm text-gray-500 mb-1">提交时间</p>
                                            <p class="font-medium text-gray-800">
                                                <?php echo date('Y-m-d H:i:s', strtotime($viewRecord['created_at'])); ?>
                                            </p>
                                        </div>
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <p class="text-sm text-gray-500 mb-1">IP地址</p>
                                            <p class="font-medium text-gray-800">
                                                <?php echo escape($viewRecord['ip_address']); ?>
                                            </p>
                                        </div>
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <p class="text-sm text-gray-500 mb-1">状态</p>
                                            <p class="font-medium text-<?php echo $viewRecord['status'] === 'processed' ? 'success' : 'warning'; ?>>
                                                <?php echo $viewRecord['status'] === 'processed' ? '已处理' : '待处理'; ?>
                                            </p>
                                        </div>
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <p class="text-sm text-gray-500 mb-1">用户代理</p>
                                            <p class="font-medium text-gray-800 truncate" title="<?php echo escape($viewRecord['user_agent']); ?>">
                                                <?php echo escape($viewRecord['user_agent']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- 表单数据 -->
                                    <div class="bg-white p-6 rounded-lg border border-gray-200">
                                        <h4 class="text-lg font-semibold text-gray-800 mb-4">表单数据</h4>
                                        <div class="space-y-4">
                                            <?php foreach ($viewRecord['data'] as $fieldName => $fieldValue): ?>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-500 mb-1">
                                                        <?php 
                                                            // 尝试获取字段标签
                                                            $fieldLabel = $fieldName;
                                                            foreach ($formFields as $field) {
                                                                if ($field['field_name'] === $fieldName) {
                                                                    $fieldLabel = $field['label'];
                                                                    break;
                                                                }
                                                            }
                                                            echo escape($fieldLabel);
                                                        ?>
                                                    </p>
                                                    <p class="text-gray-800 break-words">
                                                        <?php echo nl2br(escape($fieldValue)); ?>
                                                    </p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-6 border-t border-gray-200 bg-gray-50 rounded-b-xl flex justify-end gap-3">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="delete_record" value="<?php echo $viewRecord['id']; ?>">
                                    <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-all-300 flex items-center gap-2" onclick="return confirm('确定要删除这条记录吗？此操作不可撤销。');">
                                        <i class="fa fa-trash"></i>
                                        <span>删除记录</span>
                                    </button>
                                </form>
                                <button id="closeDetailBtn2" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-all-300">
                                    关闭
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- 搜索和筛选区域 -->
                <div class="bg-white rounded-xl p-6 shadow-sm mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">搜索和筛选</h3>
                    <form method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- 搜索框 -->
                            <div class="col-span-1 md:col-span-2">
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">
                                    搜索内容
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa fa-search text-gray-400"></i>
                                    </div>
                                    <input 
                                        type="text"
                                        id="search"
                                        name="search"
                                        class="search-input w-full pl-10 px-4 py-2.5 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                        placeholder="搜索表单内容中的关键词..."
                                        value="<?php echo escape($searchTerm); ?>"
                                    >
                                </div>
                            </div>
                            
                            <!-- 状态筛选 -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                                    状态
                                </label>
                                <select 
                                    id="status"
                                    name="status"
                                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                >
                                    <option value="">全部状态</option>
                                    <option value="pending" <?php echo ($statusFilter === 'pending') ? 'selected' : ''; ?>>待处理</option>
                                    <option value="processed" <?php echo ($statusFilter === 'processed') ? 'selected' : ''; ?>>已处理</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- 日期范围 -->
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">
                                    开始日期
                                </label>
                                <input 
                                    type="date"
                                    id="date_from"
                                    name="date_from"
                                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                    value="<?php echo escape($dateFrom); ?>"
                                >
                            </div>
                            
                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">
                                    结束日期
                                </label>
                                <input 
                                    type="date"
                                    id="date_to"
                                    name="date_to"
                                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                    value="<?php echo escape($dateTo); ?>"
                                >
                            </div>
                        </div>
                        
                        <!-- 搜索按钮 -->
                        <div class="flex justify-end gap-3">
                            <a href="records.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-all-300">
                                重置
                            </a>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-all-300">
                                搜索
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- 记录列表 -->
                <div class="bg-white rounded-xl shadow-sm mb-6">
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800">提交记录 (共 <?php echo number_format($totalRecords); ?> 条)</h3>
                        
                        <!-- 批量操作 -->
                        <form method="POST" id="batchActionForm" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <div class="flex items-center gap-3">
                                <select id="batchAction" name="action" class="px-3 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-sm">
                                    <option value="">批量操作</option>
                                    <option value="mark_processed">标记为已处理</option>
                                    <option value="delete">批量删除</option>
                                </select>
                                <button type="submit" id="applyBatchAction" class="px-3 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-all-300 text-sm opacity-50 cursor-not-allowed" disabled>
                                    应用
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- 表格 -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left w-12">
                                        <input type="checkbox" id="selectAll" class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300 rounded">
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        编号
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        提交内容
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        IP地址
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        时间
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        状态
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        操作
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($records)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-16 text-center text-gray-500">
                                            <i class="fa fa-folder-open-o text-4xl mb-3"></i>
                                            <p class="text-lg">没有找到匹配的记录</p>
                                            <p class="text-sm mt-1">请尝试调整搜索条件</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($records as $record): ?>
                                        <tr class="table-row transition-all-300">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input type="checkbox" name="records[]" value="<?php echo $record['id']; ?>" class="record-checkbox h-4 w-4 text-primary focus:ring-primary/20 border-gray-300 rounded">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                #<?php echo $record['id']; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="max-w-xs">
                                                    <?php 
                                                        // 显示主要字段内容
                                                        $displayFields = [];
                                                        if (isset($record['data']['name'])) {
                                                            $displayFields[] = ['label' => '姓名', 'value' => $record['data']['name']];
                                                        }
                                                        if (isset($record['data']['email'])) {
                                                            $displayFields[] = ['label' => '邮箱', 'value' => $record['data']['email']];
                                                        }
                                                        if (isset($record['data']['phone'])) {
                                                            $displayFields[] = ['label' => '电话', 'value' => $record['data']['phone']];
                                                        }
                                                        if (isset($record['data']['message']) && strlen($record['data']['message']) > 50) {
                                                            $displayFields[] = ['label' => '留言', 'value' => substr($record['data']['message'], 0, 50) . '...'];
                                                        }
                                                        
                                                        if (!empty($displayFields)) {
                                                            foreach ($displayFields as $field) {
                                                                echo '<div class="text-sm text-gray-700 mb-1">';
                                                                echo '<span class="font-medium text-gray-500">' . escape($field['label']) . ':</span> ' . escape($field['value']);
                                                                echo '</div>';
                                                            }
                                                        } else {
                                                            echo '<div class="text-sm text-gray-700">新提交记录</div>';
                                                        }
                                                    ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo escape($record['ip_address']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('Y-m-d H:i', strtotime($record['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $record['status'] === 'processed' ? 'green' : 'yellow'; ?>-100 text-<?php echo $record['status'] === 'processed' ? 'green' : 'yellow'; ?>-800">
                                                    <?php echo $record['status'] === 'processed' ? '已处理' : '待处理'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="records.php?id=<?php echo $record['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>"
                                                    class="text-primary hover:text-primary/80 transition-all-300 mr-3">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                                <button class="text-gray-500 hover:text-gray-700 transition-all-300 delete-btn" data-id="<?php echo $record['id']; ?>">
                                                    <i class="fa fa-trash-o"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- 分页控制 -->
                    <?php if ($totalPages > 1): ?>
                        <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
                            <div class="text-sm text-gray-600">
                                显示 <span class="font-medium"><?php echo $offset + 1; ?></span> 到 <span class="font-medium"><?php echo min($offset + $perPage, $totalRecords); ?></span> 条，共 <span class="font-medium"><?php echo number_format($totalRecords); ?></span> 条记录
                            </div>
                            
                            <nav class="flex items-center space-x-1">
                                <!-- 上一页 -->
                                <a href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>
                                    class="pagination-link px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-all-300 <?php echo $page === 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                    <?php echo $page === 1 ? 'aria-disabled="true"' : ''; ?>>
                                    <i class="fa fa-chevron-left"></i>
                                </a>
                                
                                <!-- 页码 -->
                                <?php 
                                    // 生成页码
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $startPage + 4);
                                    
                                    if ($endPage - $startPage < 4) {
                                        $startPage = max(1, $endPage - 4);
                                    }
                                    
                                    // 首页
                                    if ($startPage > 1) {
                                        echo '<a href="?page=1&search=' . urlencode($searchTerm) . '&status=' . urlencode($statusFilter) . '&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo) . '" class="pagination-link px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-all-300">1</a>';
                                        if ($startPage > 2) {
                                            echo '<span class="px-3 py-2 text-gray-400">...</span>';
                                        }
                                    }
                                    
                                    // 当前页码范围
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        if ($i === $page) {
                                            echo '<a href="?page=' . $i . '&search=' . urlencode($searchTerm) . '&status=' . urlencode($statusFilter) . '&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo) . '" class="pagination-link px-3 py-2 rounded-lg border border-primary bg-primary text-white font-medium">' . $i . '</a>';
                                        } else {
                                            echo '<a href="?page=' . $i . '&search=' . urlencode($searchTerm) . '&status=' . urlencode($statusFilter) . '&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo) . '" class="pagination-link px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-all-300">' . $i . '</a>';
                                        }
                                    }
                                    
                                    // 末页
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<span class="px-3 py-2 text-gray-400">...</span>';
                                        }
                                        echo '<a href="?page=' . $totalPages . '&search=' . urlencode($searchTerm) . '&status=' . urlencode($statusFilter) . '&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo) . '" class="pagination-link px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-all-300">' . $totalPages . '</a>';
                                    }
                                ?>
                                
                                <!-- 下一页 -->
                                <a href="?page=<?php echo min($totalPages, $page + 1); ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>
                                    class="pagination-link px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-all-300 <?php echo $page === $totalPages ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                    <?php echo $page === $totalPages ? 'aria-disabled="true"' : ''; ?>>
                                    <i class="fa fa-chevron-right"></i>
                                </a>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- 隐藏的删除表单 -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <input type="hidden" name="delete_record" id="delete_record_id">
    </form>
    
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
            
            // 关闭详情模态框
            const closeDetailBtns = document.querySelectorAll('#closeDetailBtn, #closeDetailBtn2');
            closeDetailBtns.forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', function() {
                        window.location.href = 'records.php?page=<?php echo $page; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>';
                    });
                }
            });
            
            // 全选/取消全选功能
            const selectAll = document.getElementById('selectAll');
            const recordCheckboxes = document.querySelectorAll('.record-checkbox');
            const applyBatchAction = document.getElementById('applyBatchAction');
            
            if (selectAll && recordCheckboxes.length > 0) {
                selectAll.addEventListener('change', function() {
                    recordCheckboxes.forEach(checkbox => {
                        checkbox.checked = selectAll.checked;
                    });
                    updateBatchActionButton();
                });
                
                recordCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        updateBatchActionButton();
                        // 检查是否所有复选框都被选中
                        const allChecked = Array.from(recordCheckboxes).every(cb => cb.checked);
                        selectAll.checked = allChecked;
                    });
                });
            }
            
            // 更新批量操作按钮状态
            function updateBatchActionButton() {
                const checkedCount = document.querySelectorAll('.record-checkbox:checked').length;
                if (checkedCount > 0) {
                    applyBatchAction.disabled = false;
                    applyBatchAction.classList.remove('opacity-50', 'cursor-not-allowed');
                } else {
                    applyBatchAction.disabled = true;
                    applyBatchAction.classList.add('opacity-50', 'cursor-not-allowed');
                }
            }
            
            // 批量操作表单提交前确认和处理
            const batchActionForm = document.getElementById('batchActionForm');
            const batchAction = document.getElementById('batchAction');
            
            if (batchActionForm && batchAction) {
                batchActionForm.addEventListener('submit', function(e) {
                    const action = batchAction.value;
                    const checkedBoxes = document.querySelectorAll('.record-checkbox:checked');
                    const checkedCount = checkedBoxes.length;
                    
                    if (!action || checkedCount === 0) {
                        e.preventDefault();
                        return;
                    }
                    
                    // 移除之前可能存在的隐藏字段
                    document.querySelectorAll('input[name="records[]"]').forEach(input => {
                        if (input.type === 'hidden') {
                            input.remove();
                        }
                    });
                    
                    // 创建隐藏字段来存储选中的记录ID
                    checkedBoxes.forEach(checkbox => {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'records[]';
                        hiddenInput.value = checkbox.value;
                        batchActionForm.appendChild(hiddenInput);
                    });
                    
                    if (action === 'delete') {
                        if (!confirm(`确定要删除选中的 ${checkedCount} 条记录吗？此操作不可撤销。`)) {
                            e.preventDefault();
                        }
                    } else if (action === 'mark_processed') {
                        if (!confirm(`确定要将选中的 ${checkedCount} 条记录标记为已处理吗？`)) {
                            e.preventDefault();
                        }
                    }
                });
            }
            
            // 单条记录删除按钮
            const deleteBtns = document.querySelectorAll('.delete-btn');
            const deleteForm = document.getElementById('deleteForm');
            const deleteRecordId = document.getElementById('delete_record_id');
            
            deleteBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const recordId = this.getAttribute('data-id');
                    if (confirm('确定要删除这条记录吗？此操作不可撤销。')) {
                        deleteRecordId.value = recordId;
                        deleteForm.submit();
                    }
                });
            });
            
            // 表格行悬停效果
            const tableRows = document.querySelectorAll('.table-row');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.classList.add('shadow-sm');
                    this.style.transform = 'translateY(-1px)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.classList.remove('shadow-sm');
                    this.style.transform = 'translateY(0)';
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