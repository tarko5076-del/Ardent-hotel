<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Repositories\FoodRepository;
use App\Services\Auth;
use App\Support\Helpers;

final class FoodController
{
    public function __construct(
        private readonly FoodRepository $foods,
        private readonly Auth $auth
    ) {
    }

    public function list(Request $request): void
    {
        JsonResponse::send([
            'success' => true,
            'data' => $this->foods->findAll([
                'category' => $request->query('category'),
                'search' => $request->query('search'),
                'availableOnly' => $request->query('availableOnly'),
            ]),
        ]);
    }

    public function show(Request $request): void
    {
        $food = $this->foods->findById((string) $request->route('id'));

        if ($food === null) {
            throw new HttpException('Food item not found.', 404);
        }

        JsonResponse::send([
            'success' => true,
            'data' => $food,
        ]);
    }

    public function create(Request $request): void
    {
        $user = $this->auth->user($request);
        $this->auth->requireRole($user, 'admin');

        $file = $request->file('image');
        if ($file === null || (int) ($file['error'] ?? 1) !== 0) {
            throw new HttpException('An image is required.', 400);
        }

        $storedFilename = $this->storeImage($file);

        try {
            $payload = $this->sanitizePayload($request, []);
            $food = $this->foods->create([
                ...$payload,
                'image' => $storedFilename,
            ]);

            JsonResponse::send([
                'success' => true,
                'message' => 'Food item created successfully.',
                'data' => $food,
            ], 201);
        } catch (\Throwable $throwable) {
            $this->removeImageIfPresent($storedFilename);
            throw $throwable;
        }
    }

    public function update(Request $request): void
    {
        $user = $this->auth->user($request);
        $this->auth->requireRole($user, 'admin');

        $id = (string) $request->route('id', $request->body('id', ''));
        $existingFood = $this->foods->findById($id);

        if ($existingFood === null) {
            throw new HttpException('Food item not found.', 404);
        }

        $file = $request->file('image');
        $storedFilename = $file ? $this->storeImage($file) : null;

        try {
            $payload = $this->sanitizePayload($request, $existingFood);
            $nextImage = $storedFilename ?: $existingFood['image'];

            $food = $this->foods->update($id, [
                ...$payload,
                'image' => $nextImage,
            ]);

            if ($storedFilename !== null && $existingFood['image'] !== $nextImage) {
                $this->removeImageIfPresent($existingFood['image']);
            }

            JsonResponse::send([
                'success' => true,
                'message' => 'Food item updated successfully.',
                'data' => $food,
            ]);
        } catch (\Throwable $throwable) {
            if ($storedFilename !== null) {
                $this->removeImageIfPresent($storedFilename);
            }

            throw $throwable;
        }
    }

    public function updateStock(Request $request): void
    {
        $user = $this->auth->user($request);
        $this->auth->requireRole($user, 'admin');

        $id = (string) ($request->route('id', $request->body('id', '')));

        if ($id === '') {
            throw new HttpException('Food item id is required.', 400);
        }

        $food = $this->foods->findById($id);

        if ($food === null) {
            throw new HttpException('Food item not found.', 404);
        }

        $stockQuantity = max(0, (int) $request->body('stockQuantity', $request->body('stock_quantity', 0)));
        $updated = $this->foods->updateStock($id, $stockQuantity);

        JsonResponse::send([
            'success' => true,
            'message' => 'Stock updated successfully.',
            'data' => $updated,
        ]);
    }

    public function delete(Request $request): void
    {
        $user = $this->auth->user($request);
        $this->auth->requireRole($user, 'admin');

        $id = (string) ($request->route('id', $request->body('id', '')));

        if ($id === '') {
            throw new HttpException('Food item id is required.', 400);
        }

        $food = $this->foods->findById($id);

        if ($food === null) {
            throw new HttpException('Food item not found.', 404);
        }

        $this->foods->delete($id);
        $this->removeImageIfPresent($food['image']);

        JsonResponse::send([
            'success' => true,
            'message' => 'Food item removed successfully.',
        ]);
    }

    private function sanitizePayload(Request $request, array $fallback): array
    {
        $name = trim((string) $request->body('name', $fallback['name'] ?? ''));
        $description = trim((string) $request->body('description', $fallback['description'] ?? ''));
        $price = (float) $request->body('price', $fallback['price'] ?? 0);
        $category = trim((string) $request->body('category', $fallback['category'] ?? ''));
        $stockQuantity = max(0, (int) $request->body(
            'stockQuantity',
            $request->body('stock_quantity', $fallback['stock_quantity'] ?? 0)
        ));

        if ($name === '' || $description === '' || $category === '') {
            throw new HttpException('Name, description, and category are required.', 400);
        }

        if ($price <= 0) {
            throw new HttpException('Price must be a valid number greater than zero.', 400);
        }

        if (!in_array($category, Helpers::FOOD_CATEGORIES, true)) {
            throw new HttpException('Please choose a valid category.', 400);
        }

        return [
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'category' => $category,
            'stockQuantity' => $stockQuantity,
        ];
    }

    private function storeImage(array $file): string
    {
        $mimeType = strtolower((string) ($file['type'] ?? ''));
        if (!str_starts_with($mimeType, 'image/')) {
            throw new HttpException('Only image uploads are allowed.', 400);
        }

        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new HttpException('The uploaded image must be 5MB or smaller.', 400);
        }

        $originalName = (string) ($file['name'] ?? 'image');
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBasename = preg_replace('/\s+/', '-', $basename) ?: 'image';
        $filename = time() . '-' . $safeBasename . ($extension !== '' ? '.' . $extension : '');
        $target = Helpers::uploadsDirectory() . DIRECTORY_SEPARATOR . $filename;
        $tmpPath = (string) ($file['tmp_name'] ?? '');

        if ($tmpPath === '') {
            throw new HttpException('Unable to read the uploaded image.', 400);
        }

        $moved = is_uploaded_file($tmpPath)
            ? move_uploaded_file($tmpPath, $target)
            : rename($tmpPath, $target);

        if (!$moved) {
            throw new HttpException('Unable to save the uploaded image.', 500);
        }

        return $filename;
    }

    private function removeImageIfPresent(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        $path = Helpers::uploadsDirectory() . DIRECTORY_SEPARATOR . $filename;

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
