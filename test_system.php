<?php
// 简单的系统测试脚本
require_once 'config.php';

// 设置错误显示
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 测试结果数组
$results = [
    'database_connection' => false,
    'database_query' => false,
    'csrf_token' => false,
    'session' => false,
    'file_system' => false,
    'functions_available' => []
];

// 1. 测试数据库连接
try {
    $testQuery = query("SELECT 1");
    if ($testQuery) {
        $results['database_connection'] = true;
        echo "✅ 数据库连接成功<br>";
    } else {
        echo "❌ 数据库连接失败<br>";
    }
} catch (Exception $e) {
    echo "❌ 数据库连接异常: " . $e->getMessage() . "<br>";
}

// 2. 测试数据库查询
try {
    // 检查表是否存在
    $tables = ['settings', 'form_fields', 'submissions', 'admin'];
    $allTablesExist = true;
    
    foreach ($tables as $table) {
        try {
            $tableExists = getSingle("SHOW TABLES LIKE '$table'");
            if ($tableExists) {
                echo "✅ 表 '$table' 存在<br>";
            } else {
                echo "⚠️ 表 '$table' 不存在<br>";
                $allTablesExist = false;
            }
        } catch (Exception $e) {
            echo "❌ 检查表 '$table' 异常: " . $e->getMessage() . "<br>";
            $allTablesExist = false;
        }
    }
    
    $results['database_query'] = $allTablesExist;
} catch (Exception $e) {
    echo "❌ 数据库查询异常: " . $e->getMessage() . "<br>";
}

// 3. 测试CSRF令牌
try {
    $token = generateCsrfToken();
    $tokenValid = verifyCsrfToken($token);
    
    if ($token) {
        $results['csrf_token'] = true;
        echo "✅ CSRF令牌生成成功<br>";
    } else {
        echo "❌ CSRF令牌生成失败<br>";
    }
} catch (Exception $e) {
    echo "❌ CSRF令牌测试异常: " . $e->getMessage() . "<br>";
}

// 4. 测试会话
try {
    $_SESSION['test_key'] = 'test_value';
    
    if (isset($_SESSION['test_key']) && $_SESSION['test_key'] === 'test_value') {
        $results['session'] = true;
        echo "✅ 会话测试成功<br>";
    } else {
        echo "❌ 会话测试失败<br>";
    }
} catch (Exception $e) {
    echo "❌ 会话测试异常: " . $e->getMessage() . "<br>";
}

// 5. 测试文件系统
try {
    $testDir = CACHE_DIR;
    if (!is_dir($testDir)) {
        mkdir($testDir, 0755, true);
    }
    
    $testFile = $testDir . '/test.txt';
    file_put_contents($testFile, 'test content');
    
    if (file_exists($testFile)) {
        $results['file_system'] = true;
        echo "✅ 文件系统测试成功<br>";
        unlink($testFile); // 清理测试文件
    } else {
        echo "❌ 文件系统测试失败<br>";
    }
} catch (Exception $e) {
    echo "❌ 文件系统测试异常: " . $e->getMessage() . "<br>";
}

// 6. 检查关键函数是否可用
$requiredFunctions = [
    'query', 'getSingle', 'getAll', 'execute', 'insert',
    'beginTransaction', 'commitTransaction', 'rollbackTransaction',
    'getSetting', 'updateSetting', 'verifyCsrfToken',
    'encryptPassword', 'decryptPassword', 'escape',
    'adminAccess', 'logAction'
];

echo "<br>检查关键函数可用性:<br>";
foreach ($requiredFunctions as $func) {
    if (function_exists($func)) {
        $results['functions_available'][$func] = true;
        echo "✅ $func 函数存在<br>";
    } else {
        $results['functions_available'][$func] = false;
        echo "❌ $func 函数不存在<br>";
    }
}

// 7. 测试设置操作
try {
    $testKey = 'test_setting_' . time();
    updateSetting($testKey, 'test_value');
    $value = getSetting($testKey);
    
    if ($value === 'test_value') {
        echo "✅ 设置操作测试成功<br>";
        // 清理测试设置
        query("DELETE FROM settings WHERE key_name = ?", [$testKey]);
    } else {
        echo "❌ 设置操作测试失败<br>";
    }
} catch (Exception $e) {
    echo "❌ 设置操作测试异常: " . $e->getMessage() . "<br>";
}

// 输出总结
echo "<br><h3>测试总结:</h3><pre>";
print_r($results);
echo "</pre>";

// 检查是否有任何问题
$allTestsPassed = true;
foreach ($results as $key => $value) {
    if ($key === 'functions_available') {
        foreach ($value as $func => $available) {
            if (!$available) {
                $allTestsPassed = false;
                break;
            }
        }
    } else if (!$value) {
        $allTestsPassed = false;
    }
}

if ($allTestsPassed) {
    echo "<br><strong style='color: green;'>✅ 所有测试通过！系统核心功能正常工作。</strong>";
    echo "<br>请尝试访问管理后台，如果仍然遇到500错误，请检查以下几点：";
    echo "<br>1. 确保所有文件权限正确";
    echo "<br>2. 检查Web服务器错误日志";
    echo "<br>3. 确认数据库表结构完整";
} else {
    echo "<br><strong style='color: red;'>❌ 测试未通过！系统存在问题。</strong>";
    echo "<br>请修复上述测试中发现的问题。";
}
?>