<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Game;
use App\Models\User;
use App\Models\Score;
use App\Models\GameVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class GamesController extends Controller
{
    public function listgames(Request $request)
    {
        // Ambil parameter dari request atau gunakan nilai default
        $page = $request->input('page', 0);
        $size = $request->input('size', 10);
        $sortBy = $request->input('sortBy', 'title');
        $sortDir = $request->input('sortDir', 'asc');

        // Validasi parameter request
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:0',
            'size' => 'integer|min:1',
            'sortBy' => 'in:title,popular,uploaddate',
            'sortDir' => 'in:asc,desc',
        ]);

        // Jika validasi gagal, kembalikan respons error
        if ($validator->fails()) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Request body is not valid',
                'violations' => $validator->errors()
            ], 400);
        }

        // Bangun query dasar untuk mengambil data game
        $query = Game::with(['users', 'latestGameVersion'])
            ->whereNull('deleted_at')
            ->has('latestGameVersion'); // Pastikan hanya game dengan versi terbaru yang diambil

        // Hitung total jumlah game
        $totalCount = $query->count();

        // Urutkan game berdasarkan parameter sortBy dan sortDir
        switch ($sortBy) {
            case 'uploaddate':
                $query->orderBy('created_at', $sortDir);
                break;
            case 'title':
            default:
                $query->orderBy('title', $sortDir);
                break;
        }

        // Paginate game yang sudah diurutkan berdasarkan parameter page dan size
        $games = $query->paginate($size, ['*'], 'page', $page + 1);

        // Ambil game-game yang sudah dipaginate
        $games = $query->get();

        // Sesuaikan response game-game yang sudah diambil
        $transformedGames = $games->map(function ($game) {
            $latestVersion = $game->latestGameVersion;

            // Jika game punya latestVersion, kita modify response
            //- storage_path menjadi thumbnail
            //- created_at menjadi uploadTimestamp
            //- dan menghitung score dari game, dengan mencari berdasarkan latest version id
            if ($latestVersion) {
                // Mengambil semia file dari storage path
                $thumbnails = Storage::allFiles($latestVersion->storage_path);

                // filter array menjadi hanya mengambil file berformat image (png, jpg, dll)
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];
                $thumbnailPaths = array_filter($thumbnails, function ($path) use ($imageExtensions) {
                    $extension = pathinfo($path, PATHINFO_EXTENSION);
                    return in_array(strtolower($extension), $imageExtensions);
                });

                // Ubah array yang telah difilter menjadi string yang dipisahkan oleh koma
                $thumbnailString = implode(', ', $thumbnailPaths);

                // assign atribut thumbnail
                $game->thumbnail = $thumbnailString;

                $game->uploadTimestamp = $latestVersion->created_at;
                $game->scoreCount = Score::where('game_version_id', $latestVersion->id)->count();
            } else {
                // jika kosong maka setting sebagai null
                $game->thumbnail = null;
                $game->uploadTimestamp = null;
                $game->scoreCount = 0;
            }

            // Menambahkan return author
            $game->author = $game->users ? $game->users->username : 'Tidak Diketahui';

            return $game;
        });

        // Sortir game-game berdasarkan popularitas jika sortBy adalah 'popular'
        if ($sortBy == 'popular') {
            $transformedGames = $transformedGames->sortByDesc('scoreCount');

            // Jika sortDir adalah ascending, balikkan urutan sortir
            if ($sortDir == 'asc') {
                $transformedGames = $transformedGames->sortBy('scoreCount');
            }

            // Potong game-game yang sudah disortir berdasarkan parameter paginasi
            $start = $page * $size;
            $transformedGames = $games->slice($start, $size)->values();
        }

        // Bangun respons JSON dengan metadata dan konten game
        return response()->json([
            'page' => $page,
            'size' => $size,
            'totalElements' => $totalCount,
            'content' => $transformedGames,
        ], 200);
    }

    public function postgame(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|min:3|max:80',
            'description' => 'required|min:0|max:200',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Request body is not valid',
                'violations' => $validator->errors()
            ], 400);
        }

        $gameTitle = Game::where('title', $request->title)->first();
        if ($gameTitle) {
            return response()->json([
                'status' => 'invalid',
                'slug' => 'Game title already exists'
            ], 400);
        }
        $tolowerSlug = strtolower($request->title);
        $replaceSpace = str_replace(' ', '-', $tolowerSlug);

        $gameCreate = new Game();
        $gameCreate->title = $request->title;
        $gameCreate->slug = $replaceSpace;
        $gameCreate->description = $request->description;
        $gameCreate->created_by = Auth::id();
        $gameCreate->save();

        return response()->json([
            'status' => 'success',
            'slug' => $replaceSpace
        ], 201);
    }

    public function demogame($slug)
    {
        $games = Game::where('slug', $slug)->whereNull('deleted_at')->first();
        if (!$games) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'Game not found'
            ], 403);
        }
        $games->makeHidden(['id', 'created_by', 'created_at', 'updated_at', 'deleted_at', 'users']);

        $gameVersions = GameVersion::where(['game_id' => $games->id])->latest()->get();
        if ($gameVersions->isNotEmpty()) {
            $games->thumbnail = 'games/' . $slug . '/' . $gameVersions->first()->version;
            $games->uploadTimestamp = Carbon::parse($gameVersions->first()->created_at)->format('Y-m-d H:i:s');
            $games->author = $games->users->username;
            $games->gamePath = $gameVersions->first()->storage_path;

            foreach ($gameVersions as $gvs) {
                $games->scoreCount = Score::where('game_version_id', $gvs->id)->count();
            }
        }
        return response()->json([
            $games
        ], 200);
    }

    public function updategame($slug, Request $request)
    {
        $game = Game::where('slug', $slug)->first();
        $validator = Validator::make($request->all(), [
            'title' => 'min:3|max:80',
            'description' => 'min:0|max:200',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Request body is not valid',
                'violations' => $validator->errors()
            ], 400);
        }

        if ($game->created_by != auth()->id()) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the game author',
            ], 403);
        }

        switch (true) {
            case $request->title && $request->description:
                $game->update([
                    'title' => $request->title,
                    'description' => $request->description
                ]);
                break;

            case $request->title:
                $game->update([
                    'title' => $request->title
                ]);
                break;

            case $request->description:
                $game->update([
                    'description' => $request->description
                ]);
                break;
        }

        return response()->json([
            'status' => 'success',
        ], 200);
    }

    public function deletegame($slug)
    {
        $game = Game::where('slug', $slug)->whereNull('deleted_at')->with('gameVersions')->first();
        if (!$game) {
            return response()->json([
                'status' => 'not-found',
                'message' => 'Game not found'
            ], 403);
        }

        if ($game->created_by != auth()->id()) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the game author',
            ], 403);
        }
        if ($game) {
            $game->deleted_at = Carbon::now();
            $game->save();
            foreach ($game->gameVersions as $version) {
                $version->deleted_at = Carbon::now();
                $version->save();
            }

            return response()->json([], 204)->header('Content-type', 'text/plain');
        }
    }

    public function uploadGame(Request $request, $slug)
    {
        $game = Game::where('slug', $slug)->whereNull('deleted_at')->first();
        if (!$game) {
            return response([
                'status' => 'not-found',
                'message' => 'Game not found'
            ], 403)->header('Content-Type', 'text/plain');
        }

        if ($game->created_by != Auth::id()) {
            return response('You are not the game author', 403)->header('Content-Type', 'text/plain');
        }
        $validator = Validator::make($request->all(), [
            'zipfile' => 'required|file|mimes:zip,rar',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Request body is not valid',
                'violations' => $validator->errors()
            ], 400);
        }

        if ($request->hasFile('zipfile')) {
            $latestVersion = $game->gameVersions()->orderBy('version', 'desc')->first();
            if ($latestVersion) {
                $splitV = explode('v', $latestVersion->version);
                $newVersionNumberIndex = intval($splitV[1]);
                $newVersionNumber = 'v' . $newVersionNumberIndex + 1;
            } else {
                $newVersionNumber = 'v1';
            }

            $file = $request->file('zipfile');
            $file->store('games/' . $game->slug . '/' . $newVersionNumber, 'public');
            if ($request->hasFile('thumbnail')) {
                $request->thumbnail->store('games/' . $game->slug . '/' . $newVersionNumber, 'public');
            }

            $gameVersion = new GameVersion();
            $gameVersion->game_id = $game->id;
            $gameVersion->version = $newVersionNumber;
            $gameVersion->storage_path = 'games/' . $game->slug . '/' . $newVersionNumber;
            $gameVersion->created_at = Carbon::now();
            $gameVersion->save();

            return response('Upload successful', 200)->header('Content-Type', 'text/plain');
        }

        return response('File upload failed', 500)->header('Content-Type', 'text/plain');
    }

    public function getGameFiles($slug, $version)
    {
        $files = Storage::allFiles('/games' . '/' . $slug . '/' . $version);
        return $files;
    }

    public function scoresList($slug)
    {
        $game = Game::where('slug', $slug)->whereNull('deleted_at')->first();
        if (!$game) {
            return response([
                'status' => 'not-found',
                'message' => 'Game not found'
            ], 403)->header('Content-Type', 'text/plain');
        }

        $scores = [];
        $gameVersions = GameVersion::where(['game_id' => $game->id])->get();
        foreach ($gameVersions as $version) {
            $versionScores = Score::where('game_version_id', $version->id)->get();
            foreach ($versionScores as $score) {
                $scores[] = [
                    'score' => $score->score,
                    'username' => $score->user->username,
                    'timestamp' => $score->updated_at,
                ];
            }
        }

        usort($scores, function ($a, $b) {
            return $b['score'] - $a['score'];
        });

        return response()->json([
            'scores' => $scores
        ], 200);
    }

    public function addScore(Request $request, $slug)
    {
        $game = Game::where('slug', $slug)->with('latestGameVersion')->whereNull('deleted_at')->first();
        if (!$game) {
            return response([
                'status' => 'not-found',
                'message' => 'Game not found'
            ], 403)->header('Content-Type', 'text/plain');
        }

        if ($request->score) {
            $addScore = new Score();
            $addScore->user_id = Auth::id();
            $addScore->game_version_id = $game->latestGameVersion->id;
            $addScore->score = $request->score;
            $addScore->save();

            return response()->json([
                'status' => 'success'
            ], 201);
        }
    }
}
