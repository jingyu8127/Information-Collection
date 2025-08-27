<?php
// 测试数据库连接和事务功能
require_once 'config.php';

echo "开始测试数据库连接...\n";

try {
    // 测试数据库连接
    $testQuery = getAll("SELECT 1");
    echo "✅ 数据库连接成功\n";
    
    // 测试事务功能
    echo "\n开始测试事务功能...\n";
    
    beginTransaction();
    echo "✅ 事务开始成功\n";
    
    // 测试form_fields表是否存在
    try {
        $fields = getAll("SELECT * FROM form_fields LIMIT 1");
        echo "✅ form_fields表存在，并且有" . count($fields) . "条记录\n";
    } catch (Exception $e) {
        echo "❌ form_fields表不存在或查询失败: " . $e->getMessage() . "\n";
    }
    
    // 测试settings表是否存在
    try {
        $settings = getAll("SELECT * FROM settings LIMIT 1");
        echo "✅ settings表存在，并且有" . count($settings) . "条记录\n";
    } catch (Exception $e) {
        echo "❌ settings表不存在或查询失败: " . $e->getMessage() . "\n";
    }
    
    // 回滚事务
    rollbackTransaction();
    echo "✅ 事务回滚成功\n";
    
} catch (Exception $e) {
    echo "❌ 测试过程中出现错误: " . $e->getMessage() . "\n";
    
    // 尝试回滚事务
    try {
        rollbackTransaction();
    } catch (Exception $ex) {
        // 忽略回滚错误
    }
}

echo "\n测试完成！\n";