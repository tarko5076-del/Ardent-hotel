<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Support\Helpers;
use PDO;

final class RoomRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (($filters['activeOnly'] ?? 'true') !== 'false') {
            $where[] = 'r.is_active = true';
        }

        if (!empty($filters['roomType'])) {
            $where[] = 'r.room_type = ?';
            $params[] = $filters['roomType'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(r.name LIKE ? OR r.room_type LIKE ? OR r.description LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $guests = (int) ($filters['guests'] ?? 0);
        if ($guests > 0) {
            $where[] = 'r.capacity >= ?';
            $params[] = $guests;
        }

        if (!empty($filters['checkIn']) && !empty($filters['checkOut'])) {
            $where[] = "r.id NOT IN (
                SELECT rb.room_id
                FROM room_bookings rb
                WHERE rb.status IN ('Pending', 'Confirmed')
                  AND NOT (rb.check_out <= ? OR rb.check_in >= ?)
            )";
            $params[] = $filters['checkIn'];
            $params[] = $filters['checkOut'];
        }

        $sql = 'SELECT * FROM rooms r';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY r.price_per_night ASC, r.room_number ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(fn (array $room) => $this->normalize($room), $statement->fetchAll());
    }

    public function findById(int|string $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM rooms WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        $room = $statement->fetch();

        return $room ? $this->normalize($room) : null;
    }

    public function create(array $payload): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO rooms (room_number, name, room_type, description, price_per_night, capacity, amenities, image, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $payload['roomNumber'],
            $payload['name'],
            $payload['roomType'],
            $payload['description'],
            $payload['pricePerNight'],
            $payload['capacity'],
            json_encode($payload['amenities'] ?? [], JSON_THROW_ON_ERROR),
            $payload['image'] ?? '',
            $payload['isActive'] ? 1 : 0,
        ]);

        return $this->findById((int) $this->pdo->lastInsertId()) ?? throw new \RuntimeException('Unable to create room.');
    }

    public function hasBookingConflict(PDO $executor, int $roomId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM room_bookings WHERE room_id = ? AND status IN ('Pending', 'Confirmed') AND NOT (check_out <= ? OR check_in >= ?)";
        $params = [$roomId, $checkIn, $checkOut];

        if ($excludeBookingId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeBookingId;
        }

        $statement = $executor->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public function createBooking(PDO $connection, array $payload): int
    {
        $statement = $connection->prepare(
            "INSERT INTO room_bookings (user_id, room_id, check_in, check_out, guests, total_amount, status, contact_phone, special_request) VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?)"
        );
        $statement->execute([
            $payload['userId'],
            $payload['roomId'],
            $payload['checkIn'],
            $payload['checkOut'],
            $payload['guests'],
            $payload['totalAmount'],
            $payload['contactPhone'] ?? '',
            $payload['specialRequest'] ?? '',
        ]);

        return (int) $connection->lastInsertId();
    }

    public function findBookingsByUserId(int $userId): array
    {
        $statement = $this->pdo->prepare($this->bookingsBaseQuery() . ' WHERE rb.user_id = ? ORDER BY rb.created_at DESC');
        $statement->execute([$userId]);

        return array_map(fn (array $booking) => $this->normalizeBooking($booking), $statement->fetchAll());
    }

    public function findAllBookings(): array
    {
        $statement = $this->pdo->query($this->bookingsBaseQuery() . ' ORDER BY rb.created_at DESC');

        return array_map(fn (array $booking) => $this->normalizeBooking($booking), $statement->fetchAll());
    }

    public function findBookingById(int|string $id): ?array
    {
        $statement = $this->pdo->prepare($this->bookingsBaseQuery() . ' WHERE rb.id = ? LIMIT 1');
        $statement->execute([$id]);
        $booking = $statement->fetch();

        return $booking ? $this->normalizeBooking($booking) : null;
    }

    public function updateBookingStatus(int|string $id, string $status, string $adminNote = ''): array
    {
        $statement = $this->pdo->prepare(
            "UPDATE room_bookings SET status = ?, admin_note = ?, cancelled_at = CASE WHEN ? = 'Cancelled' THEN CURRENT_TIMESTAMP ELSE NULL END, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        $statement->execute([$status, $adminNote, $status, $id]);

        return $this->findBookingById($id) ?? throw new \RuntimeException('Unable to update booking.');
    }

    public function normalize(?array $room): ?array
    {
        if ($room === null) {
            return null;
        }

        return [
            ...$room,
            'id' => (int) $room['id'],
            '_id' => (string) $room['id'],
            'price_per_night' => (float) $room['price_per_night'],
            'capacity' => (int) $room['capacity'],
            'is_active' => (bool) $room['is_active'],
            'amenities' => Helpers::parseJsonValue($room['amenities'] ?? null, []),
        ];
    }

    private function normalizeBooking(array $booking): array
    {
        return [
            ...$booking,
            'id' => (int) $booking['id'],
            '_id' => (string) $booking['id'],
            'user_id' => (int) $booking['user_id'],
            'room_id' => (int) $booking['room_id'],
            'guests' => (int) $booking['guests'],
            'total_amount' => (float) $booking['total_amount'],
            'price_per_night' => (float) ($booking['price_per_night'] ?? 0),
            'check_in' => Helpers::formatDateValue($booking['check_in'] ?? ''),
            'check_out' => Helpers::formatDateValue($booking['check_out'] ?? ''),
            'amenities' => Helpers::parseJsonValue($booking['amenities'] ?? null, []),
            'room' => [
                'id' => (int) $booking['room_id'],
                '_id' => (string) $booking['room_id'],
                'room_number' => $booking['room_number'],
                'name' => $booking['room_name'],
                'room_type' => $booking['room_type'],
                'image' => $booking['room_image'] ?? '',
                'price_per_night' => (float) ($booking['price_per_night'] ?? 0),
            ],
        ];
    }

    private function bookingsBaseQuery(): string
    {
        return 'SELECT rb.*, r.room_number, r.name AS room_name, r.room_type, r.price_per_night, r.amenities, r.image AS room_image, u.name AS customer_name, u.email AS customer_email FROM room_bookings rb INNER JOIN rooms r ON r.id = rb.room_id INNER JOIN users u ON u.id = rb.user_id';
    }
}
