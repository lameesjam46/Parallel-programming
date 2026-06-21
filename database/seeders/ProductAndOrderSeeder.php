<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;

class ProductAndOrderSeeder extends Seeder
{
    public function run(): void
    {
       
        $userIds = [];
        for ($i = 1; $i <= 100; $i++) {
            $user = User::firstOrCreate(['email' => "user{$i}@test.com"], [
                'name' => "مستخدم تجريبي رقم " . $i,
                'password' => bcrypt('123456'),
                'role' => 'user'
            ]);
            $userIds[] = $user->id;
        }

        $productsData = [];
        for ($i = 1; $i <= 500; $i++) {
            $productsData[] = [
                'name'        => 'منتج فاخر رقم ' . $i,
                'description' => 'وصف تجريبي للمنتج الفاخر رقم ' . $i,
                'price'       => rand(50, 500),
                'stock'       => rand(100, 1000), 
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }
        Product::insert($productsData);
        
        $products = Product::select('id', 'price')->get();

        $ordersData = [];
        for ($j = 1; $j <= 50000; $j++) {
            $randomProduct = $products->random();
            $randomUserId  = $userIds[array_rand($userIds)];
            $quantity      = rand(1, 5);

            $ordersData[] = [
                'user_id'     => $randomUserId,
                'product_id'  => $randomProduct->id,
                'quantity'    => $quantity,
                'status'      => 'completed', 
                'created_at'  => now(),
                'updated_at'  => now(),
            ];

            if ($j % 5000 === 0) {
                Order::insert($ordersData);
                $ordersData = []; 
            }
        }
    }
}