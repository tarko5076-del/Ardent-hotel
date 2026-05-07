<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Config\Env;
use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Repositories\CartRepository;
use App\Repositories\FoodRepository;
use App\Repositories\OrderRepository;
use App\Services\Auth;
use PDO;

final class OrderController
{
    private const VALID_STATUSES = ['Food Processing', 'Out for delivery', 'Delivered', 'Cancelled'];
    private const CANCELLABLE_STATUSES = ['Food Processing'];

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly CartRepository $cart,
        private readonly FoodRepository $foods,
        private readonly Auth $auth
    ) {
    }

    public function config(Request $request): void
    {
        JsonResponse::send([
            'success' => true,
            'paymentMethods' => [
                'cod' => true,
                'card' => $this->stripeSecretKey() !== '',
                'chapa' => $this->chapaSecretKey() !== '',
            ],
            'deliveryFee' => $this->deliveryFee(),
        ]);
    }

    public function place(Request $request): void
    {
        $user = $this->auth->user($request);
        $address = $request->body('address', []);
        $paymentMethod = strtoupper((string) $request->body('paymentMethod', 'COD'));

        $this->validateAddress($address);

        if (!in_array($paymentMethod, ['COD', 'CARD', 'CHAPA'], true)) {
            throw new HttpException('Invalid payment method.', 400);
        }

        if ($paymentMethod === 'CARD' && $this->stripeSecretKey() === '') {
            throw new HttpException('Card payments are not configured yet.', 400);
        }

        if ($paymentMethod === 'CHAPA' && $this->chapaSecretKey() === '') {
            throw new HttpException('Chapa payments are not configured yet.', 400);
        }

        $cartItems = $this->cart->getByUserId((int) $user['id']);

        if ($cartItems === []) {
            throw new HttpException('Your cart is empty.', 400);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $lockedItems = [];

            foreach ($cartItems as $cartItem) {
                $food = $this->foods->findByIdForUpdate($pdo, $cartItem['itemId']);

                if ($food === null) {
                    throw new HttpException('An item in your cart is no longer available.', 400);
                }

                if ((int) $food['stock_quantity'] < (int) $cartItem['quantity']) {
                    throw new HttpException($food['name'] . ' does not have enough stock.', 400);
                }

                $lockedItems[] = [
                    'food_id' => (int) $food['id'],
                    'name' => $food['name'],
                    'price' => (float) $food['price'],
                    'quantity' => (int) $cartItem['quantity'],
                    'image' => $food['image'],
                ];
            }

            $subtotal = array_reduce(
                $lockedItems,
                static fn (float $sum, array $item): float => $sum + ((float) $item['price'] * (int) $item['quantity']),
                0.0
            );
            $deliveryFee = $subtotal > 0 ? $this->deliveryFee() : 0.0;
            $amount = $subtotal + $deliveryFee;

            $orderId = $this->orders->createRecord($pdo, [
                'userId' => (int) $user['id'],
                'subtotal' => $subtotal,
                'deliveryFee' => $deliveryFee,
                'amount' => $amount,
                'address' => $address,
                'paymentMethod' => $paymentMethod,
            ]);

            foreach ($lockedItems as $item) {
                $this->orders->createItem($pdo, $orderId, $item);
                $statement = $pdo->prepare('UPDATE foods SET stock_quantity = stock_quantity - ? WHERE id = ?');
                $statement->execute([$item['quantity'], $item['food_id']]);
            }

            $sessionUrl = null;

            if ($paymentMethod === 'CARD') {
                $sessionUrl = $this->createStripeCheckoutSession($lockedItems, $deliveryFee, $orderId, (int) $user['id'], $address);
            } elseif ($paymentMethod === 'CHAPA') {
                $sessionUrl = $this->createChapaCheckoutSession($pdo, $lockedItems, $deliveryFee, $orderId, (int) $user['id'], $address, $amount);
            } else {
                $statement = $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?');
                $statement->execute([(int) $user['id']]);
            }

            $pdo->commit();

            JsonResponse::send([
                'success' => true,
                'message' => in_array($paymentMethod, ['CARD', 'CHAPA'], true)
                    ? 'Payment session created successfully.'
                    : 'Order placed successfully.',
                'orderId' => $orderId,
                'totals' => [
                    'subtotal' => $subtotal,
                    'deliveryFee' => $deliveryFee,
                    'amount' => $amount,
                ],
                'session_url' => $sessionUrl,
            ], 201);
        } catch (\Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($throwable instanceof HttpException) {
                throw $throwable;
            }

            throw new HttpException($throwable->getMessage() ?: 'Error placing order.', 400);
        }
    }

    public function verify(Request $request): void
    {
        $orderId = (string) $request->body('orderId', '');
        $success = $request->body('success');

        if ($orderId === '') {
            throw new HttpException('Order id is required.', 400);
        }

        $order = $this->orders->findById($orderId);

        if ($order === null) {
            throw new HttpException('Order not found.', 404);
        }

        if (($order['payment_method'] ?? '') === 'CHAPA') {
            if ($success === false || $success === 'false') {
                $this->orders->updatePaymentFailure($orderId);
                $this->cancelUnpaidOrder($orderId);

                JsonResponse::send([
                    'success' => false,
                    'message' => 'Payment cancelled.',
                ]);
            }

            if ($this->chapaMockCheckoutEnabled() && ($request->body('mock') === true || $request->body('mock') === 'true')) {
                $reference = (string) $request->body('tx_ref', '');

                if ($reference !== '' && hash_equals((string) ($order['payment_reference'] ?? ''), $reference)) {
                    $this->markOrderPaidAndReduceCart($order);

                    JsonResponse::send([
                        'success' => true,
                        'message' => 'Mock Chapa payment completed successfully.',
                    ]);
                }

                throw new HttpException('Invalid mock Chapa transaction reference.', 400);
            }

            $verification = $this->verifyChapaPayment($order);

            if ($verification['paid']) {
                $this->markOrderPaidAndReduceCart($order);

                JsonResponse::send([
                    'success' => true,
                    'message' => 'Payment verified successfully.',
                ]);
            }

            $this->orders->updatePaymentFailure($orderId);
            $this->cancelUnpaidOrder($orderId);

            JsonResponse::send([
                'success' => false,
                'message' => $verification['message'] ?: 'Payment was not completed.',
            ]);
        }

        if ($success === true || $success === 'true') {
            $this->markOrderPaidAndReduceCart($order);

            JsonResponse::send([
                'success' => true,
                'message' => 'Payment verified successfully.',
            ]);
        }

        $this->cancelUnpaidOrder($orderId);

        JsonResponse::send([
            'success' => false,
            'message' => 'Payment cancelled.',
        ]);
    }

    public function chapaWebhook(Request $request): void
    {
        $this->verifyChapaWebhookSignature($request);

        $body = $request->body();
        $reference = (string) (
            $body['tx_ref']
            ?? $body['trx_ref']
            ?? $body['reference']
            ?? ($body['data']['tx_ref'] ?? '')
        );

        if ($reference === '') {
            JsonResponse::send(['success' => true, 'message' => 'Webhook ignored.']);
        }

        $order = $this->orders->findByPaymentReference($reference);

        if ($order === null) {
            JsonResponse::send(['success' => true, 'message' => 'Webhook ignored.']);
        }

        $verification = $this->verifyChapaPayment($order);

        if ($verification['paid']) {
            $this->markOrderPaidAndReduceCart($order);
        } else {
            $this->orders->updatePaymentFailure((int) $order['id']);
        }

        JsonResponse::send(['success' => true, 'message' => 'Webhook processed.']);
    }

    public function mockChapaCheckout(Request $request): void
    {
        if (!$this->chapaMockCheckoutEnabled()) {
            throw new HttpException('Mock Chapa checkout is not enabled.', 404);
        }

        $orderId = (string) $request->query('orderId', '');
        $reference = (string) $request->query('tx_ref', '');
        $order = $orderId !== '' ? $this->orders->findById($orderId) : null;

        if ($order === null || !hash_equals((string) ($order['payment_reference'] ?? ''), $reference)) {
            throw new HttpException('Mock Chapa transaction not found.', 404);
        }

        $frontendUrl = $this->frontendReturnUrl($request);
        $successUrl = $frontendUrl . '/verify?provider=chapa&success=true&mock=true&orderId=' . rawurlencode($orderId) . '&tx_ref=' . rawurlencode($reference);
        $cancelUrl = $frontendUrl . '/verify?provider=chapa&success=false&mock=true&orderId=' . rawurlencode($orderId) . '&tx_ref=' . rawurlencode($reference);
        $orderNumber = htmlspecialchars((string) ($order['orderNumber'] ?? ('ORD-' . str_pad($orderId, 6, '0', STR_PAD_LEFT))), ENT_QUOTES, 'UTF-8');
        $amount = htmlspecialchars(number_format((float) ($order['amount'] ?? 0), 2), ENT_QUOTES, 'UTF-8');
        $currency = htmlspecialchars($this->chapaCurrency(), ENT_QUOTES, 'UTF-8');
        $safeReference = htmlspecialchars($reference, ENT_QUOTES, 'UTF-8');
        $safeSuccessUrl = htmlspecialchars($successUrl, ENT_QUOTES, 'UTF-8');
        $safeCancelUrl = htmlspecialchars($cancelUrl, ENT_QUOTES, 'UTF-8');

        header('Content-Type: text/html; charset=utf-8');
        echo <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chapa Test Checkout</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      font-family: Arial, sans-serif;
      background: #f4f8f6;
      color: #15211c;
    }
    main {
      width: min(440px, calc(100vw - 32px));
      padding: 28px;
      border: 1px solid #d7e8df;
      border-radius: 8px;
      background: #fff;
      box-shadow: 0 24px 70px rgba(21, 33, 28, 0.14);
    }
    .brand { color: #1dbf73; font-size: 28px; font-weight: 800; margin-bottom: 20px; }
    h1 { margin: 0 0 10px; font-size: 24px; }
    p { line-height: 1.6; color: #5c6b64; }
    dl { display: grid; grid-template-columns: 1fr auto; gap: 10px; margin: 24px 0; }
    dt { color: #687870; }
    dd { margin: 0; font-weight: 700; text-align: right; }
    .actions { display: grid; gap: 12px; }
    a {
      display: block;
      padding: 14px 16px;
      border-radius: 6px;
      text-align: center;
      text-decoration: none;
      font-weight: 700;
    }
    .pay { background: #1dbf73; color: #fff; }
    .cancel { border: 1px solid #d7e8df; color: #405148; }
  </style>
</head>
<body>
  <main>
    <div class="brand">Chapa</div>
    <h1>Test checkout</h1>
    <p>This is a local demo checkout. No real money is charged.</p>
    <dl>
      <dt>Order</dt>
      <dd>{$orderNumber}</dd>
      <dt>Amount</dt>
      <dd>{$currency} {$amount}</dd>
      <dt>Reference</dt>
      <dd>{$safeReference}</dd>
    </dl>
    <div class="actions">
      <a class="pay" href="{$safeSuccessUrl}">Complete test payment</a>
      <a class="cancel" href="{$safeCancelUrl}">Cancel payment</a>
    </div>
  </main>
</body>
</html>
HTML;
        exit;
    }

    public function mine(Request $request): void
    {
        $user = $this->auth->user($request);

        JsonResponse::send([
            'success' => true,
            'data' => $this->orders->findByUserId((int) $user['id']),
        ]);
    }

    public function cancel(Request $request): void
    {
        $user = $this->auth->user($request);
        $orderId = (string) $request->route('orderId', $request->body('orderId', ''));

        if ($orderId === '') {
            throw new HttpException('Order id is required.', 400);
        }

        JsonResponse::send([
            'success' => true,
            'message' => 'Order cancelled successfully.',
            'data' => $this->cancelExistingOrder($orderId, $user, ($user['role'] ?? 'user') === 'admin' ? 'admin' : 'user'),
        ]);
    }

    public function list(Request $request): void
    {
        $user = $this->auth->user($request);
        $this->auth->requireRole($user, 'admin');

        JsonResponse::send([
            'success' => true,
            'data' => $this->orders->findAll(),
        ]);
    }

    public function updateStatus(Request $request): void
    {
        $user = $this->auth->user($request);
        $this->auth->requireRole($user, 'admin');

        $orderId = (string) $request->route('orderId', $request->body('orderId', ''));
        $status = trim((string) $request->body('status', ''));

        if ($orderId === '' || $status === '') {
            throw new HttpException('Order id and status are required.', 400);
        }

        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new HttpException('Invalid order status.', 400);
        }

        if ($status === 'Cancelled') {
            JsonResponse::send([
                'success' => true,
                'message' => 'Order cancelled successfully.',
                'data' => $this->cancelExistingOrder($orderId, $user, 'admin'),
            ]);
        }

        $order = $this->orders->findById($orderId);

        if ($order === null) {
            throw new HttpException('Order not found.', 404);
        }

        JsonResponse::send([
            'success' => true,
            'message' => 'Order status updated successfully.',
            'data' => $this->orders->updateStatus($orderId, $status),
        ]);
    }

    private function validateAddress(mixed $address): void
    {
        if (!is_array($address)) {
            throw new HttpException('Delivery address is required.', 400);
        }

        foreach (['firstName', 'lastName', 'email', 'street', 'city', 'country', 'phone'] as $field) {
            if (trim((string) ($address[$field] ?? '')) === '') {
                throw new HttpException($field . ' is required.', 400);
            }
        }
    }

    private function cancelExistingOrder(int|string $orderId, array $user, string $actor): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $statement->execute([$orderId]);
            $order = $statement->fetch();

            if (!$order) {
                throw new HttpException('Order not found.', 404);
            }

            if ($actor !== 'admin' && (int) $order['user_id'] !== (int) $user['id']) {
                throw new HttpException('You do not have permission to cancel this order.', 403);
            }

            if (!$this->canCancel($order)) {
                $message = (bool) $order['payment']
                    ? 'Paid orders can no longer be cancelled automatically. Please contact support.'
                    : 'This order can no longer be cancelled.';
                throw new HttpException($message, 400);
            }

            foreach ($this->orders->findItemsForRestore($pdo, $orderId) as $item) {
                $restore = $pdo->prepare('UPDATE foods SET stock_quantity = stock_quantity + ? WHERE id = ?');
                $restore->execute([(int) $item['quantity'], (int) $item['food_id']]);
            }

            $update = $pdo->prepare(
                "UPDATE orders SET status = 'Cancelled', cancelled_at = CURRENT_TIMESTAMP, cancelled_by = ?, cancellation_reason = '', updated_at = CURRENT_TIMESTAMP WHERE id = ?"
            );
            $update->execute([$actor, $orderId]);

            $pdo->commit();

            return $this->orders->findById($orderId) ?? throw new HttpException('Order not found.', 404);
        } catch (\Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($throwable instanceof HttpException) {
                throw $throwable;
            }

            throw new HttpException('Unable to cancel this order.', 500);
        }
    }

    private function canCancel(array $order): bool
    {
        if (in_array($order['status'], ['Cancelled', 'Delivered'], true)) {
            return false;
        }

        if (!in_array($order['status'], self::CANCELLABLE_STATUSES, true)) {
            return false;
        }

        return !(bool) $order['payment'];
    }

    private function deliveryFee(): float
    {
        return (float) Env::get('DELIVERY_FEE', '2');
    }

    private function stripeSecretKey(): string
    {
        return trim((string) Env::get('STRIPE_SECRET_KEY', ''));
    }

    private function chapaSecretKey(): string
    {
        return trim((string) Env::get('CHAPA_SECRET_KEY', ''));
    }

    private function chapaCurrency(): string
    {
        return strtoupper(trim((string) Env::get('CHAPA_CURRENCY', 'ETB')) ?: 'ETB');
    }

    private function chapaWebhookSecret(): string
    {
        return trim((string) Env::get('CHAPA_WEBHOOK_SECRET', ''));
    }

    private function frontendReturnUrl(Request $request): string
    {
        $referer = (string) $request->header('referer', '');
        $scheme = parse_url($referer, PHP_URL_SCHEME);
        $host = parse_url($referer, PHP_URL_HOST);
        $port = parse_url($referer, PHP_URL_PORT);

        if (is_string($scheme) && is_string($host) && in_array($scheme, ['http', 'https'], true)) {
            return $scheme . '://' . $host . ($port ? ':' . $port : '');
        }

        return rtrim((string) Env::get('FRONTEND_URL', 'http://localhost:5173'), '/');
    }

    private function chapaMockCheckoutEnabled(): bool
    {
        return filter_var(Env::get('CHAPA_MOCK_CHECKOUT', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    private function markOrderPaidAndReduceCart(array $order): void
    {
        if ((bool) ($order['payment'] ?? false)) {
            return;
        }

        $this->orders->updatePayment((int) $order['id'], true);

        foreach ($order['items'] as $item) {
            $currentCartItem = $this->cart->findItem((int) $order['user_id'], (int) $item['food_id']);

            if ($currentCartItem === null) {
                continue;
            }

            $nextQuantity = (int) $currentCartItem['quantity'] - (int) $item['quantity'];

            if ($nextQuantity <= 0) {
                $this->cart->deleteItem((int) $order['user_id'], (int) $item['food_id']);
            } else {
                $this->cart->setQuantity((int) $order['user_id'], (int) $item['food_id'], $nextQuantity);
            }
        }
    }

    private function cancelUnpaidOrder(int|string $orderId): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare('SELECT payment FROM orders WHERE id = ? FOR UPDATE');
            $statement->execute([$orderId]);
            $order = $statement->fetch();

            if (!$order) {
                $pdo->commit();
                return;
            }

            if ((bool) $order['payment']) {
                $pdo->commit();
                return;
            }

            foreach ($this->orders->findItemsForRestore($pdo, $orderId) as $item) {
                $restore = $pdo->prepare('UPDATE foods SET stock_quantity = stock_quantity + ? WHERE id = ?');
                $restore->execute([(int) $item['quantity'], (int) $item['food_id']]);
            }

            $this->orders->delete($pdo, $orderId);
            $pdo->commit();
        } catch (\Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw new HttpException('Error verifying payment.', 500);
        }
    }

    private function createChapaCheckoutSession(
        PDO $connection,
        array $items,
        float $deliveryFee,
        int $orderId,
        int $userId,
        array $address,
        float $amount
    ): string {
        $frontendUrl = (string) Env::get('FRONTEND_URL', 'http://localhost:5173');
        $apiBaseUrl = (string) Env::get('API_BASE_URL', 'http://localhost:5001');
        $txRef = 'ardent-order-' . $orderId . '-' . bin2hex(random_bytes(4));

        $this->orders->updatePaymentReference($connection, $orderId, $txRef);

        if ($this->chapaMockCheckoutEnabled()) {
            return $apiBaseUrl . '/api/order/chapa/mock-checkout?orderId=' . rawurlencode((string) $orderId) . '&tx_ref=' . rawurlencode($txRef);
        }

        $payload = [
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $this->chapaCurrency(),
            'email' => (string) ($address['email'] ?? ''),
            'first_name' => (string) ($address['firstName'] ?? ''),
            'last_name' => (string) ($address['lastName'] ?? ''),
            'phone_number' => (string) ($address['phone'] ?? ''),
            'tx_ref' => $txRef,
            'callback_url' => $apiBaseUrl . '/api/order/chapa/webhook',
            'return_url' => $frontendUrl . '/verify?provider=chapa&success=true&orderId=' . $orderId,
            'customization' => [
                'title' => 'Ardent food order',
                'description' => 'Payment for order ORD-' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT),
            ],
            'meta' => [
                'order_id' => (string) $orderId,
                'user_id' => (string) $userId,
                'items' => array_map(
                    static fn (array $item): array => [
                        'name' => (string) $item['name'],
                        'quantity' => (int) $item['quantity'],
                    ],
                    $items
                ),
                'delivery_fee' => number_format($deliveryFee, 2, '.', ''),
            ],
        ];

        $decoded = $this->chapaRequest('POST', 'https://api.chapa.co/v1/transaction/initialize', $payload);
        $checkoutUrl = (string) ($decoded['data']['checkout_url'] ?? '');

        if (($decoded['status'] ?? '') !== 'success' || $checkoutUrl === '') {
            throw new HttpException((string) ($decoded['message'] ?? 'Unable to create the Chapa checkout session.'), 400);
        }

        return $checkoutUrl;
    }

    private function verifyChapaPayment(array $order): array
    {
        $reference = trim((string) ($order['payment_reference'] ?? ''));

        if ($reference === '') {
            return ['paid' => false, 'message' => 'Missing Chapa transaction reference.'];
        }

        $decoded = $this->chapaRequest(
            'GET',
            'https://api.chapa.co/v1/transaction/verify/' . rawurlencode($reference)
        );
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        $status = strtolower((string) ($data['status'] ?? $decoded['status'] ?? ''));
        $verifiedReference = (string) ($data['tx_ref'] ?? '');
        $verifiedCurrency = strtoupper((string) ($data['currency'] ?? ''));
        $verifiedAmount = (float) ($data['amount'] ?? 0);
        $expectedAmount = (float) ($order['amount'] ?? 0);

        if (
            $status === 'success'
            && hash_equals($reference, $verifiedReference)
            && $verifiedCurrency === $this->chapaCurrency()
            && abs($verifiedAmount - $expectedAmount) < 0.01
        ) {
            return ['paid' => true, 'message' => ''];
        }

        return [
            'paid' => false,
            'message' => (string) ($decoded['message'] ?? 'Chapa payment verification failed.'),
        ];
    }

    private function chapaRequest(string $method, string $url, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            throw new HttpException('Chapa payments are not available on this PHP setup.', 400);
        }

        $headers = [
            'Authorization: Bearer ' . $this->chapaSecretKey(),
            'Content-Type: application/json',
        ];
        $curl = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload ?? [], JSON_THROW_ON_ERROR);
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false || $error !== '') {
            throw new HttpException('Unable to contact Chapa right now.', 400);
        }

        $decoded = json_decode($response, true);

        if ($statusCode >= 400 || !is_array($decoded)) {
            $message = is_array($decoded) ? ($decoded['message'] ?? null) : null;
            throw new HttpException($message ?: 'Chapa request failed.', 400);
        }

        return $decoded;
    }

    private function verifyChapaWebhookSignature(Request $request): void
    {
        $secret = $this->chapaWebhookSecret();

        if ($secret === '') {
            return;
        }

        $signatures = array_filter([
            (string) $request->header('chapa-signature', ''),
            (string) $request->header('x-chapa-signature', ''),
        ]);

        $expectedPayloadSignature = hash_hmac('sha256', $request->rawBody(), $secret);
        $expectedSecretSignature = hash_hmac('sha256', $secret, $secret);

        foreach ($signatures as $signature) {
            if (
                hash_equals($expectedPayloadSignature, $signature)
                || hash_equals($expectedSecretSignature, $signature)
            ) {
                return;
            }
        }

        throw new HttpException('Invalid Chapa webhook signature.', 401);
    }

    private function createStripeCheckoutSession(array $items, float $deliveryFee, int $orderId, int $userId, array $address): string
    {
        if (!function_exists('curl_init')) {
            throw new HttpException('Card payments are not available on this PHP setup.', 400);
        }

        $frontendUrl = (string) Env::get('FRONTEND_URL', 'http://localhost:5173');
        $apiBaseUrl = (string) Env::get('API_BASE_URL', 'http://localhost:5001');
        $payload = [
            'mode' => 'payment',
            'success_url' => $frontendUrl . '/verify?success=true&orderId=' . $orderId,
            'cancel_url' => $frontendUrl . '/verify?success=false&orderId=' . $orderId,
            'customer_email' => (string) ($address['email'] ?? ''),
            'metadata[orderId]' => (string) $orderId,
            'metadata[userId]' => (string) $userId,
        ];

        foreach ($items as $index => $item) {
            $payload["line_items[$index][price_data][currency]"] = 'usd';
            $payload["line_items[$index][price_data][product_data][name]"] = $item['name'];
            $payload["line_items[$index][price_data][unit_amount]"] = (string) round((float) $item['price'] * 100);
            $payload["line_items[$index][quantity]"] = (string) $item['quantity'];

            if (($item['image'] ?? '') !== '') {
                $payload["line_items[$index][price_data][product_data][images][0]"] = $apiBaseUrl . '/images/' . $item['image'];
            }
        }

        if ($deliveryFee > 0) {
            $index = count($items);
            $payload["line_items[$index][price_data][currency]"] = 'usd';
            $payload["line_items[$index][price_data][product_data][name]"] = 'Delivery fee';
            $payload["line_items[$index][price_data][unit_amount]"] = (string) round($deliveryFee * 100);
            $payload["line_items[$index][quantity]"] = '1';
        }

        $curl = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->stripeSecretKey(),
            ],
            CURLOPT_POSTFIELDS => http_build_query($payload),
        ]);

        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false || $error !== '') {
            throw new HttpException('Unable to create the Stripe checkout session.', 400);
        }

        $decoded = json_decode($response, true);

        if ($statusCode >= 400 || !is_array($decoded) || empty($decoded['url'])) {
            $message = is_array($decoded) ? ($decoded['error']['message'] ?? null) : null;
            throw new HttpException($message ?: 'Unable to create the Stripe checkout session.', 400);
        }

        return (string) $decoded['url'];
    }
}
