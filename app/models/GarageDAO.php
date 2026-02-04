<?php
class GarageDAO {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    private function getGarageNameColumn() {
        // Try to detect a human-friendly name column in `garages`.
        $stmt = $this->db->query("SHOW COLUMNS FROM garages");
        $cols = array_map(fn($r) => $r['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        $candidates = ['garage_name', 'name', 'title', 'garage_title', 'location'];
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) return $c;
        }
        return null;
    }

    public function getCapacity($garageId) {
        $stmt = $this->db->prepare(
            "SELECT total_spaces FROM garages WHERE garage_id=?"
        );
        $stmt->execute([$garageId]);
        return $stmt->fetchColumn();
    }

    public function listGarages() {
        $nameCol = $this->getGarageNameColumn();
        if ($nameCol) {
            $stmt = $this->db->query("SELECT garage_id, {$nameCol} AS garage_name FROM garages ORDER BY garage_id ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fallback: no known name column; return ids only.
        $stmt = $this->db->query("SELECT garage_id, CONCAT('Garage #', garage_id) AS garage_name FROM garages ORDER BY garage_id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
