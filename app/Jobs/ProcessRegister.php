<?php

namespace App\Jobs;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ProcessRegister implements ShouldQueue
{
    use Queueable;


    protected $data;
   
    public function __construct( $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
       $user = \App\Models\User::create([
        'name' => $this->data['name'],
        'email' => time() . rand(1,9999) . '@test.com',
        'password' => Hash::make($this->data['password']),
        'role' => 'user',
    ]);

         Mail::to($user->email)->send(new \App\Mail\WelcomeMail($user->name));
    }
}
