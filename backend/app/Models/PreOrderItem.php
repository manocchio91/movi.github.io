<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreOrderItem extends Model
{
    protected $table = 'preorder_items';

    protected $fillable = ['preorder_id', 'menu_item_id', 'quantity', 'unit_price_snapshot', 'item_name_snapshot', 'subtotal'];

    public function preorder(): BelongsTo
    {
        return $this->belongsTo(PreOrder::class, 'preorder_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
