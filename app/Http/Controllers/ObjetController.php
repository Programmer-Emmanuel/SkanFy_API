<?php

namespace App\Http\Controllers;

use App\Models\Objet;
use App\Models\Qr;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ObjetController extends Controller
{
    /**
     * 🔹 Envoi d'une image sur imgbb et retour du lien
     */
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

    /**
     * 🔹 Création d’un objet lié à un QR
     */
public function create_objet(Request $request, $qrId)
{
    // ✅ Validation des champs
    $validator = Validator::make($request->all(), [
        "nom_objet" => "nullable|string|max:255",
        "description" => "nullable|string|min:20",
        "image_objet" => "nullable|image|mimes:jpg,jpeg,png|max:2048"
    ], [
        "nom_objet.string" => "Le nom de l’objet doit être une chaîne de caractères.",
        "description.string" => "La description doit être une chaîne de caractères.",
        "description.min" => "La description doit avoir au minimum 20 caractères.",
        "image_objet.image" => "Le fichier doit être une image.",
        "image_objet.mimes" => "L’image doit être au format JPG, JPEG ou PNG.",
        "image_objet.max" => "L’image ne doit pas dépasser 2 Mo."
    ]);

    if ($validator->fails()) {
        return response()->json([
            "success" => false,
            "message" => $validator->errors()->first()
        ], 422);
    }

    // ✅ Vérification de l’utilisateur connecté
    $user = $request->user();
    if (!$user) {
        return response()->json([
            "success" => false,
            "message" => "Utilisateur introuvable ou token invalide."
        ], 404);
    }

    try {
        // 🔍 Vérification du QR
        $qr = Qr::find($qrId);
        if (!$qr) {
            return response()->json([
                "success" => false,
                "message" => "Id du code QR introuvable."
            ], 404);
        }

        // ⚠️ Si le QR contient déjà un objet, on bloque
        if ($qr->id_objet != null) {
            return response()->json([
                "success" => false,
                "message" => "Ce code QR contient déjà un objet. Supprimez-le avant d’en créer un nouveau."
            ], 422);
        }

        // ✅ Upload de l’image si présente
        $imageUrl = null;
        if ($request->hasFile('image_objet')) {
            $imageUrl = $this->uploadImageToHosting($request->file('image_objet'));
        }

        // ✅ Création de l’objet
        $objet = new Objet();
        $objet->nom_objet = $request->nom_objet;
        $objet->description = $request->description;
        $objet->image_objet = $imageUrl;
        $objet->save();

        // ✅ Lier l’objet et activer le QR
        $qr->update([
            'id_objet' => $objet->id,
            'id_user' => $user->id,
            'is_active' => 1
        ]);

        return response()->json([
            "success" => true,
            "message" => "Objet créé et QR activé avec succès.",
            "data" =>$objet,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "message" => "Erreur lors de la création de l’objet.",
            "error" => $e->getMessage()
        ], 500);
    }
}


    /**
     * 🔹 Mise à jour d’un objet
     */
    public function update_objet(Request $request, $qrId)
    {
        $validator = Validator::make($request->all(), [
            "nom_objet" => "nullable|string",
            "description" => "nullable|string|min:20",
            "image_objet" => "nullable|image|mimes:jpg,jpeg,png|max:2048"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "success" => false,
                "message" => $validator->errors()->first()
            ], 422);
        }

        $user = $request->user();
        $qr = Qr::find($qrId);

        if (!$user || !$qr || $qr->id_user !== $user->id || !$qr->id_objet) {
            return response()->json([
                "success" => false,
                "message" => "QR code ou utilisateur invalide."
            ], 404);
        }

        try {
            $objet = Objet::find($qr->id_objet);
            if (!$objet) {
                return response()->json([
                    "success" => false,
                    "message" => "Objet introuvable."
                ], 404);
            }

            // ✅ Upload nouvelle image si présente
            if ($request->hasFile('image_objet')) {
                $objet->image_objet = $this->uploadImageToHosting($request->file('image_objet'));
            }

            $objet->update([
                "nom_objet" => $request->nom_objet ?? $objet->nom_objet,
                "description" => $request->description ?? $objet->description,
                "image_objet" => $objet->image_objet
            ]);

            return response()->json([
                "success" => true,
                "message" => "Objet mis à jour avec succès.",
                "data" => $objet
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Erreur lors de la mise à jour de l’objet.",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔹 Suppression d’un objet lié au QR
     */
    public function delete_objet(Request $request, $qrId)
    {
        $user = $request->user();
        $qr = Qr::find($qrId);

        if (!$user || !$qr || $qr->id_user !== $user->id) {
            return response()->json([
                "success" => false,
                "message" => "QR code ou utilisateur invalide."
            ], 404);
        }

        if (is_null($qr->id_objet)) {
            return response()->json([
                "success" => false,
                "message" => "Aucun objet n’est associé à ce QR code."
            ], 404);
        }

        try {
            $objet = Objet::find($qr->id_objet);
            if ($objet) {
                $objet->delete();
            }

            $qr->update(['id_objet' => null]);

            return response()->json([
                "success" => true,
                "message" => "Objet supprimé avec succès."
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Erreur lors de la suppression de l’objet.",
                "error" => $e->getMessage()
            ], 500);
        }
    }
}
