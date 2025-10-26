<?php

namespace App\Http\Controllers;

use App\Mail\SendOtpMail;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AuthController extends Controller
{
public function register_user(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'nullable|string',
            'email_user' => 'required|email',
            'tel_user' => 'nullable|digits:10',
            'password' => 'required|string|min:8'
        ], [
            'email_user.required' => 'L’email est obligatoire.',
            'email_user.email' => 'L’adresse e-mail est invalide.',
            'tel_user.required' => 'Le numéro de téléphone est obligatoire.',
            'tel_user.digits' => 'Le numéro de téléphone doit contenir 10 carctères.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $existingUser = User::where('email_user', $request->email_user)->first();

            // Si un utilisateur existe déjà et est vérifié
            if ($existingUser && $existingUser->is_verify) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet email est déjà utilisé par un compte vérifié.'
                ], 409);
            }

            // Si un utilisateur existe mais non vérifié, on le supprime
            if ($existingUser && !$existingUser->is_verify) {
                $existingUser->delete();
            }

            // Génération du code OTP
            $otp = rand(100000, 999999);

            // Création du nouvel utilisateur
            $user = new User();
            $user->nom = $request->nom;
            $user->email_user = $request->email_user;
            $user->tel_user = $request->tel_user;
            $user->password = Hash::make($request->password);
            $user->otp = $otp;
            $user->otp_expire_at = Carbon::now()->addMinutes(10);
            $user->is_verify = false;
            $user->save();

            // Envoi du code OTP par e-mail
            Mail::to($request->email_user)->send(new SendOtpMail($otp));

            return response()->json([
                'success' => true,
                'message' => 'Un code OTP a été envoyé à votre adresse e-mail pour vérification.'
            ], 201);

        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de l’inscription.",
                'erreur' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Vérification du code OTP
    public function verify_otp(Request $request)
    {
        $request->validate([
            'email_user' => 'required|email',
            'otp' => 'required|string|size:6'
        ]);

        $user = User::where('email_user', $request->email_user)
                    ->where('otp', $request->otp)
                    ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Code OTP invalide.'
            ], 400);
        }

        if (Carbon::now()->greaterThan($user->otp_expire_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Le code OTP a expiré.'
            ], 400);
        }

        $user->is_verify = true;
        $user->otp = null;
        $user->otp_expire_at = null;
        $user->save();

        $token = $user->createToken('user_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Vérification réussie.',
            'data' => $user,
            'token' => $token
        ]);
    }

    public function login_user(Request $request)
{
    $request->validate([
        'email_user' => 'required|email',
        'password' => 'required|string|min:8'
    ]);

    $user = User::where('email_user', $request->email_user)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Identifiants incorrects.'
        ], 401);
    }

    // 🔹 Vérifie que le compte est vérifié
    if (!$user->is_verify) {
        return response()->json([
            'success' => false,
            'message' => 'Votre compte n’est pas encore vérifié. Veuillez d’abord vérifier votre compte.'
        ], 403);
    }

    // ✅ Création du token d’accès
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'success' => true,
        'message' => 'Connexion réussie',
        'user' => $user,
        'token' => $token
    ], 200);
}


    public function info_user(Request $request){
        try{
            $user = $request->user();
            if(!$user){
                return response()->json([
                    "success" => false,
                    "message" => "Utilisateur introuvable ou token invalide."
                ], 403);
            }
            return response()->json([
                "success" => true,
                "data" => $user,
                "message" => "Info de l’utilisateur affiché avec succès"
            ], 200);
        }
        catch(QueryException $e){
            return response()->json([
                "success" => false,
                "message" => "Erreur lors de l’affichage des informations de l’utilisateur",
                "erreur" => $e->getMessage()
            ], 500);
        }
    }



    public function login_admin(Request $request){
        $validator = Validator::make($request->all(), [
            'email_admin' => 'required|email',
            'password_admin' => 'required|string|min:8'
        ],[
            'email_admin.required' => 'L’email est obligatoire.',
            'email_admin.email' => 'L’email doit être de type mail',
            'password_admin.required' => 'Le mot de passe est obligatoire;',
            'password_admin.min' => 'Le mot de passe doit contenir au minimum 8 caracteres.'
        ]);

        if($validator->fails()){
            return response()->json([
                "success" => false,
                "message" => $validator->errors()->first()
            ], 422);
        }

        try{
            $admin = Admin::where("email_admin", $request->email_admin)->first();
            if(!$admin){
                return response()->json([
                    "success" => false,
                    "message" => "Mot de passe ou email incorrect"
                ], 404);
            }

            if($admin && Hash::check($request->password_admin, $admin->password_admin)){
                $token = $admin->createToken('admin_token')->plainTextToken;
                return response()->json([
                    "success" => true,
                    "data" => $admin,
                    "token" => $token,
                    "message" => "Connexion de l’administrateur réussie"
                ]);
            }
            return response()->json([
                "success" => false,
                "message" => "Mot de passe ou email incorrect"
            ], 404);
        }
        catch(QueryException $e){
            return response()->json([
                "success" => false,
                "message" => "Erreur lors de la connexion de l’administrateur",
                "erreur" => $e->getMessage()
            ]);
        }

    }

        public function info_admin(Request $request){
        try{
            $admin = $request->user();
            if(!$admin){
                return response()->json([
                    "success" => false,
                    "message" => "Administrateur introuvable ou token invalide."
                ], 403);
            }
            return response()->json([
                "success" => true,
                "data" => $admin,
                "message" => "Info de l’administrateur affiché avec succès"
            ], 200);
        }
        catch(QueryException $e){
            return response()->json([
                "success" => false,
                "message" => "Erreur lors de l’affichage des informations de l’administrateur",
                "erreur" => $e->getMessage()
            ], 500);
        }
    }
}
