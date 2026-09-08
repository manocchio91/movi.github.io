<?php

namespace App\Actions\PreOrders;

use App\Models\MenuItem;
use App\Models\PreOrder;
use App\Models\Reservation;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpsertPreOrder
{
    public function __construct(private readonly AnalyticsRecorder $analytics) {}

    public function handle(Reservation $reservation, array $data): PreOrder
    {
        return DB::transaction(function () use ($reservation, $data): PreOrder {
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status->value !== 'confirmed' || ! $locked->service->preorder_enabled) {
                throw ValidationException::withMessages(['reservation' => ['La prenotazione non supporta il preordine.']]);
            }
            $deadline = $locked->starts_at->copy()->subMinutes($locked->service->preorder_edit_deadline_minutes ?? config('menu.preorder_edit_deadline_minutes'));
            if (now()->greaterThanOrEqualTo($deadline)) {
                throw ValidationException::withMessages(['reservation' => ['La deadline del preordine è trascorsa.']]);
            }
            $ids = collect($data['items'])->pluck('menu_item_ulid');
            $items = MenuItem::query()->whereIn('ulid', $ids)->where('active', true)->where('available', true)->whereHas('section.menu', fn ($q) => $q->whereKey($locked->service->preorder_menu_id)->where('status', 'published')->where('active', true))->get()->keyBy('ulid');
            if ($items->count() !== $ids->unique()->count()) {
                throw ValidationException::withMessages(['items' => ['Uno o più piatti non sono disponibili nel menu configurato.']]);
            }
            $preorder = PreOrder::query()->firstOrNew(['reservation_id' => $locked->id]);
            $created = ! $preorder->exists;
            $preorder->fill(['ulid' => $preorder->ulid ?: (string) Str::ulid(), 'status' => 'confirmed', 'notes' => $data['notes'] ?? null, 'locked_at' => $deadline, 'total_known_amount' => 0])->save();
            $preorder->items()->delete();
            $total = 0;
            foreach ($data['items'] as $row) {
                $item = $items[$row['menu_item_ulid']];
                $subtotal = $item->price_cents === null ? null : $item->price_cents * $row['quantity'];
                $total += $subtotal ?? 0;
                $preorder->items()->create(['menu_item_id' => $item->id, 'quantity' => $row['quantity'], 'unit_price_snapshot' => $item->price_cents, 'item_name_snapshot' => $item->name, 'subtotal' => $subtotal]);
            }
            $preorder->update(['total_known_amount' => $total]);
            if ($created) {
                $this->analytics->recordActivity('preorder_started', $locked->activity, $locked->user, $locked);
            }
            $this->analytics->recordActivity($created ? 'preorder_confirmed' : 'preorder_updated', $locked->activity, $locked->user, $locked);

            return $preorder->load('items.menuItem');
        });
    }
}
