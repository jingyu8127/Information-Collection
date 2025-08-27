<?php
// 包含系统配置文件
require_once 'config.php';

// 获取网站标题和背景图API
$siteTitle = getSetting('site_title', '信息收集系统');
$backgroundApi = getSetting('background_api', 'https://picsum.photos/1920/1080');

// 获取表单字段配置
$formFields = getAll("SELECT * FROM form_fields WHERE status = 'active' ORDER BY order_index ASC");

// 表单提交处理
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = '表单验证失败，请重试';
    } else {
        $formData = [];
        $validationRules = [];
        
        // 构建验证规则和处理表单数据
        foreach ($formFields as $field) {
            $fieldName = $field['field_name'];
            $fieldValue = $_POST[$fieldName] ?? '';
            
            // 构建验证规则
            $rules = [];
            if ($field['required']) {
                $rules[] = 'required';
            }
            
            // 根据字段类型添加特定验证规则
            switch ($field['type']) {
                case 'email':
                    $rules[] = 'email';
                    break;
                case 'tel':
                case 'number':
                    $rules[] = 'number';
                    break;
            }
            
            if (!empty($rules)) {
                $validationRules[$fieldName] = implode('|', $rules);
            }
            
            $formData[$fieldName] = $fieldValue;
        }
        
        // 执行表单验证
        $validationResult = validateForm($formData, $validationRules);
        
        if ($validationResult['valid']) {
            // 保存提交数据
            $dataJson = json_encode($formData, JSON_UNESCAPED_UNICODE);
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            
            $sql = "INSERT INTO submissions (data, ip_address, user_agent) VALUES (:data, :ip, :user_agent)";
            $submissionId = insert($sql, [
                ':data' => $dataJson,
                ':ip' => $ipAddress,
                ':user_agent' => $userAgent
            ]);
            
            if ($submissionId) {
                // 发送邮件通知（如果配置了）
                $emailTo = getSetting('email_to');
                if (!empty($emailTo)) {
                    $emailSubject = getSetting('email_subject', '新的表单提交');
                    
                    // 构建邮件内容
                    $emailBody = '<h2>新的表单提交</h2>';
                    $emailBody .= '<p>提交时间: ' . date('Y-m-d H:i:s') . '</p>';
                    $emailBody .= '<p>IP地址: ' . $ipAddress . '</p>';
                    $emailBody .= '<table border="1" cellspacing="0" cellpadding="5">';
                    
                    foreach ($formData as $fieldName => $fieldValue) {
                        // 获取字段标签
                        $fieldLabel = $fieldName;
                        foreach ($formFields as $field) {
                            if ($field['field_name'] === $fieldName) {
                                $fieldLabel = $field['label'];
                                break;
                            }
                        }
                        
                        $emailBody .= '<tr>';
                        $emailBody .= '<td><strong>' . $fieldLabel . '</strong></td>';
                        $emailBody .= '<td>' . nl2br(htmlspecialchars($fieldValue)) . '</td>';
                        $emailBody .= '</tr>';
                    }
                    
                    $emailBody .= '</table>';
                    
                    // 发送邮件通知（如果配置了）
                    $smtpHost = getSetting('smtp_host');
                    $smtpPort = getSetting('smtp_port', 587);
                    $smtpUsername = getSetting('smtp_username');
                    $encryptedPassword = getSetting('smtp_password');
                    $smtpPassword = !empty($encryptedPassword) ? decryptPassword($encryptedPassword) : '';
                    $smtpEncryption = getSetting('smtp_encryption', 'tls');
                    $emailFrom = !empty($smtpUsername) ? $smtpUsername : getSetting('email_from');
                    $fromName = getSetting('site_title', '信息收集系统');
                    
                    // 使用带有详细日志的sendMail函数
                    $emailSent = sendMail(
                        $emailTo, 
                        $emailSubject, 
                        $emailBody, 
                        $fromName, 
                        $emailFrom, 
                        $smtpHost, 
                        $smtpPort, 
                        $smtpUsername, 
                        $smtpPassword, 
                        $smtpEncryption
                    );
                    
                    // 即使邮件发送失败，也继续流程，确保用户体验不受影响
                    if (!$emailSent) {
                        error_log("邮件发送失败，但表单提交成功，提交ID: $submissionId");
                    }
                }
                
                // 标记提交成功
                $success = true;
                
                // 重定向到成功页面（确保邮件发送操作完成后再重定向）
                redirect('success.php');
            } else {
                $errors['submit'] = '提交失败，请稍后重试';
            }
        } else {
            $errors = array_merge($errors, $validationResult['errors']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($siteTitle); ?></title>
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
            background: rgba(0, 0, 0, 0.6);
            z-index: -1;
        }
        
        /* 表单卡片样式 */
        .form-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .form-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
        }
        
        /* 输入框动画效果 */
        .form-input:focus {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
        }
        
        /* 按钮动画效果 */
        .btn-primary {
            background: linear-gradient(135deg, #3B82F6, #6366F1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.6s ease;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        /* 动画定义 */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* 响应式调整 */
        @media (max-width: 640px) {
            .form-card {
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
        <!-- 表单卡片 -->
        <div class="form-card w-full max-w-2xl p-8">
            <!-- 标题区域 -->
            <div class="text-center mb-8">
                <h1 class="text-[clamp(1.8rem,4vw,2.5rem)] font-bold text-dark mb-2 animate-[slideUp_0.5s_ease]" style="animation-delay: 0.1s;">
                    <?php echo escape($siteTitle); ?>
                </h1>
                <p class="text-gray-600 text-lg animate-[slideUp_0.5s_ease]" style="animation-delay: 0.2s;">
                    请填写以下信息，我们将尽快与您联系
                </p>
            </div>
            
            <!-- 错误提示区域 -->
            <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 animate-[slideUp_0.5s_ease]">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fa fa-exclamation-circle text-red-500 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-red-700 font-medium">表单提交失败，请检查以下问题：</p>
                            <ul class="mt-2 text-red-600 list-disc pl-5 space-y-1">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo escape($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- 表单 -->
            <form id="mainForm" method="POST" class="space-y-6">
                <!-- CSRF令牌 -->
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <!-- 动态表单字段 -->
                <?php $animationDelay = 0.3; ?>
                <?php foreach ($formFields as $field): ?>
                    <div class="animate-[slideUp_0.5s_ease]" style="animation-delay: <?php echo $animationDelay; ?>s;">
                        <label for="<?php echo escape($field['field_name']); ?>" class="block text-sm font-medium text-gray-700 mb-1">
                            <?php echo escape($field['label']); ?><?php if ($field['required']): ?> <span class="text-red-500">*</span><?php endif; ?>
                        </label>
                        
                        <?php if ($field['type'] === 'textarea'): ?>
                            <!-- 多行文本输入 -->
                            <textarea 
                                id="<?php echo escape($field['field_name']); ?>"
                                name="<?php echo escape($field['field_name']); ?>"
                                rows="4"
                                class="form-input w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all-300"
                                placeholder="<?php echo escape($field['placeholder']); ?>"
                                <?php if ($field['required']): ?>required<?php endif; ?>
                            ><?php echo escape($_POST[$field['field_name']] ?? ''); ?></textarea>
                        
                        <?php elseif ($field['type'] === 'select'): ?>
                            <!-- 下拉选择框 -->
                            <select 
                                id="<?php echo escape($field['field_name']); ?>"
                                name="<?php echo escape($field['field_name']); ?>"
                                class="form-input w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all-300 appearance-none bg-white"
                                <?php if ($field['required']): ?>required<?php endif; ?>
                            >
                                <option value="">请选择</option>
                                <?php if (!empty($field['options'])): ?>
                                    <?php $options = explode(',', $field['options']); ?>
                                    <?php foreach ($options as $option): ?>
                                        <option value="<?php echo escape(trim($option)); ?>" <?php echo (isset($_POST[$field['field_name']]) && $_POST[$field['field_name']] === trim($option)) ? 'selected' : ''; ?>>
                                            <?php echo escape(trim($option)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                <i class="fa fa-chevron-down"></i>
                            </div>
                        
                        <?php elseif ($field['type'] === 'checkbox'): ?>
                            <!-- 复选框 -->
                            <div class="flex items-center space-x-2">
                                <input 
                                    type="checkbox"
                                    id="<?php echo escape($field['field_name']); ?>"
                                    name="<?php echo escape($field['field_name']); ?>"
                                    class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary/20"
                                    <?php if (isset($_POST[$field['field_name']]) && $_POST[$field['field_name']] === 'on'): ?>checked<?php endif; ?>
                                >
                                <label for="<?php echo escape($field['field_name']); ?>" class="text-gray-700">
                                    <?php echo escape($field['placeholder']); ?>
                                </label>
                            </div>
                        
                        <?php else: ?>
                            <!-- 普通输入框 -->
                            <input 
                                type="<?php echo escape($field['type']); ?>"
                                id="<?php echo escape($field['field_name']); ?>"
                                name="<?php echo escape($field['field_name']); ?>"
                                class="form-input w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all-300"
                                placeholder="<?php echo escape($field['placeholder']); ?>"
                                value="<?php echo escape($_POST[$field['field_name']] ?? ''); ?>"
                                <?php if ($field['required']): ?>required<?php endif; ?>
                            >
                        <?php endif; ?>
                    </div>
                    <?php $animationDelay += 0.1; ?>
                <?php endforeach; ?>
                
                <!-- 按钮组 -->
                <div class="flex flex-col sm:flex-row gap-4 mt-8 animate-[slideUp_0.5s_ease]" style="animation-delay: <?php echo $animationDelay; ?>s;">
                    <button type="submit" class="btn-primary flex-1 px-6 py-3 text-white font-semibold rounded-lg shadow-lg flex items-center justify-center gap-2">
                        <i class="fa fa-paper-plane"></i>
                        <span>提交信息</span>
                    </button>
                    <button type="reset" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition-all-300 flex items-center justify-center gap-2">
                        <i class="fa fa-refresh"></i>
                        <span>重置</span>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- 页脚 -->
        <footer class="mt-12 text-white text-center animate-[slideUp_0.5s_ease]" style="animation-delay: 0.8s;">
            <p>&copy; <?php echo date('Y'); ?> <?php echo escape($siteTitle); ?> - 版权所有</p>
            <p class="text-sm mt-1 text-gray-300">本系统基于PHP开发，采用前后端一体化架构</p>
        </footer>
    </div>
    
    <!-- JavaScript -->
    <script>
        // 页面加载完成后执行
        document.addEventListener('DOMContentLoaded', function() {
            // 表单验证
            const form = document.getElementById('mainForm');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    let isValid = true;
                    const requiredFields = form.querySelectorAll('[required]');
                    
                    // 简单的客户端验证
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.classList.add('border-red-500');
                            field.classList.add('focus:border-red-500');
                            
                            // 添加错误提示动画
                            field.classList.add('animate-pulse');
                            setTimeout(() => {
                                field.classList.remove('animate-pulse');
                            }, 1000);
                        } else {
                            field.classList.remove('border-red-500');
                            field.classList.remove('focus:border-red-500');
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        
                        // 显示滚动到第一个错误字段
                        const firstError = form.querySelector('.border-red-500');
                        if (firstError) {
                            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            firstError.focus();
                        }
                    }
                });
                
                // 输入框事件监听
                const inputs = form.querySelectorAll('input, textarea, select');
                inputs.forEach(input => {
                    input.addEventListener('focus', function() {
                        this.classList.add('border-primary');
                        this.classList.add('focus:border-primary');
                    });
                    
                    input.addEventListener('blur', function() {
                        if (!this.classList.contains('border-red-500')) {
                            this.classList.remove('border-primary');
                        }
                    });
                });
            }
            
            // 背景图片加载失败处理
            const backgroundImage = document.getElementById('backgroundImage');
            backgroundImage.addEventListener('error', function() {
                // 如果原图加载失败，使用备用背景
                this.src = 'https://picsum.photos/1920/1080?fallback=1';
            });
            
            // 平滑滚动
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
        });
        
        // 窗口大小变化时的响应式调整
        window.addEventListener('resize', function() {
            // 可以在这里添加窗口大小变化时的逻辑
        });
        
        // 页面滚动事件
        window.addEventListener('scroll', function() {
            // 可以在这里添加页面滚动时的逻辑
        });
    </script>
</body>
</html>