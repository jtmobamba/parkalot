<?php
/**
 * EmployeeController - Handles employee management operations
 */
class EmployeeController {
    private $employeeDAO;
    
    public function __construct($db) {
        require_once __DIR__ . '/../models/EmployeeDAO.php';
        $this->employeeDAO = new EmployeeDAO($db);
    }

    /**
     * Get all employees
     */
    public function getAllEmployees() {
        try {
            $employees = $this->employeeDAO->getAllEmployees();
            return ['success' => true, 'employees' => $employees];
        } catch (Exception $e) {
            return ['error' => 'Failed to fetch employees'];
        }
    }

    /**
     * Get single employee details
     */
    public function getEmployee($userId) {
        try {
            $employee = $this->employeeDAO->getEmployeeById($userId);
            if ($employee) {
                return ['success' => true, 'employee' => $employee];
            }
            return ['error' => 'Employee not found'];
        } catch (Exception $e) {
            return ['error' => 'Failed to fetch employee'];
        }
    }

    /**
     * Create new employee contract
     */
    public function createContract($data) {
        try {
            $result = $this->employeeDAO->createContract(
                $data->user_id,
                $data->employee_type,
                $data->department,
                $data->position,
                $data->salary,
                $data->hire_date,
                $data->contract_start_date,
                $data->contract_end_date ?? null
            );
            
            if ($result) {
                return ['success' => true, 'message' => 'Contract created successfully'];
            }
            return ['error' => 'Failed to create contract'];
        } catch (Exception $e) {
            return ['error' => 'Failed to create contract: ' . $e->getMessage()];
        }
    }

    /**
     * Update employee contract
     */
    public function updateContract($contractId, $data) {
        try {
            $updateData = (array) $data;
            $result = $this->employeeDAO->updateContract($contractId, $updateData);
            
            if ($result) {
                return ['success' => true, 'message' => 'Contract updated successfully'];
            }
            return ['error' => 'Failed to update contract'];
        } catch (Exception $e) {
            return ['error' => 'Failed to update contract'];
        }
    }

    /**
     * Terminate employee contract
     */
    public function terminateContract($contractId) {
        try {
            $result = $this->employeeDAO->terminateContract($contractId);
            
            if ($result) {
                return ['success' => true, 'message' => 'Contract terminated'];
            }
            return ['error' => 'Failed to terminate contract'];
        } catch (Exception $e) {
            return ['error' => 'Failed to terminate contract'];
        }
    }

    /**
     * Get employees by role
     */
    public function getEmployeesByRole($role) {
        try {
            $employees = $this->employeeDAO->getEmployeesByRole($role);
            return ['success' => true, 'employees' => $employees];
        } catch (Exception $e) {
            return ['error' => 'Failed to fetch employees'];
        }
    }
}
?>
