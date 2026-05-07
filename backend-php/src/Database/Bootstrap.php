<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Database;
use App\Config\Env;
use PDO;

final class Bootstrap
{
    public static function initialize(): void
    {
        self::ensureUploadsDirectory();
        self::ensureTables();
        self::ensureDefaultRooms();
        self::ensureAdminAccount();
    }

    private static function ensureUploadsDirectory(): void
    {
        $uploadsDirectory = Env::get('UPLOADS_DIR', 'storage/uploads');

        if (!str_starts_with($uploadsDirectory, DIRECTORY_SEPARATOR) && !preg_match('/^[A-Za-z]:\\\\/', $uploadsDirectory)) {
            $uploadsDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $uploadsDirectory;
        }

        if (!is_dir($uploadsDirectory)) {
            mkdir($uploadsDirectory, 0777, true);
        }
    }

    private static function ensureTables(): void
    {
        $pdo = Database::connection();

        $statements = [
            <<<SQL
            CREATE TABLE IF NOT EXISTS users (
              id INT AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(255) NOT NULL,
              email VARCHAR(255) UNIQUE NOT NULL,
              password VARCHAR(255) NOT NULL,
              phone VARCHAR(30) DEFAULT '',
              default_address JSON NULL,
              role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS foods (
              id INT AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(255) NOT NULL,
              description TEXT NOT NULL,
              price DECIMAL(10, 2) NOT NULL,
              image VARCHAR(500) NOT NULL,
              category VARCHAR(100) NOT NULL,
              stock_quantity INT NOT NULL DEFAULT 0,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS cart_items (
              id INT AUTO_INCREMENT PRIMARY KEY,
              user_id INT NOT NULL,
              food_id INT NOT NULL,
              quantity INT NOT NULL DEFAULT 1,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY unique_cart_item (user_id, food_id),
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              FOREIGN KEY (food_id) REFERENCES foods(id) ON DELETE CASCADE
            )
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS orders (
              id INT AUTO_INCREMENT PRIMARY KEY,
              user_id INT NOT NULL,
              subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0,
              delivery_fee DECIMAL(10, 2) NOT NULL DEFAULT 0,
              amount DECIMAL(10, 2) NOT NULL,
              address JSON NOT NULL,
              status VARCHAR(50) NOT NULL DEFAULT 'Food Processing',
              payment BOOLEAN NOT NULL DEFAULT FALSE,
              payment_method VARCHAR(20) NOT NULL DEFAULT 'COD',
              payment_reference VARCHAR(120) DEFAULT NULL,
              payment_status VARCHAR(30) NOT NULL DEFAULT 'pending',
              cancelled_at TIMESTAMP NULL DEFAULT NULL,
              cancelled_by VARCHAR(20) DEFAULT NULL,
              cancellation_reason VARCHAR(255) DEFAULT '',
              order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS order_items (
              id INT AUTO_INCREMENT PRIMARY KEY,
              order_id INT NOT NULL,
              food_id INT NOT NULL,
              name VARCHAR(255) NOT NULL,
              price DECIMAL(10, 2) NOT NULL,
              quantity INT NOT NULL DEFAULT 1,
              image VARCHAR(500),
              FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            )
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS favorite_items (
              id INT AUTO_INCREMENT PRIMARY KEY,
              user_id INT NOT NULL,
              food_id INT NOT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY unique_favorite_item (user_id, food_id),
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              FOREIGN KEY (food_id) REFERENCES foods(id) ON DELETE CASCADE
            )
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS rooms (
              id INT AUTO_INCREMENT PRIMARY KEY,
              room_number VARCHAR(30) NOT NULL UNIQUE,
              name VARCHAR(120) NOT NULL,
              room_type VARCHAR(80) NOT NULL,
              description TEXT NOT NULL,
              price_per_night DECIMAL(10, 2) NOT NULL,
              capacity INT NOT NULL DEFAULT 2,
              amenities JSON NULL,
              image VARCHAR(500) DEFAULT '',
              is_active BOOLEAN NOT NULL DEFAULT TRUE,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS room_bookings (
              id INT AUTO_INCREMENT PRIMARY KEY,
              user_id INT NOT NULL,
              room_id INT NOT NULL,
              check_in DATE NOT NULL,
              check_out DATE NOT NULL,
              guests INT NOT NULL DEFAULT 1,
              total_amount DECIMAL(10, 2) NOT NULL,
              status ENUM('Pending', 'Confirmed', 'Cancelled', 'Completed') NOT NULL DEFAULT 'Pending',
              contact_phone VARCHAR(30) DEFAULT '',
              special_request TEXT,
              admin_note VARCHAR(255) DEFAULT '',
              cancelled_at TIMESTAMP NULL DEFAULT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
            )
            SQL,
        ];

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        $columns = [
            ['users', 'phone', "phone VARCHAR(30) DEFAULT ''"],
            ['users', 'default_address', 'default_address JSON NULL'],
            ['users', 'role', "role ENUM('user', 'admin') NOT NULL DEFAULT 'user'"],
            ['users', 'updated_at', 'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
            ['foods', 'stock_quantity', 'stock_quantity INT NOT NULL DEFAULT 0'],
            ['orders', 'subtotal', 'subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0'],
            ['orders', 'delivery_fee', 'delivery_fee DECIMAL(10, 2) NOT NULL DEFAULT 0'],
            ['orders', 'payment_method', "payment_method VARCHAR(20) NOT NULL DEFAULT 'COD'"],
            ['orders', 'payment_reference', 'payment_reference VARCHAR(120) DEFAULT NULL'],
            ['orders', 'payment_status', "payment_status VARCHAR(30) NOT NULL DEFAULT 'pending'"],
            ['orders', 'cancelled_at', 'cancelled_at TIMESTAMP NULL DEFAULT NULL'],
            ['orders', 'cancelled_by', 'cancelled_by VARCHAR(20) DEFAULT NULL'],
            ['orders', 'cancellation_reason', "cancellation_reason VARCHAR(255) DEFAULT ''"],
            ['orders', 'updated_at', 'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
            ['room_bookings', 'admin_note', "admin_note VARCHAR(255) DEFAULT ''"],
            ['room_bookings', 'cancelled_at', 'cancelled_at TIMESTAMP NULL DEFAULT NULL'],
        ];

        foreach ($columns as [$table, $column, $definition]) {
            self::ensureColumnExists($pdo, $table, $column, $definition);
        }
    }

    private static function ensureColumnExists(PDO $pdo, string $table, string $column, string $definition): void
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $statement->execute([$table, $column]);

        if ((int) $statement->fetchColumn() === 0) {
            $pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s', $table, $definition));
        }
    }

    private static function ensureDefaultRooms(): void
    {
        $pdo = Database::connection();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM rooms')->fetchColumn();

        if ($count > 0) {
            return;
        }

        $rooms = [
            [
                'A101',
                'Deluxe King Room',
                'Deluxe',
                'Spacious king room with city view, premium bedding, and complimentary breakfast.',
                145,
                2,
                json_encode(['King bed', 'Wi-Fi', 'Smart TV', 'Breakfast'], JSON_THROW_ON_ERROR),
                '',
            ],
            [
                'B204',
                'Executive Twin Room',
                'Executive',
                'Twin-room setup designed for business travelers with work desk and lounge access.',
                165,
                2,
                json_encode(['Twin beds', 'Wi-Fi', 'Work desk', 'Lounge access'], JSON_THROW_ON_ERROR),
                '',
            ],
            [
                'C307',
                'Family Suite',
                'Suite',
                'Large family suite with connected living area and extra sleeping arrangements.',
                240,
                4,
                json_encode(['2 bedrooms', 'Mini kitchen', 'Wi-Fi', 'Room service'], JSON_THROW_ON_ERROR),
                '',
            ],
            [
                'D409',
                'Panorama Premium Suite',
                'Premium',
                'Premium suite with skyline panorama, private lounge corner, and late checkout.',
                320,
                3,
                json_encode(['King bed', 'Panorama view', 'Late checkout', 'Premium bath'], JSON_THROW_ON_ERROR),
                '',
            ],
        ];

        $statement = $pdo->prepare(
            'INSERT INTO rooms (room_number, name, room_type, description, price_per_night, capacity, amenities, image, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, true)'
        );

        foreach ($rooms as $room) {
            $statement->execute($room);
        }
    }

    private static function ensureAdminAccount(): void
    {
        $email = strtolower(trim((string) Env::get('ADMIN_EMAIL', '')));
        $password = (string) Env::get('ADMIN_PASSWORD', '');
        $name = (string) Env::get('ADMIN_NAME', 'Platform Admin');

        if ($email === '' || $password === '') {
            return;
        }

        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();

        if (!$user) {
            $insert = $pdo->prepare(
                'INSERT INTO users (name, email, password, phone, role, default_address) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_BCRYPT),
                '',
                'admin',
                null,
            ]);
            return;
        }

        if (($user['role'] ?? 'user') !== 'admin') {
            $update = $pdo->prepare("UPDATE users SET role = 'admin', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $update->execute([$user['id']]);
        }
    }
}
