<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Token;
use App\Models\PersonalAccessTokens;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

date_default_timezone_set("America/Argentina/Buenos_Aires");
class AuthController extends Controller
{
    public function register(Request $request)
    {
        $dataValidation = $this->getValidationFactory()->make($request->only(['fullName', 'email', 'password']), [
            'fullName' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8'

        ]);

        if (!$dataValidation->passes()) {
            return response()->json([
                'message' => 'Invalid values were provided, check documentation for validation requirements.',
            ], 400);
        }

        $validatedData = $request->only(['fullName', 'email', 'password']);

        if (User::where('email', $validatedData['email'])->exists()) {
            return response()->json([
                'message' => 'Email is already in use.',
            ], 409);
        }
        $user = User::create([
            'fullName' => $validatedData['fullName'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password'])
        ]);

        $user->createToken('auth_token')->plainTextToken;
        $currentDate = date('Y/m/d H:i:s');
        $expTimeStamp = strtotime(" $currentDate + 5 minutes");

        $tokenValue = rand(111111, 999999);
        $expiration =  date('Y/m/d H:i:s', $expTimeStamp);
        Token::create([
            'tokenValue' => $tokenValue,
            'email' => $validatedData['email'],
            'expiration' =>  $expiration
        ]);

        Mail::to($validatedData['email'])->send(new Mailer(['tokenValue' => $tokenValue, 'expiration' => $expiration]));

        return response()->json([
            'message' => 'Account must be activated, an email has been sent.',
        ]);
    }
    public function login(Request $request)
    {
        $dataValidation = $this->getValidationFactory()->make($request->only(['email', 'password']), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8'

        ]);

        if (!$dataValidation->passes()) {
            return response()->json([
                'message' => 'Invalid values were provided, check documentation for validation requirements.',
            ], 400);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid login credentials'
            ], 401);
        }

        $user = User::where('email', $request['email'])->firstOrFail();
        if (!$user->email_verified_at) {
            return response()->json([
                'message' => 'Account has not been activated.'
            ], 401);
        }
        if (PersonalAccessTokens::where('tokenable_id', $user->id)->exists()) {
            PersonalAccessTokens::where('tokenable_id', $user->id)->delete();
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'username' => $user->fullName,
            'access_token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'message' => 'User logged out.'
        ]);
    }

    public function accountActivation(Request $request)
    {
        $expiredTokens = Token::where('expiration', '<', date('Y/m/d H:i:s'))->get();
        Token::where('expiration', '<', date('Y/m/d H:i:s'))->delete();
        foreach ($expiredTokens as $token) {
            User::where('email', $token->email)->where('email_verified_at', null)->delete();
        }

        $dataValidation = $this->getValidationFactory()->make($request->only(['email', 'token']), [
            'email' => 'required|string|email|max:255',
            'token' => 'required|integer|min:6'

        ]);

        if (!$dataValidation->passes()) {
            return response()->json([
                'message' => 'Invalid values were provided, check documentation for validation requirements.',
            ], 400);
        }

        $validatedData = $request->only(['email', 'token']);

        if (Token::where('email', $validatedData['email'])->where('tokenValue', $validatedData['token'])->exists()) {
            User::where('email', $validatedData['email'])->update(['email_verified_at' => date('Y/m/d H:i:s')]);
            Token::where('email', $validatedData['email'])->delete();

            return response()->json([
                'message' => 'User activated successfully.'
            ]);
        }
        return response()->json([
            'message' => 'Token has expired or provided values are incorrect.'
        ], 404);
    }
}
