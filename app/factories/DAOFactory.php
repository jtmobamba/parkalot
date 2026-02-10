<?php
require_once __DIR__ . '/../models/UserDAO.php';
require_once __DIR__ . '/../models/GarageDAO.php';
require_once __DIR__ . '/../models/ReservationDAO.php';
require_once __DIR__ . '/../models/CustomerSpaceDAO.php';
require_once __DIR__ . '/../models/CustomerSpaceBookingDAO.php';
require_once __DIR__ . '/../models/PaymentDAO.php';

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

    public static function customerSpaceDAO($db) {
        return new CustomerSpaceDAO($db);
    }

    public static function customerSpaceBookingDAO($db) {
        return new CustomerSpaceBookingDAO($db);
    }

    public static function paymentDAO($db) {
        return new PaymentDAO($db);
    }
}
?>