<?php
require_once __DIR__ . '/../models/UserDAO.php';
require_once __DIR__ . '/../models/GarageDAO.php';
require_once __DIR__ . '/../models/ReservationDAO.php';

class DAOFactory {
    public static function userDAO($db) {
        return new UserDAO($db);
    }

    public static function garageDAO($db) {
        return new GarageDAO($db);
    }

    public static function reservationDAO($db) {
        return new ReservationDAO($db);
    }
}
?>