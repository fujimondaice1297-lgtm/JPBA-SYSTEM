<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $data = $r->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message'=>'Invalid credentials'], 401);
        }
        $token = $user->createToken('mobile')->plainTextToken;

        $bowler = $user->proBowler; // nullでもOK
        return response()->json([
            'token' => $token,
            'user'  => ['id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'role'=>$user->role],
            'bowler'=> $bowler ? [
                'id'=>$bowler->id,'license_no'=>$bowler->license_no,'name'=>$bowler->name_kanji,
                'district'=>$bowler->district?->label,'sex'=>$bowler->sex
            ] : null,
        ]);
    }
}
