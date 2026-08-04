<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']])['q'];
        $like = '%'.$q.'%';

        return response()->json(['data' => [
            'organizers' => OrganizerProfile::query()->where(fn ($query) => $query->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like))->limit(10)->get(['ulid', 'name', 'verification_status']),
            'customers' => User::query()->where('role', UserRole::Customer->value)->where(fn ($query) => $query->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like))->limit(10)->get(['ulid', 'name', 'email', 'status']),
            'events' => Event::query()->where('title', 'ilike', $like)->limit(10)->get(['ulid', 'title', 'status']),
        ]]);
    }

    public function customer(User $customer): JsonResponse
    {
        abort_unless($customer->role === UserRole::Customer, 404);
        $customer->loadCount(['bookings', 'favorites', 'reviews', 'followedOrganizers']);

        return response()->json(['data' => [
            ...$customer->only(['ulid', 'name', 'email', 'status', 'created_at']),
            'bookings_count' => $customer->bookings_count,
            'favorites_count' => $customer->favorites_count,
            'reviews_count' => $customer->reviews_count,
            'followed_organizers_count' => $customer->followed_organizers_count,
            'recent_bookings' => $customer->bookings()->with('event:id,ulid,title')->latest('booked_at')->limit(10)->get(['ulid', 'event_id', 'status', 'booking_code', 'booked_at']),
        ]]);
    }
}
