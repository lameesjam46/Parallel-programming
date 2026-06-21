<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Product;
use App\Models\CartItem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class TestDataJsonSeeder extends Seeder
{
    public function run(): void
    {
        $products = [];
        for ($i = 1; $i <= 5; $i++) {
            $products[] = Product::updateOrCreate(
                ['id' => $i],
                [
                    'name' => "منتج تجريبي $i",
                    'description' => "وصف المنتج $i",
                    'price' => rand(50, 500),
                    'stock' => 100000
                ]
            );
        }

        $requests = [];


        for ($i = 1; $i <= 100; $i++) {
           
            $user = User::updateOrCreate(
                ['email' => "user$i@test.com"],
                [
                    'name' => "User $i",
                    'password' => Hash::make('password123'),
                ]
            );

            $randomProduct = $products[array_rand($products)];
            $quantity = rand(1, 3);

            CartItem::updateOrCreate(
                ['user_id' => $user->id, 'product_id' => $randomProduct->id],
                ['quantity' => $quantity]
            );

            $requests[] = [
                'user_id'    => $user->id,
                'product_id' => $randomProduct->id,
                'quantity'   => $quantity,
                'price'      => $randomProduct->price,
                'method'     => 'card'
            ];
        }
        $jsonData = json_encode([
            'is_test_batch' => true,
            'requests' => $requests
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $path = 'C:/xampp/apache/bin/test_data.json';

        try {
            File::put($path, $jsonData);
            $this->command->info(" تم إنشاء 100 مستخدم، وتعبئة سلالهم، وتوليد ملف JSON في: " . $path);
        } catch (\Exception $e) {
            $this->command->error(" فشل حفظ الملف. تأكدي من مسار الأباتشي: " . $e->getMessage());
        }
    }
}
