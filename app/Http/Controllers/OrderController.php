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





    public function protectedCheckout(Request $request)
    {
        try {
            $user = User::findOrFail($request->user_id);

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







public function Checkout(Request $request)
    {
        $allRequests = $request->input('requests');
          $index = Cache::increment('checkout_test_index') - 1;
        if (!isset($allRequests[$index])) {
            return response()->json(['message' => 'Done'], 200);
        }
        $data = $allRequests[$index];
        try {
            DB::transaction(function () use ($data) {
                $product = Product::where('id', $data['product_id'])->lockForUpdate()->first();
                $product->decrement('stock', $data['quantity']);
                Order::create([
                    'user_id' => $data['user_id'],
                    'product_id' => $data['product_id'],
                    'quantity' => $data['quantity'],
                    'total_price' => $data['price'] * $data['quantity'],
                    'method' => $data['method'],
                    'confirmation_code' => 'TEST-' . Str::random(6),
                    'status' => 'confirmed'
                ]);
                DB::table('cart_items')
                ->where('user_id', $data['user_id'])
                    ->where('product_id', $data['product_id'])
                    ->delete();
            });

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }









    public function transactionDemo(Request $request)
{
    $mode = $request->query('mode', 'unsafe');
    $user = User::find($request->user_id);
    
    if (!$user) {
        return response()->json([
            'message' => 'User not found'
        ], 404);
    }

    try {
        if ($mode === 'safe') {
            DB::transaction(function () use ($user) {
                $cartItems = CartItem::where('user_id', $user->id)->get();
                foreach ($cartItems as $item) {
                    $product = Product::find($item->product_id);
                    
                    if (!$product || $product->stock < $item->quantity) {
                        throw new \Exception("المخزون غير كاف");
                    }
                    
                    $product->decrement('stock', $item->quantity);
                    
                    throw new \Exception("فشل أثناء إنشاء الطلب");
                    
                    Order::create([
                        'user_id' => $user->id,
                        'product_id' => $product->id,
                        'quantity' => $item->quantity,
                        'total_price' => $product->price * $item->quantity,
                        'method' => 'card',
                        'confirmation_code' => 'CONF-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8)),
                        'status' => 'confirmed'
                    ]);
                    $item->delete();
                }
        });
        } else {
            $cartItems = CartItem::where('user_id', $user->id)->get();
            foreach ($cartItems as $item) {
                $product = Product::find($item->product_id);
                
                if (!$product || $product->stock < $item->quantity) {
                    return response()->json([
                        'message' => 'المخزون غير كاف'
                    ], 400);
                }
                
                $product->decrement('stock', $item->quantity);
                
                throw new \Exception("فشل أثناء إنشاء الطلب");
                
                Order::create([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'quantity' => $item->quantity,
                    'total_price' => $product->price * $item->quantity,
                    'method' => 'card',
                    'confirmation_code' => 'CONF-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8)),
                    'status' => 'confirmed'
                ]);
                $item->delete();
            }
        }
    } catch (\Exception $e) {
        return response()->json([
            'mode' => $mode,
            'message' => $e->getMessage()
        ], 500);
    }

    return response()->json([
        'message' => 'Done'
    ]);
}
}
