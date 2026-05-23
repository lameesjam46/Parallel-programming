<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRegister;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;


class AuthController extends Controller
{
    // public function register(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required|string|max:255',
    //         'email' => 'required|email|unique:users',
    //         'password' => 'required|string|min:6',
    //     ]);

    //     $user = User::create([
    //         'name' => $request->name,
    //         'email' => $request->email,
    //         'password' => Hash::make($request->password),
    //         'role' =>  'user',
    //     ]);



    //     return response()->json([
    //         'status' => true,
    //         'message' => 'User created successfully',
    //         'data' => $user
    //     ], 201);
    // }






//     public function register(Request $request) {
//     // 1. التخزين الفوري في قاعدة البيانات
//     $user = User::create([
//         'name' => $request->name,
//         'email' => $request->email,
//         'password' => bcrypt($request->password),
//     ]);

//     // 2. محاكاة العملية الثقيلة (المسؤولة عن البطء)
//     // نضع ثانية واحدة فقط لكي لا ينهار النظام عند تجربة 5 مستخدمين
//     sleep(1); 

//     return response()->json([
//         'message' => 'User registered and stored in DB',
//         'user_id' => $user->id
//     ], 201);
// }


//     public function register(Request $request)
// {
//     $request->validate([
//         'name' => 'required|string|max:255',
//         'email' => 'required|email|unique:users',
//         'password' => 'required|string|min:6',
//     ]);


//       \App\Jobs\ProcessRegister::dispatch($request->all());

//     return response()->json([
//         'status' => true,
//         'message' => 'طلبك قيد المعالجة، سيتم إنشاء الحساب خلال لحظات'
//     ], 202);  
// }








public function register(Request $request)
{
    $request->validate([
        'name' => 'required|string',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6',
    ]);

    if ($request->query('mode') === 'ideal') {

      ProcessRegister::dispatch($request->all());

        return response()->json(['message' => 'تم التسجيل بنجاح '], 202);

    } else {
        
        $user = User::create([
            'name' => $request->name,
            'email' => time() . rand(1,9999) . '@test.com',
            'password' => Hash::make($request->password),
            'role' => 'user',
        ]);



        Mail::to($user->email)->send(new WelcomeMail($user->name));

        return response()->json(['message' =>  'تم التسجيل بنجاح '], 201);
    }
}


























    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid email or password'
            ], 401);
        }

        $user = Auth::user();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'status' => true,
            'token' => $token,
            'user' => $user
        ]);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out'
        ]);
    }
}
