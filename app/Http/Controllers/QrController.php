<?php

namespace App\Http\Controllers;

use App\Models\Qr;
use App\Models\Cree;
use App\Models\Occasion;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use ZipArchive;

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
            'id_occasion' => 'required' 
        ], [
            'nombre_qr.required' => 'Le nombre de QR est requis.',
            'nombre_qr.integer' => 'Le nombre de QR doit être un entier.',
            'nombre_qr.min' => 'Le nombre de QR doit être au minimum 1.',
            'nombre_qr.max' => 'Le nombre de QR ne peut pas dépasser 100.',
            'id_occasion.required' => "L’id de l’occasion doit être requis."
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
        $occasion = Occasion::find($request->id_occasion);
        if (!$occasion) {
            return response()->json([
                "success" => false,
                "message" => "Occasion introuvable."
            ], 404);
        }

        // ✅ Préparer les dossiers
        $rootPath = storage_path('app/occasions');
        if (!File::exists($rootPath)) {
            File::makeDirectory($rootPath, 0777, true, true);
        }

        $occasionPath = $rootPath . '/' . $occasion->nom_occasion;
        if (!File::exists($occasionPath)) {
            File::makeDirectory($occasionPath, 0777, true, true);
        }

        $qrs = [];
        $maxGeneration = Qr::where('id_occasion', $request->id_occasion)->max('generation');
        $newGeneration = is_null($maxGeneration) ? 1 : $maxGeneration + 1; // Commence à 1 si aucun QR


        // ✅ Création multiple de QR codes
        for ($i = 0; $i < $request->nombre_qr; $i++) {
            $qr = Qr::create([
                'id' => Str::uuid(),
                'is_active' => false,
                'link_id' => null,
                'image_qr' => null,
                'id_occasion' => $occasion->id,
                'id_objet' => null,
                'id_user' => null,
                'generation' => $newGeneration
            ]);
            // 🔗 Ton lien unique
$link_id = "https://skanfy.com/info-qr/{$qr->id}";

// 📸 Ton logo (dans storage)
$logoPath = public_path('storage/images/image.jpg');

// 🔄 Vérifie que l’image existe
if (!file_exists($logoPath)) {
    dd("Logo introuvable : " . $logoPath);
}

// 🌀 Génération du QR en SVG simple
$qrSvg = QrCode::format('svg')
    ->size(300)
    ->generate($link_id);

// 🧠 Convertir le logo en base64 (pour l'injecter dans le SVG)
$logoBase64 = base64_encode(file_get_contents($logoPath));

// ⚙️ Taille et position du logo dans le SVG
$logoSize = 70; // largeur et hauteur du logo
$qrSize = 300;
$x = ($qrSize - $logoSize) / 2;
$y = ($qrSize - $logoSize) / 2;

// 🖼️ Ajouter le logo (avec contour blanc arrondi)
$logoSvg = "
    <rect x='$x' y='$y' width='$logoSize' height='$logoSize' rx='15' ry='15' fill='white'/>
    <image href='data:image/jpeg;base64,$logoBase64' x='$x' y='$y' width='$logoSize' height='$logoSize' clip-path='url(#rounded)'/>
";

// 💬 Injecter le logo dans le SVG
$qrSvgWithLogo = str_replace('</svg>', $logoSvg . '</svg>', $qrSvg);

// ✅ Base64 final à enregistrer ou afficher
$qrBase64 = base64_encode($qrSvgWithLogo);

            // ✅ Sauvegarder la version SVG dans la base
            $qr->update([
                'link_id' => $link_id,
                'image_qr' => $qrBase64,
            ]);

            // 🖼️ Sauvegarde locale du PNG sans Imagick
            // On envoie le SVG vers une API externe qui convertit en PNG
            $response = Http::asForm()->post('https://api.qrserver.com/v1/create-qr-code/', [
                'data' => $link_id,
                'size' => '300x300',
            ]);

            if ($response->successful()) {
                $pngData = $response->body();
                $pngPath = $occasionPath . '/' . $qr->id . '.png';
                File::put($pngPath, $pngData);
            }

            // ✅ Journalisation
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
            "tel_user" => $qr->user->tel_user ? [
                'value' => $qr->user->tel_user,
                'is_whatsapp' => $qr->user->is_whatsapp_un ?? 0
            ] : null,
            "autre_tel" => $qr->user->autre_tel ? [
                'value' => $qr->user->autre_tel,
                'is_whatsapp' => $qr->user->is_whatsapp_deux ?? 0
            ] : null,
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

public function occasion($id){
    try{
        $occasion = Occasion::find($id);
        $nombreGenerated = Qr::where('id_occasion', $occasion->id)->count();

            // Total de QR qui ont un objet (donc scannés/activés)
            $nombreScanned = Qr::where('id_occasion', $occasion->id)
                                ->whereNotNull('id_objet')
                                ->count();
        if(!$occasion){
            return response()->json([
                "success" => false,
                "message" => "Occasion non trouvé."
            ],404);
        }
        return response()->json([
            "success" => true,
            "data" => [
                "id" => $occasion->id,
                "name" => $occasion->nom_occasion,
                "description" => $occasion->description ?? "Aucune description disponible",
                "nombre_generated" => $nombreGenerated,
                "nombre_scanned" => $nombreScanned
            ]
        ]);
    }
    catch(QueryException $e){
        return response()->json([
            "success" => false,
            "message" => "Erreur survenue lors de l’afficage de l’occasion",
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

        $rootPath = storage_path('app/occasions');
        if (!File::exists($rootPath)) {
            File::makeDirectory($rootPath, 0777, true, true);
        }

        $occasionPath = $rootPath . '/' . $occasion->nom_occasion;
        if (!File::exists($occasionPath)) {
            File::makeDirectory($occasionPath, 0777, true, true);
        }

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

public function historique_occasion()
{
    try {
        // Récupérer les occasions avec au moins un QR généré
        $occasions = Occasion::with('qrs')
            ->withCount('qrs')
            ->having('qrs_count', '>', 0)
            ->get();

        $data = $occasions->map(function ($occasion) {
            // Calcul du nombre de générations
            $generationCount = $occasion->qrs->groupBy('generation')->count();

            return [
                'id' => $occasion->id,
                'organisation' => $occasion->nom_occasion,
                'generated_number' => $occasion->qrs_count,
                'total_history' => $generationCount,
                'download_link' => route('occasions.download.zip', ['id' => $occasion->id]),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Historiques des occasions'
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de l\'affichage de la liste des historiques.',
            'erreur' => $e->getMessage(),
        ], 500);
    }
}


public function generations_occasion($id)
{
    try {
        $occasion = Occasion::with(['qrs' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }])
        ->withCount('qrs')
        ->find($id);

        if (!$occasion) {
            return response()->json([
                'success' => false,
                'message' => 'Occasion introuvable.',
            ], 404);
        }

        // Regrouper les QR par génération
        $generations = $occasion->qrs
            ->groupBy('generation')
            ->map(function ($group, $generation) use ($occasion) {
                return [
                    'id' => $generation + 1,
                    'nom_generation' => $occasion->nom_occasion . " " .$generation + 1,
                    'number_generated' => $group->count(),
                    'generated_at' => optional($group->first()->created_at)->format('Y-m-d H:i:s'),
                    'download_link' => route('occasions.download.zip', [
                        'id' => $occasion->id,
                        'generation' => $generation
                    ])
                ];
            })
            ->values(); // format array

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $occasion->id,
                'organisation' => $occasion->nom_occasion,
                'generated_number' => $occasion->qrs_count,
                'download_link' => route('occasions.download.zip', ['id' => $occasion->id]),
                'total_history' => $generations->count(),
                'generations' => $generations,
            ],
            'message' => 'Générations des QR pour cette occasion',
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des générations.',
            'erreur' => $e->getMessage(),
        ], 500);
    }
}


public function downloadZip($id, $generation = null)
{
    $occasion = Occasion::find($id);

    if (!$occasion) {
        return response()->json([
            'success' => false,
            'message' => 'Occasion introuvable.',
        ], 404);
    }

    // Construire le chemin du dossier en fonction de la génération
    $folderName = $occasion->nom_occasion;
    if ($generation) {
        $folderName .= "_generation_{$generation}";
    }
    
    $folderPath = storage_path("app/occasions/{$folderName}");
    $pngFolderPath = "$folderPath/png";

    if (!File::exists($pngFolderPath)) {
        File::makeDirectory($pngFolderPath, 0777, true, true);
    }

    // Récupérer les QR codes avec ou sans filtre de génération
    $query = Qr::where('id_occasion', $occasion->id);
    
    if ($generation) {
        $query->where('generation', $generation);
    }
    
    $qrs = $query->get();

    if ($qrs->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => $generation 
                ? "Aucun QR trouvé pour cette occasion et cette génération." 
                : "Aucun QR trouvé pour cette occasion.",
        ], 404);
    }

    $logoPath = public_path('storage/images/image.jpg');

    if (!file_exists($logoPath)) {
        return response()->json([
            'success' => false,
            'message' => 'Logo introuvable.',
        ], 404);
    }

    $logoSize = 70;
    $qrSize = 300;

    foreach ($qrs as $qr) {
        $link_id = "https://skanfy.com/info-qr/{$qr->id}";
        $pngPath = "$pngFolderPath/{$qr->id}.png";

        // Génération QR
        $qrPng = QrCode::format('png')
            ->size($qrSize)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($link_id);

        $tempQrPath = storage_path("app/temp_qr.png");
        File::put($tempQrPath, $qrPng);

        // Charger le QR code
        $qrImage = imagecreatefrompng($tempQrPath);
        
        // Convertir le QR code en true color pour éviter les problèmes de palette
        $trueColorQr = imagecreatetruecolor(imagesx($qrImage), imagesy($qrImage));
        imagecopy($trueColorQr, $qrImage, 0, 0, 0, 0, imagesx($qrImage), imagesy($qrImage));
        imagedestroy($qrImage);
        $qrImage = $trueColorQr;

        // Charger le logo
        $logoInfo = getimagesize($logoPath);
        $logoType = $logoInfo[2];

        switch ($logoType) {
            case IMAGETYPE_PNG:
                $logoImage = imagecreatefrompng($logoPath);
                break;
            case IMAGETYPE_JPEG:
                $logoImage = imagecreatefromjpeg($logoPath);
                break;
            case IMAGETYPE_GIF:
                $logoImage = imagecreatefromgif($logoPath);
                break;
            default:
                $logoImage = imagecreatefromjpeg($logoPath);
                break;
        }

        // Convertir le logo en true color pour préserver les couleurs
        $trueColorLogo = imagecreatetruecolor(imagesx($logoImage), imagesy($logoImage));
        imagecopy($trueColorLogo, $logoImage, 0, 0, 0, 0, imagesx($logoImage), imagesy($logoImage));
        imagedestroy($logoImage);
        $logoImage = $trueColorLogo;

        // Redimensionner le logo
        $resizedLogo = imagecreatetruecolor($logoSize, $logoSize);
        
        if ($logoType == IMAGETYPE_PNG) {
            // Configuration pour PNG avec transparence
            imagealphablending($resizedLogo, false);
            imagesavealpha($resizedLogo, true);
            $transparent = imagecolorallocatealpha($resizedLogo, 0, 0, 0, 127);
            imagefill($resizedLogo, 0, 0, $transparent);
        } else {
            // Pour JPEG - utiliser un fond blanc
            $white = imagecolorallocate($resizedLogo, 255, 255, 255);
            imagefill($resizedLogo, 0, 0, $white);
        }

        // Redimensionnement avec interpolation de meilleure qualité
        imagecopyresampled(
            $resizedLogo, $logoImage,
            0, 0, 0, 0,
            $logoSize, $logoSize,
            imagesx($logoImage), imagesy($logoImage)
        );

        // Position centrée
        $x = (imagesx($qrImage) - $logoSize) / 2;
        $y = (imagesy($qrImage) - $logoSize) / 2; 

        // Méthode de fusion améliorée
        if ($logoType == IMAGETYPE_PNG) {
            // Pour PNG avec transparence
            $this->imagecopymerge_alpha($qrImage, $resizedLogo, $x, $y, 0, 0, $logoSize, $logoSize, 100);
        } else {
            // Pour JPEG - copie normale
            imagecopy($qrImage, $resizedLogo, $x, $y, 0, 0, $logoSize, $logoSize);
        }

        // Ajouter le texte "Scannez pour rendre" en bas de l'image
        $text = "Scannez pour rendre";
        
        // Calculer la nouvelle taille de l'image (ajouter de l'espace pour le texte)
        $originalWidth = imagesx($qrImage);
        $originalHeight = imagesy($qrImage);
        
        // Configuration de la police - CORRIGÉ ICI
        $fontSize = 22; // Taille pour Montserrat (peut être 12, 14, 16, etc.)
        $fontPath = public_path('fonts/Roboto-Medium.ttf');
        
        // Hauteur estimée pour le texte (ajustez selon votre besoin)
        $textHeight = 56;
        
        // Créer une nouvelle image avec l'espace pour le texte
        $finalWidth = $originalWidth;
        $finalHeight = $originalHeight + $textHeight;
        
        // S'assurer que l'image reste carrée en prenant la plus grande dimension
        $squareSize = max($finalWidth, $finalHeight);
        $finalWidth = $squareSize;
        $finalHeight = $squareSize;
        
        $finalImage = imagecreatetruecolor($finalWidth, $finalHeight);
        
        // Fond blanc pour l'image finale
        $white = imagecolorallocate($finalImage, 255, 255, 255);
        imagefill($finalImage, 0, 0, $white);
        
        // Copier le QR code au centre de l'image carrée
        $qrX = ($finalWidth - $originalWidth) / 2;
        $qrY = ($finalHeight - $originalHeight - $textHeight) / 2;
        imagecopy($finalImage, $qrImage, $qrX, $qrY, 0, 0, $originalWidth, $originalHeight);
        
        // Ajouter la coleur du texte
        // $textColor = imagecolorallocate($finalImage, 0, 0, 0); // Noir
        // $textColor = imagecolorallocate($finalImage, 0, 0, 128); // Bleu marrine
        $textColor = imagecolorallocate($finalImage, 255, 80, 0); //orange vif 
        
        // Vérifier si la police Montserrat existe
        if (file_exists($fontPath)) {
            // Utiliser imagettftext avec Montserrat
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
            $textWidth = $bbox[2] - $bbox[0];
            $textX = ($finalWidth - $textWidth) / 2;
            $textY = $qrY + $originalHeight + 30; // Position ajustée
            
            imagettftext($finalImage, $fontSize, 0, $textX, $textY, $textColor, $fontPath, $text);
        } else {
            // Fallback avec imagestring si la police n'existe pas
            $fontSize = 5; // Maximum pour imagestring
            $textWidth = imagefontwidth($fontSize) * strlen($text);
            $textX = ($finalWidth - $textWidth) / 2;
            $textY = $qrY + $originalHeight + 15;
            imagestring($finalImage, $fontSize, $textX, $textY, $text, $textColor);
        }

        // Sauvegarde avec compression maximale
        imagepng($finalImage, $pngPath, 9);

        // Nettoyage
        imagedestroy($qrImage);
        imagedestroy($logoImage);
        imagedestroy($resizedLogo);
        imagedestroy($finalImage);
        File::delete($tempQrPath);
    }

    // Création du ZIP
    $zipFileName = $generation 
        ? "{$occasion->nom_occasion}_generation_{$generation}.zip"
        : "{$occasion->nom_occasion}.zip";
    
    $zipFile = storage_path("app/occasions/{$zipFileName}");

    if (File::exists($zipFile)) {
        File::delete($zipFile);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
        foreach (File::files($pngFolderPath) as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();
    }

    // Nettoyer le dossier temporaire après création du ZIP
    File::deleteDirectory($folderPath);

    return response()->download($zipFile)->deleteFileAfterSend(true);
}

/**
 * Fonction helper pour fusionner avec transparence
 */
private function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct)
{
    // Créer un masque temporaire
    $cut = imagecreatetruecolor($src_w, $src_h);
    
    // Copier la région source
    imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
    imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
    
    // Copier le résultat final
    imagecopy($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h);
    
    imagedestroy($cut);
}


}
