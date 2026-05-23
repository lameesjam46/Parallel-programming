<?php

namespace App\Http\Controllers;

use App\Services\ProductService;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class ProductController extends Controller
{
    protected $productService;

    /*public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function index()
    {
        $products = $this->productService->listProducts();

        return ProductResource::collection($products);
    }*/


    public function store(Request $request)
    {


        // التحقق من البيانات
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'stock' => 'required|integer'
        ]);

        // إنشاء المنتج
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


        // جلب المنتجات
        $products = Product::all();

        return response()->json([
            'status' => true,
            'data' => $products
        ]);
    }
}
