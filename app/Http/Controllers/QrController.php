<?php

namespace App\Http\Controllers;

use App\Models\Qr;
use App\Models\Cree;
use App\Models\Occasion;
use Illuminate\Database\QueryException;
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
    public function creer_qr(Request $request, $id_occasion)
{
    try {
        // ✅ Validation
        $validator = Validator::make($request->all(), [
            'nombre_qr' => 'required|integer|min:1|max:100',
        ], [
            'nombre_qr.required' => 'Le nombre de QR est requis.',
            'nombre_qr.integer' => 'Le nombre de QR doit être un entier.',
            'nombre_qr.min' => 'Le nombre de QR doit être au minimum 1.',
            'nombre_qr.max' => 'Le nombre de QR ne peut pas dépasser 100.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "success" => false,
                "message" => $validator->errors()->first()
            ], 422);
        }

        // ✅ Vérifier l'admin connecté
        $admin = Auth::user();
        if (!$admin) {
            return response()->json([
                "success" => false,
                "message" => "Token invalide ou expiré."
            ], 403);
        }

        // ✅ Vérifier si l’occasion existe
        $occasion = \App\Models\Occasion::find($id_occasion);
        if (!$occasion) {
            return response()->json([
                "success" => false,
                "message" => "Occasion introuvable."
            ], 404);
        }

        $qrs = [];

        // ✅ Création multiple de QR codes liés à cette occasion
        for ($i = 0; $i < $request->nombre_qr; $i++) {
            $qr = Qr::create([
                'id' => Str::uuid(),
                'is_active' => false,
                'link_id' => null,
                'image_qr' => null,
                'id_occasion' => $occasion->id,
                'id_objet' => null,
                'id_user' => null,
            ]);

            // Lien unique
            $link_id = "https://www.skanfy.com/{$qr->id}";

            // Génération du QR Code
            $qrSvg = QrCode::size(300)->generate($link_id);
            $qrBase64 = base64_encode($qrSvg);

            // Mise à jour du QR
            $qr->update([
                'link_id' => $link_id,
                'image_qr' => $qrBase64,
            ]);

            // Enregistrement de la création
            Cree::create([
                'id' => Str::uuid(),
                'admin_id' => $admin->id,
                'qr_id' => $qr->id,
            ]);

            $qrs[] = $qr;
        }

        return response()->json([
            "success" => true,
            "message" => "{$request->nombre_qr} QR Code(s) créés avec succès pour l’occasion '{$occasion->nom_occasion}'.",
            "data" => $qrs,
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
        // 🔑 Récupération du token et du user (non obligatoire)
        $token = $request->bearerToken();
        $userScanner = null;

        if ($token) {
            try {
                $userScanner = Auth::guard('sanctum')->user();
            } catch (\Exception $e) {
                $userScanner = null;
            }
        }

        // 🔍 Récupération du QR avec ses relations
        $qr = Qr::with(['user', 'occasion', 'objet'])->find($qrId);

        if (!$qr) {
            return response()->json([
                "success" => false,
                "message" => "QR Code non trouvé"
            ], 404);
        }

        // ✅ Formatage sans activer ni modifier le QR
        $data = $this->formatCommeInscription($qr);

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

        $token = $request->bearerToken();
        $userScanner = null;

        if ($token) {
            try {
                $userScanner = Auth::guard('sanctum')->user();
            } catch (\Exception $e) {
                $userScanner = null;
            }
        }

        $qr = Qr::with(['user', 'occasion', 'objet'])
            ->where('link_id', $request->link_id)
            ->first();

        if (!$qr) {
            return response()->json([
                "success" => false,
                "message" => "QR Code non trouvé"
            ], 404);
        }

        $data = $this->formatCommeInscription($qr);

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
private function formatCommeInscription($qr, $request = null)
{
    $request = $request ?? request();

    $token = $request->bearerToken();
    $userScanner = null;

    if ($token) {
        try {
            $userScanner = Auth::guard('sanctum')->user();
        } catch (\Exception $e) {
            $userScanner = null;
        }
    }

    $hasOwner = $qr->id_user ? 1 : 0;
    $owner = ($userScanner && $qr->id_user === $userScanner->id) ? 1 : 0;
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
        "owner" => $owner,
        "has_owner" => $hasOwner,
        "info" => $info,
        "user" => $qr->user ? [
            "id" => $qr->user->id,
            "nom" => $qr->user->nom,
            "email_user" => $qr->user->email_user,
            "tel_user" => $qr->user->tel_user,
            "autre_tel" => $qr->user->autre_tel,
            "image_profil" => $qr->user->image_profil
        ] : null,
        "occasion" => $qr->occasion ? [
            "id" => $qr->occasion->id,
            "nom_occasion" => $qr->occasion->nom_occasion,
        ] : null,
        "objet" => $qr->objet ? [
            "id" => $qr->objet->id,
            "nom_objet" => $qr->objet->nom_objet,
            "description" => $qr->objet->description ?? null,
            "additional_info" => $qr->objet->additional_info ?? null
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

public function liste_occasion(Request $request)
{
    try {
        // 🔹 Récupération de toutes les occasions
        $occasions = Occasion::all();

        if ($occasions->isEmpty()) {
            return response()->json([
                "success" => true,
                "data" => [],
                "message" => "Aucune occasion trouvée."
            ], 200);
        }

        // 🔹 Construction du format demandé
        $data = $occasions->map(function ($occasion) {
            // Total de QR générés pour cette occasion
            $nombreGenerated = Qr::where('id_occasion', $occasion->id)->count();

            // Total de QR qui ont un objet (donc scannés/activés)
            $nombreScanned = Qr::where('id_occasion', $occasion->id)
                                ->whereNotNull('id_objet')
                                ->count();

            return [
                "id" => $occasion->id,
                "name" => $occasion->nom_occasion,
                "description" => $occasion->description ?? "Aucune description disponible",
                "nombre_generated" => $nombreGenerated,
                "nombre_scanned" => $nombreScanned
            ];
        });

        return response()->json([
            "success" => true,
            "message" => "Liste des occasions récupérée avec succès.",
            "data" => $data
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "message" => "Erreur lors de la récupération des occasions.",
            "erreur" => $e->getMessage()
        ], 500);
    }
}

public function delete_occasion(Request $request, $id){
    try{
        $occasion = Occasion::find($id);
        if(!$occasion){
            return response()->json([
                "success" => false,
                "message" => "Occasion non trouvée"
            ],404);
        }
        $occasion->delete();
        return response()->json([
            "success" => true,
            "message" => "Occasion supprimee avec succès"
        ],200);
    }
    catch(QueryException $e){
        return response()->json([
            "success" =>false,
            "message" => "Erreur lors de la supression de l’occasion",
            "erreur" => $e->getMessage()
        ]);
    }

}

public function ajout_occasion(Request $request){
    $validator = Validator::make($request->all(), [
        'nom_occasion' => "required|string",
        'description' => "nullable"
    ],[
        'nom_occasion.required' => "Le nom de l’occasion est requis."
    ]);

    if($validator->fails()){
        return response()->json([
            "success" => false,
            "message" => $validator->errors()->first()
        ]);
    }
    try{
        $occasion = new Occasion();
        $occasion->nom_occasion = $request->nom_occasion;
        $occasion->description = $request->description;
        $occasion->save();

        return response()->json([
            "success" => true,
            "data" => $occasion,
            "message" => "Occasion crée avec succès"
        ]);

    }
    catch(QueryException $e){
        return response()->json([
            "success" => false,
            "message" => "Erreur lors de la création d’une occasion",
            "erreur" => $e->getMessage()
        ]);
    }
}

public function update_occasion(Request $request, $id){
    $occasion = Occasion::find($id);
    $validator = Validator::make($request->all(), [
        'nom_occasion' => "required|string",
        'description' => "nullable"
    ],[
        'nom_occasion.required' => "Le nom de l’occasion est requis."
    ]);

    if($validator->fails()){
        return response()->json([
            "success" => false,
            "message" => $validator->errors()->first()
        ]);
    }
    try{
        $occasion->nom_occasion = $request->nom_occasion;
        $occasion->description = $request->description;
        $occasion->save();

        return response()->json([
            "success" => true,
            "data" => $occasion,
            "message" => "Occasion mis à jour avec succès"
        ]);

    }
    catch(QueryException $e){
        return response()->json([
            "success" => false,
            "message" => "Erreur lors de la mise à jour de l’occasion",
            "erreur" => $e->getMessage()
        ]);
    }
}


}
