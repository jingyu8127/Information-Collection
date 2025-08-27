<?php
// 包含系统配置文件
require_once '../config.php';

// 已登录用户重定向到控制面板
if (isLoggedIn()) {
    redirect('index.php');
}

// 获取网站标题和背景图API
$siteTitle = getSetting('site_title', 'PHP全栈网站系统');
$backgroundApi = getSetting('background_api', 'https://picsum.photos/1920/1080');

// 登录表单处理
$error = '';
$logoutReason = '';

// 检查是否有退出原因cookie
if (isset($_COOKIE['logout_reason'])) {
    $logoutReason = $_COOKIE['logout_reason'];
    // 清除cookie
    setcookie('logout_reason', '', time() - 3600, '/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = '表单验证失败，请重试';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // 验证表单数据
        if (empty($username) || empty($password)) {
            $error = '请输入用户名和密码';
        } else {
            // 查询管理员信息
            $sql = "SELECT * FROM admin WHERE username = :username AND status = 'active'";
            $admin = getSingle($sql, [':username' => $username]);
            
            if ($admin && password_verify($password, $admin['password'])) {
                // 验证成功，设置会话
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['admin_email'] = $admin['email'];
                
                // 更新最后登录时间
                $sql = "UPDATE admin SET last_login = NOW() WHERE id = :id";
                execute($sql, [':id' => $admin['id']]);
                
                // 记录登录日志
                logAction('管理员登录', '用户：' . $username);
                
                // 重定向到控制面板
                redirect('index.php');
            } else {
                $error = '用户名或密码错误';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - <?php echo escape($siteTitle); ?></title>
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
                        dark: '#1F2937',
                        light: '#F9FAFB'
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
            .bg-blur {
                backdrop-filter: blur(8px);
            }
            .text-shadow {
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .transition-all-300 {
                transition: all 0.3s ease;
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
        
        /* 背景图片容器 */
        .bg-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        
        .bg-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            animation: fadeIn 1s ease-in-out;
        }
        
        /* 背景遮罩 */
        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: -1;
        }
        
        /* 登录卡片样式 */
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.8s ease-out;
        }
        
        /* 输入框样式 */
        .form-input {
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
        }
        
        /* 登录按钮样式 */
        .btn-login {
            background: linear-gradient(135deg, #3B82F6, #6366F1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        /* 动画定义 */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(50px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* 响应式调整 */
        @media (max-width: 640px) {
            .login-card {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- 背景图片和遮罩 -->
    <div class="bg-container">
        <img id="backgroundImage" class="bg-image" src="<?php echo escape($backgroundApi); ?>?random=<?php echo time(); ?>" alt="背景图片">
    </div>
    <div class="bg-overlay"></div>
    
    <!-- 主要内容 -->
    <div class="min-h-screen flex flex-col justify-center items-center p-4">
        <!-- 登录卡片 -->
        <div class="login-card w-full max-w-md p-8">
            <!-- 登录标题 -->
            <div class="text-center mb-8">
                <h1 class="text-[clamp(1.5rem,3vw,2rem)] font-bold text-dark mb-2">
                    管理员登录
                </h1>
                <p class="text-gray-600">请输入您的账户信息</p>
            </div>
            
            <!-- 退出原因提示 -->
            <?php if (!empty($logoutReason)): ?>
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 animate-pulse">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fa fa-info-circle text-blue-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-blue-700"><?php echo escape($logoutReason); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- 错误提示 -->
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 animate-pulse">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fa fa-exclamation-circle text-red-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-red-700"><?php echo escape($error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- 登录表单 -->
            <form id="loginForm" method="POST" class="space-y-6">
                <!-- CSRF令牌 -->
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <!-- 用户名输入框 -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                        用户名
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa fa-user text-gray-400"></i>
                        </div>
                        <input 
                            type="text"
                            id="username"
                            name="username"
                            class="form-input w-full pl-10 px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                            placeholder="请输入用户名"
                            value="<?php echo escape($_POST['username'] ?? ''); ?>"
                            required
                        >
                    </div>
                </div>
                
                <!-- 密码输入框 -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        密码
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa fa-lock text-gray-400"></i>
                        </div>
                        <input 
                            type="password"
                            id="password"
                            name="password"
                            class="form-input w-full pl-10 px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                            placeholder="请输入密码"
                            required
                        >
                        <button 
                            type="button" 
                            id="togglePassword"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                        >
                            <i class="fa fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
                
                <!-- 记住密码选项 -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input 
                            type="checkbox"
                            id="remember"
                            name="remember"
                            class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300 rounded"
                            <?php echo (isset($_POST['remember']) && $_POST['remember'] === 'on') ? 'checked' : ''; ?>
                        >
                        <label for="remember" class="ml-2 block text-sm text-gray-700">
                            记住我
                        </label>
                    </div>
                    <div>
                        <a href="#" class="text-sm text-primary hover:text-primary/80 transition-all-300">
                            忘记密码？
                        </a>
                    </div>
                </div>
                
                <!-- 登录按钮 -->
                <button 
                    type="submit"
                    class="btn-login w-full px-6 py-3 text-white font-semibold rounded-lg shadow-lg flex items-center justify-center gap-2"
                >
                    <i class="fa fa-sign-in"></i>
                    <span>登录</span>
                </button>
            </form>
            
            <!-- 安全提示 -->
            <div class="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-100">
                <p class="text-sm text-blue-700 flex items-start gap-2">
                    <i class="fa fa-info-circle mt-0.5 text-blue-500"></i>
                    <span>为了您的账户安全，请确保在安全的网络环境下登录，并在使用完毕后退出登录。如果您忘记密码，请联系系统管理员。</span>
                </p>
            </div>
        </div>
        
        <!-- 页脚 -->
        <footer class="mt-12 text-white text-center">
            <p>&copy; <?php echo date('Y'); ?> <?php echo escape($siteTitle); ?> - 版权所有</p>
        </footer>
    </div>
    
    <!-- JavaScript -->
    <script>
        // 页面加载完成后执行
        document.addEventListener('DOMContentLoaded', function() {
            // 密码显示/隐藏切换
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                
                // 切换图标
                const icon = togglePassword.querySelector('i');
                if (type === 'password') {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
            
            // 表单提交动画
            const loginForm = document.getElementById('loginForm');
            
            loginForm.addEventListener('submit', function() {
                const submitButton = this.querySelector('button[type="submit"]');
                
                // 禁用按钮并显示加载状态
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 登录中...';
            });
            
            // 输入框焦点动画
            const inputs = document.querySelectorAll('input:not([type="checkbox"])');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('scale-[1.02]');
                    this.parentElement.style.transition = 'all 0.3s ease';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('scale-[1.02]');
                });
            });
            
            // 背景图片加载失败处理
            const backgroundImage = document.getElementById('backgroundImage');
            backgroundImage.addEventListener('error', function() {
                // 如果原图加载失败，使用备用背景
                this.src = 'https://picsum.photos/1920/1080?fallback=1';
            });
            
            // 添加页面加载完成的视觉反馈
            setTimeout(function() {
                document.body.classList.add('opacity-100');
                document.body.classList.remove('opacity-0');
            }, 100);
        });
    </script>
</body>
</html>