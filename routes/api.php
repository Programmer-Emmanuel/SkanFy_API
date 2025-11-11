<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ObjetController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\QrController;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Authentification de l’utilisateur
Route::post('/register/user', [AuthController::class, "register_user"]);
Route::post('/login/user', [AuthController::class, "login_user"]);
Route::post('/verify/code/otp', [AuthController::class, "verify_otp"]);
Route::post('/login', [AuthController::class, "login"]);

// Scan d'un QR code (nécessite un utilisateur ou admin connecté)
Route::get('/scan/qr/{qrId}', [QrController::class, "scanner_qr"]);
Route::post('/scan/qr/link', [QrController::class, "scanner_via_lien"]);
Route::middleware('auth:sanctum')->group(function(){
    //Afficher les infos d'un objet a partir de son qrId
    Route::get('/info/objet/{qrId}', [ObjetController::class, "info_objet"]);
        //Informations de l’utilisateur
    Route::get('/info/user', [AuthController::class, "info_user"]);

    Route::post('/update/info/', [AuthController::class, 'update_info']);

    Route::post('/change/password/', [AuthController::class, 'change_password']);
});

Route::middleware('auth:user')->group(function(){
    //Modifier les infos de l’utilisateur
    Route::post('/update/info/user', [AuthController::class, 'update_info_user']);

    //Formatter code Qr
    Route::post('/qr/user/{qrId}/formatte', [QrController::class, 'formater_qr_user']);

    //Ajouter un objet au code Qr
    Route::post('/create/objet/qr/{qrId}', [ObjetController::class, "create_objet"]);
    //Modifier un objet au code Qr
    Route::post('/update/objet/qr/{qrId}', [ObjetController::class, "update_objet"]);
    //Supprimer un objet du code Qr
    Route::post('/delete/objet/qr/{qrId}', [ObjetController::class, "delete_objet"]);
    //Modifier le password de l’utilisateur
    Route::post('/change/password/user', [AuthController::class, 'change_user_password']);
    //obtenir tous les objets de l’user
    Route::get('/objets/user', [ObjetController::class, 'all_objet_user']);
});

//Authentification Administrateur
Route::post('/login/admin', [AuthController::class, "login_admin"]);

Route::middleware('auth:admin')->group(function(){
    //Creer un sous admin
    Route::post('register/sous-admin', [AuthController::class, 'creer_sous_admin']);
    //Informations de l’administrateur
    Route::get('/info/admin', [AuthController::class, "info_admin"]);
    //Modifier les infos de l’admin
    Route::post('/update/info/admin', [AuthController::class, 'update_admin_info']);
    //Modifier le password de l’admin
    Route::post('/change/password/admin', [AuthController::class, 'change_password']);

    // Creation de code Qr (nécessite un admin connecté)
    Route::post('/create/qr', [QrController::class, "creer_qr"]);

    //Formatter code Qr
    Route::post('/qr/{qrId}/formatte', [QrController::class, 'formater_qr']);

    //Liste des code qr
    Route::get('/liste/qr', [QrController::class, 'liste_qr']);

    //Liste des code qr par occasions
    Route::get('/liste/qr/occasion', [QrController::class, 'liste_qr_par_occasion']);

    //Liste des utilisateurs
    Route::get('/users', [AuthController::class, "liste_user"]);
    Route::post('/delete/user/{id}', [AuthController::class, "delete_user"]);

    //Creation d’une occasion
    Route::post('/ajout/occasion', [QrController::class, 'ajout_occasion']);
    //Liste des occasions
    Route::get('/liste/occasions', [QrController::class, 'liste_occasion']);
    //Afficher une occasion
    Route::get('/occasion/{id}', [QrController::class, 'occasion']);
    //Update d’une occasion
    Route::post('/update/occasion/{id}', [QrController::class, 'update_occasion']);
    //Suppression d’une occasion
    Route::post('/delete/occasion/{id}', [QrController::class, 'delete_occasion']);
    //Historique des occasion
    Route::get('/historique/occasions', [QrController::class, 'historique_occasion']);

    Route::get('/liste/admins', [AuthController::class, 'liste_admin']);
    
});

Route::get('/occasions/{id}/download-zip', [QrController::class, 'downloadZip'])->name('occasions.download.zip');

Route::post('/password/forgot', [PasswordResetController::class, 'sendResetLink']);
Route::post('/password/verify/{token}', [PasswordResetController::class, 'verifyToken']);
Route::post('/password/reset/{token}', [PasswordResetController::class, 'resetPassword']);




    //test sms
    Route::get('/test-sms', function () {
        $twilio = new TwilioService();
        $twilio->sendSMS('+2250140022693', 'Test depuis Laravel 🚀');
        return 'Message envoyé !';
    });



