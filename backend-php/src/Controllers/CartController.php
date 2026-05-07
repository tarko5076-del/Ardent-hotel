<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Repositories\CartRepository;
use App\Repositories\FoodRepository;
use App\Services\Auth;

final class CartController
{
    public function __construct(
        private readonly CartRepository $cart,
        private readonly FoodRepository $foods,
        private readonly Auth $auth
    ) {
    }

    public function get(Request $request): void
    {
        $user = $this->auth->user($request);
        $items = $this->cart->getByUserId((int) $user['id']);

        JsonResponse::send([
            'success' => true,
            ...$this->cart->buildResponse($items),
        ]);
    }

    public function create(Request $request): void
    {
        $user = $this->auth->user($request);
        $itemId = (string) $request->body('itemId', $request->body('foodId', ''));
        $quantityToAdd = max(1, (int) $request->body('quantity', 1));

        if ($itemId === '') {
            throw new HttpException('Food item id is required.', 400);
        }

        $food = $this->foods->findById($itemId);

        if ($food === null) {
            throw new HttpException('Food item not found.', 404);
        }

        if (!$food['isAvailable']) {
            throw new HttpException('This item is currently sold out.', 400);
        }

        $existing = $this->cart->findItem((int) $user['id'], $itemId);
        $nextQuantity = (int) ($existing['quantity'] ?? 0) + $quantityToAdd;

        if ($nextQuantity > (int) $food['stock_quantity']) {
            throw new HttpException('Requested quantity exceeds available stock.', 400);
        }

        $this->cart->setQuantity((int) $user['id'], $itemId, $nextQuantity);
        $this->respondWithCart((int) $user['id'], 'Added to cart.');
    }

    public function update(Request $request): void
    {
        $user = $this->auth->user($request);
        $itemId = (string) $request->route('itemId', $request->body('itemId', $request->body('foodId', '')));
        $quantity = (int) $request->body('quantity', null);

        if ($itemId === '' || !is_numeric((string) $request->body('quantity', ''))) {
            throw new HttpException('Food item id and quantity are required.', 400);
        }

        if ($quantity <= 0) {
            $this->cart->setQuantity((int) $user['id'], $itemId, 0);
            $this->respondWithCart((int) $user['id'], 'Cart updated.');
            return;
        }

        $food = $this->foods->findById($itemId);

        if ($food === null) {
            throw new HttpException('Food item not found.', 404);
        }

        if ($quantity > (int) $food['stock_quantity']) {
            throw new HttpException('Requested quantity exceeds available stock.', 400);
        }

        $this->cart->setQuantity((int) $user['id'], $itemId, $quantity);
        $this->respondWithCart((int) $user['id'], 'Cart updated.');
    }

    public function delete(Request $request): void
    {
        $user = $this->auth->user($request);
        $itemId = (string) $request->route('itemId', $request->body('itemId', $request->body('foodId', '')));

        if ($itemId === '') {
            throw new HttpException('Food item id is required.', 400);
        }

        $existing = $this->cart->findItem((int) $user['id'], $itemId);

        if ($existing !== null) {
            $this->cart->setQuantity((int) $user['id'], $itemId, (int) $existing['quantity'] - 1);
        }

        $this->respondWithCart((int) $user['id'], 'Removed from cart.');
    }

    public function clear(Request $request): void
    {
        $user = $this->auth->user($request);
        $this->cart->clearByUserId((int) $user['id']);

        JsonResponse::send([
            'success' => true,
            'message' => 'Cart cleared successfully.',
            'cartItems' => [],
            'cartData' => new \stdClass(),
            'subtotal' => 0,
            'totalItems' => 0,
        ]);
    }

    public function sync(Request $request): void
    {
        $user = $this->auth->user($request);
        $items = $request->body('items');

        if (!is_array($items)) {
            throw new HttpException('A cart items object is required.', 400);
        }

        $issues = [];

        foreach ($items as $foodId => $rawQuantity) {
            $quantity = (int) $rawQuantity;

            if ($quantity <= 0) {
                continue;
            }

            $food = $this->foods->findById((string) $foodId);

            if ($food === null) {
                $issues[] = 'Item ' . $foodId . ' no longer exists and was skipped.';
                continue;
            }

            if (!$food['isAvailable']) {
                $issues[] = $food['name'] . ' is currently sold out and was skipped.';
                continue;
            }

            $existing = $this->cart->findItem((int) $user['id'], (string) $foodId);
            $existingQuantity = (int) ($existing['quantity'] ?? 0);
            $nextQuantity = min((int) $food['stock_quantity'], $existingQuantity + $quantity);

            if ($nextQuantity < $existingQuantity + $quantity) {
                $issues[] = $food['name'] . ' was capped at available stock.';
            }

            $this->cart->setQuantity((int) $user['id'], (string) $foodId, $nextQuantity);
        }

        $cartItems = $this->cart->getByUserId((int) $user['id']);

        JsonResponse::send([
            'success' => true,
            'message' => $issues === [] ? 'Cart synced successfully.' : 'Cart synced with a few adjustments.',
            'issues' => $issues,
            ...$this->cart->buildResponse($cartItems),
        ]);
    }

    private function respondWithCart(int $userId, string $message): void
    {
        $items = $this->cart->getByUserId($userId);

        JsonResponse::send([
            'success' => true,
            'message' => $message,
            ...$this->cart->buildResponse($items),
        ]);
    }
}
