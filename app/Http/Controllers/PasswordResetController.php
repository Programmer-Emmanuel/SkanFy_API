<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;


class PasswordResetController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email_user' => 'required|email|exists:users,email_user'
        ]);

        // Supprimer les anciens tokens
        DB::table('password_reset_tokens')->where('email', $request->email_user)->delete();

        // Générer un token unique
        $token = Str::random(64);

        // Enregistrer le token
        DB::table('password_reset_tokens')->insert([
            'email' => $request->email_user,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);

        // URL du lien front (ex : ton frontend React)
        $resetUrl = "https://skanfy.com/reset-password?token={$token}&email={$request->email_user}";

        // Envoi de l’e-mail
        Mail::to($request->email_user)->send(new PasswordResetMail($resetUrl));

        return response()->json([
            'success' => true,
            'message' => 'Un lien de réinitialisation a été envoyé à votre adresse email.'
        ],200);
    }

    public function verifyToken($token)
{
    $record = DB::table('password_reset_tokens')->where('token', $token)->first();

    if (!$record) {
        return response()->json(['success' => false, 'message' => 'Token invalide.'], 400);
    }

    // Vérifie l’expiration (par ex : 60 minutes)
    if (Carbon::parse($record->created_at)->addMinutes(10)->isPast()) {
        return response()->json(['success' => false, 'message' => 'Token expiré.'], 400);
    }

    return response()->json(['success' => true, 'message' => 'Token valide']);
}



public function resetPassword(Request $request, $token)
{
    $request->validate([
        'password' => 'required|min:6|confirmed',
    ]);

    $record = DB::table('password_reset_tokens')->where('token', $token)->first();

    if (!$record) {
        return response()->json(['success' => false, 'message' => 'Lien invalide ou expiré.'], 400);
    }

    User::where('email_user', $record->email)->update([
        'password' => Hash::make($request->password)
    ]);

    DB::table('password_reset_tokens')->where('email', $record->email)->delete();

    return response()->json(['success' => true, 'message' => 'Mot de passe réinitialisé avec succès.'], 200);
}


}
