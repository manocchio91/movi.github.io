<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reservations\TransitionReservation;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizerReservationIndexRequest;
use App\Http\Requests\TransitionReservationRequest;
use App\Http\Resources\PreOrderResource;
use App\Http\Resources\ReservationResource;
use App\Models\ActivityService;
use App\Models\Reservation;
use App\Services\Reservations\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrganizerReservationController extends Controller
{
    public function index(OrganizerReservationIndexRequest $request): JsonResponse
    {
        return ReservationResource::collection($this->query($request)->latest('starts_at')->paginate($request->integer('per_page', 50)))->response();
    }

    public function show(Reservation $reservation): JsonResponse
    {
        $this->authorize('view', $reservation);

        $reservation->load(['activity.municipality', 'service', 'user', 'review', 'preorder.items']);
        $data = ReservationResource::make($reservation)->resolve();
        $data['preorder'] = $reservation->preorder ? PreOrderResource::make($reservation->preorder)->resolve() : null;

        return response()->json(['data' => $data]);
    }

    public function transition(TransitionReservationRequest $request, Reservation $reservation, TransitionReservation $transition): JsonResponse
    {
        $this->authorize('manageAttendance', $reservation);

        return response()->json(['data' => ReservationResource::make($transition->handle($request->user(), $reservation, ReservationStatus::from($request->validated('status'))))]);
    }

    public function dashboard(AvailabilityService $availability): JsonResponse
    {
        $base = $this->ownedQuery();
        $today = CarbonImmutable::today();
        $summary = function (CarbonImmutable $date) use ($base, $availability): array {
            $day = (clone $base)->whereDate('starts_at', $date)->get();
            $confirmed = $day->where('status', ReservationStatus::Confirmed);
            $services = ActivityService::query()->whereHas('activity', fn (Builder $q) => $q->where('organizer_profile_id', request()->user()->organizerProfile->id))->get();
            $capacity = $services->sum(fn (ActivityService $service): int => (int) collect($availability->forDate($service, $date))->sum('capacity'));
            $covers = (int) $confirmed->sum('party_size');

            return ['date' => $date->toDateString(), 'reservations' => $day->count(), 'covers' => (int) $confirmed->sum('party_size'),
                'capacity' => $capacity, 'available' => max(0, $capacity - $covers), 'critical' => $covers > $capacity,
                'cancellations' => $day->where('status', ReservationStatus::Cancelled)->count(), 'no_show' => $day->where('status', ReservationStatus::NoShow)->count()];
        };

        /** @var Collection<int, Reservation> $preorderReservations */
        $preorderReservations = (clone $base)->whereDate('starts_at', $today)->whereHas('preorder', fn (Builder $q) => $q->where('status', 'confirmed'))->with(['service', 'preorder.items'])->get();
        $preparation = $preorderReservations->groupBy(fn (Reservation $reservation): string => $reservation->service->name.' '.$reservation->starts_at->format('H:i'))->map(fn ($rows) => $rows->pluck('preorder')->flatMap->items->groupBy('item_name_snapshot')->map(fn ($items, $name) => ['name' => $name, 'quantity' => $items->sum('quantity')])->values());

        return response()->json(['data' => ['today' => $summary($today), 'next_days' => collect(range(1, 7))->map(fn (int $day) => $summary($today->addDays($day))),
            'upcoming' => ReservationResource::collection((clone $base)->where('status', ReservationStatus::Confirmed)->where('starts_at', '>=', now())->with(['activity', 'service', 'user', 'preorder.items'])->oldest('starts_at')->limit(10)->get()),
            'preorder_preparation' => $preparation]]);
    }

    public function customers(): JsonResponse
    {
        $profile = request()->user()->organizerProfile;
        abort_unless($profile !== null, 403);
        $rows = DB::table('reservations')->join('activities', 'activities.id', '=', 'reservations.activity_id')->join('users', 'users.id', '=', 'reservations.user_id')
            ->where('activities.organizer_profile_id', $profile->id)->groupBy('users.id', 'users.ulid', 'users.name')->select(['users.ulid', 'users.name'])
            ->selectRaw('COUNT(reservations.id) as reservations_count, MAX(reservations.starts_at) as last_reservation_at')
            ->selectRaw("SUM(CASE WHEN reservations.status = 'cancelled' THEN 1 ELSE 0 END) as cancellations")
            ->selectRaw("SUM(CASE WHEN reservations.status = 'no_show' THEN 1 ELSE 0 END) as no_shows")->orderByDesc('last_reservation_at')->paginate(50);
        $rows->through(fn (object $row): array => [
            'ulid' => $row->ulid,
            'name' => $row->name,
            'reservations_count' => (int) $row->reservations_count,
            'last_reservation_at' => $row->last_reservation_at,
            'cancellations' => (int) $row->cancellations,
            'no_shows' => (int) $row->no_shows,
            'repeat_customer' => (int) $row->reservations_count > 1,
        ]);

        return response()->json($rows);
    }

    public function analytics(): JsonResponse
    {
        $query = $this->ownedQuery();
        $total = (clone $query)->count();
        $covers = (int) (clone $query)->sum('party_size');
        $count = fn (ReservationStatus $status): int => (clone $query)->where('status', $status)->count();
        $cancelled = $count(ReservationStatus::Cancelled);
        $completed = $count(ReservationStatus::Completed);
        $noShow = $count(ReservationStatus::NoShow);
        $repeat = DB::query()->fromSub((clone $query)->select('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1'), 'repeat_customers')->count();

        return response()->json(['data' => ['reservations' => $total, 'covers' => $covers, 'cancellations' => $cancelled, 'completed' => $completed, 'no_show' => $noShow,
            'cancellation_rate' => $total ? round($cancelled / $total * 100, 1) : 0, 'no_show_rate' => $total ? round($noShow / $total * 100, 1) : 0,
            'repeat_customers' => $repeat, 'average_party_size' => $total ? round($covers / $total, 1) : 0]]);
    }

    private function ownedQuery(): Builder
    {
        $profile = request()->user()->organizerProfile;
        abort_unless($profile !== null, 403);

        return Reservation::query()->whereHas('activity', fn (Builder $q) => $q->where('organizer_profile_id', $profile->id));
    }

    private function query(OrganizerReservationIndexRequest $request): Builder
    {
        return $this->ownedQuery()->with(['activity.municipality', 'service', 'user', 'review', 'preorder.items'])
            ->when($request->date === 'today', fn (Builder $q) => $q->whereDate('starts_at', today()))
            ->when($request->date === 'tomorrow', fn (Builder $q) => $q->whereDate('starts_at', today()->addDay()))
            ->when($request->from, fn (Builder $q, string $v) => $q->whereDate('starts_at', '>=', $v))->when($request->to, fn (Builder $q, string $v) => $q->whereDate('starts_at', '<=', $v))
            ->when($request->status, fn (Builder $q, string $v) => $q->where('status', $v))
            ->when($request->activity, fn (Builder $q, string $v) => $q->whereHas('activity', fn (Builder $a) => $a->where('ulid', $v)))
            ->when($request->service, fn (Builder $q, string $v) => $q->whereHas('service', fn (Builder $s) => $s->where('ulid', $v)))
            ->when($request->search, fn (Builder $q, string $v) => $q->whereHas('user', fn (Builder $u) => $u->where('name', 'like', '%'.addcslashes($v, '%_').'%')));
    }
}
