<?php
/**
 * VehicleExpenses Class
 * Handles all vehicle expense-related database operations
 */

class VehicleExpenses {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Add a new vehicle expense
     * @param int $vehicle_id
     * @param int $user_id
     * @param string $expense_type (fuel, repairs, gear_oil, lub_oil, grease, maintenance, parts, other)
     * @param float $amount
     * @param string $expense_date (YYYY-MM-DD)
     * @param string $description
     * @param int $trip_ticket_id (optional)
     * @return int|false - New expense ID or false on failure
     */
    public function addExpense($vehicle_id, $user_id, $expense_type, $amount, $expense_date, $description = null, $trip_ticket_id = null) {
        try {
            $sql = "INSERT INTO vehicle_expenses (vehicle_id, user_id, trip_ticket_id, expense_type, amount, expense_date, description) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $vehicle_id,
                $user_id,
                $trip_ticket_id,
                $expense_type,
                $amount,
                $expense_date,
                $description
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error adding vehicle expense: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all expenses for a specific vehicle
     * @param int $vehicle_id
     * @param string $start_date (optional, YYYY-MM-DD)
     * @param string $end_date (optional, YYYY-MM-DD)
     * @return array
     */
    public function getVehicleExpenses($vehicle_id, $start_date = null, $end_date = null) {
        try {
            $sql = "SELECT ve.*, v.vehicle_no, u.first_name, u.last_name, u.username, t.control_no 
                    FROM vehicle_expenses ve
                    LEFT JOIN vehicles v ON ve.vehicle_id = v.id
                    LEFT JOIN users u ON ve.user_id = u.id
                    LEFT JOIN trip_tickets t ON ve.trip_ticket_id = t.id
                    WHERE ve.vehicle_id = ?";
            
            $params = [$vehicle_id];
            
            if ($start_date) {
                $sql .= " AND ve.expense_date >= ?";
                $params[] = $start_date;
            }
            
            if ($end_date) {
                $sql .= " AND ve.expense_date <= ?";
                $params[] = $end_date;
            }
            
            $sql .= " ORDER BY ve.expense_date DESC, ve.created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching vehicle expenses: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get expenses by type
     * @param string $expense_type
     * @param string $start_date (optional)
     * @param string $end_date (optional)
     * @return array
     */
    public function getExpensesByType($expense_type, $start_date = null, $end_date = null) {
        try {
            $sql = "SELECT ve.*, v.vehicle_no, u.first_name, u.last_name 
                    FROM vehicle_expenses ve
                    LEFT JOIN vehicles v ON ve.vehicle_id = v.id
                    LEFT JOIN users u ON ve.user_id = u.id
                    WHERE ve.expense_type = ?";
            
            $params = [$expense_type];
            
            if ($start_date) {
                $sql .= " AND ve.expense_date >= ?";
                $params[] = $start_date;
            }
            
            if ($end_date) {
                $sql .= " AND ve.expense_date <= ?";
                $params[] = $end_date;
            }
            
            $sql .= " ORDER BY ve.expense_date DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching expenses by type: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get total expenses for a vehicle
     * @param int $vehicle_id
     * @param string $start_date (optional)
     * @param string $end_date (optional)
     * @return float
     */
    public function getTotalExpenses($vehicle_id, $start_date = null, $end_date = null) {
        try {
            $sql = "SELECT SUM(amount) as total FROM vehicle_expenses WHERE vehicle_id = ?";
            
            $params = [$vehicle_id];
            
            if ($start_date) {
                $sql .= " AND expense_date >= ?";
                $params[] = $start_date;
            }
            
            if ($end_date) {
                $sql .= " AND expense_date <= ?";
                $params[] = $end_date;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return floatval($result['total'] ?? 0);
        } catch (PDOException $e) {
            error_log("Error calculating total expenses: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get expenses breakdown by type for a vehicle
     * @param int $vehicle_id
     * @param string $start_date (optional)
     * @param string $end_date (optional)
     * @return array
     */
    public function getExpenseBreakdown($vehicle_id, $start_date = null, $end_date = null) {
        try {
            $sql = "SELECT expense_type, COUNT(*) as count, SUM(amount) as total, AVG(amount) as average
                    FROM vehicle_expenses
                    WHERE vehicle_id = ?";
            
            $params = [$vehicle_id];
            
            if ($start_date) {
                $sql .= " AND expense_date >= ?";
                $params[] = $start_date;
            }
            
            if ($end_date) {
                $sql .= " AND expense_date <= ?";
                $params[] = $end_date;
            }
            
            $sql .= " GROUP BY expense_type ORDER BY total DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting expense breakdown: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update an expense record
     * @param int $expense_id
     * @param array $data - ['expense_type' => '...', 'amount' => 100, 'description' => '...', 'expense_date' => '2026-01-26']
     * @return bool
     */
    public function updateExpense($expense_id, $data) {
        try {
            $updates = [];
            $params = [];
            
            $allowed_fields = ['expense_type', 'amount', 'description', 'expense_date'];
            
            foreach ($data as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    $updates[] = "$key = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($updates)) {
                return false;
            }
            
            $params[] = $expense_id;
            
            $sql = "UPDATE vehicle_expenses SET " . implode(", ", $updates) . " WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error updating expense: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete an expense record
     * @param int $expense_id
     * @return bool
     */
    public function deleteExpense($expense_id) {
        try {
            $sql = "DELETE FROM vehicle_expenses WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$expense_id]);
        } catch (PDOException $e) {
            error_log("Error deleting expense: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get a single expense record
     * @param int $expense_id
     * @return array|false
     */
    public function getExpense($expense_id) {
        try {
            $sql = "SELECT ve.*, v.vehicle_no, u.first_name, u.last_name, t.control_no
                    FROM vehicle_expenses ve
                    LEFT JOIN vehicles v ON ve.vehicle_id = v.id
                    LEFT JOIN users u ON ve.user_id = u.id
                    LEFT JOIN trip_tickets t ON ve.trip_ticket_id = t.id
                    WHERE ve.id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$expense_id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching expense: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get monthly expense summary for a vehicle
     * @param int $vehicle_id
     * @param int $year (optional, defaults to current year)
     * @return array
     */
    public function getMonthlyExpenseSummary($vehicle_id, $year = null) {
        try {
            $year = $year ?? date('Y');
            
            $sql = "SELECT MONTH(expense_date) as month, YEAR(expense_date) as year,
                           SUM(amount) as total, COUNT(*) as count
                    FROM vehicle_expenses
                    WHERE vehicle_id = ? AND YEAR(expense_date) = ?
                    GROUP BY YEAR(expense_date), MONTH(expense_date)
                    ORDER BY YEAR(expense_date) DESC, MONTH(expense_date) DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$vehicle_id, $year]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting monthly summary: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all expense types
     * @return array
     */
    public function getExpenseTypes() {
        return [
            'fuel' => 'Fuel',
            'repairs' => 'Repairs',
            'gear_oil' => 'Gear Oil',
            'lub_oil' => 'Lubricant Oil',
            'grease' => 'Grease',
            'maintenance' => 'Maintenance',
            'parts' => 'Parts',
            'other' => 'Other'
        ];
    }
    
    /**
     * Get all vehicles
     * @return array
     */
    public function getAllVehicles() {
        try {
            $sql = "SELECT id, vehicle_no, vehicle_type, status FROM vehicles ORDER BY vehicle_no ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching vehicles: " . $e->getMessage());
            return [];
        }
    }
}
?>
