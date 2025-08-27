<?php
// 包含系统配置文件
require_once '../config.php';

/**
 * 删除特定的缓存
 * @param string $key 缓存键
 * @return bool 是否删除成功
 */
function deleteCache($key) {
    // 调用config.php中已有的clearCache函数
    return clearCache($key);
}

/**
 * 获取背景图片URL
 * @return string 背景图片URL
 */
function getBackgroundImage() {
    // 获取背景设置
    $bgProvider = getSetting('bg_provider', 'random');
    $bgApiUrls = getSetting('bg_api_urls');
    $bgCustomImages = json_decode(getSetting('bg_custom_images', '[]'), true);
    $bgEnabled = getSetting('bg_enabled', 1);
    
    // 如果背景未启用，返回空
    if (!$bgEnabled) {
        return '';
    }
    
    // 根据不同的提供方返回不同的背景图片
    switch ($bgProvider) {
        case 'random':
            // 随机背景API列表
            $defaultApis = [
                'https://picsum.photos/1920/1080',
                'https://source.unsplash.com/random/1920x1080',
                'https://random.photos/1920/1080'
            ];
            
            // 从默认API中随机选择一个
            $randomApi = $defaultApis[array_rand($defaultApis)];
            return $randomApi;
            
        case 'custom_api':
            // 使用自定义API
            if (!empty($bgApiUrls)) {
                $apiUrls = explode("\n", $bgApiUrls);
                $apiUrls = array_map('trim', $apiUrls);
                $apiUrls = array_filter($apiUrls);
                
                if (!empty($apiUrls)) {
                    return reset($apiUrls); // 返回第一个API URL
                }
            }
            break;
            
        case 'custom_images':
            // 使用自定义上传的图片
            if (!empty($bgCustomImages)) {
                $randomImage = $bgCustomImages[array_rand($bgCustomImages)];
                return '../uploads/' . $randomImage;
            }
            break;
    }
    
    // 默认返回随机背景
    return 'https://picsum.photos/1920/1080';
}

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
        // 处理背景设置更新
        if (isset($_POST['update_background_settings'])) {
            $bgProvider = $_POST['bg_provider'] ?? 'random';
            $bgApiUrls = $_POST['bg_api_urls'] ?? '';
            $bgCustomImages = $_POST['bg_custom_images'] ?? '';
            $bgOverlayOpacity = floatval($_POST['bg_overlay_opacity'] ?? 0.5);
            $bgRefreshInterval = intval($_POST['bg_refresh_interval'] ?? 30);
            $bgEnabled = isset($_POST['bg_enabled']) ? 1 : 0;
            
            // 验证透明度值
            if ($bgOverlayOpacity < 0 || $bgOverlayOpacity > 1) {
                $bgOverlayOpacity = 0.5;
            }
            
            // 验证刷新间隔
            if ($bgRefreshInterval < 5 || $bgRefreshInterval > 3600) {
                $bgRefreshInterval = 30;
            }
            
            // 更新设置
            updateSetting('bg_provider', $bgProvider);
            updateSetting('bg_api_urls', $bgApiUrls);
            updateSetting('bg_custom_images', $bgCustomImages);
            updateSetting('bg_overlay_opacity', $bgOverlayOpacity);
            updateSetting('bg_refresh_interval', $bgRefreshInterval);
            updateSetting('bg_enabled', $bgEnabled);
            
            $successMessage = '背景设置已成功更新';
            logAction('更新背景设置', '修改了网站背景配置');
        }
        
        // 处理立即更新背景
        if (isset($_POST['update_background_now'])) {
            // 清除背景缓存
            deleteCache('current_background');
            $successMessage = '背景已成功更新';
            logAction('手动更新背景', '立即刷新了网站背景');
        }
        
        // 处理添加自定义API
        if (isset($_POST['add_custom_api'])) {
            $customApiName = $_POST['custom_api_name'] ?? '';
            $customApiUrl = $_POST['custom_api_url'] ?? '';
            
            if (empty($customApiName) || empty($customApiUrl)) {
                $errorMessage = 'API名称和URL不能为空';
            } else {
                // 获取现有自定义API列表
                $customApis = json_decode(getSetting('custom_background_apis', '[]'), true);
                
                // 添加新API
                $customApis[] = [
                    'name' => $customApiName,
                    'url' => $customApiUrl
                ];
                
                // 保存更新后的列表
                updateSetting('custom_background_apis', json_encode($customApis));
                $successMessage = '自定义API已成功添加';
                logAction('添加自定义背景API', '添加了API: ' . $customApiName);
            }
        }
        
        // 处理删除自定义API
        if (isset($_POST['delete_custom_api']) && is_numeric($_POST['delete_custom_api'])) {
            $apiIndex = intval($_POST['delete_custom_api']);
            
            // 获取现有自定义API列表
            $customApis = json_decode(getSetting('custom_background_apis', '[]'), true);
            
            if (isset($customApis[$apiIndex])) {
                // 删除API
                $deletedApi = $customApis[$apiIndex];
                array_splice($customApis, $apiIndex, 1);
                
                // 保存更新后的列表
                updateSetting('custom_background_apis', json_encode($customApis));
                $successMessage = '自定义API已成功删除';
                logAction('删除自定义背景API', '删除了API: ' . $deletedApi['name']);
            } else {
                $errorMessage = '无效的API索引';
            }
        }
        
        // 处理上传自定义图片
        if (isset($_POST['upload_image'])) {
            // 检查是否有文件上传
            if (isset($_FILES['custom_image']) && $_FILES['custom_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['custom_image'];
                $fileName = basename($file['name']);
                $fileSize = $file['size'];
                $fileTmpName = $file['tmp_name'];
                $fileType = $file['type'];
                
                // 验证文件类型
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($fileType, $allowedTypes)) {
                    $errorMessage = '只支持JPG、PNG、GIF和WebP格式的图片';
                } else if ($fileSize > 10 * 1024 * 1024) { // 10MB限制
                    $errorMessage = '图片大小不能超过10MB';
                } else {
                    // 创建uploads目录（如果不存在）
                    $uploadDir = '../uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // 生成唯一文件名
                    $uniqueFileName = uniqid('bg_', true) . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
                    $targetFilePath = $uploadDir . $uniqueFileName;
                    
                    // 移动上传文件
                    if (move_uploaded_file($fileTmpName, $targetFilePath)) {
                        // 获取现有自定义图片列表
                        $customImages = json_decode(getSetting('bg_custom_images', '[]'), true);
                        
                        // 添加新图片
                        $customImages[] = $uniqueFileName;
                        
                        // 保存更新后的列表
                        updateSetting('bg_custom_images', json_encode($customImages));
                        
                        // 更新当前设置的图片URLs
                        $currentImageUrls = getSetting('bg_custom_images_urls', '');
                        if (empty($currentImageUrls)) {
                            $currentImageUrls = $uniqueFileName;
                        } else {
                            $currentImageUrls .= "\n" . $uniqueFileName;
                        }
                        updateSetting('bg_custom_images_urls', $currentImageUrls);
                        
                        $successMessage = '自定义图片已成功上传';
                        logAction('上传自定义背景图片', '上传了图片: ' . $uniqueFileName);
                    } else {
                        $errorMessage = '图片上传失败，请稍后重试';
                    }
                }
            } else {
                $errorMessage = '请选择要上传的图片文件';
            }
        }
        
        // 处理删除自定义图片
        if (isset($_POST['delete_image']) && !empty($_POST['delete_image'])) {
            $imageFileName = $_POST['delete_image'];
            
            // 获取现有自定义图片列表
            $customImages = json_decode(getSetting('bg_custom_images', '[]'), true);
            
            if (in_array($imageFileName, $customImages)) {
                // 删除文件
                $filePath = '../uploads/' . $imageFileName;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // 从列表中移除
                $customImages = array_diff($customImages, [$imageFileName]);
                $customImages = array_values($customImages); // 重新索引数组
                
                // 保存更新后的列表
                updateSetting('bg_custom_images', json_encode($customImages));
                
                // 更新当前设置的图片URLs
                $currentImageUrls = getSetting('bg_custom_images_urls', '');
                $imageUrlsArray = explode("\n", $currentImageUrls);
                $imageUrlsArray = array_diff($imageUrlsArray, [$imageFileName]);
                $currentImageUrls = implode("\n", $imageUrlsArray);
                updateSetting('bg_custom_images_urls', $currentImageUrls);
                
                $successMessage = '自定义图片已成功删除';
                logAction('删除自定义背景图片', '删除了图片: ' . $imageFileName);
            } else {
                $errorMessage = '无效的图片文件';
            }
        }
    }
}

// 获取当前背景设置
$bgProvider = getSetting('bg_provider', 'random');
$bgApiUrls = getSetting('bg_api_urls');
$bgCustomImagesUrls = getSetting('bg_custom_images_urls', '');
$bgCustomImages = json_decode(getSetting('bg_custom_images', '[]'), true);
$bgOverlayOpacity = getSetting('bg_overlay_opacity', 0.5);
$bgRefreshInterval = getSetting('bg_refresh_interval', 30);
$bgEnabled = getSetting('bg_enabled', 1);

// 获取自定义API列表
$customBackgroundApis = json_decode(getSetting('custom_background_apis', '[]'), true);

// 获取当前背景图片URL用于预览
$currentBackground = getBackgroundImage();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>背景管理 - <?php echo escape($siteTitle); ?></title>
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
        
        /* 背景预览样式 */
        .bg-preview-container {
            position: relative;
            min-height: 200px;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .bg-preview-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }
        
        .bg-preview-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, var(--overlay-opacity));
        }
        
        .bg-preview-content {
            position: relative;
            z-index: 10;
            color: white;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }
        
        /* 图片网格样式 */
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .image-item {
            position: relative;
            border-radius: 0.5rem;
            overflow: hidden;
            aspect-ratio: 4/3;
            cursor: pointer;
        }
        
        .image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .image-item:hover img {
            transform: scale(1.05);
        }
        
        .image-item-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .image-item:hover .image-item-overlay {
            opacity: 1;
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
                        <a href="background.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg sidebar-item-active">
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
                        背景管理
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
                                <span class="text-sm font-medium text-gray-800">背景管理</span>
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
                
                <!-- 当前背景预览 -->
                <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6">当前背景预览</h3>
                    
                    <div class="bg-preview-container">
                        <img 
                            src="<?php echo escape($currentBackground); ?>" 
                            alt="当前背景预览" 
                            class="bg-preview-image"
                            onError="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1MDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjZmZmZmZmIj48cGF0aCBkPSJNMjUwIDEwMGMtNTUuMiAwLTEwMCA0NC44LTEwMCAxMDBzNDQuOCAxMDAgMTAwIDEwMCAxMDAgLTQ0LjggMTAwLTEwMC00NC44LTEwMC0xMDAtMTAweiIgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIi8+PHBhdGggZD0iTTI1MCAyMDBjLTI3LjYgMC01MC0yMi40LTUwLTUwczIyLjQtNTAgNTAtNTAgNTAgMjIuNCA1MCA1MC0yMi40IDUwLTUwIDUwem0wLTc1YzE4LjcgMCAzNSAxNi4zIDM1IDM1cy0xNi4zIDM1LTM1IDM1LTM1LTE2LjMtMzUtMzUgMTYuMy0zNSAzNS0zNXoiIGZpbGw9IiMwMDAiIGZpbGwtb3BhY2l0eT0iMC4wNSIvPjxjaXJjbGUgY3g9IjI1MCIgY3k9IjEwMCIgcj0iNzUiIGZpbGw9IiMwMDAiIGZpbGwtb3BhY2l0eT0iMC4zIi8+PHRleHQgeD0iMjUwIiB5PSI4MCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZm9udC1mYW1pbHk9IkludGVyLCBzdHlsZXQgYW5jaG9yIiByeD0iMiIgZmlsbD0iIzAwMCI+5Zu+5rW35rOV5LyB55+lPC90ZXh0Pjwvc3ZnPg=='"
                        >
                        <div class="bg-preview-overlay" style="--overlay-opacity: <?php echo $bgOverlayOpacity; ?>"></div>
                        <div class="bg-preview-content flex items-center justify-center h-full p-6 text-center">
                            <div>
                                <h3 class="text-2xl font-bold mb-2"><?php echo escape($siteTitle); ?></h3>
                                <p class="text-lg">这是网站背景的预览效果</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-center">
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="update_background_now" value="1">
                            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-all-300 flex items-center gap-2">
                                <i class="fa fa-refresh"></i>
                                <span>立即更新背景</span>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- 背景设置卡片 -->
                <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6">背景设置</h3>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="update_background_settings" value="1">
                        
                        <!-- 启用背景 -->
                        <div class="flex items-center mb-6">
                            <input 
                                type="checkbox" 
                                id="bg_enabled" 
                                name="bg_enabled" 
                                class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300 rounded"
                                <?php echo ($bgEnabled) ? 'checked' : ''; ?>
                            >
                            <label for="bg_enabled" class="ml-2 text-sm font-medium text-gray-700">
                                启用动态背景
                            </label>
                        </div>
                        
                        <!-- 背景提供者 -->
                        <div>
                            <label for="bg_provider" class="block text-sm font-medium text-gray-700 mb-1">
                                背景来源
                            </label>
                            <select 
                                id="bg_provider"
                                name="bg_provider" 
                                class="form-select w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                onchange="toggleBackgroundSettings(this.value)"
                            >
                                <option value="random" <?php echo ($bgProvider === 'random') ? 'selected' : ''; ?>>随机图床</option>
                                <option value="api" <?php echo ($bgProvider === 'api') ? 'selected' : ''; ?>>自定义API</option>
                                <option value="custom" <?php echo ($bgProvider === 'custom') ? 'selected' : ''; ?>>自定义图片</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fa fa-info-circle mr-1"></i>
                                选择网站背景的来源方式
                            </p>
                        </div>
                        
                        <!-- API URL设置 (随机图床和自定义API共用) -->
                        <div id="api_urls_container" style="display: <?php echo ($bgProvider === 'random' || $bgProvider === 'api') ? 'block' : 'none'; ?>">
                            <label for="bg_api_urls" class="block text-sm font-medium text-gray-700 mb-1">
                                <?php echo ($bgProvider === 'random') ? '图床API地址' : '自定义API地址'; ?>
                            </label>
                            <textarea 
                                id="bg_api_urls"
                                name="bg_api_urls" 
                                class="form-textarea w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none min-h-[100px]"
                                placeholder="每个API地址一行">
                                <?php echo escape($bgApiUrls); ?>
                            </textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fa fa-info-circle mr-1"></i>
                                每个API地址一行，系统将随机选择一个使用
                            </p>
                            
                            <!-- 内置API推荐 -->
                            <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <p class="text-xs text-gray-600 font-medium mb-2">推荐API地址：</p>
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="copy-btn px-2 py-1 bg-primary/10 text-primary rounded text-xs" data-url="https://picsum.photos/1920/1080">
                                            <i class="fa fa-copy mr-1"></i>复制
                                        </button>
                                        <code class="text-xs text-gray-600">https://picsum.photos/1920/1080</code>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="copy-btn px-2 py-1 bg-primary/10 text-primary rounded text-xs" data-url="https://source.unsplash.com/random/1920x1080">
                                            <i class="fa fa-copy mr-1"></i>复制
                                        </button>
                                        <code class="text-xs text-gray-600">https://source.unsplash.com/random/1920x1080</code>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="copy-btn px-2 py-1 bg-primary/10 text-primary rounded text-xs" data-url="https://api.ixiaowai.cn/api/api.php">
                                            <i class="fa fa-copy mr-1"></i>复制
                                        </button>
                                        <code class="text-xs text-gray-600">https://api.ixiaowai.cn/api/api.php</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 自定义图片设置 -->
                        <div id="custom_images_container" style="display: <?php echo ($bgProvider === 'custom') ? 'block' : 'none'; ?>">
                            <label for="bg_custom_images_urls" class="block text-sm font-medium text-gray-700 mb-1">
                                自定义图片URLs
                            </label>
                            <textarea 
                                id="bg_custom_images_urls"
                                name="bg_custom_images_urls" 
                                class="form-textarea w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none min-h-[100px]"
                                placeholder="每个图片URL一行">
                                <?php echo escape($bgCustomImagesUrls); ?>
                            </textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fa fa-info-circle mr-1"></i>
                                每个图片URL一行，系统将随机选择一个显示
                            </p>
                        </div>
                        
                        <!-- 背景效果设置 -->
                        <div class="pt-4 border-t border-gray-200">
                            <h4 class="text-md font-medium text-gray-700 mb-4">背景效果设置</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- 遮罩层透明度 -->
                                <div>
                                    <label for="bg_overlay_opacity" class="block text-sm font-medium text-gray-700 mb-1">
                                        遮罩层透明度
                                    </label>
                                    <input 
                                        type="range" 
                                        id="bg_overlay_opacity"
                                        name="bg_overlay_opacity" 
                                        min="0" 
                                        max="1" 
                                        step="0.1" 
                                        value="<?php echo $bgOverlayOpacity; ?>" 
                                        class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                                        oninput="updateOpacityDisplay(this.value)"
                                    >
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>透明</span>
                                        <span id="opacity_value"><?php echo ($bgOverlayOpacity * 100); ?>%</span>
                                        <span>不透明</span>
                                    </div>
                                </div>
                                
                                <!-- 刷新间隔 -->
                                <div>
                                    <label for="bg_refresh_interval" class="block text-sm font-medium text-gray-700 mb-1">
                                        背景刷新间隔（秒）
                                    </label>
                                    <input 
                                        type="range" 
                                        id="bg_refresh_interval"
                                        name="bg_refresh_interval" 
                                        min="5" 
                                        max="300" 
                                        step="5" 
                                        value="<?php echo $bgRefreshInterval; ?>" 
                                        class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                                        oninput="updateIntervalDisplay(this.value)"
                                    >
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>5秒</span>
                                        <span id="interval_value"><?php echo $bgRefreshInterval; ?>秒</span>
                                        <span>300秒</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 保存按钮 -->
                        <div class="pt-4 flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-all-300 flex items-center gap-2">
                                <i class="fa fa-save"></i>
                                <span>保存背景设置</span>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- 自定义API管理 -->
                <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6">自定义API管理</h3>
                    
                    <!-- 添加自定义API -->
                    <div class="border border-dashed border-gray-300 rounded-lg p-4 mb-6">
                        <h4 class="text-md font-medium text-gray-700 mb-4">添加自定义背景API</h4>
                        
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="add_custom_api" value="1">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- API名称 -->
                                <div>
                                    <label for="custom_api_name" class="block text-sm font-medium text-gray-700 mb-1">
                                        API名称
                                    </label>
                                    <input 
                                        type="text" 
                                        id="custom_api_name"
                                        name="custom_api_name" 
                                        class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                        placeholder="例如：Unsplash随机图"
                                    >
                                </div>
                                
                                <!-- API URL -->
                                <div>
                                    <label for="custom_api_url" class="block text-sm font-medium text-gray-700 mb-1">
                                        API URL
                                    </label>
                                    <input 
                                        type="url" 
                                        id="custom_api_url"
                                        name="custom_api_url" 
                                        class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                        placeholder="https://api.example.com/random"
                                    >
                                </div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-all-300 flex items-center gap-2">
                                    <i class="fa fa-plus-circle"></i>
                                    <span>添加API</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- 自定义API列表 -->
                    <?php if (!empty($customBackgroundApis)): ?>
                        <div class="space-y-4">
                            <h4 class="text-md font-medium text-gray-700 mb-2">已添加的自定义API</h4>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                API名称
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                API URL
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                操作
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($customBackgroundApis as $index => $api): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo escape($api['name']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500 break-all max-w-xs">
                                                        <?php echo escape($api['url']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <button type="button" class="test-api-btn text-primary hover:text-primary/80 transition-all-300" data-url="<?php echo escape($api['url']); ?>">
                                                        <i class="fa fa-eye mr-1"></i>测试
                                                    </button>
                                                    <span class="mx-2 text-gray-300">|</span>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="delete_custom_api" value="<?php echo $index; ?>">
                                                        <button type="submit" class="text-red-500 hover:text-red-700 transition-all-300" onclick="return confirm('确定要删除这个API吗？');">
                                                            <i class="fa fa-trash-o mr-1"></i>删除
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6 text-gray-500">
                            <i class="fa fa-link text-3xl mb-2"></i>
                            <p>暂无自定义API</p>
                            <p class="text-sm mt-1">使用上方表单添加自定义背景API</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- 自定义图片管理 -->
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6">自定义图片管理</h3>
                    
                    <!-- 上传自定义图片 -->
                    <div class="border border-dashed border-gray-300 rounded-lg p-4 mb-6">
                        <h4 class="text-md font-medium text-gray-700 mb-4">上传自定义背景图片</h4>
                        
                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="upload_image" value="1">
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- 图片选择 -->
                                <div class="md:col-span-2">
                                    <label for="custom_image" class="block text-sm font-medium text-gray-700 mb-1">
                                        选择图片
                                    </label>
                                    <input 
                                        type="file" 
                                        id="custom_image"
                                        name="custom_image" 
                                        accept="image/*"
                                        class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                                    >
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fa fa-info-circle mr-1"></i>
                                        支持JPG、PNG、GIF和WebP格式，最大10MB
                                    </p>
                                </div>
                                
                                <div class="flex items-end">
                                    <button type="submit" class="w-full px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-all-300 flex items-center justify-center gap-2">
                                        <i class="fa fa-upload"></i>
                                        <span>上传图片</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- 已上传图片列表 -->
                    <?php if (!empty($bgCustomImages)): ?>
                        <div class="space-y-4">
                            <h4 class="text-md font-medium text-gray-700 mb-2">已上传的图片</h4>
                            
                            <div class="images-grid">
                                <?php foreach ($bgCustomImages as $imageFileName): ?>
                                    <div class="image-item">
                                        <img 
                                            src="../uploads/<?php echo escape($imageFileName); ?>"
                                            alt="自定义背景图片"
                                        >
                                        <div class="image-item-overlay">
                                            <button type="button" class="delete-image-btn text-white bg-red-500 p-2 rounded-full hover:bg-red-600 transition-all-300"
                                                data-filename="<?php echo escape($imageFileName); ?>">
                                                <i class="fa fa-trash-o"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6 text-gray-500">
                            <i class="fa fa-picture-o text-3xl mb-2"></i>
                            <p>暂无自定义图片</p>
                            <p class="text-sm mt-1">使用上方表单上传自定义背景图片</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- 隐藏的删除图片表单 -->
    <form id="deleteImageForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <input type="hidden" name="delete_image" id="delete_image_filename">
    </form>
    
    <!-- API测试模态框 -->
    <div id="apiTestModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        <div class="relative bg-white rounded-lg max-w-2xl w-full mx-4 overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">API测试结果</h3>
                <button type="button" id="closeApiTestModal" class="text-gray-400 hover:text-gray-500">
                    <i class="fa fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <div id="apiTestResult" class="mb-4">
                    <p class="text-sm text-gray-500">正在测试API，请稍候...</p>
                </div>
                <div id="apiTestImageContainer" class="mt-4 hidden">
                    <img id="apiTestImage" src="" alt="API测试结果" class="w-full rounded-lg">
                </div>
            </div>
            <div class="flex justify-end p-4 border-t border-gray-200">
                <button type="button" id="closeApiTestModalBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-all-300">
                    关闭
                </button>
            </div>
        </div>
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
            
            // 切换背景设置显示
            window.toggleBackgroundSettings = function(provider) {
                const apiUrlsContainer = document.getElementById('api_urls_container');
                const customImagesContainer = document.getElementById('custom_images_container');
                
                if (apiUrlsContainer && customImagesContainer) {
                    if (provider === 'random' || provider === 'api') {
                        apiUrlsContainer.style.display = 'block';
                        customImagesContainer.style.display = 'none';
                    } else if (provider === 'custom') {
                        apiUrlsContainer.style.display = 'none';
                        customImagesContainer.style.display = 'block';
                    } else {
                        apiUrlsContainer.style.display = 'none';
                        customImagesContainer.style.display = 'none';
                    }
                }
            };
            
            // 更新透明度显示
            window.updateOpacityDisplay = function(value) {
                const opacityValue = document.getElementById('opacity_value');
                const bgPreviewOverlay = document.querySelector('.bg-preview-overlay');
                
                if (opacityValue && bgPreviewOverlay) {
                    opacityValue.textContent = Math.round(value * 100) + '%';
                    bgPreviewOverlay.style.setProperty('--overlay-opacity', value);
                }
            };
            
            // 更新刷新间隔显示
            window.updateIntervalDisplay = function(value) {
                const intervalValue = document.getElementById('interval_value');
                
                if (intervalValue) {
                    intervalValue.textContent = value + '秒';
                }
            };
            
            // 复制按钮功能
            const copyBtns = document.querySelectorAll('.copy-btn');
            
            copyBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const url = this.getAttribute('data-url');
                    
                    // 创建临时输入框
                    const tempInput = document.createElement('input');
                    tempInput.value = url;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                    
                    // 显示复制成功
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fa fa-check mr-1"></i>已复制';
                    this.classList.remove('bg-primary/10', 'text-primary');
                    this.classList.add('bg-green-100', 'text-green-600');
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('bg-green-100', 'text-green-600');
                        this.classList.add('bg-primary/10', 'text-primary');
                    }, 2000);
                });
            });
            
            // API测试功能
            const testApiBtns = document.querySelectorAll('.test-api-btn');
            const apiTestModal = document.getElementById('apiTestModal');
            const closeApiTestModal = document.getElementById('closeApiTestModal');
            const closeApiTestModalBtn = document.getElementById('closeApiTestModalBtn');
            const apiTestResult = document.getElementById('apiTestResult');
            const apiTestImageContainer = document.getElementById('apiTestImageContainer');
            const apiTestImage = document.getElementById('apiTestImage');
            
            // 打开模态框
            testApiBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const url = this.getAttribute('data-url');
                    
                    // 重置模态框内容
                    apiTestResult.innerHTML = '<p class="text-sm text-gray-500">正在测试API，请稍候...</p>';
                    apiTestImageContainer.style.display = 'none';
                    
                    // 显示模态框
                    apiTestModal.classList.remove('hidden');
                    
                    // 测试API
                    testApi(url);
                });
            });
            
            // 关闭模态框
            function closeModal() {
                apiTestModal.classList.add('hidden');
            }
            
            closeApiTestModal.addEventListener('click', closeModal);
            closeApiTestModalBtn.addEventListener('click', closeModal);
            
            // 点击模态框外部关闭
            apiTestModal.addEventListener('click', function(event) {
                if (event.target === apiTestModal) {
                    closeModal();
                }
            });
            
            // 测试API函数
            function testApi(url) {
                // 创建图片对象测试URL是否有效
                const img = new Image();
                let testUrl = url;
                
                // 为URL添加时间戳防止缓存
                if (testUrl.includes('?')) {
                    testUrl += '&t=' + Date.now();
                } else {
                    testUrl += '?t=' + Date.now();
                }
                
                img.onload = function() {
                    apiTestResult.innerHTML = '<div class="flex items-center text-green-600"><i class="fa fa-check-circle text-xl mr-2"></i><span>API测试成功</span></div>';
                    apiTestImage.src = testUrl;
                    apiTestImageContainer.style.display = 'block';
                };
                
                img.onerror = function() {
                    apiTestResult.innerHTML = '<div class="flex items-center text-red-600"><i class="fa fa-exclamation-circle text-xl mr-2"></i><span>API测试失败，请检查URL是否正确</span></div>';
                };
                
                // 设置超时
                setTimeout(() => {
                    if (!apiTestImage.complete) {
                        img.src = ''; // 取消加载
                        apiTestResult.innerHTML = '<div class="flex items-center text-yellow-600"><i class="fa fa-exclamation-triangle text-xl mr-2"></i><span>API测试超时，请检查网络连接</span></div>';
                    }
                }, 5000);
                
                img.src = testUrl;
            }
            
            // 删除图片功能
            const deleteImageBtns = document.querySelectorAll('.delete-image-btn');
            const deleteImageForm = document.getElementById('deleteImageForm');
            const deleteImageFilename = document.getElementById('delete_image_filename');
            
            deleteImageBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const filename = this.getAttribute('data-filename');
                    
                    if (confirm('确定要删除这张图片吗？')) {
                        deleteImageFilename.value = filename;
                        deleteImageForm.submit();
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