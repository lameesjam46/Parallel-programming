<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\CartItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $item;

    /**
     * استقبال البيانات الضرورية للمهمة
     */
    public function __construct(User $user, CartItem $item)
    {
        $this->user = $user;
        $this->item = $item;
    }

    /**
     * تنفيذ المهمة (منطق المعالجة بالتوازي)
     */
    public function handle()
    {
        try {
            DB::transaction(function () {
                $product = Product::where('id', $this->item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product || $product->stock < $this->item->quantity) {
                    throw new \Exception("المخزون غير كافٍ للمنتج: " . ($product->name ?? 'ID: '.$this->item->product_id));
                }

                $product->decrement('stock', $this->item->quantity);

                Order::create([
                    'user_id'           => $this->user->id,
                    'product_id'        => $product->id,
                    'quantity'          => $this->item->quantity,
                    'total_price'       => $product->price * $this->item->quantity,
                    'method'            => 'card',
                    'confirmation_code' => 'CONF-' . Str::upper(Str::random(8)),
                    'status'            => 'confirmed'
                ]);

                $this->item->delete();
            });

        } catch (\Exception $e) {
            throw $e;
        }
    }
}
