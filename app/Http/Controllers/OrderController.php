<?php
namespace App\Http\Controllers;
use App\Models\Product;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class OrderController extends Controller
{
    // 1. حل مشكلة الـ Race Condition (Pessimistic Locking)
public function checkout(Request $request)
{
    return DB::transaction(function () use ($request) {
        $product = Product::where('id', $request->product_id)
                          ->lockForUpdate()
                          ->first();

        if (!$product || $product->stock < $request->quantity) {
            return response()->json([
                'status'  => false,
                'message' => 'Out of stock, sorry'
            ], 422);
        }

        $product->decrement('stock', $request->quantity);

        return response()->json([
            'status'          => true,
            'message'         => 'Purchase successful',
            'remaining_stock' => $product->fresh()->stock,
        ], 200);
    });
}
public function checkoutWithSemaphore(Request $request)
{
    $semaphoreKey  = 'checkout_semaphore_count';
    $maxConcurrent = 5;

    $currentCount = (int) Cache::get($semaphoreKey, 0);

    if ($currentCount >= $maxConcurrent) {
        return response()->json([
            'status'            => false,
            'message'           => 'Server busy, please try again later',
            'active_operations' => $currentCount,
            'max_allowed'       => $maxConcurrent,
        ], 503);
    }

    Cache::increment($semaphoreKey);

    try {
        return DB::transaction(function () use ($request) {
            $product = Product::where('id', $request->product_id)
                              ->lockForUpdate()
                              ->first();

            if (!$product || $product->stock < $request->quantity) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Out of stock',
                ], 422);
            }

            $product->decrement('stock', $request->quantity);

            return response()->json([
                'status'          => true,
                'message'         => 'Purchase successful',
                'remaining_stock' => $product->fresh()->stock,
            ], 200);
        });

    } finally {
        Cache::decrement($semaphoreKey);
    }
}











/**
     * تتبع الكود غير المحمي
     */
    public  function unprotectedCheckout(Request $request)
{
    $user =User::find($request->user_id);
    if (!$user) return response()->json(['error' => 'User not found'], 404);

    $cartItems =CartItem::where('user_id', $user->id)->get();

    foreach ($cartItems as $item) {
        $product =Product::find($item->product_id);
        if (!$product) continue;

        $stockInMemory = $product->stock;
        usleep(1000000); // تأخير 1 ثانية

        if ($stockInMemory >= $item->quantity) {
            DB::table('products')
                ->where('id', $product->id)
                ->update(['stock' => $stockInMemory - $item->quantity]);

            Order::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'quantity' => $item->quantity,
                'total_price' => $product->price * $item->quantity,
                'method' => 'card',
                'confirmation_code' => 'CONF-' . Str::random(6),
                'status' => 'confirmed'
            ]);
        }
    }


}





/**
     * تتبع الكود المحمي
     */
    public function protectedCheckout(Request $request)
    {
        try {
            $user = User::findOrFail($request->user_id);

            // المتطلب 8: استخدام المعاملات لضمان الذرية (Atomicity)
            return DB::transaction(function () use ($user) {
                $cartItems = CartItem::where('user_id', $user->id)->get();

                if ($cartItems->isEmpty()) {
                    throw new \Exception("Cart is empty");
                }

                foreach ($cartItems as $item) {
                    // المتطلب 7: تطبيق القفل التشاؤمي (lockForUpdate) لمنع التضارب
                    $product = Product::where('id', $item->product_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$product || $product->stock < $item->quantity) {
                        throw new \Exception("Insufficient Stock for Product: " . ($product->id));
                    }

                    // تحديث المخزون داخل قاعدة البيانات مباشرة (Atomic Update)
                   $product->decrement('stock', $item->quantity);

                    Order::create([
                        'user_id'           => $user->id,
                        'product_id'        => $product->id,
                        'quantity'          => $item->quantity,
                        'total_price'       => $product->price * $item->quantity,
                        'method'            => 'card',
                        'confirmation_code' => 'SAFE-' . Str::random(6),
                        'status'            => 'confirmed'
                    ]);
                }

                return response()->json(['message' => 'Done Protected'], 200);
            });

        } catch (\Exception $e) {
            // في حال الفشل، الـ Transaction ستقوم بعمل Rollback تلقائياً
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }










    public function processSequentially(Request $request)
    {

        $userId = $request->query('user_id', 1);

        $cartItems = CartItem::where('user_id', $userId)->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Cart is empty for User: ' . $userId]);
        }

        foreach ($cartItems as $item) {
            usleep(200000);
            $product = Product::find($item->product_id);

            if ($product && $product->stock >= $item->quantity) {
                $product->decrement('stock', $item->quantity);

                Order::create([
                    'user_id' => $userId,
                    'product_id' => $product->id,
                    'quantity' => $item->quantity,
                    'total_price' => $product->price * $item->quantity,
                    'method' => 'card',
                    'confirmation_code' => 'CONF-' . Str::upper(Str::random(8)),
                    'status' => 'confirmed'
                ]);
                $item->delete();
            }
        }


        return response()->json([
            'message' => 'Done Sequentially!',
            'user_id' => $userId,
            'items_processed' => count($cartItems)
        ]);
    }
}
