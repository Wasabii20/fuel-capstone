<?php
session_start();
require_once 'db_connect.php';

$search_result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search_term = isset($_POST['search']) ? trim($_POST['search']) : '';
    
    if (empty($search_term)) {
        $error = "Please enter a Control Number or Ticket ID";
    } else {
        try {
            // Search by control number or ticket ID
            $sql = "SELECT t.id, t.control_no, t.ticket_date, t.qr_code, d.full_name 
                    FROM trip_tickets t 
                    LEFT JOIN drivers d ON t.driver_id = d.driver_id 
                    WHERE t.control_no LIKE ? OR t.id = ?
                    LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['%' . $search_term . '%', intval($search_term)]);
            $search_result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$search_result) {
                $error = "No ticket found matching: " . htmlspecialchars($search_term);
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Trip Ticket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-form input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .search-form input:focus {
            outline: none;
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .search-form button {
            padding: 12px 25px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-form button:hover {
            background: #5568d3;
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }
        
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
        }
        
        .ticket-result {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .result-header h2 {
            color: #333;
            font-size: 18px;
        }
        
        .status-badge {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .result-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            color: #999;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .detail-value {
            color: #333;
            font-size: 14px;
            font-weight: 500;
        }
        
        .qr-display {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: white;
            border-radius: 8px;
        }
        
        .qr-display img {
            max-width: 200px;
            height: auto;
            border: 2px solid #667eea;
            padding: 8px;
            border-radius: 8px;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            flex: 1;
            padding: 12px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #1565c0;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-qrcode"></i> Search Trip Ticket</h1>
        <p class="subtitle">Find a ticket by Control Number or Ticket ID</p>
        
        <form class="search-form" method="POST">
            <input type="text" name="search" placeholder="Enter Control No or Ticket ID..." required autofocus>
            <button type="submit"><i class="fas fa-search"></i> Search</button>
        </form>
        
        <div class="info-box">
            <i class="fas fa-info-circle"></i> You can scan a QR code or manually enter a Control Number (e.g., TT-001-2026) or Ticket ID.
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($search_result): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i> Ticket found successfully!
            </div>
            
            <div class="ticket-result">
                <div class="result-header">
                    <h2><?php echo htmlspecialchars($search_result['control_no']); ?></h2>
                    <span class="status-badge">Ticket ID: <?php echo $search_result['id']; ?></span>
                </div>
                
                <div class="result-details">
                    <div class="detail-item">
                        <span class="detail-label">Driver Name</span>
                        <span class="detail-value"><?php echo htmlspecialchars($search_result['full_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Ticket Date</span>
                        <span class="detail-value"><?php echo date('M j, Y', strtotime($search_result['ticket_date'])); ?></span>
                    </div>
                </div>
                
                <?php if ($search_result['qr_code']): ?>
                    <div class="qr-display">
                        <img src="<?php echo htmlspecialchars($search_result['qr_code']); ?>" alt="QR Code">
                    </div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <a href="view_ticket.php?id=<?php echo $search_result['id']; ?>&control=<?php echo urlencode($search_result['control_no']); ?>" class="btn btn-primary">
                        <i class="fas fa-eye"></i> View Ticket
                    </a>
                    <a href="view_ticket.php?id=<?php echo $search_result['id']; ?>&control=<?php echo urlencode($search_result['control_no']); ?>&format=pdf" class="btn btn-secondary">
                        <i class="fas fa-download"></i> Download PDF
                    </a>
                    <a href="view_ticket.php?id=<?php echo $search_result['id']; ?>&control=<?php echo urlencode($search_result['control_no']); ?>" class="btn btn-secondary" onclick="window.location.href = this.href; window.print(); return false;">
                        <i class="fas fa-print"></i> Print
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
