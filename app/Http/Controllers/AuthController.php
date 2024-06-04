<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Administrator;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function signup(Request $request) {
        $validator = Validator::make($request->all(),[
            'username' => 'required|unique:users,username|min:4|max:60',
            'password' => 'required|min:5',
        ]);
        if($validator->fails()){
            return response()->json([
                'status' => 'invalid',
                'message' => 'Request body is not valid',
                'violations' => $validator->errors()
            ], 400);
        }

        $user = new User();
        $user->username = $request->username;
        $user->password = bcrypt($request->password);
        $user->last_login_at = Carbon::now();
        $user->save();

        if(Auth::attempt($request->only(['username', 'password']))){
            $user = User::where('username', $request->username)->first();

            $token = $user->createToken("SANCTUMTOKEN")->plainTextToken;
            return response()->json([
                'status' => 'success',
                'token' => $token
            ], 201);
        }
    }

    public function signin(Request $request) {
        $validator = Validator::make($request->all(), [
            'username' => 'required|min:4|max:60',
            'password' => 'required|min:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Request body is not valid',
                'violations' => $validator->errors()
            ], 400);
        }

        $user = Administrator::where('username', $request->username)->first();
        $guard = 'admin';

        if (!$user) {
            $user = User::where('username', $request->username)->first();
            $guard = 'web';
        }

        if (!$user) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Username does not exist',
            ], 401);
        }

        if (!Auth::guard($guard)->attempt($request->only(['username', 'password']))) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Wrong username or password',
            ], 401);
        }

        $user->last_login_at = Carbon::now();
        $user->save();

        $token = $user->createToken("SANCTUMTOKEN")->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token
        ], 200);
    }

    public function signout(Request $request) {
        if(
        $request->user()->Tokens()->delete()
        ){
            return response()->json([
                'status' => 'success',
            ], 200);
        }
    }
}
