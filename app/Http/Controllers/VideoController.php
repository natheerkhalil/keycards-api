<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Playlist;
use App\Models\Relationship;

use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Http;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class VideoController extends Controller
{
    private $apiKey;
    public $geniusAccessToken;

    public function __construct()
    {
        $this->apiKey = \Config::get('video.yt_api_key');
        $this->geniusAccessToken = \Config::get('video.genius_access_token');
    }

    // GET VIDEO LYRICS
    public function lyrics(Request $request)
    {
        // VALIDATE THAT TITLE HAS BEEN PROVIDED
        $request->validate([
            "title" => "required|string"
        ]);

        $title = $request->title;
        $artist = "";

        if ($request->artist) {
            $artist = $request->artist;
        }

        try {
            // FETCH VIDEO LYRICS USING GENIUS API
            $client = new Client();
            $searchUrl = 'https://api.genius.com/search';
            $response = $client->request('GET', $searchUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->geniusAccessToken,
                ],
                'query' => [
                    'q' => "$title $artist"
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $hits = $data['response']['hits'];

            if (count($hits) > 0) {
                $songPath = $hits[0]['result']['path'];
                $songUrl = "https://genius.com$songPath";

                // FETCH THE LYRICS FROM THE SONG PAGE
                $songPageResponse = $client->request('GET', $songUrl);
                $htmlContent = $songPageResponse->getBody()->getContents();

                // USE SYMFONY DOMCRAWLER TO PARSE THE LYRICS
                $crawler = new Crawler($htmlContent);
                $lyricsContainers = $crawler->filterXPath('//div[@data-lyrics-container="true"] | //div[contains(@class, "Lyrics__Container")]');

                if ($lyricsContainers->count()) {
                    $lyricsText = '';
                    foreach ($lyricsContainers as $container) {
                        $lyricsText .= $container->ownerDocument->saveHTML($container);
                    }
                    $lyricsText = strip_tags($lyricsText, '<br>');
                    $lyricsText = str_replace('<br>', "\n", $lyricsText);
                    return response()->json(['lyrics' => $lyricsText]);
                } else {
                    return response()->json(['lyrics' => 'Lyrics not found.'], 404);
                }
            } else {
                return response()->json(['lyrics' => 'Song not found.'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error fetching lyrics.'], 500);
        }
    }

    // EXTRACT LYRICS FROM GENIUS API RESPONSE
    private function extractLyrics($html)
    {
        // USE REGEX TO EXTRACT LYRICS FROM THE HTML
        preg_match('/<div class="lyrics">([\s\S]*?)<\/div>/', $html, $lyricsMatch);

        if (isset($lyricsMatch[1])) {
            return strip_tags($lyricsMatch[1]);
        } else {
            // FALLBACK METHOD TO EXTRACT LYRICS IF THE FIRST METHOD FAILS
            preg_match_all('/<div class="Lyrics__Container.*?">([\s\S]*?)<\/div>/', $html, $lyricsMatches);

            if (isset($lyricsMatches[1]) && count($lyricsMatches[1]) > 0) {
                $lyrics = '';
                foreach ($lyricsMatches[1] as $lyricsPart) {
                    $lyrics .= strip_tags($lyricsPart) . "\n";
                }
                return $lyrics;
            }
        }

        return 'Lyrics not found.';
    }

    // ENSURE YOUTUBE VIDEO EXISTS
    private function validateYouTubeVideo($videoId)
    {
        $url = "https://www.googleapis.com/youtube/v3/videos?id={$videoId}&key={$this->apiKey}&part=id";

        $response = Http::get($url);

        if ($response->successful()) {
            $data = $response->json();
            return !empty($data['items']);
        }

        return false;
    }

    // GET VIDEO PLAYLIST (NOTE - THIS FUNCTION IS DEPRECATED)
    public function playlist(Request $request)
    {
        // VALIDATE THAT VIDEO URL EXISTS
        $request->validate([
            "url" => "required|string|exists:videos,url",
        ]);

        $user = Auth::user();

        // GET VIDEO VIA URL FROM AUTHENTICATED USER
        $video = Video::where("username", $user->username)->where("url", $request->url)->firstOrFail();

        try {
            // CHECK IF VIDEO HAS A PLAYLIST - IF NOT, RETURN ERROR
            $pl_id = Relationship::where("video", $video->id)->where("username", $user->username)->value("playlist");
            $playlist = Playlist::where("id", $pl_id)->where("username", $user->username)->first();
        } catch (\Exception $e) {
            return response()->json(["error" => "This video doesn't have a playlist"], 404);
        }

        if (!$playlist) {
            return response()->json(["error" => "This video doesn't have a playlist" . $pl_id], 404);
        }

        // RETURN PLAYLIST DATA
        $data = [
            "id" => $playlist->id,
            "name" => $playlist->name,
            "videos" => $playlist->videos()->orderBy("created_at", "desc")->get(),
            "thumbnail" => $playlist->videos()->first()->thumbnail
        ];

        return response()->json(["data" => $data]);
    }

    // REMOVE OR ADD VIDEO TO FAVOURITES
    public function fav(Request $request)
    {
        // VALIDATE THAT VIDEO URL EXISTS
        $request->validate([
            "url" => "required|string|exists:videos,url",
        ]);

        $user = Auth::user();

        // GET VIDEO VIA URL FROM AUTHENTICATED USER
        $video = Video::where("username", $user->username)->where("url", $request->url)->firstOrFail();

        // UPDATE FAVOURITE STATUS AND DATE
        $video->update([
            "fav" => !$video->fav,
            "fav_date" => now()
        ]);
    }

    // GET FAVOURITED VIDEOS
    public function favs()
    {
        $user = Auth::user();

        // GET ALL FAVOURITED VIDEOS BY USERNAME AND ORDER BY FAV DATE
        $favs = Video::where("username", $user->username)->where("fav", true)->orderBy("fav_date", "desc")->get();

        // UNSERIALIZE SKIP FOR EACH FAVOURITED VIDEO
        foreach ($favs as $k => $f) {
            $f->skip = unserialize($f->skip);
        }

        // RETURN FAVOURITED VIDEOS
        return response()->json([
            "data" => $favs
        ]);
    }

    // GET ALL VIDEOS
    public function all()
    {
        $user = Auth::user();

        // GET ALL VIDEOS BY USERNAME AND ORDER BY CREATED DATE DESC
        $videos = Video::where("username", $user->username)->orderBy("created_at", "desc")->get();

        // UNSERIALIZE SKIP FOR EACH VIDEO
        foreach ($videos as $v) {
            $v->skip = unserialize($v->skip);
        }

        // RETURN ALL VIDEOS
        return response()->json([
            "data" => $videos
        ]);
    }

    // CHECK IF VIDEO EXISTS
    public function load(Request $request)
    {
        // VALIDATE THAT VIDEO URL EXISTS
        $request->validate([
            "url" => "required|string|exists:videos,url",
        ]);

        $user = Auth::user();

        // GET VIDEO VIA URL FROM AUTHENTICATED USER
        $video = Video::where("username", $user->username)->where("url", $request->url)->first();

        // IF VIDEO DOESN'T EXIST, RETURN ERROR
        if (!$video) {
            return response()->json(["error" => "Video doesn't exist"], 404);
        }

        // UNSERIALIZE VIDEO SKIP
        $video->skip = unserialize($video->skip);

        // RETURN VIDEO DATA
        return response()->json([
            "data" => $video
        ], 200);
    }

    // SAVE OR CREATE VIDEO
    public function save(Request $request)
    {
        // VALIDATE THAT REQUIRED DATA IS PROVIDED
        $request->validate([
            "title" => "required|string|max:255",
            "desc" => "nullable|string|max:255",
            "skip" => "nullable|array",
            "start" => ["required", "regex:/^\d+(\.\d+)?$/"],
            "end" => ["required", "regex:/^\d+(\.\d+)?$/", "gte:start"],
            "lyrics" => "nullable|string|max:9999",
            "fav" => "nullable|boolean",
            "speed" => "required|numeric|between:0.25,2.0"
        ]);

        // CHECK THAT YOUTUBE VIDEO EXISTS
        if (!$this->validateYouTubeVideo($request->url)) {
            return response()->json(["error" => "Video does not exist"], 400);
        }

        $user = Auth::user();

        // GET VIDEO VIA URL FROM AUTHENTICATED USER (IF EXISTS)
        $video = Video::where("url", $request->url)->where("username", $user->username)->first();

        // SERIALIZE EMPTY SKIP ARRAY
        $skip = serialize([]);

        // VALIDATE SKIP RANGES
        if ($request->skip != null && !empty($request->skip)) {
            $skip = $request->skip;

            foreach ($skip as $s) {
                $start = $s["start"];
                $end = $s["end"];

                if (!preg_match('/^\d+(\.\d+)?$/', $start) || !preg_match('/^\d+(\.\d+)?$/', $end)) {
                    return response()->json(["error" => "Invalid skip range"], 400);
                }

                if ($start >= $end) {
                    return response()->json(["error" => "Invalid skip range"], 400);
                }
            }

            $skip = serialize($skip);
        }

        // IF VIDEO EXISTS, UPDATE IT
        if ($video) {
            $video->update([
                "title" => $request->title,
                "desc" => $request->desc,
                "start" => $request->start,
                "end" => $request->end,
                "skip" => $skip,
                "lyrics" => $request->lyrics,
                "speed" => $request->speed
            ]);

            return response()->json(["data" => $request->desc], 201);
        }

        // CHECK IF USER HAS REACHED VIDEO LIMIT
        $membership = $user->membership;

        if ($membership == "0" && Video::where("username", $user->username)->count() >= 99) {
            return response()->json(["error" => "You have reached your video limit"], 403);
        }

        // IF VIDEO DOESN'T EXIST, CREATE IT
        $video = Video::create([
            "title" => $request->title,
            "desc" => $request->desc,
            "thumbnail" => "https://i.ytimg.com/vi/" . $request->url . "/hqdefault.jpg",
            "url" => $request->url,
            "start" => $request->start,
            "end" => $request->end,
            "lyrics" => $request->lyrics || null,
            "skip" => $skip,
            "username" => $user->username,
            "fav" => $request->fav || false,
            "speed" => $request->speed
        ]);

        // RETURN VIDEO DATA
        return response()->json([
            "data" => $video,
        ], 201);
    }

    // DELETE VIDEO
    public function delete(Request $request)
    {
        // VALIDATE THAT VIDEO URL EXISTS
        $request->validate([
            "url" => "required|exists:videos,url",
        ]);

        $user = Auth::user();

        // GET VIDEO VIA URL FROM AUTHENTICATED USER
        $video = Video::where("url", $request->url)->where("username", $user->username)->firstOrFail();

        // DELETE RELATIONSHIP TO DATABASE, IF EXISTS
        $relationships = Relationship::where("username", $user->username)->where("video", $video->id)->get();
        foreach ($relationships as $r) {
            $r->delete();
        }

        // DELETE VIDEO
        $video->delete();

        return response()->json([
            "message" => "Video deleted successfully",
        ], 200);
    }

    // GET RANDOM VIDEO (NOTE: THIS FUNCTION IS DEPRECATED, NOW VIDEO IS FETCHED CLIENT-SIDE ON SPA VIA ALL VIDEOS)
    public function random(Request $request)
    {
        $request->validate([
            "url" => "required|exists:videos,url",
        ]);

        $video = Video::where("username", Auth::user()->username)->where("url", '!=', $request->url)->inRandomOrder()->first();

        $data = $video;
        $data->skip = unserialize($data->skip);

        return response()->json(["data" => $data], 200);
    }

    // GET VIDEO DATA ON LOAD (NOTE: THIS FUNCTION IS DEPRECATED, NOW VIDEO IS FETCHED ON SPA VIA ALL VIDEOS OR FAVOURITES)
    public function fetch()
    {
        $user = Auth::user();

        $video = Video::where("username", $user->username)->where("fav", true)->orderBy("fav_date", "desc")->first();

        if (!$video) {
            $video = Video::where("username", $user->username)->latest()->first();
        }

        $video->skip = unserialize($video->skip);

        return response()->json([
            "data" => $video
        ], 200);
    }
}
