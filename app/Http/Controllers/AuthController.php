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
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

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
        'tel_user.digits' => 'Le numéro de téléphone doit contenir 10 chiffres.',
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
        // Vérifier si un utilisateur existe déjà avec le même email ou téléphone
        $existingUser = User::where('email_user', $request->email_user)
            ->orWhere('tel_user', $request->tel_user)
            ->first();

        if ($existingUser) {
            // Si le compte est déjà vérifié => bloquer
            if ($existingUser->is_verify) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet email ou numéro est déjà utilisé par un compte vérifié.'
                ], 409);
            }

            // Sinon (non vérifié) => supprimer l'ancien pour recréer un nouveau
            $existingUser->delete();
        }

        // Génération du code OTP
        $otp = rand(1000, 9999);

        // Création du nouvel utilisateur
        $user = new User();
        $user->nom = $request->nom;
        $user->email_user = $request->email_user;
        $user->tel_user = $request->tel_user;
        $user->password = Hash::make($request->password);
        $user->otp = $otp;
        $user->otp_expire_at = Carbon::now()->addMinutes(10);
        $user->is_verify = false;
        $user->type_account = 0;
        $user->save();

        // Envoi du code OTP par e-mail
        Mail::to($user->email_user)->send(new SendOtpMail($otp));

        return response()->json([
            'success' => true,
            'message' => 'Un code OTP a été envoyé à votre adresse e-mail pour vérification.',
        ], 201);

    } catch (QueryException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de l’inscription.',
            'erreur' => $e->getMessage(),
        ], 500);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Une erreur est survenue.',
            'erreur' => $e->getMessage(),
        ], 500);
    }
}


    // ✅ Vérification du code OTP
    public function verify_otp(Request $request)
    {
        $request->validate([
            'email_user' => 'required|email',
            'otp' => 'required|string|size:4'
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

        $data = $user->toArray();
        unset($data['type_account']);
        $data['token'] = $token;

        return response()->json([
            'success' => true,
            'message' => 'Vérification réussie.',
            'data' => [
                "id" => $user->id,
                "nom" => $user->nom,
                "email_user" => $user->email_user,
                "tel_user" => $user->tel_user,
                "token" => $token
            ],
            'type_account' => $user->type_account
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

    $data = $user->toArray();
    $data['token'] = $token;

    return response()->json([
        'success' => true,
        'message' => 'Connexion réussie',
        'data' => $data
    ], 200);
}


public function info_user(Request $request)
{
    try {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "Utilisateur introuvable ou token invalide."
            ], 403);
        }

        // 🧩 Si c’est un ADMIN
        if (isset($user->email_admin)) {
            // ✅ Décodage JSON du tel_admin (si stocké comme texte JSON)
            $tel_admin = json_decode($user->tel_admin, true);

            $data = [
                "id" => $user->id,
                "nom" => $user->nom_admin,
                "email_user" => $user->email_admin,
                "tel_user" => [
                    "value" => $tel_admin['value'] ?? $user->tel_admin ?? null,
                    "is_whatsapp" => $tel_admin['is_whatsapp'] ?? $user->is_whatsapp ?? false,
                ],
                "image_profil" => $user->image_profil ?? null,
                "is_verify" => 1,
                "type_account" => $user->type_account,
                "created_at" => $user->created_at,
                "updated_at" => $user->updated_at,
            ];

            // 🔗 Lien WhatsApp pour admin
            if (($tel_admin['is_whatsapp'] ?? $user->is_whatsapp) && ($tel_admin['value'] ?? $user->tel_admin)) {
                $tel = preg_replace('/\D+/', '', $tel_admin['value'] ?? $user->tel_admin);
                $data['tel_user']['whatsapp_link'] = "https://wa.me/+225{$tel}";
            }

            return response()->json([
                "success" => true,
                "data" => $data,
                "message" => "Informations de l’administrateur affichées avec succès."
            ], 200);
        }

        // 🧍 Si c’est un UTILISATEUR
        $data = [
            "id" => $user->id,
            "nom" => $user->nom,
            "email_user" => $user->email_user,
            "tel_user" => $user->tel_user ? [
                "value" => $user->tel_user,
                "is_whatsapp" => (bool) $user->is_whatsapp_un,
            ] : null,
            "autre_tel" => $user->autre_tel ? [
                "value" => $user->autre_tel,
                "is_whatsapp" => (bool) $user->is_whatsapp_deux,
            ] : null,
            "image_profil" => $user->image_profil,
            "is_verify" => $user->is_verify,
            "type_account" => $user->type_account,
            "created_at" => $user->created_at,
            "updated_at" => $user->updated_at,
        ];

        // 🔗 Lien WhatsApp pour le premier numéro
        if ($user->is_whatsapp_un && $user->tel_user) {
            $tel = preg_replace('/\D+/', '', $user->tel_user);
            $data['tel_user']['whatsapp_link'] = "https://wa.me/+225{$tel}";
        }

        // 🔗 Lien WhatsApp pour le deuxième numéro
        if ($user->is_whatsapp_deux && $user->autre_tel) {
            $tel2 = preg_replace('/\D+/', '', $user->autre_tel);
            $data['autre_tel']['whatsapp_link'] = "https://wa.me/+225{$tel2}";
        }

        return response()->json([
            "success" => true,
            "data" => $data,
            "message" => "Informations de l’utilisateur affichées avec succès."
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "message" => "Erreur lors de l’affichage des informations.",
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


    public function update_info_user(Request $request)
{
    try {
        $user = $request->user();

        // ✅ Validation des champs
        $validator = Validator::make(
            $request->all(),
            [
                'nom' => 'nullable|string|max:255',
                'email_user' => 'nullable|email',
                'tel_user.value' => 'nullable|string|digits:10',
                'tel_user.is_whatsapp' => 'nullable|boolean',
                'autre_tel.value' => 'nullable|string|digits:10',
                'autre_tel.is_whatsapp' => 'nullable|boolean',
                'image_profil' => 'nullable|image|max:2048',
            ],
            [
                'nom.string' => 'Le nom doit être une chaîne de caractères.',
                'nom.max' => 'Le nom ne doit pas dépasser 255 caractères.',
                'email_user.email' => 'L’adresse e-mail n’est pas valide.',
                'tel_user.value.digits' => 'Le numéro de téléphone doit contenir 10 chiffres.',
                'autre_tel.value.digits' => 'Le second numéro doit contenir 10 chiffres.',
                'image_profil.image' => 'Le fichier doit être une image.',
                'image_profil.max' => 'L’image ne doit pas dépasser 2 Mo.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // ✅ Upload de l’image si fournie
        if ($request->hasFile('image_profil')) {
            try {
                $imageUrl = $this->uploadImageToHosting($request->file('image_profil'));
                $user->image_profil = $imageUrl;
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => "Erreur lors de l'envoi de l'image.",
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        // ✅ Mise à jour des champs simples
        if ($request->filled('nom')) {
            $user->nom = $request->nom;
        }
        if ($request->filled('email_user')) {
            $user->email_user = $request->email_user;
        }

        // 🟢 Gestion des champs imbriqués
        if ($request->has('tel_user.value')) {
            $user->tel_user = $request->input('tel_user.value');
        }
        if ($request->has('tel_user.is_whatsapp')) {
            $user->is_whatsapp_un = (bool) $request->input('tel_user.is_whatsapp');
        }

        if ($request->has('autre_tel.value')) {
            $user->autre_tel = $request->input('autre_tel.value');
        }
        if ($request->has('autre_tel.is_whatsapp')) {
            $user->is_whatsapp_deux = (bool) $request->input('autre_tel.is_whatsapp');
        }

        $user->save();

        // ✅ Réponse structurée
        $data = [
            "id" => $user->id,
            "nom" => $user->nom,
            "email_user" => $user->email_user,
            "tel_user" => [
                "value" => $user->tel_user,
                "is_whatsapp" => (bool) $user->is_whatsapp_un,
            ],
            "autre_tel" => [
                "value" => $user->autre_tel,
                "is_whatsapp" => (bool) $user->is_whatsapp_deux,
            ],
            "image_profil" => $user->image_profil,
            "is_verify" => $user->is_verify,
            "type_account" => $user->type_account,
        ];

        // 🔗 Ajout des liens WhatsApp automatiques
        if ($user->is_whatsapp_un && $user->tel_user) {
            $tel1 = preg_replace('/\D+/', '', $user->tel_user);
            $data['tel_user']['whatsapp_link'] = "https://wa.me/+225{$tel1}";
        }

        if ($user->is_whatsapp_deux && $user->autre_tel) {
            $tel2 = preg_replace('/\D+/', '', $user->autre_tel);
            $data['autre_tel']['whatsapp_link'] = "https://wa.me/+225{$tel2}";
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Informations mises à jour avec succès.',
        ], 200);

    } catch (QueryException $e) {
        return response()->json([
            'success' => false,
            'message' => "Erreur survenue lors de la mise à jour de l'utilisateur.",
            'error' => $e->getMessage(),
        ], 500);
    }
}






    private function uploadImageToHosting($image)
    {
        $apiKey = '9b1ab6564d99aab6418ad53d3451850b';

        if (!$image->isValid()) {
            throw new \Exception("Fichier image non valide.");
        }

        $imageContent = base64_encode(file_get_contents($image->getRealPath()));

        $response = Http::asForm()->post('https://api.imgbb.com/1/upload', [
            'key' => $apiKey,
            'image' => $imageContent,
        ]);

        if ($response->successful()) {
            return $response->json()['data']['url'];
        }

        throw new \Exception("Erreur lors de l'envoi de l'image : " . $response->body());
    }

public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required|string|min:8'
    ]);

    // 🔹 Vérifie d’abord dans la table des utilisateurs
    $user = User::where('email_user', $request->email)->first();

    if ($user && Hash::check($request->password, $user->password)) {

        if (!$user->is_verify) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte n’est pas encore vérifié. Veuillez d’abord vérifier votre compte.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $data = $user->toArray();
        unset($data['type_account']); // 👈 Retire la clé du tableau
        $data['token'] = $token;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'type_account' => $user->type_account, // 👈 Déplacé ici
            'data' => [
                "id" => $user->id,
                "nom" => $user->nom,
                "email_user" => $user->email_user,
                "tel_user" => $user->tel_user,
                "token" => $token
            ]
        ], 200);
    }

    // 🔹 Sinon, on vérifie dans la table des administrateurs
    $admin = Admin::where('email_admin', $request->email)->first();

    if ($admin && Hash::check($request->password, $admin->password_admin)) {
        $token = $admin->createToken('admin_token')->plainTextToken;

        $data = $admin->toArray();
        unset($data['type_account']); // 👈 Supprime du data
        $data['token'] = $token;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'type_account' => $admin->type_account, // 👈 Placé ici
            'data' => [
                "id" => $admin->id,
                "nom" => $admin->nom_admin,
                "email_user" => $admin->email_admin,
                "tel_user" => $admin->tel_admin,
                "token" => $token
            ]
        ], 200);
    }

    // 🔹 Si aucun utilisateur trouvé
    return response()->json([
        'success' => false,
        'message' => 'Aucun utilisateur trouvé.'
    ], 404);
}



public function creer_sous_admin(Request $request)
{
    $validator = Validator::make($request->all(), [
        'nom' => 'required|string|max:255',
        'email_user' => 'required|email|unique:admins,email_admin',
        'tel_user' => 'required|digits:10|unique:admins,tel_admin',
    ], [
        'nom.required' => 'Le nom est obligatoire.',
        'email_user.required' => 'L’adresse e-mail est obligatoire.',
        'email_user.email' => 'L’adresse e-mail est invalide.',
        'email_user.unique' => 'Cet e-mail est déjà utilisé.',
        'tel_user.required' => 'Le numéro de téléphone est obligatoire.',
        'tel_user.digits' => 'Le numéro de téléphone doit contenir 10 chiffres.',
        'tel_user.unique' => 'Ce numéro est déjà utilisé.',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => $validator->errors()->first()
        ], 422);
    }

    try {
        $nom = preg_replace('/\s+/', '', $request->nom);
        $partNom = Str::substr(Str::lower($nom), 0, 3);
        if (Str::length($partNom) < 3) {
            $partNom = Str::padRight($partNom, 3, 'x');
        }

        $telDigits = preg_replace('/\D/', '', $request->tel_user);
        $partTel = substr($telDigits, 0, 2);
        if (strlen($partTel) < 2) {
            $partTel = str_pad($partTel, 2, '0', STR_PAD_RIGHT);
        }

        $rawPassword = $partNom . $partTel . 'admin';
        $hashedPassword = Hash::make($rawPassword);
        
        $subAdmin = Admin::create([
            'nom_admin' => $request->nom,
            'email_admin' => $request->email_user,
            'tel_admin' => $request->tel_user,
            'is_whatsapp' => false,
            'password_admin' => $hashedPassword,
            'type_account' => 1 
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sous-administrateur créé avec succès.',
            'data' => [
                'id' => $subAdmin->id,
                'nom' => $subAdmin->nom_admin,
                'email_user' => $subAdmin->email_admin,
                'tel_user' => $subAdmin->tel_admin,
                'password' => $rawPassword
            ]
        ], 201);
    } catch (QueryException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la création du sous-administrateur.',
            'error' => $e->getMessage()
        ], 500);
    }
}




public function update_admin_info(Request $request)
{
    try {
        $admin = $request->user(); // admin authentifié

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => "Administrateur introuvable ou token invalide."
            ], 403);
        }

        // Validation (unique email/tel sauf pour l'admin courant)
        $validator = Validator::make($request->all(), [
            'nom_admin' => 'nullable|string|max:255',
            'email_admin' => [
                'nullable',
                'email',
                Rule::unique('admins', 'email_admin')->ignore($admin->id, 'id')
            ],
            'tel_admin' => [
                'nullable',
                'digits:10',
                Rule::unique('admins', 'tel_admin')->ignore($admin->id, 'id')
            ],
            // ajouter d'autres champs si besoin (ex: image, adresse...)
        ], [
            'email_admin.email' => 'L’adresse e-mail est invalide.',
            'email_admin.unique' => 'Cet e-mail est déjà utilisé.',
            'tel_admin.digits' => 'Le numéro de téléphone doit contenir 10 chiffres.',
            'tel_admin.unique' => 'Ce numéro est déjà utilisé.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Mise à jour conditionnelle
        if ($request->filled('nom_admin')) $admin->nom_admin = $request->nom_admin;
        if ($request->filled('email_admin')) $admin->email_admin = $request->email_admin;
        if ($request->filled('tel_admin')) $admin->tel_admin = $request->tel_admin;
        // si tu veux gérer un upload d'image, fais-le ici et assigne $admin->image = $url;

        $admin->save();

        $data = $admin->toArray();
        // Ne jamais renvoyer le password
        unset($data['password_admin']);

        return response()->json([
            'success' => true,
            'message' => 'Informations de l’administrateur mises à jour avec succès.',
            'data' => $data
        ], 200);

    } catch (QueryException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour des informations de l’administrateur.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function update_info(Request $request)
{
    try {
        $auth = $request->user();

        if (!$auth) {
            return response()->json([
                'success' => false,
                'message' => "Utilisateur ou administrateur introuvable ou token invalide."
            ], 403);
        }

        // 🔍 Détection du type (admin ou user)
        $isAdmin = isset($auth->email_admin);

        // ✅ Validation commune
        $validator = Validator::make($request->all(), [
            'nom' => 'nullable|string|max:255',
            'email_user' => [
                'nullable',
                'email',
                $isAdmin
                    ? Rule::unique('admins', 'email_admin')->ignore($auth->id, 'id')
                    : Rule::unique('users', 'email_user')->ignore($auth->id, 'id')
            ],
            'tel_user.value' => 'nullable|string|digits:10',
            'tel_user.is_whatsapp' => 'nullable|boolean',
            'autre_tel.value' => 'nullable|string|digits:10',
            'autre_tel.is_whatsapp' => 'nullable|boolean',
            'image_profil' => 'nullable|image|max:2048',
        ], [
            'nom.string' => 'Le nom doit être une chaîne de caractères.',
            'nom.max' => 'Le nom ne doit pas dépasser 255 caractères.',
            'email_user.email' => 'L’adresse e-mail n’est pas valide.',
            'tel_user.value.digits' => 'Le numéro de téléphone doit contenir 10 chiffres.',
            'autre_tel.value.digits' => 'Le second numéro doit contenir 10 chiffres.',
            'image_profil.image' => 'Le fichier doit être une image.',
            'image_profil.max' => 'L’image ne doit pas dépasser 2 Mo.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // ✅ Upload de l’image si fournie
        if ($request->hasFile('image_profil')) {
            try {
                $imageUrl = $this->uploadImageToHosting($request->file('image_profil'));
                $auth->image_profil = $imageUrl;
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => "Erreur lors de l'envoi de l'image.",
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        // ✅ Mise à jour du nom
        if ($request->filled('nom')) {
            if ($isAdmin) {
                $auth->nom_admin = $request->nom;
            } else {
                $auth->nom = $request->nom;
            }
        }

        // ✅ Mise à jour de l’e-mail
        if ($request->filled('email_user')) {
            if ($isAdmin) {
                $auth->email_admin = $request->email_user;
            } else {
                $auth->email_user = $request->email_user;
            }
        }

        // ✅ Téléphone principal
        if ($request->has('tel_user.value')) {
            if ($isAdmin) {
                $auth->tel_admin = $request->input('tel_user.value');
            } else {
                $auth->tel_user = $request->input('tel_user.value');
            }
        }

        if ($request->has('tel_user.is_whatsapp')) {
            if ($isAdmin) {
                $auth->is_whatsapp = (bool) $request->input('tel_user.is_whatsapp');
            } else {
                $auth->is_whatsapp_un = (bool) $request->input('tel_user.is_whatsapp');
            }
        }

        // ✅ Second téléphone (pour user uniquement)
        if (!$isAdmin) {
            if ($request->has('autre_tel.value')) {
                $auth->autre_tel = $request->input('autre_tel.value');
            }
            if ($request->has('autre_tel.is_whatsapp')) {
                $auth->is_whatsapp_deux = (bool) $request->input('autre_tel.is_whatsapp');
            }
        }

        $auth->save();

        // ✅ Structure de réponse commune
        $data = [
            "id" => $auth->id,
            "nom" => $isAdmin ? $auth->nom_admin : $auth->nom,
            "email_user" => $isAdmin ? $auth->email_admin : $auth->email_user,
            "tel_user" => [
                "value" => $isAdmin ? $auth->tel_admin : $auth->tel_user,
                "is_whatsapp" => (bool) ($isAdmin ? $auth->is_whatsapp : $auth->is_whatsapp_un),
            ],
            "autre_tel" => !$isAdmin ? [
                "value" => $auth->autre_tel,
                "is_whatsapp" => (bool) $auth->is_whatsapp_deux,
            ] : null,
            "image_profil" => $auth->image_profil,
            "type_account" => $auth->type_account,
        ];

        // 🔗 Lien WhatsApp principal
        $tel1 = preg_replace('/\D+/', '', $data['tel_user']['value']);
        if ($data['tel_user']['is_whatsapp'] && $tel1) {
            $data['tel_user']['whatsapp_link'] = "https://wa.me/+225{$tel1}";
        }

        // 🔗 Lien WhatsApp secondaire (user uniquement)
        if (!$isAdmin && $data['autre_tel'] && $data['autre_tel']['is_whatsapp']) {
            $tel2 = preg_replace('/\D+/', '', $data['autre_tel']['value']);
            $data['autre_tel']['whatsapp_link'] = "https://wa.me/+225{$tel2}";
        }

        return response()->json([
            'success' => true,
            'message' => 'Informations mises à jour avec succès.',
            'data' => $data,
        ], 200);

    } catch (QueryException $e) {
        return response()->json([
            'success' => false,
            'message' => "Erreur lors de la mise à jour des informations.",
            'error' => $e->getMessage(),
        ], 500);
    }
}


public function change_password(Request $request)
{
    try {
        $auth = $request->user();

        if (!$auth) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable ou token invalide.'
            ], 403);
        }

        $isAdmin = isset($auth->password_admin);

        $validator = Validator::make($request->all(), [
            'ancien_password' => 'required|string|min:8',
            'nouveau' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "success" => false,
                "message" => $validator->errors()->first()
            ], 422);
        }

        $old = $request->ancien_password;
        $new = $request->nouveau;

        // ✅ Vérifier ancien mot de passe
        $passwordField = $isAdmin ? 'password_admin' : 'password';
        if (!Hash::check($old, $auth->$passwordField)) {
            return response()->json([
                'success' => false,
                'message' => "L'ancien mot de passe est incorrect."
            ], 500);
        }

        // ✅ Mise à jour
        $auth->$passwordField = Hash::make($new);
        $auth->save();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe mis à jour avec succès.'
        ], 200);

    } catch (QueryException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour du mot de passe.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function liste_user(Request $request)
{
    try {
        $users = User::where('type_account', 0)->get();

        $data = $users->map(function ($user) {
            // 🔹 Transformer tel_user
            $user->tel_user = [
                'value' => $user->tel_user,
                'is_whatsapp' => (int) $user->is_whatsapp_un,
            ];

            // 🔹 Transformer autre_tel
            $user->autre_tel = [
                'value' => $user->autre_tel,
                'is_whatsapp' => (int) $user->is_whatsapp_deux,
            ];

            // 🔹 Retirer les anciens champs inutiles dans la réponse
            unset($user->is_whatsapp_un, $user->is_whatsapp_deux);

            return $user;
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Liste des utilisateurs affichée avec succès.',
        ], 200);

    } catch (QueryException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération de la liste des utilisateurs.',
            'erreur' => $e->getMessage(),
        ], 500);
    }
}


public function delete_user(Request $request, $id){
    try{
        $user = User::find($id);
        if(!$user){
            return response()->json([
                "success" => false,
                "message" => "Utilisateur non trouvé"
            ],404);
        }
        $user->delete();
        return response()->json([
            "success" => true,
            "message" => "Utilisateur supprimé avec succès."
        ]);

    }
    catch(QueryException $e){
        return response()->json([
            "success" => false,
            "message" => "Erreur lors de la suppression de l’utilisateur.",
            "erreur" => $e->getMessage()
        ]);
    }
}
public function liste_admin(Request $request)
{
    $admin = $request->user();

    // 🔐 Vérifie les droits d’accès
    if ($admin->type_account != 2) {
        return response()->json([
            'success' => false,
            'message' => 'Vous n’êtes pas autorisé à afficher la liste des admins.'
        ], 403);
    }

    try {
        $liste = Admin::where('type_account', 1)->get();

        $data = $liste->map(function ($item) {
            // 🔹 Recalcul du mot de passe si l’admin n’a jamais été modifié
            if ($item->created_at->equalTo($item->updated_at)) {
                $nom = preg_replace('/\s+/', '', $item->nom_admin);
                $partNom = Str::substr(Str::lower($nom), 0, 3);
                if (Str::length($partNom) < 3) {
                    $partNom = Str::padRight($partNom, 3, 'x');
                }

                $telDigits = preg_replace('/\D/', '', $item->tel_admin);
                $partTel = substr($telDigits, 0, 2);
                if (strlen($partTel) < 2) {
                    $partTel = str_pad($partTel, 2, '0', STR_PAD_RIGHT);
                }

                $rawPassword = $partNom . $partTel . 'admin';
                $password = $rawPassword;
            } else {
                $password = '-';
            }

            // 🔸 Structure identique à celle des users
            return [
                'id' => $item->id,
                'nom' => $item->nom_admin,
                'tel_user' => [
                    'value' => $item->tel_admin,
                    'is_whatsapp' => (int) $item->is_whatsapp_un ?? 0,
                ],
                'image_profil' => $item->image_profil ? url('storage/' . $item->image_profil) : null,
                'email_user' => $item->email_admin,
                'password' => $password,
                'otp' => $item->otp,
                'otp_expire_at' => $item->otp_expire_at,
                'is_verify' => $item->is_verify,
                'type_account' => $item->type_account,
                'remember_token' => $item->remember_token,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Liste des admins affichée avec succès.',
            'data' => $data
        ], 200);

    } catch (QueryException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de l’affichage de la liste des admins.',
            'erreur' => $e->getMessage()
        ], 500);
    }
}





}
