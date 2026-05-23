<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CartItem;
use App\Models\User;
use App\Jobs\ProcessOrderJob;
use Illuminate\Support\Facades\Log;

class ProcessBatchOrders extends Command
{
    protected $signature = 'orders:process-batch';
    protected $description = 'تقسيم طلبات السلة ومعالجتها بالتوازي عبر الطوابير';

    public function handle()
    {
        $totalItems = CartItem::count();

        $this->info("بدء توزيع ($totalItems) مهمة إلى الطوابير...");

        CartItem::chunk(50, function ($cartItems) {
            foreach ($cartItems as $item) {
                $user = User::find($item->user_id);

                if ($user) {
                    ProcessOrderJob::dispatch($user, $item);
                }
            }
            $this->comment('تم إرسال دفعة (50 عنصر) إلى جدول المهام...');
        });


        $this->info(" نجاح: تم توزيع جميع المهام في");

    }
}
