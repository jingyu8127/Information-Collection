<?php
// 包含系统配置文件
require_once 'config.php';

// 获取网站标题、背景图API和成功提示信息
$siteTitle = getSetting('site_title', 'PHP全栈网站系统');
$backgroundApi = getSetting('background_api', 'https://picsum.photos/1920/1080');
$successMessage = getSetting('success_message', '提交成功！感谢您的反馈，我们会尽快与您联系。');
$redirectDelay = getSetting('redirect_delay', 3); // 自动跳转延迟（秒）

// 设置自动跳转
header("refresh:$redirectDelay;url=index.php");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>提交成功 - <?php echo escape($siteTitle); ?></title>
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
            background: rgba(0, 0, 0, 0.6);
            z-index: -1;
        }
        
        /* 成功卡片样式 */
        .success-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.8s ease-out;
        }
        
        /* 成功图标动画 */
        .success-icon {
            animation: pulse 2s ease-in-out infinite;
        }
        
        /* 返回按钮样式 */
        .btn-back {
            background: linear-gradient(135deg, #3B82F6, #6366F1);
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }
        
        /* 计时器样式 */
        .countdown {
            font-weight: bold;
            color: #3B82F6;
            transition: all 0.3s ease;
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
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* 响应式调整 */
        @media (max-width: 640px) {
            .success-card {
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
        <!-- 成功信息卡片 -->
        <div class="success-card w-full max-w-md p-8 text-center">
            <!-- 成功图标 -->
            <div class="success-icon inline-flex items-center justify-center w-24 h-24 rounded-full bg-green-100 text-success mb-6">
                <i class="fa fa-check text-5xl"></i>
            </div>
            
            <!-- 成功标题 -->
            <h1 class="text-[clamp(1.5rem,3vw,2rem)] font-bold text-dark mb-4">
                提交成功！
            </h1>
            
            <!-- 成功提示信息 -->
            <p class="text-gray-600 text-lg mb-8 leading-relaxed">
                <?php echo nl2br(escape($successMessage)); ?>
            </p>
            
            <!-- 自动跳转提示 -->
            <p class="text-gray-500 mb-8">
                页面将在 <span id="countdown" class="countdown text-xl"><?php echo $redirectDelay; ?></span> 秒后自动返回首页
            </p>
            
            <!-- 返回按钮 -->
            <a href="index.php" class="btn-back inline-block px-8 py-3 text-white font-semibold rounded-lg shadow-lg transition-all-300">
                <i class="fa fa-arrow-left mr-2"></i>立即返回首页
            </a>
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
            // 倒计时功能
            let countdown = <?php echo $redirectDelay; ?>;
            const countdownElement = document.getElementById('countdown');
            
            const timer = setInterval(function() {
                countdown--;
                countdownElement.textContent = countdown;
                
                // 添加数字变化动画
                countdownElement.classList.add('scale-125');
                setTimeout(() => {
                    countdownElement.classList.remove('scale-125');
                }, 200);
                
                if (countdown <= 0) {
                    clearInterval(timer);
                    // 页面已设置自动跳转，这里可以添加额外的跳转逻辑（如果需要）
                }
            }, 1000);
            
            // 背景图片加载失败处理
            const backgroundImage = document.getElementById('backgroundImage');
            backgroundImage.addEventListener('error', function() {
                // 如果原图加载失败，使用备用背景
                this.src = 'https://picsum.photos/1920/1080?fallback=1';
            });
            
            // 返回按钮悬停效果增强
            const backButton = document.querySelector('.btn-back');
            backButton.addEventListener('mouseenter', function() {
                this.classList.add('scale-105');
            });
            
            backButton.addEventListener('mouseleave', function() {
                this.classList.remove('scale-105');
            });
            
            backButton.addEventListener('mousedown', function() {
                this.classList.add('scale-95');
            });
            
            backButton.addEventListener('mouseup', function() {
                this.classList.remove('scale-95');
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