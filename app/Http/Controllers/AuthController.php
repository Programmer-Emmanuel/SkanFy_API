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

class AuthController extends Controller
{
public function register_user(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'nullable|string',
            'email_user' => 'required|email|unique:users',
            'tel_user' => 'nullable|digits:10|unique:users',
            'password' => 'required|string|min:8'
        ], [
            'email_user.required' => 'L’email est obligatoire.',
            'email_user.email' => 'L’adresse e-mail est invalide.',
            'email_user.unique' => 'L’email est déjà utilisé.',
            'tel_user.required' => 'Le numéro de téléphone est obligatoire.',
            'tel_user.digits' => 'Le numéro de téléphone doit contenir 10 carctères.',
            'tel_user.unique' => 'Le numéro de telephone est déjà utilisé.',
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

        // Convertir l'utilisateur en tableau
        $data = $user->toArray();

        // ✅ Si le user a WhatsApp, on ajoute le lien WhatsApp
        if ($user->is_whatsapp && $user->tel_user) {
            // On nettoie le numéro (au cas où il contiendrait des espaces ou des symboles)
            $tel = preg_replace('/\D+/', '', $user->tel_user);
            $data['whatsapp'] = "https://wa.me/+225{$tel}";
        }

        return response()->json([
            "success" => true,
            "data" => $data,
            "message" => "Info de l’utilisateur affichée avec succès"
        ], 200);

    } catch (QueryException $e) {
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

    public function update_info_user(Request $request)
{
    try {
        $user = $request->user();

        // ✅ Validation avec messages personnalisés
        $validator = Validator::make(
            $request->all(),
            [
                'nom' => 'nullable|string|max:255',
                'email_user' => 'nullable|email',
                'tel_user' => 'nullable|string|digits:10',
                'autre_tel' => 'nullable|string|digits:10',
                'is_whatsapp' => 'nullable|boolean',
                'image_profil' => 'nullable|image|max:2048',
            ],
            [
                'nom.string' => 'Le nom doit être une chaîne de caractères.',
                'nom.max' => 'Le nom ne doit pas dépasser 255 caractères.',
                'email_user.email' => 'L’adresse e-mail n’est pas valide.',
                'tel_user.digits' => 'Le numéro de téléphone doit contenir 10 chiffres.',
                'autre_tel.digits' => 'Le second numéro de téléphone doit contenir 10 chiffres.',
                'image_profil.image' => 'Le fichier doit être une image.',
                'image_profil.max' => 'L’image ne doit pas dépasser 2 Mo.',
            ]
        );

        // ❌ Si la validation échoue
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

        // ✅ Mise à jour uniquement si le champ est rempli
        $user->nom = $request->nom;
        if ($request->filled('email_user')) $user->email_user = $request->email_user;
        $user->tel_user = $request->tel_user;
        $user->autre_tel = $request->autre_tel;
        $user->is_whatsapp = (bool)$request->is_whatsapp;

        $user->save();

        // ✅ Réponse finale
        $response = [
            'success' => true,
            'data' => $user,
            'message' => 'Informations mises à jour avec succès.',
        ];

        // ✅ Lien WhatsApp si applicable
        if (!empty($user->autre_tel) && $user->is_whatsapp) {
            $numero = preg_replace('/\D/', '', $user->tel_user);
            $response['whatsapp'] = "https://wa.me/+225{$numero}";
        }

        return response()->json($response, 200);

    } catch (QueryException $e) {
        return response()->json([
            'success' => false,
            'message' => "Erreur survenue lors de la modification des informations de l'utilisateur.",
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
            'nom_admin' => 'required|string|max:255',
            'email_admin' => 'required|email|unique:admins,email_admin',
            'tel_admin' => 'required|digits:10|unique:admins,tel_admin',
            'password_admin' => 'required|string|min:8',
        ], [
            'nom_admin.required' => 'Le nom est obligatoire.',
            'email_admin.required' => 'L’adresse e-mail est obligatoire.',
            'email_admin.email' => 'L’adresse e-mail est invalide.',
            'email_admin.unique' => 'Cet e-mail est déjà utilisé.',
            'tel_admin.required' => 'Le numéro de téléphone est obligatoire.',
            'tel_admin.digits' => 'Le numéro de téléphone doit contenir 10 chiffres.',
            'tel_admin.unique' => 'Ce numéro est déjà utilisé.',
            'password_admin.required' => 'Le mot de passe est obligatoire.',
            'password_admin.min' => 'Le mot de passe doit contenir au moins 8 caractères.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $subAdmin = Admin::create([
                'nom_admin' => $request->nom_admin,
                'email_admin' => $request->email_admin,
                'tel_admin' => $request->tel_admin,
                'password_admin' => Hash::make($request->password_admin),
                'type_account' => 1 
            ]);

            $token = $subAdmin->createToken('auth_token')->plainTextToken;

            $data = $subAdmin->toArray();
            $data['token'] = $token;

            return response()->json([
                'success' => true,
                'message' => 'Sous-administrateur créé avec succès.',
                'data' => $subAdmin
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

public function change_admin_password(Request $request)
{
    try{
        $validator = Validator::make($request->all(),[
        'old_password' => 'required|string|min:8',
        'new_password' => 'required|string|min:8' // new_password_confirmation
    ], [
        'old_password.required' => "L'ancien mot de passe est requis.",
        'new_password.required' => 'Le nouveau mot de passe est requis.',
        'new_password.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
    ]);

    if($validator->fails()){
        return response()->json([
            "success" => false,
            "message" => $validator->errors()->first()
        ],422);
    }
    $admin = $request->user();
    if (!$admin) {
        return response()->json([
            'success' => false,
            'message' => 'Administrateur introuvable ou token invalide.'
        ], 403);
    }

    // Vérifier l'ancien mot de passe
    if (!Hash::check($request->old_password, $admin->password_admin)) {
        return response()->json([
            'success' => false,
            'message' => "L'ancien mot de passe est incorrect."
        ], 401);
    }

    // Tout est ok -> mise à jour
    $admin->password_admin = Hash::make($request->new_password);
    $admin->save();

    // Optionnel : révoquer tous les tokens pour forcer reconnexion
    // $admin->tokens()->delete();

    return response()->json([
        'success' => true,
        'message' => 'Mot de passe mis à jour avec succès.'
    ], 200);
    }
    catch(QueryException $e){
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour du mot de passe de l’administrateur.',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function change_user_password(Request $request)
{
    try{
        $validator = Validator::make($request->all(),[
        'ancien_password' => 'required|string|min:8',
        'nouveau' => 'required|string|min:8' // new_password_confirmation
    ], [
        'ancien_password.required' => "L'ancien mot de passe est requis.",
        'nouveau.required' => 'Le nouveau mot de passe est requis.',
        'nouveau.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
    ]);

    if($validator->fails()){
        return response()->json([
            "success" => false,
            "message" => $validator->errors()->first()
        ],422);
    }
    $user = $request->user();
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Utilisateur introuvable ou token invalide.'
        ], 403);
    }

    // Vérifier l'ancien mot de passe
    if (!Hash::check($request->ancien_password, $user->password)) {
        return response()->json([
            'success' => false,
            'message' => "L'ancien mot de passe est incorrect."
        ], 401);
    }

    // Tout est ok -> mise à jour
    $user->password = Hash::make($request->nouveau);
    $user->save();

    // Optionnel : révoquer tous les tokens pour forcer reconnexion
    // $user->tokens()->delete();

    return response()->json([
        'success' => true,
        'message' => 'Mot de passe mis à jour avec succès.'
    ], 200);
    }
    catch(QueryException $e){
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour du mot de passe de l’utilisateur.',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function liste_user(){
    try{
        $user = User::all();
        if(empty($user)){
            return response()->json([
                "success" => true,
                "data" => [],
                "message" => "Liste des utilisateurs affichées"
            ]);
        }
            return response()->json([
                "success" => true,
                "data" => $user,
                "message" => "Liste des utilisateurs affichées"
            ]);
    }
    catch(QueryException $e){
        return response()->json([
            "success" => false,
            "message" => "Erreur lors de l’affichage de la liste des utilisateurs.",
            "erreur" => $e->getMessage()
        ]);
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



}
