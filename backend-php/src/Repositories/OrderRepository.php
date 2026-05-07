<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Support\Helpers;
use PDO;

final class OrderRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findByUserId(int $userId): array
    {
        $statement = $this->pdo->prepare(
            $this->ordersBaseQuery() . ' WHERE o.user_id = ? ORDER BY o.order_date DESC'
        );
        $statement->execute([$userId]);

        return $this->attachItemsAndNormalize($statement->fetchAll());
    }

    public function findAll(): array
    {
        $statement = $this->pdo->query($this->ordersBaseQuery() . ' ORDER BY o.order_date DESC');

        return $this->attachItemsAndNormalize($statement->fetchAll());
    }

    public function findById(int|string $id): ?array
    {
        $statement = $this->pdo->prepare($this->ordersBaseQuery() . ' WHERE o.id = ? LIMIT 1');
        $statement->execute([$id]);
        $orders = $this->attachItemsAndNormalize($statement->fetchAll());

        return $orders[0] ?? null;
    }

    public function createRecord(PDO $connection, array $payload): int
    {
        $statement = $connection->prepare(
            "INSERT INTO orders (user_id, subtotal, delivery_fee, amount, address, status, payment, payment_method) VALUES (?, ?, ?, ?, ?, 'Food Processing', false, ?)"
        );
        $statement->execute([
            $payload['userId'],
            $payload['subtotal'],
            $payload['deliveryFee'],
            $payload['amount'],
            json_encode($payload['address'], JSON_THROW_ON_ERROR),
            $payload['paymentMethod'],
        ]);

        return (int) $connection->lastInsertId();
    }

    public function createItem(PDO $connection, int $orderId, array $item): void
    {
        $statement = $connection->prepare(
            'INSERT INTO order_items (order_id, food_id, name, price, quantity, image) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $orderId,
            $item['food_id'],
            $item['name'],
            $item['price'],
            $item['quantity'],
            $item['image'] ?: null,
        ]);
    }

    public function updatePayment(int|string $id, bool $payment): void
    {
        $statement = $this->pdo->prepare('UPDATE orders SET payment = ?, payment_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $statement->execute([$payment ? 1 : 0, $payment ? 'paid' : 'pending', $id]);
    }

    public function updatePaymentReference(PDO $connection, int|string $id, string $reference): void
    {
        $statement = $connection->prepare(
            'UPDATE orders SET payment_reference = ?, payment_status = ? WHERE id = ?'
        );
        $statement->execute([$reference, 'pending', $id]);
    }

    public function updatePaymentFailure(int|string $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE orders SET payment = false, payment_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $statement->execute(['failed', $id]);
    }

    public function findByPaymentReference(string $reference): ?array
    {
        $statement = $this->pdo->prepare($this->ordersBaseQuery() . ' WHERE o.payment_reference = ? LIMIT 1');
        $statement->execute([$reference]);
        $orders = $this->attachItemsAndNormalize($statement->fetchAll());

        return $orders[0] ?? null;
    }

    public function updateStatus(int|string $id, string $status): array
    {
        $statement = $this->pdo->prepare(
            'UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $statement->execute([$status, $id]);

        return $this->findById($id) ?? throw new \RuntimeException('Unable to update order.');
    }

    public function delete(PDO $connection, int|string $id): void
    {
        $statement = $connection->prepare('DELETE FROM orders WHERE id = ?');
        $statement->execute([$id]);
    }

    public function findItemsForRestore(PDO $connection, int|string $orderId): array
    {
        $statement = $connection->prepare('SELECT food_id, quantity FROM order_items WHERE order_id = ?');
        $statement->execute([$orderId]);

        return $statement->fetchAll();
    }

    private function attachItemsAndNormalize(array $orders): array
    {
        if ($orders === []) {
            return [];
        }

        $orderIds = array_map(fn (array $order) => (int) $order['id'], $orders);
        $placeholders = implode(', ', array_fill(0, count($orderIds), '?'));
        $itemsStatement = $this->pdo->prepare(
            "SELECT id, order_id, food_id, name, price, quantity, image FROM order_items WHERE order_id IN ($placeholders) ORDER BY id ASC"
        );
        $itemsStatement->execute($orderIds);
        $items = $itemsStatement->fetchAll();

        $itemsByOrderId = [];
        foreach ($items as $item) {
            $itemsByOrderId[(int) $item['order_id']][] = [
                ...$item,
                'id' => (int) $item['food_id'],
                '_id' => (string) $item['food_id'],
                'food_id' => (int) $item['food_id'],
                'price' => (float) $item['price'],
                'quantity' => (int) $item['quantity'],
            ];
        }

        return array_map(function (array $order) use ($itemsByOrderId): array {
            $id = (int) $order['id'];
            $address = Helpers::parseJsonValue($order['address'] ?? null, []);

            return [
                ...$order,
                'id' => $id,
                '_id' => (string) $id,
                'orderNumber' => 'ORD-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                'amount' => (float) $order['amount'],
                'subtotal' => (float) ($order['subtotal'] ?? 0),
                'delivery_fee' => (float) ($order['delivery_fee'] ?? 0),
                'payment' => (bool) $order['payment'],
                'items' => $itemsByOrderId[$id] ?? [],
                'address' => $address,
            ];
        }, $orders);
    }

    private function ordersBaseQuery(): string
    {
        return 'SELECT o.*, u.name AS customer_name, u.email AS customer_email FROM orders o INNER JOIN users u ON u.id = o.user_id';
    }
}
