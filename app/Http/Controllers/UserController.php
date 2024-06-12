<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Administrator;
use App\Models\Score;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Carbon\Carbon;

class UserController extends Controller
{
    public function adminlist()
    {
        $admins = Administrator::all();
        $adminId = $admins->pluck('id')->toArray();
        if (!in_array(Auth::id(), $adminId)) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not administrator',
            ], 403);
        }

        return response()->json([
            'totalElement' => count($admins),
            'content' => $admins,
        ], 200);
    }

    public function addUser(Request $request)
    {
        $users = User::all();
        $usrnm = $users->pluck('username')->toArray();
        if (in_array($request->username, $usrnm)) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Username already exists'
            ], 400);
        }
        $validator = Validator::make($request->all(), [
            'username' => 'required|unique:users,username|min:4|max:60',
            'password' => 'required|min:5|max:10'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Request body is not valid',
                'violations' => $validator->errors()
            ], 400);
        }

        $admins = Administrator::all();
        $adminId = $admins->pluck('id')->toArray();
        if (!in_array(Auth::id(), $adminId)) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not administrator',
            ], 403);
        }

        $user = new User();
        $user->username = $request->username;
        $user->password = bcrypt($request->password);
        $user->save();

        if ($user) {
            return response()->json([
                'status' => 'success',
                'username' => $request->username
            ], 201);
        }
    }

    public function updateUser(Request $request, $id)
    {
        $users = User::all();
        $usrnm = $users->pluck('username')->toArray();
        if (in_array($request->username, $usrnm)) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Username already exists'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|unique:users,username|min:4|max:60',
            'password' => 'required|min:5|max:10'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Request body is not valid',
                'violations' => $validator->errors()
            ], 400);
        }


        $admins = Administrator::all();
        $adminId = $admins->pluck('id')->toArray();
        if (!in_array(Auth::id(), $adminId)) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not administrator',
            ], 403);
        }

        $user = User::where('id', $id)->first();
        if (!$user) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'User not found'
            ], 403);
        }
        $user->username = $request->username;
        $user->password = bcrypt($request->password);
        $user->save();

        if ($user) {
            return response()->json([
                'status' => 'success',
                'username' => $request->username
            ], 201);
        }
    }

    public function deleteUser($id)
    {
        $admins = Administrator::all();
        $adminId = $admins->pluck('id')->toArray();
        if (!in_array(Auth::id(), $adminId)) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not administrator',
            ], 403);
        }
        $user = User::where('id', $id)->whereNull('deleted_at')->first();
        if($user->deleted_at = Carbon::now()){
            $user->save();
            return response([

            ], 204)->header('Content-Type', 'text/plain');
        }
    }

    public function userlist()
    {
        $users = User::whereNull('deleted_at')->get();
        $admins = Administrator::all();
        $adminId = $admins->pluck('id')->toArray();
        if (!in_array(Auth::id(), $adminId)) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not administrator',
            ], 403);
        }

        $users->makeHidden(['delete_reason', 'delete_reason','id']);
        return response()->json([
            'totalElement' => count($users),
            'content' => $users,
        ], 200);
    }


    public function userlistid()
    {
        $users = User::whereNull('deleted_at')->get();
        $admins = Administrator::all();
        $adminId = $admins->pluck('id')->toArray();
        if (!in_array(Auth::id(), $adminId)) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not administrator',
            ], 403);
        }

        $users->makeHidden(['delete_reason']);
        return response()->json([
            'totalElement' => count($users),
            'content' => $users,
        ], 200);
    }

    public function userdetail($username)
    {
        $user = User::where('username', $username)->with('games')->first();
        if (!$user) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'User not foud'
            ], 403);
        }
        $user->registeredTimestamp = $user->last_login_at;
        $user->authoredGames = $user->games;

        $score = Score::where('user_id', $user->id)->with('game_version.game')->orderBy('score', 'desc')->get();

        $user->makeHidden(['id', 'user_id', 'last_login_at', 'created_at', 'updated_at', 'deleted_at', 'delete_reason', 'games']);
        $score->makeHidden(['id', 'user_id', 'game_version_id', 'created_at', 'updated_at', 'game_version']);

        $newScores = $score->map(function ($value) {
            if ($value->game_version && $value->game_version->game) {
                return [
                    'game' => $value->game_version->game,
                    'score' => $value->score,
                    'timestamp' => $value->created_at,
                ];
            }
            return [
                'game' => null,
                'score' => $value->score,
                'timestamp' => $value->created_at,
            ];
        });

        $user->highscore = $newScores;
        return response()->json(
            $user,
            200
        );
    }

    public function blockUser(Request $request, $id) {
        $admins = Administrator::all();
        $adminId = $admins->pluck('id')->toArray();
        if (!in_array(Auth::id(), $adminId)) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not administrator',
            ], 403);
        }
        $user = User::where(['id' => $id, 'deleted_at' => null])->first();
        if ($user) {
            $user->delete_reason = $request->delete_reason ? $request->delete_reason : 'Violation';
            $user->save();

            return response([

            ], 204)->header('Content-Type', 'text/plain');
        } else {
            return response()->json([
                'status' => 'not-found',
                'message' => 'User not found'
            ], 403);
        }
    }

    public function unblockUser(Request $request, $id) {
        $user = User::where('id', $id)->first();
        if (!$user) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'User not foud'
            ], 403);
        }else{
            $user->delete_reason = null;
            $user->save();

            return response([

            ], 204)->header('Content-Type', 'text/plain');
        }
    }
}
