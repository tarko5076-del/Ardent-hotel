<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Repositories\RoomRepository;
use App\Services\Auth;
use App\Support\Helpers;
use DateTimeImmutable;
use PDOException;

final class RoomController
{
    private const ROOM_BOOKING_STATUSES = ['Pending', 'Confirmed', 'Cancelled', 'Completed'];
    private const CANCELLABLE_STATUSES = ['Pending', 'Confirmed'];

    public function __construct(
        private readonly RoomRepository $rooms,
        private readonly Auth $auth
    ) {
    }

    public function list(Request $request): void
    {
        JsonResponse::send([
            'success' => true,
            'data' => $this->rooms->findAll([
                'search' => $request->query('search'),
                'roomType' => $request->query('roomType'),
                'guests' => $request->query('guests'),
                'checkIn' => $request->query('checkIn'),
                'checkOut' => $request->query('checkOut'),
                'activeOnly' => $request->query('activeOnly'),
            ]),
        ]);
    }

    public function show(Request $request): void
    {
        $room = $this->rooms->findById((string) $request->route('roomId'));

        if ($room === null) {
            throw new HttpException('Room not found.', 404);
        }

        JsonResponse::send([
            'success' => true,
            'data' => $room,
        ]);
    }

    public function create(Request $request): void
    {
        $user = $this->auth->user($request);
        $this->auth->requireRole($user, 'admin');

        $roomNumber = trim((string) $request->body('roomNumber', ''));
        $name = trim((string) $request->body('name', ''));
        $roomType = trim((string) $request->body('roomType', ''));
        $description = trim((string) $request->body('description', ''));
        $pricePerNight = (float) $request->body('pricePerNight', 0);
        $capacity = (int) $request->body('capacity', 0);
        $amenities = $this->sanitizeAmenities($request->body('amenities'));
        $image = trim((string) $request->body('image', ''));
        $isActive = !in_array($request->body('isActive', true), [false, 'false'], true);

        if ($roomNumber === '' || $name === '' || $roomType === '' || $description === '') {
            throw new HttpException('Room number, name, room type, and description are required.', 400);
        }

        if ($pricePerNight <= 0) {
            throw new HttpException('Price per night must be a valid number greater than zero.', 400);
        }

        if ($capacity <= 0) {
            throw new HttpException('Capacity must be a valid whole number greater than zero.', 400);
        }

        try {
            $room = $this->rooms->create([
                'roomNumber' => $roomNumber,
                'name' => $name,
                'roomType' => $roomType,
                'description' => $description,
                'pricePerNight' => $pricePerNight,
                'capacity' => $capacity,
                'amenities' => $amenities,
                'image' => $image,
                'isActive' => $isActive,
            ]);

            JsonResponse::send([
                'success' => true,
                'message' => 'Room created successfully.',
                'data' => $room,
            ], 201);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new HttpException('A room with this room number already exists.', 409);
            }

            throw $exception;
        }
    }

    public function book(Request $request): void
    {
        $user = $this->auth->user($request);
        $roomId = (string) $request->body('roomId', $request->body('id', ''));
        $guests = (int) $request->body('guests', 1);
        $contactPhone = trim((string) $request->body('contactPhone', $user['phone'] ?? ''));
        $specialRequest = trim((string) $request->body('specialRequest', ''));
        [$checkInDate, $checkOutDate, $checkIn, $checkOut] = $this->validateDates(
            (string) $request->body('checkIn', ''),
            (string) $request->body('checkOut', '')
        );

        if ($roomId === '') {
            throw new HttpException('Room id is required.', 400);
        }

        if ($guests <= 0) {
            throw new HttpException('Guests must be a valid whole number greater than zero.', 400);
        }

        $room = $this->rooms->findById($roomId);

        if ($room === null || !$room['is_active']) {
            throw new HttpException('Room not found or unavailable.', 404);
        }

        if ($guests > (int) $room['capacity']) {
            throw new HttpException('This room supports up to ' . $room['capacity'] . ' guest(s).', 400);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($this->rooms->hasBookingConflict($pdo, (int) $room['id'], $checkIn, $checkOut)) {
                throw new HttpException('This room is already booked for the selected dates.', 400);
            }

            $nights = (int) $checkOutDate->diff($checkInDate)->format('%a');
            $bookingId = $this->rooms->createBooking($pdo, [
                'userId' => (int) $user['id'],
                'roomId' => (int) $room['id'],
                'checkIn' => $checkIn,
                'checkOut' => $checkOut,
                'guests' => $guests,
                'totalAmount' => (float) $room['price_per_night'] * $nights,
                'contactPhone' => $contactPhone,
                'specialRequest' => $specialRequest,
            ]);

            $pdo->commit();

            JsonResponse::send([
                'success' => true,
                'message' => 'Room booking created successfully.',
                'data' => $this->rooms->findBookingById($bookingId),
            ], 201);
        } catch (\Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($throwable instanceof HttpException) {
                throw $throwable;
            }

            throw new HttpException('Unable to create your booking right now.', 400);
        }
    }

    public function mine(Request $request): void
    {
        $user = $this->auth->user($request);

        JsonResponse::send([
            'success' => true,
            'data' => $this->rooms->findBookingsByUserId((int) $user['id']),
        ]);
    }

    public function cancel(Request $request): void
    {
        $user = $this->auth->user($request);
        $bookingId = (string) $request->route('bookingId', $request->body('bookingId', ''));

        if ($bookingId === '') {
            throw new HttpException('Booking id is required.', 400);
        }

        $booking = $this->rooms->findBookingById($bookingId);

        if ($booking === null) {
            throw new HttpException('Booking not found.', 404);
        }

        if ((int) $booking['user_id'] !== (int) $user['id']) {
            throw new HttpException('You do not have permission to cancel this booking.', 403);
        }

        if (!in_array($booking['status'], self::CANCELLABLE_STATUSES, true)) {
            throw new HttpException('This booking can no longer be cancelled.', 400);
        }

        JsonResponse::send([
            'success' => true,
            'message' => 'Room booking cancelled successfully.',
            'data' => $this->rooms->updateBookingStatus($bookingId, 'Cancelled', 'Cancelled by guest'),
        ]);
    }

    public function listAdmin(Request $request): void
    {
        $user = $this->auth->user($request);
        $this->auth->requireRole($user, 'admin');

        JsonResponse::send([
            'success' => true,
            'data' => $this->rooms->findAllBookings(),
        ]);
    }

    public function updateStatus(Request $request): void
    {
        $user = $this->auth->user($request);
        $this->auth->requireRole($user, 'admin');

        $bookingId = (string) $request->route('bookingId', $request->body('bookingId', ''));
        $status = trim((string) $request->body('status', ''));
        $adminNote = trim((string) $request->body('adminNote', ''));

        if ($bookingId === '' || $status === '') {
            throw new HttpException('Booking id and status are required.', 400);
        }

        if (!in_array($status, self::ROOM_BOOKING_STATUSES, true)) {
            throw new HttpException('Invalid booking status.', 400);
        }

        $booking = $this->rooms->findBookingById($bookingId);

        if ($booking === null) {
            throw new HttpException('Booking not found.', 404);
        }

        if (in_array($status, ['Pending', 'Confirmed'], true)) {
            $pdo = Database::connection();
            if ($this->rooms->hasBookingConflict($pdo, (int) $booking['room_id'], $booking['check_in'], $booking['check_out'], (int) $booking['id'])) {
                throw new HttpException(
                    'Unable to mark this booking active because the room is already booked for these dates.',
                    400
                );
            }
        }

        JsonResponse::send([
            'success' => true,
            'message' => 'Room booking status updated successfully.',
            'data' => $this->rooms->updateBookingStatus($bookingId, $status, $adminNote),
        ]);
    }

    private function sanitizeAmenities(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn ($item) => trim((string) $item), $value)));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }

    private function validateDates(string $checkInValue, string $checkOutValue): array
    {
        $checkInDate = DateTimeImmutable::createFromFormat('Y-m-d', substr($checkInValue, 0, 10)) ?: null;
        $checkOutDate = DateTimeImmutable::createFromFormat('Y-m-d', substr($checkOutValue, 0, 10)) ?: null;

        if (!$checkInDate || !$checkOutDate) {
            throw new HttpException('Valid check-in and check-out dates are required.', 400);
        }

        if ($checkOutDate <= $checkInDate) {
            throw new HttpException('Check-out date must be after check-in date.', 400);
        }

        $today = new DateTimeImmutable('today');
        if ($checkInDate < $today) {
            throw new HttpException('Check-in date cannot be in the past.', 400);
        }

        return [$checkInDate, $checkOutDate, $checkInDate->format('Y-m-d'), $checkOutDate->format('Y-m-d')];
    }
}
