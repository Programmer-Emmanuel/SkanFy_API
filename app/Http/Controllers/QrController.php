<?php

namespace App\Http\Controllers;

use App\Models\Qr;
use App\Models\Cree;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrController extends Controller
{
    /**
     * ✅ Création d’un QR Code par un admin
     */
    public function creer_qr(Request $request)
{
    try {
        // ✅ Validation
        $validator = Validator::make($request->all(), [
            'nombre_qr' => 'required|integer|min:1|max:100',
            'nom_occasion' => 'required|string|max:255',
        ], [
            'nombre_qr.required' => 'Le nombre de QR est requis.',
            'nombre_qr.integer' => 'Le nombre de QR doit être un entier.',
            'nombre_qr.min' => 'Le nombre de QR doit être au minimum 1.',
            'nombre_qr.max' => 'Le nombre de QR ne peut pas dépasser 100.',
            'nom_occasion.required' => 'Le nom de l’occasion est requis.',
            'nom_occasion.string' => 'Le nom de l’occasion doit être une chaîne de caractères.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "success" => false,
                "message" => $validator->errors()->first()
            ], 422);
        }

        // ✅ Récupération de l’admin connecté
        $admin = Auth::user();
        if (!$admin) {
            return response()->json([
                "success" => false,
                "message" => "Token invalide ou expiré."
            ], 403);
        }

        // ✅ Création de l’occasion
        $occasion = \App\Models\Occasion::create([
            'id' => Str::uuid(),
            'nom_occasion' => $request->nom_occasion,
        ]);

        $qrs = [];

        // ✅ Création multiple de QR codes
        for ($i = 0; $i < $request->nombre_qr; $i++) {

            // Étape 1️⃣ : créer le QR sans link_id pour avoir un ID effectif
            $qr = Qr::create([
                'id' => Str::uuid(),
                'is_active' => false,
                'link_id' => null,
                'image_qr' => null,
                'id_occasion' => $occasion->id,
                'id_objet' => null,
                'id_user' => null,
            ]);

            // Étape 2️⃣ : créer le link_id basé sur l'ID réel
            $link_id = "https://site-front.com/{$qr->id}";

            // Étape 3️⃣ : générer le QR code avec ce lien
            $qrSvg = QrCode::size(300)->generate($link_id);
            $qrBase64 = base64_encode($qrSvg);

            // Étape 4️⃣ : mettre à jour le QR avec son lien et image
            $qr->update([
                'link_id' => $link_id,
                'image_qr' => $qrBase64,
            ]);

            // Étape 5️⃣ : enregistrer la relation avec l'admin
            Cree::create([
                'id' => Str::uuid(),
                'admin_id' => $admin->id,
                'qr_id' => $qr->id,
            ]);

            $qrs[] = $qr;
        }

        return response()->json([
            "success" => true,
            "message" => "{$request->nombre_qr} QR Code(s) créés avec succès pour l’occasion '{$request->nom_occasion}'.",
            "data" => $qrs, // Tu peux aussi renvoyer $this->formatCommeInscription($qrs) si tu veux un format particulier
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "message" => "Erreur lors de la création des QR.",
            "erreur" => $e->getMessage(),
        ], 500);
    }
}


    /**
     * ✅ Scan d’un QR Code par son ID
     */
public function scanner_qr(Request $request, $qrId)
{
    try {
        $userScanner = Auth::user();

        // 🔍 Récupération du QR avec ses relations
        $qr = Qr::with(['user', 'occasion', 'objet'])->find($qrId);
        if (!$qr) {
            return response()->json([
                "success" => false,
                "message" => "QR Code non trouvé"
            ], 404);
        }

        // ❌ Aucun changement dans la base (pas d’update de is_active ni id_user)
        $qr->load(['user', 'occasion', 'objet']);

        // ✅ Formatage des données finales
        $data = $this->formatCommeInscription($qr);

        // 👤 Si un utilisateur est connecté, on peut renvoyer ses infos à titre indicatif
        // if ($userScanner) {
        //     $data['user_scanner'] = [
        //         'id' => $userScanner->id,
        //         'nom' => $userScanner->nom ?? null,
        //         'email_user' => $userScanner->email_user ?? null,
        //         'tel_user' => $userScanner->tel_user ?? null,
        //         'type_account' => $userScanner->type_account ?? null,
        //     ];
        // }

        $data['has_owner'] = $qr->id_user ? 1 : 0;

        return response()->json([
            "success" => true,
            "message" => "QR Code scanné avec succès",
            "data" => $data
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "message" => "Erreur lors du scan du QR",
            "erreur" => $e->getMessage()
        ], 500);
    }
}
public function scanner_via_lien(Request $request)
{
    try {
        $request->validate([
            'link_id' => 'required|string'
        ]);

        $userScanner = Auth::user();

        // 🔍 Recherche du QR via son lien
        $qr = Qr::with(['user', 'occasion', 'objet'])
            ->where('link_id', $request->link_id)
            ->first();

        if (!$qr) {
            return response()->json([
                "success" => false,
                "message" => "QR Code non trouvé"
            ], 404);
        }

        // ❌ Aucun update — simple consultation
        $qr->load(['user', 'occasion', 'objet']);

        // ✅ Formatage final
        $data = $this->formatCommeInscription($qr);

        // 👤 Inclure les infos du user connecté si présent (facultatif)
        // if ($userScanner) {
        //     $data['user_scanner'] = [
        //         'id' => $userScanner->id,
        //         'nom_user' => $userScanner->nom_user ?? null,
        //         'email_user' => $userScanner->email_user ?? null,
        //         'tel_user' => $userScanner->tel_user ?? null,
        //         'type_account' => $userScanner->type_account ?? null,
        //     ];
        // }

        $data['has_owner'] = $qr->id_user ? 1 : 0;

        return response()->json([
            "success" => true,
            "message" => "QR Code scanné avec succès",
            "data" => $data
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "message" => "Erreur lors du scan du lien",
            "erreur" => $e->getMessage()
        ], 500);
    }
}





    /**
     * ✅ Active le QR et associe le user scanner
     */
    private function activateQrAndAssociateUser($qr, $userScanner)
    {
        $qr->update([
            'id_user' => $userScanner->id,
            'is_active' => true
        ]);

        $qr->load(['user', 'occasion', 'objet']);

        return response()->json([
            "success" => true,
            "message" => "QR Code activé avec succès",
            "data" => $this->formatCommeInscription($qr)
        ], 200);
    }

    /**
     * ✅ Formatage = réinitialisation du QR (sans propriétaire)
     */
    public function formater_qr($qrId)
    {
        try {
            $qr = Qr::find($qrId);

            if (!$qr) {
                return response()->json([
                    "success" => false,
                    "message" => "QR Code non trouvé"
                ], 404);
            }

            // On "vide" les relations
            $qr->update([
                'id_user' => null,
                'id_objet' => null,
                'id_occasion' => null,
                'is_active' => false
            ]);

            return response()->json([
                "success" => true,
                "message" => "QR Code formaté avec succès (désormais sans propriétaire)",
                "data" => $this->formatCommeInscription($qr->fresh(['user', 'occasion', 'objet']))
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Erreur lors du formatage du QR",
                "erreur" => $e->getMessage()
            ], 500);
        }
    }

    public function formater_qr_user(Request $request, $qrId)
{
    try {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "Utilisateur introuvable ou token invalide."
            ], 401);
        }

        $qr = Qr::find($qrId);

        if (!$qr) {
            return response()->json([
                "success" => false,
                "message" => "QR Code non trouvé."
            ], 404);
        }

        // Vérifie que le QR appartient à l’utilisateur
        if ($qr->id_user !== $user->id) {
            return response()->json([
                "success" => false,
                "message" => "Ce QR Code n’appartient pas à cet utilisateur."
            ], 403);
        }

        // ✅ On vide les relations
        $qr->update([
            'id_user' => null,
            'id_objet' => null,
            'id_occasion' => null,
            'is_active' => false
        ]);

        return response()->json([
            "success" => true,
            "message" => "QR Code formaté avec succès. Il est désormais libre d’utilisation.",
            "data" => $this->formatCommeInscription($qr->fresh(['user', 'occasion', 'objet']))
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "message" => "Erreur lors du formatage du QR Code.",
            "erreur" => $e->getMessage()
        ], 500);
    }
}


    /**
     * ✅ Affiche le QR formaté (lecture seule)
     */
    public function getQrFormatted($qrId)
    {
        try {
            $qr = Qr::with(['user', 'occasion', 'objet'])->find($qrId);

            if (!$qr) {
                return response()->json([
                    "success" => false,
                    "message" => "QR Code non trouvé"
                ], 404);
            }

            return response()->json([
                "success" => true,
                "message" => "QR Code formaté avec succès",
                "data" => $this->formatCommeInscription($qr)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Erreur lors de la récupération du QR",
                "erreur" => $e->getMessage()
            ], 500);
        }
    }


    /**
 * ✅ Liste tous les QR Codes avec leurs relations et infos formatées
 */
public function liste_qr()
{
     $admin = Auth::user();
            if (!$admin) {
                return response()->json([
                    "success" => false,
                    "message" => "Token invalid"
                ], 401);
            }
    try {
        // Récupération de tous les QR avec leurs relations
        $qrs = Qr::with(['user', 'objet', 'occasion'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Formattage uniforme
        $data = $qrs->map(function ($qr) {
            return $this->formatCommeInscription($qr);
        });

        return response()->json([
            "success" => true,
            "message" => "Liste des QR Codes récupérée avec succès",
            "count" => $qrs->count(),
            "data" => $data
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "message" => "Erreur lors de la récupération des QR Codes",
            "erreur" => $e->getMessage()
        ], 500);
    }
}



    /**
     * ✅ Format de sortie cohérent pour le frontend
     */
private function formatCommeInscription($qr)
{
    $userScanner = Auth::user();

    // 🧠 Vérifie si le QR appartient à un utilisateur
    $hasOwner = $qr->id_user ? 1 : 0;

    // 🔐 Vérifie si l'utilisateur connecté est le propriétaire
    $owner = ($userScanner && $qr->id_user === $userScanner->id) ? 1 : 0;

    // 📦 Vérifie s’il y a un objet lié
    $info = $qr->objet ? 1 : 0;

    return [
        "qr" => [
            "id" => $qr->id,
            "is_active" => $qr->is_active,
            "link_id" => $qr->link_id,
            "image_qr" => $qr->image_qr,
            "created_at" => $qr->created_at,
            "updated_at" => $qr->updated_at,
        ],
        "owner" => $owner,       // ✅ 1 = c’est moi le propriétaire
        "has_owner" => $hasOwner, // ✅ 1 = un utilisateur possède déjà ce QR
        "info" => $info,         // ✅ 1 = contient un objet
        "user" => $qr->user ? [
            "id" => $qr->user->id,
            "nom" => $qr->user->nom,
            "email_user" => $qr->user->email_user,
            "tel_user" => $qr->user->tel_user,
        ] : null,
        "occasion" => $qr->occasion ? [
            "id" => $qr->occasion->id,
            "nom_occasion" => $qr->occasion->nom_occasion,
        ] : null,
        "objet" => $qr->objet ? [
            "id" => $qr->objet->id,
            "nom_objet" => $qr->objet->nom_objet,
            "description" => $qr->objet->description ?? null
        ] : null,
    ];
}




    public function liste_qr_par_occasion(Request $request)
{
    $admin = Auth::user();
    if (!$admin) {
        return response()->json([
            "success" => false,
            "message" => "Token invalide ou expiré."
        ], 401);
    }

    try {
        // ✅ Validation
        $validator = Validator::make($request->all(), [
            'nom_occasion' => 'required|string|min:2',
        ], [
            'nom_occasion.required' => 'Le champ nom_occasion est requis.',
            'nom_occasion.string' => 'Le champ nom_occasion doit être une chaîne de caractères.',
            'nom_occasion.min' => 'Le champ nom_occasion doit contenir au moins 2 caractères.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "success" => false,
                "message" => $validator->errors()->first()
            ], 422);
        }

        $nom = strtolower($request->nom_occasion);

        // ✅ Recherche insensible à la casse (LOWER)
        $qrs = Qr::with(['user', 'objet', 'occasion'])
            ->whereHas('occasion', function ($query) use ($nom) {
                $query->whereRaw('LOWER(nom_occasion) LIKE ?', ["%{$nom}%"]);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        if ($qrs->isEmpty()) {
            return response()->json([
                "success" => false,
                "message" => "Aucun QR Code trouvé pour l’occasion contenant '{$nom}'."
            ], 404);
        }

        $data = $qrs->map(function ($qr) {
            return $this->formatCommeInscription($qr);
        });

        return response()->json([
            "success" => true,
            "message" => "Liste des QR Codes trouvés pour l’occasion contenant '{$nom}'.",
            "count" => $qrs->count(),
            "data" => $data
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "message" => "Erreur lors de la recherche des QR Codes.",
            "erreur" => $e->getMessage()
        ], 500);
    }
}

}
