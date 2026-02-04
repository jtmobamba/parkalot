<?
$router->get('/availability', function() use ($db) {
    $garageId = $_GET['garage_id'];
    $stmt = $db->prepare("
        SELECT total_spaces -
        (SELECT COUNT(*) FROM reservations
         WHERE garage_id=? AND status='active')
        AS available
        FROM garages WHERE garage_id=?
    ");
    $stmt->execute([$garageId,$garageId]);
    echo json_encode($stmt->fetch());
});
