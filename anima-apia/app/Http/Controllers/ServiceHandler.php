<?php

namespace App\Http\Controllers;

use Validator;
use Illuminate\Http\Request;
use App\Models\Pot;
use App\Models\Donation;
use App\Models\Comment;
use Illuminate\Support\Facades\Cache;
use App\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

date_default_timezone_set("America/Argentina/Buenos_Aires");

class ServiceHandler extends Controller
{
    // Comments ------------------------------------------------------------------------------------------------------------------------------
    public function createComment(Request $request)
    {

        $dataValidation = Validator::make($request->all(), [
            'potID' => 'required|integer|exists:App\Models\Pot,id',
            'body' => 'required|string|max:255'
        ]);

        if ($dataValidation->fails()) {
            return response()->json([
                'message' => 'Invalid values were provided, check documentation for validation requirements.',
            ], 400);
        }

        $validatedData = $request->only(['potID', 'body']);
        $user = $request->user();

        Comment::create([
            'potID' => $validatedData['potID'],
            'authorEmail' => $user->email,
            'body' => $validatedData['body']
        ]);

        return response()->json([
            'message' => 'New comment created.'
        ]);
    }

    public function getCommentsFromPot($potID)
    {
        $dataValidation = Validator::make(['potID' => $potID], [
            'potID' => 'required|integer'
        ]);
        
        if ($dataValidation->fails()) {
            return response()->json([
                'message' => 'Invalid values were provided, check documentation for validation requirements.',
            ], 400);
        }
        $Comments = Comment::where('potID', $potID)->get();
        return response()->json([
            "Comments" => $Comments
        ], 200);
    }

    public function getCommentsFromUser(Request $request)
    {
        $Comments = Comment::where('authorEmail', $request->user()->email)->get();
        return response()->json([
            "Comments" => $Comments
        ], 200);
    }
    // Pots ------------------------------------------------------------------------------------------------------------------------------
    public function getAllPotsFromUser(Request $request)
    {
        $user = $request->user();
        $Pots = Pot::where('state', 1)->where('authorEmail', $user->email)
            ->select('*')
            ->get();

        return response()->json([
            'Pots' => $Pots
        ], 200);
    }

    public function getAllPots()
    {
        $potsCache = Cache::get('pots');

        if ($potsCache) {
            return response()->json([
                'Pots' => $potsCache
            ], 200);
        }

        $Pots = Pot::where('state', 1)
            ->select('*')
            ->get();

        Cache::put('pots', $Pots, 600);

        return response()->json([
            'Pots' => $Pots
        ], 200);
    }

    public function createPot(Request $request)
    {

        $dataValidation = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'desc' => 'required|string',
            'openFrom' => 'required|date_format:H:i',
            'to' => 'required|date_format:H:i',
            'address' => 'required|string',
            'image' => 'required|mimes:jpg,png,jpeg,gif,svg'
        ]);

        if ($dataValidation->fails()) {
            return response()->json([
                'message' => 'Invalid values were provided, check documentation for validation requirements.',
            ], 400);
        }

        $validatedData = $request->only(['name', 'desc', 'openFrom', 'to', 'address']);
        $user = $request->user();

        Pot::create([
            'name' => $validatedData['name'],
            'authorEmail' => $user->email,
            'desc' => $validatedData['desc'],
            'openFrom' => $validatedData['openFrom'],
            'to' => $validatedData['to'],
            'address' => $validatedData['address']
        ]);

        $latestPot = Pot::where([
            ['name', '=', $validatedData['name']],
            ['authorEmail', '=',  $user->email],
            ['desc', '=',  $validatedData['desc']],
            ['openFrom', '=', $validatedData['openFrom']],
            ['to', '=', $validatedData['to']]
        ])->orderBy('id', 'desc')->select('id')->limit(1)->get()[0]->id;

        $fileName = "pot_" . $latestPot . ".jpg";
        $request->file('image')->move(public_path("/assets/pots/pot_$latestPot"), $fileName);
        Pot::where('id', $latestPot)->update(['imageURL' => url("/assets/pots/pot_$latestPot" . '/' . $fileName)]);

        Cache::forget('pots');

        return response()->json([
            'message' => 'New pot created.'
        ]);
    }

    public function getPotById(Request $request, $id)
    {
        if (Pot::where('id', $id)->doesntExist()) {
            return response()->json([
                'message' => "No pot under id $id was found."
            ], 404);
        }
        $Pot = Pot::where('id', $id)->get();
        return response()->json([
            'Pot' => $Pot
        ], 200);
    }
    // Pagers ------------------------------------------------------------------------------------------------------------------------------
    public function pagerWithAuth(Request $request, $contentType, $offset, $limit)
    {
        $dataValidation = $this->getValidationFactory()->make(['offset' => $offset, 'limit' => $limit], [
            'offset' => 'required|integer',
            'limit' => 'required|integer'
        ]);

        if (!$dataValidation->passes()) {
            return response()->json([
                'message' => 'Invalid offset or limit values were provided, check documentation for validation requirements.',
            ], 400);
        }
        switch ($contentType) {
            case 'donations':

                $user = $request->user();
                $pagesLeft = ceil((Donation::where('authorEmail', $user->email)->count() / $limit) - $offset);

                $Donations = Donation::where('authorEmail', $user->email)
                    ->skip($limit * $offset)
                    ->take($limit)
                    ->get();

                if ($pagesLeft < 0) {
                    $pagesLeft = 0;
                }
                $pagesLeft = $pagesLeft - 1;

                return response()->json([
                    'Donations' => $Donations,
                    'PagesLeft' => abs($pagesLeft)
                ], 200);

                break;

            case 'pots':

                $user = $request->user();
                $pagesLeft = ceil((Pot::where('authorEmail', $user->email)->count() / $limit) - $offset);

                $Pots = Pot::where('authorEmail', $user->email)
                    ->skip($limit * $offset)
                    ->take($limit)
                    ->get();

                $pagesLeft = $pagesLeft - 1;

                if ($pagesLeft < 0) {
                    $pagesLeft = 0;
                }

                return response()->json([
                    'Pots' => $Pots,
                    'PagesLeft' => abs($pagesLeft)
                ], 200);

                break;

            default:
                return response()->json([
                    'message' => "Content of type '$contentType' not found."
                ], 404);
        }
    }

    public function pagerWithoutAuth($contentType, $offset, $limit)
    {
        $dataValidation = $this->getValidationFactory()->make(['offset' => $offset, 'limit' => $limit], [
            'offset' => 'required|integer',
            'limit' => 'required|integer'
        ]);

        if (!$dataValidation->passes()) {
            return response()->json([
                'message' => 'Invalid offset or limit values were provided, check documentation for validation requirements.',
            ], 400);
        }
        switch ($contentType) {
            case 'donations':

                $pagesLeft = ceil((Donation::count() / $limit) - $offset);

                $Donations = Donation::skip($limit * $offset)
                    ->take($limit)
                    ->get();

                if ($pagesLeft < 0) {
                    $pagesLeft = 0;
                }
                $pagesLeft = $pagesLeft - 1;

                return response()->json([
                    'Donations' => $Donations,
                    'PagesLeft' => abs($pagesLeft)
                ], 200);

                break;

            case 'pots':

                $pagesLeft = ceil((Pot::where('state', 1)->count() / $limit) - $offset);

                $Pots = Pot::where('state', 1)->skip($limit * $offset)
                    ->take($limit)
                    ->get();

                if ($pagesLeft < 0) {
                    $pagesLeft = 0;
                }
                $pagesLeft = $pagesLeft - 1;

                return response()->json([
                    'Pots' => $Pots,
                    'PagesLeft' => abs($pagesLeft)
                ], 200);

                break;

            default:
                return response()->json([
                    'message' => "Content of type '$contentType' not found."
                ], 404);
        }
    }
    // Donations ------------------------------------------------------------------------------------------------------------------------------
    public function getDonationsFromUser(Request $request)
    {
        $user = $request->user();
        $donations = Donation::where('authorEmail', $user->email)
            ->select('*')
            ->get();
        return response()->json([
            'Donations' => $donations
        ]);
    }

    public function createDonation(Request $request)
    {
        $dataValidation = $this->getValidationFactory()->make($request->only(['potId', 'donationType']), [
            'potId' => 'required|integer',
            'donationType' => 'required|string'
        ]);

        if (!$dataValidation->passes()) {
            return response()->json([
                'message' => 'Invalid values were provided, check documentation for validation requirements.',
            ], 400);
        }

        $validatedData = $request->only(['potId', 'donationType']);

        $user = $request->user();

        if (Pot::where('id', $validatedData['potId'])->doesntExist()) {
            return response()->json([
                'message' => 'Pot not found.'
            ], 404);
        }
        Donation::create([
            'potId' => $validatedData['potId'],
            'authorEmail' => $user->email,
            'donationType' => $validatedData['donationType']
        ]);

        $potOwner = Pot::where('id', $validatedData['potId'])->select('authorEmail')->get()[0]->authorEmail;
        Mail::to($potOwner)->send(new Mailer(['authorEmail' => $user->email, 'donationType' => $validatedData['donationType']]));
        return response()->json([
            'message' => 'New donation created.'
        ]);
    }
}
