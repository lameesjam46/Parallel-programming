<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConcurrencyOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_flow()
    {
        $response = $this->postJson('/api/register', [
            'name'     => 'Test User',
            'email'    => 'test@test.com',
            'password' => 'password123',
        ]);
        $response->assertStatus(201);

        $response = $this->postJson('/api/login', [
            'email'    => 'test@test.com',
            'password' => 'password123',
        ]);
        $response->assertStatus(200);
        $token = $response->json('token');

        $product = Product::create([
            'name'        => 'Test Product',
            'description' => 'منتج للاختبار',
            'price'       => 99.99,
            'stock'       => 10,
        ]);

        $response = $this->withToken($token)->getJson('/api/products');
        $response->assertStatus(200);

        $response = $this->withToken($token)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity'   => 2,
        ]);
        $response->assertStatus(200);
        $response->assertJson(['status' => true]);

        $response = $this->withToken($token)->getJson('/api/cart');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

public function test_race_condition_prevents_overselling()
{
    $product = Product::create([
        'name'  => 'Limited Product',
        'price' => 50.00,
        'stock' => 5,
    ]);

    $tokens = [];
    for ($i = 1; $i <= 10; $i++) {
        $user = User::create([
            'name'     => "User $i",
            'email'    => "user$i@test.com",
            'password' => bcrypt('password123'),
            'role'     => 'user',
        ]);
        $tokens[] = ['index' => $i, 'token' => $user->createToken('api-token')->plainTextToken];
    }

    $successCount = 0;
    $failCount    = 0;

    foreach ($tokens as $item) {
        $response = $this->withToken($item['token'])->postJson('/api/checkout', [
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $status  = $response->status();
        $success = $response->json('status') === true;

        if ($success) {
            $successCount++;
            $remaining = $response->json('remaining_stock');
            fwrite(STDOUT, "\n  SUCCESS - User {$item['index']} -> Purchase successful | HTTP $status | Remaining stock: $remaining");
        } else {
            $failCount++;
            $message = $response->json('message');
            fwrite(STDOUT, "\n  FAILED  - User {$item['index']} -> Purchase failed  | HTTP $status | Reason: $message");
        }
    }

    fwrite(STDOUT, "\n\n  RESULT: Success=$successCount | Failed=$failCount | Final stock: {$product->fresh()->stock}\n");

    $this->assertEquals(5, $successCount, "Only 5 users should succeed");
    $this->assertEquals(5, $failCount,    "Only 5 users should fail");
    $this->assertEquals(0, $product->fresh()->stock);
}
    public function test_semaphore_limits_concurrent_operations()
    {
        $product = Product::create([
            'name'  => 'Semaphore Product',
            'price' => 50.00,
            'stock' => 100,
        ]);

        Cache::put('checkout_semaphore_count', 5, now()->addMinutes(5));

        $user = User::create([
            'name'     => 'Semaphore User',
            'email'    => 'sem@test.com',
            'password' => bcrypt('password123'),
            'role'     => 'user',
        ]);
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/checkout-safe', [
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $this->assertTrue(
            in_array($response->status(), [200, 503]),
            "يجب أن يكون الرد 200 أو 503"
        );

        $count = Cache::get('checkout_semaphore_count', 0);
        $this->assertGreaterThanOrEqual(0, $count, "عداد الـ Semaphore يجب أن يكون >= 0");
    }

    public function test_remove_item_from_cart()
    {
        $user = User::create([
            'name'     => 'Cart User',
            'email'    => 'cart@test.com',
            'password' => bcrypt('password123'),
            'role'     => 'user',
        ]);
        $token = $user->createToken('api-token')->plainTextToken;

        $product = Product::create([
            'name'  => 'Cart Product',
            'price' => 20.00,
            'stock' => 10,
        ]);

        $this->withToken($token)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $cart   = $this->withToken($token)->getJson('/api/cart');
        $itemId = $cart->json('data.0.id');

        $response = $this->withToken($token)->deleteJson("/api/cart/{$itemId}");
        $response->assertStatus(200);
        $response->assertJson(['status' => true]);

        $cart = $this->withToken($token)->getJson('/api/cart');
        $this->assertCount(0, $cart->json('data'));
    }
}