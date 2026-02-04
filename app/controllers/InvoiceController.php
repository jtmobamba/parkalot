<?php

class InvoiceController {
    private $db;
    private $ratePerHour = 2.00;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getUserInvoice($userId) {
        // Detect a garage "name" column (optional)
        $nameCol = null;
        try {
            $stmtCols = $this->db->query("SHOW COLUMNS FROM garages");
            $cols = array_map(fn($r) => $r['Field'], $stmtCols->fetchAll(PDO::FETCH_ASSOC));
            foreach (['garage_name', 'name', 'title', 'garage_title', 'location'] as $c) {
                if (in_array($c, $cols, true)) {
                    $nameCol = $c;
                    break;
                }
            }
        } catch (Throwable $e) {
            $nameCol = null;
        }

        $selectGarageName = $nameCol ? "g.{$nameCol} AS garage_name" : "CONCAT('Garage #', r.garage_id) AS garage_name";
        
        // Get price from reservation if it exists, otherwise calculate
        $stmt = $this->db->prepare(
            "SELECT r.reservation_id, r.garage_id, r.user_id, {$selectGarageName}, 
                    r.start_time, r.end_time, r.price, r.status, r.created_at,
                    g.price_per_hour
             FROM reservations r
             LEFT JOIN garages g ON g.garage_id = r.garage_id
             WHERE r.user_id = ?
             ORDER BY r.reservation_id DESC"
        );

        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = 0;

        foreach ($rows as &$r) {
            // Use stored price if available, otherwise calculate
            if (isset($r['price']) && $r['price'] > 0) {
                $price = floatval($r['price']);
            } else {
                // Calculate price based on duration
                $start = new DateTime($r['start_time']);
                $end   = new DateTime($r['end_time']);

                $seconds = max(0, $end->getTimestamp() - $start->getTimestamp());
                $hours = $seconds / 3600; // Decimal hours
                
                // Use garage price_per_hour or fallback to default
                $pricePerHour = isset($r['price_per_hour']) && $r['price_per_hour'] > 0 
                    ? floatval($r['price_per_hour']) 
                    : $this->ratePerHour;
                
                $price = $hours * $pricePerHour;
            }
            
            $r['price'] = number_format($price, 2, '.', '');
            $total += $price;
        }

        return [
            "count" => count($rows),
            "total" => number_format($total, 2, '.', ''),
            "reservations" => $rows
        ];
    }
}

