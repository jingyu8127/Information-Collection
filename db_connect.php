<?php
// 数据库连接配置
$host = 'localhost'; // 数据库主机名
$dbname = ''; // 数据库名称
$username = ''; // 数据库用户名
$password = ''; // 数据库密码
$charset = 'utf8mb4'; // 字符集

// DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// PDO 选项
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// 创建数据库连接
try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die('数据库连接失败: ' . $e->getMessage());
}

/**
 * 开始事务
 */
function beginTransaction() {
    global $pdo;
    $pdo->beginTransaction();
}

/**
 * 提交事务
 */
function commitTransaction() {
    global $pdo;
    $pdo->commit();
}

/**
 * 回滚事务
 */
function rollbackTransaction() {
    global $pdo;
    $pdo->rollBack();
}

/**
 * 执行SQL查询并返回结果
 * @param string $sql SQL语句
 * @param array $params 参数数组
 * @return mixed 查询结果
 */
function query($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * 获取单条记录
 * @param string $sql SQL语句
 * @param array $params 参数数组
 * @return array|null 记录数据
 */
function getSingle($sql, $params = []) {
    return query($sql, $params)->fetch();
}

/**
 * 获取多条记录
 * @param string $sql SQL语句
 * @param array $params 参数数组
 * @return array 记录数据集
 */
function getAll($sql, $params = []) {
    return query($sql, $params)->fetchAll();
}

/**
 * 执行插入操作
 * @param string $sql SQL语句
 * @param array $params 参数数组
 * @return int 插入的ID
 */
function insert($sql, $params = []) {
    global $pdo;
    query($sql, $params);
    return $pdo->lastInsertId();
}

/**
 * 执行更新或删除操作
 * @param string $sql SQL语句
 * @param array $params 参数数组
 * @return int 受影响的行数
 */
function execute($sql, $params = []) {
    return query($sql, $params)->rowCount();
}

/**
 * 转义特殊字符，防止SQL注入
 * @param string $str 要转义的字符串
 * @return string 转义后的字符串
 */
function escape($str) {
    global $pdo;
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * 加密密码
 * @param string $password 要加密的密码
 * @return string 加密后的密码
 */
function encryptPassword($password) {
    // 在实际应用中，应该使用更安全的加密方法
    // 这里我们使用base64编码作为简单的示例
    return base64_encode($password);
}

/**
 * 解密密码
 * @param string $encryptedPassword 加密后的密码
 * @return string 解密后的密码
 */
function decryptPassword($encryptedPassword) {
    // 与encryptPassword相对应的解密方法
    return base64_decode($encryptedPassword);
}
?>