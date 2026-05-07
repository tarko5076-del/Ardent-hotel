<?php

declare(strict_types=1);

spl_autoload_register(static function (string $className): void {
    $prefix = 'App\\';

    if (!str_starts_with($className, $prefix)) {
        return;
    }

    $relativeClass = substr($className, strlen($prefix));
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

use App\Config\Env;
use App\Database\Bootstrap;
use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Http\Router;
use App\Repositories\CartRepository;
use App\Repositories\FavoriteRepository;
use App\Repositories\FoodRepository;
use App\Repositories\OrderRepository;
use App\Repositories\RoomRepository;
use App\Repositories\UserRepository;
use App\Services\Auth;
use App\Controllers\CartController;
use App\Controllers\FavoriteController;
use App\Controllers\FoodController;
use App\Controllers\OrderController;
use App\Controllers\RoomController;
use App\Controllers\UserController;

Env::load(dirname(__DIR__) . '/.env');
Bootstrap::initialize();

$allowedOrigins = array_values(array_filter([
    Env::get('FRONTEND_URL', 'http://localhost:5173'),
    Env::get('ADMIN_URL', 'http://localhost:5174'),
]));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, chapa-signature, x-chapa-signature');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$userRepository = new UserRepository();
$foodRepository = new FoodRepository();
$cartRepository = new CartRepository();
$favoriteRepository = new FavoriteRepository($foodRepository);
$orderRepository = new OrderRepository();
$roomRepository = new RoomRepository();
$auth = new Auth($userRepository);

$userController = new UserController($userRepository, $auth);
$foodController = new FoodController($foodRepository, $auth);
$cartController = new CartController($cartRepository, $foodRepository, $auth);
$favoriteController = new FavoriteController($favoriteRepository, $foodRepository, $auth);
$orderController = new OrderController($orderRepository, $cartRepository, $foodRepository, $auth);
$roomController = new RoomController($roomRepository, $auth);

$router = new Router();

$router->get('/', [$userController, 'health']);

$router->post('/api/user/register', [$userController, 'register']);
$router->post('/api/user/login', [$userController, 'login']);
$router->get('/api/user/me', [$userController, 'me']);
$router->get('/api/user/profile', [$userController, 'me']);
$router->put('/api/user/me', [$userController, 'updateMe']);

$router->get('/api/food', [$foodController, 'list']);
$router->get('/api/food/list', [$foodController, 'list']);
$router->post('/api/food', [$foodController, 'create']);
$router->post('/api/food/add', [$foodController, 'create']);
$router->patch('/api/food/{id}/stock', [$foodController, 'updateStock']);
$router->post('/api/food/stock', [$foodController, 'updateStock']);
$router->put('/api/food/{id}', [$foodController, 'update']);
$router->post('/api/food/{id}/update', [$foodController, 'update']);
$router->delete('/api/food/{id}', [$foodController, 'delete']);
$router->post('/api/food/remove', [$foodController, 'delete']);
$router->get('/api/food/{id}', [$foodController, 'show']);

$router->get('/api/favorites', [$favoriteController, 'list']);
$router->post('/api/favorites/{foodId}', [$favoriteController, 'create']);
$router->delete('/api/favorites/{foodId}', [$favoriteController, 'delete']);

$router->get('/api/cart', [$cartController, 'get']);
$router->post('/api/cart/items', [$cartController, 'create']);
$router->patch('/api/cart/items/{itemId}', [$cartController, 'update']);
$router->delete('/api/cart/items/{itemId}', [$cartController, 'delete']);
$router->delete('/api/cart', [$cartController, 'clear']);
$router->post('/api/cart/sync', [$cartController, 'sync']);
$router->post('/api/cart/add', [$cartController, 'create']);
$router->post('/api/cart/remove', [$cartController, 'delete']);
$router->post('/api/cart/clear', [$cartController, 'clear']);
$router->post('/api/cart/removeAll', [$cartController, 'clear']);
$router->post('/api/cart/get', [$cartController, 'get']);

$router->get('/api/order/config', [$orderController, 'config']);
$router->post('/api/order/verify', [$orderController, 'verify']);
$router->get('/api/order/chapa/mock-checkout', [$orderController, 'mockChapaCheckout']);
$router->post('/api/order/chapa/webhook', [$orderController, 'chapaWebhook']);
$router->post('/api/order/place', [$orderController, 'place']);
$router->get('/api/order/mine', [$orderController, 'mine']);
$router->post('/api/order/{orderId}/cancel', [$orderController, 'cancel']);
$router->get('/api/order/userorders', [$orderController, 'mine']);
$router->post('/api/order/userorders', [$orderController, 'mine']);
$router->get('/api/order/list', [$orderController, 'list']);
$router->get('/api/order', [$orderController, 'list']);
$router->patch('/api/order/{orderId}/status', [$orderController, 'updateStatus']);
$router->put('/api/order/status', [$orderController, 'updateStatus']);
$router->post('/api/order/status', [$orderController, 'updateStatus']);

$router->get('/api/rooms/list', [$roomController, 'list']);
$router->get('/api/rooms/bookings/mine', [$roomController, 'mine']);
$router->post('/api/rooms/bookings/{bookingId}/cancel', [$roomController, 'cancel']);
$router->get('/api/rooms/bookings', [$roomController, 'listAdmin']);
$router->patch('/api/rooms/bookings/{bookingId}/status', [$roomController, 'updateStatus']);
$router->post('/api/rooms', [$roomController, 'create']);
$router->post('/api/rooms/book', [$roomController, 'book']);
$router->get('/api/rooms/{roomId}', [$roomController, 'show']);

try {
    $request = Request::capture();
    $router->dispatch($request);
} catch (HttpException $exception) {
    JsonResponse::send([
        'success' => false,
        'message' => $exception->getMessage(),
    ], $exception->getStatusCode());
} catch (Throwable $throwable) {
    error_log('Unhandled PHP backend error: ' . $throwable->getMessage());

    JsonResponse::send([
        'success' => false,
        'message' => 'Unexpected server error.',
    ], 500);
}
