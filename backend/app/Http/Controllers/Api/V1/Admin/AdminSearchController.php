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
    public function customers(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'in:active,pending,suspended'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $customers = User::query()->where('role', UserRole::Customer->value)
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['q'] ?? null, fn ($query, $search) => $query->where(fn ($inner) => $inner->where('name', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%')))
            ->withCount(['bookings', 'reviews'])->latest()->paginate((int) ($data['per_page'] ?? 20));

        return response()->json(['data' => $customers]);
    }

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
            'attended_events' => $customer->bookings()->whereIn('status', ['checked_in', 'completed'])->with('event:id,ulid,title,starts_at')->latest('booked_at')->limit(20)->get(['ulid', 'event_id', 'status', 'booked_at']),
            'reviews' => $customer->reviews()->with('event:id,ulid,title')->latest('reviewed_at')->limit(20)->get(['ulid', 'event_id', 'rating', 'comment', 'status', 'reviewed_at']),
        ]]);
    }
}
