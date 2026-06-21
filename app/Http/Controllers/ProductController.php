<?php

namespace App\Http\Controllers;
use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\ProductService;
use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ProductController extends Controller
{
    protected $productService;

  

    public function store(Request $request)
    {


        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'stock' => 'required|integer'
        ]);

        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Product created successfully',
            'data' => $product
        ]);
    }


    public function index()
    {

        $products = Product::all();

        return response()->json([
            'status' => true,
            'data' => $products
        ]);
    }




public function getMostRequestedProducts(Request $request)
{
    if ($request->query('mode') === 'ideal') {

        $products = Cache::remember(
            'products:most_requested',
            3600,
            function () {

                return Product::join(
                    'orders',
                    'products.id',
                    '=',
                    'orders.product_id'
                )
                ->select(
                    'products.id',
                    'products.name',
                    'products.description',
                    'products.price',
                    'products.stock',
                    DB::raw('SUM(orders.quantity) as total_sales')
                )
                ->groupBy(
                    'products.id',
                    'products.name',
                    'products.description',
                    'products.price',
                    'products.stock'
                )
                ->orderBy('total_sales', 'desc')
                ->take(10)
                ->get();
            }
        );

        return response()->json([
            'status' => true,
            'mode' => 'Ideal (Redis Cache)',
            'data' => $products
        ]);
    }

    $products = Product::join(
        'orders',
        'products.id',
        '=',
        'orders.product_id'
    )
    ->select(
        'products.id',
        'products.name',
        'products.description',
        'products.price',
        'products.stock',
        DB::raw('SUM(orders.quantity) as total_sales')
    )
    ->groupBy(
        'products.id',
        'products.name',
        'products.description',
        'products.price',
        'products.stock'
    )
    ->orderBy('total_sales', 'desc')
    ->take(10)
    ->get();

    return response()->json([
        'status' => true,
        'mode' => 'Normal',
        'data' => $products
    ]);
}










public function placeOrder(Request $request, $mode = 'safe')
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        $userId    = cache()->increment('test_user_counter') % 100 ?: 100;
        $productId = $request->input('product_id');
        $quantity  = $request->input('quantity');

        try {
           
            if ($mode === 'unsafe') {
                
                $product = Product::findOrFail($productId);

                if ($product->stock < $quantity) {
                    throw new Exception("المخزون غير كافٍ");
                }

                usleep(200000); 

                $product->stock = $product->stock - $quantity;
                $product->save();

                Order::create([
                    'user_id'    => $userId,
                    'product_id' => $productId,
                    'quantity'   => $quantity,
                    'status'     => 'completed'
                ]);

                Cache::forget('products:most_requested');

                return response()->json([
                    'success' => true,
                    'mode'    => 'unsafe',
                    'message' => 'تم تنفيذ الطلب بدون حماية'
                ], 200);
            }

         $lock = Cache::lock("product:{$productId}");

         try {
              return $lock->block(0, function () use ($productId, $quantity, $userId) {
                
                 // return DB::transaction(function () use ($productId, $quantity, $userId) {
                    
                     $product = Product::findOrFail($productId);

                     if ($product->stock < $quantity) {
                         throw new Exception("المخزون غير كافٍ");
                     }
                     usleep(200000); 
                     $product->stock -= $quantity;
                     $product->save();
 
                     Order::create([
                         'user_id'    => $userId,
                         'product_id' => $productId,
                         'quantity'   => $quantity,
                         'status'     => 'completed'
                     ]);
 
                    Cache::forget('products:most_requested');
 
                     return response()->json([
                         'success' => true,
                         'mode'    => 'safe',
                         'message' => 'تم تنفيذ الطلب بأمان '  
                     ], 201);
                 // });
             });
 
         } catch (LockTimeoutException $e) {
              return response()->json([
                'success' => false ,
                 'message' => 'السيرفر مضغوط حالياً، تم إلغاء الطلب '
             ], 409);
         }
         } catch (Exception $e) {
             return response()->json([
                 'success' => false,
                 'message' => $e->getMessage()
              ], 400);
        } 
    }}  