<?php
/**
 * EmployeeDAO - Data Access Object for Employee Management
 * Handles employee contracts, tracking, and management operations
 */
class EmployeeDAO {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Get all employees with their contract details
     */
    public function getAllEmployees() {
        $stmt = $this->db->query("
            SELECT 
                u.user_id,
                u.full_name,
                u.email,
                u.role,
                ec.contract_id,
                ec.employee_type,
                ec.department,
                ec.position,
                ec.salary,
                ec.hire_date,
                ec.contract_start_date,
                ec.contract_end_date,
                ec.status as contract_status
            FROM users u
            INNER JOIN employee_contracts ec ON u.user_id = ec.user_id
            WHERE u.role IN ('employee', 'senior_employee', 'manager')
            ORDER BY ec.hire_date DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get employee by user ID
     */
    public function getEmployeeById($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                u.user_id,
                u.full_name,
                u.email,
                u.role,
                u.last_login,
                ec.contract_id,
                ec.employee_type,
                ec.department,
                ec.position,
                ec.salary,
                ec.hire_date,
                ec.contract_start_date,
                ec.contract_end_date,
                ec.status as contract_status
            FROM users u
            INNER JOIN employee_contracts ec ON u.user_id = ec.user_id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create employee contract
     */
    public function createContract($userId, $employeeType, $department, $position, $salary, $hireDate, $contractStartDate, $contractEndDate = null) {
        $stmt = $this->db->prepare("
            INSERT INTO employee_contracts 
            (user_id, employee_type, department, position, salary, hire_date, contract_start_date, contract_end_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $userId,
            $employeeType,
            $department,
            $position,
            $salary,
            $hireDate,
            $contractStartDate,
            $contractEndDate
        ]);
    }

    /**
     * Update employee contract
     */
    public function updateContract($contractId, $data) {
        $fields = [];
        $values = [];

        if (isset($data['department'])) {
            $fields[] = "department = ?";
            $values[] = $data['department'];
        }
        if (isset($data['position'])) {
            $fields[] = "position = ?";
            $values[] = $data['position'];
        }
        if (isset($data['salary'])) {
            $fields[] = "salary = ?";
            $values[] = $data['salary'];
        }
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $values[] = $data['status'];
        }
        if (isset($data['contract_end_date'])) {
            $fields[] = "contract_end_date = ?";
            $values[] = $data['contract_end_date'];
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $contractId;
        $sql = "UPDATE employee_contracts SET " . implode(", ", $fields) . " WHERE contract_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Get active employees by role
     */
    public function getEmployeesByRole($role) {
        $stmt = $this->db->prepare("
            SELECT 
                u.user_id,
                u.full_name,
                u.email,
                ec.department,
                ec.position
            FROM users u
            INNER JOIN employee_contracts ec ON u.user_id = ec.user_id
            WHERE u.role = ? AND ec.status = 'active'
            ORDER BY u.full_name ASC
        ");
        $stmt->execute([$role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get employee activity statistics
     */
    public function getEmployeeStats($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_activities,
                MAX(created_at) as last_activity,
                DATE(created_at) as activity_date,
                COUNT(*) as daily_count
            FROM activity_logs
            WHERE user_id = ?
            GROUP BY DATE(created_at)
            ORDER BY activity_date DESC
            LIMIT 30
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Terminate employee contract
     */
    public function terminateContract($contractId) {
        $stmt = $this->db->prepare("
            UPDATE employee_contracts 
            SET status = 'terminated', contract_end_date = CURDATE()
            WHERE contract_id = ?
        ");
        return $stmt->execute([$contractId]);
    }
}
?>
