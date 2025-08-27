<?php
// 包含系统配置文件
require_once '../config.php';

// 验证管理员访问权限
adminAccess();

// 获取网站标题
$siteTitle = getSetting('site_title', 'PHP全栈网站系统');

// 获取统计数据
$totalSubmissions = getSingle("SELECT COUNT(*) as count FROM submissions")['count'];
$todaySubmissions = getSingle("SELECT COUNT(*) as count FROM submissions WHERE DATE(created_at) = CURDATE()")['count'];
$totalVisits = getSingle("SELECT COALESCE(MAX(value), 0) as count FROM settings WHERE key_name = 'total_visits'")['count'];
$pendingSubmissions = getSingle("SELECT COUNT(*) as count FROM submissions WHERE status = 'pending'")['count'];

// 获取最近提交记录
$recentSubmissions = getAll("SELECT * FROM submissions ORDER BY created_at DESC LIMIT 5");

// 格式化提交数据
foreach ($recentSubmissions as &$submission) {
    $submission['data'] = json_decode($submission['data'], true);
    $submission['created_at'] = date('Y-m-d H:i:s', strtotime($submission['created_at']));
}

// 获取系统信息
$phpVersion = phpversion();
$mysqlVersion = getSingle("SELECT VERSION() as version")['version'];
$serverTime = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>控制面板 - <?php echo escape($siteTitle); ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    
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
        
        /* 统计卡片样式 */
        .stat-card {
            transition: all 0.3s ease;
            border-left: 4px solid #3B82F6;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
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
                        <a href="index.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg sidebar-item-active">
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
                        控制面板
                    </div>
                    
                    <!-- 用户信息 -->
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <button class="flex items-center gap-2 text-gray-600 hover:text-primary transition-all-300">
                                <i class="fa fa-bell-o text-xl"></i>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                    <?php echo $pendingSubmissions; ?>
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
                <!-- 欢迎信息 -->
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-800">
                        欢迎回来，<?php echo escape($_SESSION['admin_username']); ?>！
                    </h2>
                    <p class="text-gray-600 mt-2">
                        今天是 <?php echo date('Y年m月d日'); ?>，让我们开始今天的工作吧。
                    </p>
                </div>
                
                <!-- 统计卡片区域 -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- 总提交量 -->
                    <div class="stat-card bg-white rounded-xl p-6 shadow-sm border-l-4 border-primary">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">总提交量</p>
                                <h3 class="text-3xl font-bold text-gray-800 mt-1">
                                    <?php echo number_format($totalSubmissions); ?>
                                </h3>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary text-xl">
                                <i class="fa fa-paper-plane"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 今日提交 -->
                    <div class="stat-card bg-white rounded-xl p-6 shadow-sm border-l-4 border-success">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">今日提交</p>
                                <h3 class="text-3xl font-bold text-gray-800 mt-1">
                                    <?php echo number_format($todaySubmissions); ?>
                                </h3>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-success/10 flex items-center justify-center text-success text-xl">
                                <i class="fa fa-calendar-check-o"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 总访问量 -->
                    <div class="stat-card bg-white rounded-xl p-6 shadow-sm border-l-4 border-info">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">总访问量</p>
                                <h3 class="text-3xl font-bold text-gray-800 mt-1">
                                    <?php echo number_format($totalVisits); ?>
                                </h3>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-info/10 flex items-center justify-center text-info text-xl">
                                <i class="fa fa-eye"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 待处理 -->
                    <div class="stat-card bg-white rounded-xl p-6 shadow-sm border-l-4 border-warning">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">待处理</p>
                                <h3 class="text-3xl font-bold text-gray-800 mt-1">
                                    <?php echo number_format($pendingSubmissions); ?>
                                </h3>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-warning/10 flex items-center justify-center text-warning text-xl">
                                <i class="fa fa-clock-o"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 图表和数据区域 -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- 图表 -->
                    <div class="lg:col-span-2 bg-white rounded-xl p-6 shadow-sm">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-semibold text-gray-800">提交趋势</h3>
                            <div class="text-sm text-gray-500">过去30天</div>
                        </div>
                        <div class="h-80">
                            <canvas id="submissionsChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- 快捷操作 -->
                    <div class="bg-white rounded-xl p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6">快捷操作</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <a href="records.php" class="flex flex-col items-center justify-center p-4 rounded-lg border border-gray-200 hover:border-primary hover:bg-primary/5 transition-all-300">
                                <i class="fa fa-list-alt text-primary text-xl mb-2"></i>
                                <span class="text-sm font-medium text-gray-700">查看记录</span>
                            </a>
                            <a href="form-config.php" class="flex flex-col items-center justify-center p-4 rounded-lg border border-gray-200 hover:border-primary hover:bg-primary/5 transition-all-300">
                                <i class="fa fa-pencil-square-o text-primary text-xl mb-2"></i>
                                <span class="text-sm font-medium text-gray-700">配置表单</span>
                            </a>
                            <a href="email-settings.php" class="flex flex-col items-center justify-center p-4 rounded-lg border border-gray-200 hover:border-primary hover:bg-primary/5 transition-all-300">
                                <i class="fa fa-envelope-o text-primary text-xl mb-2"></i>
                                <span class="text-sm font-medium text-gray-700">邮件设置</span>
                            </a>
                            <a href="system.php" class="flex flex-col items-center justify-center p-4 rounded-lg border border-gray-200 hover:border-primary hover:bg-primary/5 transition-all-300">
                                <i class="fa fa-cog text-primary text-xl mb-2"></i>
                                <span class="text-sm font-medium text-gray-700">系统设置</span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- 最近提交记录 -->
                <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">最近提交记录</h3>
                        <a href="records.php" class="text-sm text-primary hover:text-primary/80 flex items-center gap-1 transition-all-300">
                            查看全部
                            <i class="fa fa-angle-right"></i>
                        </a>
                    </div>
                    
                    <!-- 记录表格 -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
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
                                        操作
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($recentSubmissions)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                            <i class="fa fa-folder-open-o text-3xl mb-2"></i>
                                            <p>暂无提交记录</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentSubmissions as $submission): ?>
                                        <tr class="table-row transition-all-300">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                #<?php echo $submission['id']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="truncate max-w-[200px]" title="<?php echo htmlspecialchars(json_encode($submission['data'])); ?>">
                                                    <?php 
                                                        // 显示主要字段内容
                                                        $displayContent = '';
                                                        if (isset($submission['data']['name'])) {
                                                            $displayContent = $submission['data']['name'];
                                                        } elseif (isset($submission['data']['email'])) {
                                                            $displayContent = $submission['data']['email'];
                                                        } else {
                                                            $displayContent = '新提交';
                                                        }
                                                        echo escape($displayContent);
                                                    ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo escape($submission['ip_address']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $submission['created_at']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="records.php?id=<?php echo $submission['id']; ?>
                                                    class="text-primary hover:text-primary/80 transition-all-300 mr-3">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                                <button class="text-gray-500 hover:text-gray-700 transition-all-300">
                                                    <i class="fa fa-trash-o"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- 系统信息 -->
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6">系统信息</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-gray-600">PHP版本</span>
                                <span class="text-gray-800 font-medium"><?php echo escape($phpVersion); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-gray-600">MySQL版本</span>
                                <span class="text-gray-800 font-medium"><?php echo escape($mysqlVersion); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">服务器时间</span>
                                <span class="text-gray-800 font-medium"><?php echo $serverTime; ?></span>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-gray-600">系统版本</span>
                                <span class="text-gray-800 font-medium"><?php echo SYSTEM_VERSION; ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-gray-600">当前用户</span>
                                <span class="text-gray-800 font-medium"><?php echo escape($_SESSION['admin_username']); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">用户角色</span>
                                <span class="text-gray-800 font-medium"><?php echo escape($_SESSION['admin_role']); ?></span>
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
            
            // 提交趋势图表
            const ctx = document.getElementById('submissionsChart');
            if (ctx) {
                // 模拟过去30天的数据
                const labels = Array.from({length: 30}, (_, i) => {
                    const date = new Date();
                    date.setDate(date.getDate() - 29 + i);
                    return `${date.getMonth() + 1}/${date.getDate()}`;
                });
                
                // 生成随机数据
                const data = Array.from({length: 30}, () => Math.floor(Math.random() * 20) + 1);
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '提交数量',
                            data: data,
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });
            }
            
            // 添加表格行悬停效果
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
            
            // 统计卡片悬停效果
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 10px 20px rgba(0, 0, 0, 0.1)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)';
                });
            });
            
            // 快捷操作按钮悬停效果
            const quickActions = document.querySelectorAll('.quick-action');
            quickActions.forEach(action => {
                action.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05)';
                });
                
                action.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
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