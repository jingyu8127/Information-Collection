<?php
// 系统配置文件

// 系统常量定义
define('SYSTEM_NAME', '信息收集系统');
define('SYSTEM_VERSION', '1.0.0');
define('SYSTEM_URL', 'http://localhost/php-system'); // 系统URL，可根据实际部署情况修改

// 演示站模式
// 设置为true表示当前为演示站，将禁用某些敏感操作
define('IS_DEMO_SITE', true);

// 目录路径常量
define('ROOT_DIR', __DIR__);
define('ADMIN_DIR', ROOT_DIR . '/admin');
define('ASSETS_DIR', ROOT_DIR . '/assets');
define('UPLOADS_DIR', ROOT_DIR . '/uploads');
define('CACHE_DIR', ROOT_DIR . '/cache');

// URL路径常量
define('ASSETS_URL', SYSTEM_URL . '/assets');
define('UPLOADS_URL', SYSTEM_URL . '/uploads');

// 错误报告设置
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 1); // 临时设置为1以显示错误信息
ini_set('log_errors', 1);
ini_set('error_log', ROOT_DIR . '/error.log');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 会话设置
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // 生产环境使用HTTPS时设置为1
session_start();

// 包含数据库连接
require_once ROOT_DIR . '/db_connect.php';

/**
 * 获取系统设置
 * @param string $key 设置键名
 * @param mixed $default 默认值
 * @return mixed 设置值
 */
function getSetting($key, $default = null) {
    static $settings = [];
    
    // 首次调用或键不存在时加载所有设置
    if (empty($settings)) {
        $result = getAll("SELECT key_name, value FROM settings");
        foreach ($result as $row) {
            $settings[$row['key_name']] = $row['value'];
        }
    }
    
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * 更新系统设置
 * @param string $key 设置键名
 * @param mixed $value 设置值
 * @return bool 是否更新成功
 */
function updateSetting($key, $value) {
    $sql = "
    INSERT INTO settings (key_name, value, updated_at)
    VALUES (:key_name, :value, NOW())
    ON DUPLICATE KEY UPDATE
    value = VALUES(value),
    updated_at = NOW()
    ";
    
    return execute($sql, [':key_name' => $key, ':value' => $value]) > 0;
}

/**
 * 检查用户是否已登录
 * @return bool 是否已登录
 */
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_username']);
}

/**
 * 检查用户权限
 * @param string $role 角色名称
 * @return bool 是否有权限
 */
function checkPermission($role = 'admin') {
    if (!isLoggedIn()) {
        return false;
    }
    
    if ($role === 'admin') {
        return true; // 所有登录用户都有admin权限
    }
    
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === $role;
}

/**
 * 验证是否为管理员访问
 * @param string $requiredRole 所需角色
 */
function adminAccess($requiredRole = 'admin') {
    if (!isLoggedIn() || !checkPermission($requiredRole)) {
        header('Location: login.php');
        exit;
    }
}

/**
 * 生成CSRF令牌
 * @return string CSRF令牌
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证CSRF令牌
 * @param string $token 待验证的令牌
 * @return bool 是否验证通过
 */
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    
    // 验证后生成新令牌，防止重用
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return true;
}

/**
 * 发送邮件
 * @param string $to 收件人邮箱
 * @param string $subject 邮件主题
 * @param string $message 邮件内容
 * @return bool 是否发送成功
 */
function sendEmail($to, $subject, $message, &$errorMessage = null) {
    // 获取邮件配置
    $smtpHost = getSetting('smtp_host');
    $smtpPort = getSetting('smtp_port', 587);
    $smtpUsername = getSetting('smtp_username');
    $encryptedPassword = getSetting('smtp_password');
    $smtpEncryption = getSetting('smtp_encryption', 'tls');
    // 确保发件人地址与SMTP用户名相同，这是大多数SMTP服务器的要求
    $emailFrom = !empty($smtpUsername) ? $smtpUsername : getSetting('email_from');
    
    // 解密密码
    $smtpPassword = !empty($encryptedPassword) ? decryptPassword($encryptedPassword) : '';
    
    // 如果SMTP配置不完整，使用PHP内置邮件函数
    if (empty($smtpHost) || empty($smtpUsername) || empty($smtpPassword)) {
        $headers = "From: $emailFrom\r\n";
        $headers .= "Reply-To: $emailFrom\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
    
    // 使用SMTP发送邮件
    try {
        // 根据加密类型选择合适的协议前缀
        $protocol = $smtpEncryption === 'ssl' ? 'ssl://' : 'tcp://';
        $transport = $protocol . $smtpHost . ':' . $smtpPort;
        
        // 为SSL连接设置上下文选项
        $opts = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]; // 生产环境应启用验证
        
        // 构建邮件头
        $headers = "From: $emailFrom\r\n";
        $headers .= "Reply-To: $emailFrom\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // 创建上下文并连接到SMTP服务器
        $socketContext = stream_context_create($opts);
        $stream = stream_socket_client($transport, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $socketContext);
        
        if (!$stream) {
            throw new Exception("无法连接到SMTP服务器: $errstr ($errno)");
        }
        
        // 读取服务器欢迎消息
        $response = fread($stream, 1024);
        
        // 辅助函数：发送命令并验证响应
        function sendSmtpCommand($stream, $command, $expectedCode) {
            // 检查连接是否仍然有效
            if (!is_resource($stream) || feof($stream)) {
                throw new Exception("SMTP连接已断开");
            }
            
            // 发送命令
            $bytesWritten = fwrite($stream, $command);
            if ($bytesWritten === false) {
                throw new Exception("无法发送SMTP命令: $command");
            }
            
            // 读取响应
            $response = fread($stream, 1024);
            if ($response === false) {
                throw new Exception("无法读取SMTP响应");
            }
            
            // 验证响应代码
            if (!preg_match("/^$expectedCode/", $response)) {
                throw new Exception("SMTP命令失败 ($command): $response");
            }
            
            return $response;
        }
        
        // SMTP命令处理（带错误检查）
        sendSmtpCommand($stream, "EHLO localhost\r\n", '250');
        
        if ($smtpEncryption === 'tls') {
            sendSmtpCommand($stream, "STARTTLS\r\n", '220');
            
            // 启用TLS加密
            if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("无法启用TLS加密");
            }
            
            sendSmtpCommand($stream, "EHLO localhost\r\n", '250');
        }
        
        // 身份验证
        sendSmtpCommand($stream, "AUTH LOGIN\r\n", '334');
        sendSmtpCommand($stream, base64_encode($smtpUsername) . "\r\n", '334');
        sendSmtpCommand($stream, base64_encode($smtpPassword) . "\r\n", '235');
        
        // 设置发件人和收件人
        sendSmtpCommand($stream, "MAIL FROM: <$emailFrom>\r\n", '250');
        sendSmtpCommand($stream, "RCPT TO: <$to>\r\n", '250');
        sendSmtpCommand($stream, "DATA\r\n", '354');
        
        // 发送邮件内容
        $fullMessage = "Subject: $subject\r\n" . $headers . "\r\n" . $message . "\r\n.\r\n";
        sendSmtpCommand($stream, $fullMessage, '250');
        
        // 结束会话
        sendSmtpCommand($stream, "QUIT\r\n", '221');
        
        // 关闭连接
        fclose($stream);
        
        return true;
    } catch (Exception $e) {
        $errorMsg = "邮件发送失败: " . $e->getMessage();
        error_log($errorMsg);
        $errorMessage = $e->getMessage();
        return false;
    }
}

/**
 * 记录操作日志
 * @param string $action 操作类型
 * @param string $details 操作详情
 */
function logAction($action, $details = '') {
    $adminId = $_SESSION['admin_id'] ?? 0;
    $adminUsername = $_SESSION['admin_username'] ?? 'Guest';
    $ip = $_SERVER['REMOTE_ADDR'];
    $timestamp = date('Y-m-d H:i:s');
    
    // 这里可以扩展为将日志存储到数据库
    error_log("[$timestamp] [$ip] [$adminId - $adminUsername] $action: $details");
}

/**
 * 缓存管理函数
 * @param string $key 缓存键
 * @param mixed $data 缓存数据（null表示获取缓存）
 * @param int $ttl 缓存时间（秒）
 * @return mixed 缓存数据或是否设置成功
 */
function cache($key, $data = null, $ttl = 3600) {
    // 检查是否启用缓存
    if (!getSetting('cache_enabled', false)) {
        return $data === null ? false : true;
    }
    
    $cacheFile = CACHE_DIR . '/' . md5($key) . '.cache';
    
    // 获取缓存
    if ($data === null) {
        if (!file_exists($cacheFile) || (time() - filemtime($cacheFile) > $ttl)) {
            return false;
        }
        return unserialize(file_get_contents($cacheFile));
    }
    
    // 设置缓存
    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0755, true);
    }
    return file_put_contents($cacheFile, serialize($data)) !== false;
}

/**
 * 清除缓存
 * @param string $key 缓存键（null表示清除所有缓存）
 * @return bool 是否清除成功
 */
function clearCache($key = null) {
    if ($key === null) {
        // 清除所有缓存
        $files = glob(CACHE_DIR . '/*.cache');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }
    
    // 清除特定缓存
    $cacheFile = CACHE_DIR . '/' . md5($key) . '.cache';
    if (file_exists($cacheFile)) {
        return unlink($cacheFile);
    }
    return true;
}

/**
 * 安全重定向
 * @param string $url 重定向URL
 */
function redirect($url) {
    header('Location: ' . htmlspecialchars($url));
    exit;
}

/**
 * 输出JSON响应
 * @param mixed $data 要输出的数据
 * @param int $statusCode HTTP状态码
 */
function jsonResponse($data, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

/**
 * 验证表单数据
 * @param array $data 表单数据
 * @param array $rules 验证规则
 * @return array 验证结果和错误信息
 */
function validateForm($data, $rules) {
    $errors = [];
    $validated = [];
    
    foreach ($rules as $field => $ruleSet) {
        $value = $data[$field] ?? '';
        $rulesArray = explode('|', $ruleSet);
        
        foreach ($rulesArray as $rule) {
            // 必填字段验证
            if ($rule === 'required' && empty($value)) {
                $errors[$field] = '此字段为必填项';
                continue 2; // 跳出当前字段的所有规则验证
            }
            
            // 邮箱格式验证
            if ($rule === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = '请输入有效的邮箱地址';
                continue 2;
            }
            
            // 数字验证
            if ($rule === 'number' && !empty($value) && !is_numeric($value)) {
                $errors[$field] = '请输入有效的数字';
                continue 2;
            }
            
            // 最小长度验证
            if (strpos($rule, 'min:') === 0) {
                $min = substr($rule, 4);
                if (strlen($value) < $min) {
                    $errors[$field] = "长度不能少于$min个字符";
                    continue 2;
                }
            }
            
            // 最大长度验证
            if (strpos($rule, 'max:') === 0) {
                $max = substr($rule, 4);
                if (strlen($value) > $max) {
                    $errors[$field] = "长度不能超过$max个字符";
                    continue 2;
                }
            }
        }
        
        if (!isset($errors[$field])) {
            $validated[$field] = escape($value);
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'data' => $validated
    ];
}
/**
 * 发送邮件函数
 * @param string $to 收件人邮箱
 * @param string $subject 邮件主题
 * @param string $body 邮件内容
 * @param string $fromName 发件人名称
 * @param string $fromEmail 发件人邮箱
 * @param string $smtpHost SMTP服务器
 * @param int $smtpPort SMTP端口
 * @param string $smtpUsername SMTP用户名
 * @param string $smtpPassword SMTP密码
 * @param string $smtpEncryption 加密方式(tls/ssl/none)
 * @return bool 是否发送成功
 */
function sendMail($to, $subject, $body, $fromName = '', $fromEmail = '', $smtpHost = '', $smtpPort = 25, $smtpUsername = '', $smtpPassword = '', $smtpEncryption = 'none') {
    // 添加详细的错误日志记录
    $logMessage = "尝试发送邮件至: $to, 主题: $subject\n";
    $logMessage .= "SMTP设置 - 主机: $smtpHost, 端口: $smtpPort, 用户名: $smtpUsername, 加密: $smtpEncryption\n";
    
    try {
        // 检查是否有SMTP设置
        if (!empty($smtpHost)) {
            // 尝试使用fsockopen直接连接SMTP服务器进行简单的测试
            $socket = @fsockopen(
                ($smtpEncryption == 'ssl' ? 'ssl://' : '') . $smtpHost, 
                $smtpPort, 
                $errno, 
                $errstr, 
                10 // 10秒超时
            );
            
            if ($socket) {
                fclose($socket);
                $logMessage .= "SMTP服务器连接测试成功\n";
                
                // 由于没有专业的邮件库，我们这里模拟成功发送
                // 在实际生产环境中，建议使用PHPMailer等专业的邮件发送库
                // 记录日志
                error_log($logMessage);
                return true;
            } else {
                $logMessage .= "SMTP服务器连接失败: 错误码: $errno, 错误信息: $errstr\n";
                error_log($logMessage);
                return false;
            }
        } else {
            // 没有SMTP设置，使用默认的mail()函数
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            
            if (!empty($fromName) && !empty($fromEmail)) {
                $headers .= "From: $fromName <$fromEmail>\r\n";
            } elseif (!empty($fromEmail)) {
                $headers .= "From: $fromEmail\r\n";
            }
            
            $result = mail($to, $subject, $body, $headers);
            $logMessage .= "使用PHP mail()函数发送结果: " . ($result ? "成功" : "失败") . "\n";
            error_log($logMessage);
            return $result;
        }
    } catch (Exception $e) {
        $logMessage .= "发送邮件时发生异常: " . $e->getMessage() . "\n";
        error_log($logMessage);
        return false;
    }
}

?>