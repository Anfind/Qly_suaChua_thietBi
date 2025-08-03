<?php
/**
 * Lớp Database - Kết nối và quản lý cơ sở dữ liệu
 */
class Database {
    private $host = 'localhost';
    private $dbname = 'equipment_repair_management';
    private $username = 'root';
    // private $password = '210506';
    private $password = '';

    private $pdo;
    private static $instance = null;

    public function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau.");
        }
    }

    /**
     * Singleton pattern để đảm bảo chỉ có 1 kết nối database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Lấy PDO connection
     */
    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Chuẩn bị và thực thi query
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
            throw new Exception("Lỗi thực thi truy vấn cơ sở dữ liệu: " . $e->getMessage());
        }
    }

    /**
     * Lấy một dòng dữ liệu
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Lấy tất cả dòng dữ liệu
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Thêm dữ liệu và trả về ID
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";
        
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    /**
     * Cập nhật dữ liệu
     */
    public function update($table, $data, $where, $whereParams = []) {
        try {
            $fields = [];
            $params = [];
            
            // Tạo SET clause với placeholder bình thường
            foreach ($data as $field => $value) {
                $fields[] = "{$field} = ?";
                $params[] = $value;
            }
            
            // Thêm where parameters
            $params = array_merge($params, $whereParams);
            
            $sql = "UPDATE {$table} SET " . implode(', ', $fields) . " WHERE {$where}";
            
            return $this->query($sql, $params);
        } catch (PDOException $e) {
            error_log("Update failed: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
            throw new Exception("Lỗi cập nhật dữ liệu: " . $e->getMessage());
        }
    }

    /**
     * Xóa dữ liệu
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }

    /**
     * Bắt đầu transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollback();
    }

    /**
     * Kiểm tra có transaction đang active không
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }

    /**
     * Kiểm tra kết nối
     */
    public function isConnected() {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>
