<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Product;
use App\Models\CartItem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::updateOrCreate(
            ['id' => 1],
            [
                'name' => "منتج ",
                'description' => "مخزون قليل لإثبات الـ Race Condition",
                'price' => 100,
                'stock' => 10
            ]
        );

        $requests = [];

        for ($i = 1; $i <= 4; $i++) {
            $user = User::updateOrCreate(
                ['email' => "user$i@test.com"],
                [
                    'name' => "User $i",
                    'password' => Hash::make('password123'),
                ]
            );

            $quantity = 5;

            CartItem::updateOrCreate(
                ['user_id' => $user->id, 'product_id' => $product->id],
                ['quantity' => $quantity]
            );

            // إضافة البيانات للمصفوفة التي ستتحول لـ JSON
            $requests[] = [
                'user_id'    => $user->id,
                'product_id' => $product->id,
                'quantity'   => $quantity,
                'method'     => 'card'
            ];
        }

        $jsonData = json_encode([
            'is_test_batch' => true,
            'total_users' => 4,
            'requests' => $requests
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        
        $path = 'C:/xampp/apache/bin/test.json';

        try {
            File::put($path, $jsonData);
            $this->command->info(" تم تجهيز 4 مستخدمين بنجاح.");
            $this->command->info(" ملف الـ JSON جاهز في: " . $path);
        } catch (\Exception $e) {
            $this->command->error(" فشل حفظ الملف: " . $e->getMessage());
        }
    }
}
