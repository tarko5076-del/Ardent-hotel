<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Repositories\FavoriteRepository;
use App\Repositories\FoodRepository;
use App\Services\Auth;

final class FavoriteController
{
    public function __construct(
        private readonly FavoriteRepository $favorites,
        private readonly FoodRepository $foods,
        private readonly Auth $auth
    ) {
    }

    public function list(Request $request): void
    {
        $user = $this->auth->user($request);
        JsonResponse::send([
            'success' => true,
            ...$this->payload((int) $user['id']),
        ]);
    }

    public function create(Request $request): void
    {
        $user = $this->auth->user($request);
        $foodId = (string) $request->route('foodId', $request->body('foodId', $request->body('itemId', '')));

        if ($foodId === '') {
            throw new HttpException('Food item id is required.', 400);
        }

        $food = $this->foods->findById($foodId);

        if ($food === null) {
            throw new HttpException('Food item not found.', 404);
        }

        $this->favorites->add((int) $user['id'], $foodId);

        JsonResponse::send([
            'success' => true,
            'message' => $food['name'] . ' saved to your wishlist.',
            ...$this->payload((int) $user['id']),
        ]);
    }

    public function delete(Request $request): void
    {
        $user = $this->auth->user($request);
        $foodId = (string) $request->route('foodId', $request->body('foodId', $request->body('itemId', '')));

        if ($foodId === '') {
            throw new HttpException('Food item id is required.', 400);
        }

        $this->favorites->remove((int) $user['id'], $foodId);

        JsonResponse::send([
            'success' => true,
            'message' => 'Item removed from your wishlist.',
            ...$this->payload((int) $user['id']),
        ]);
    }

    private function payload(int $userId): array
    {
        $items = $this->favorites->listByUserId($userId);

        return [
            'data' => $items,
            'favoriteIds' => array_map(fn (array $item) => $item['_id'], $items),
            'totalFavorites' => count($items),
        ];
    }
}
