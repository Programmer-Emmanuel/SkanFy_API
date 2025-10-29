<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ObjetController;
use App\Http\Controllers\QrController;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Authentification de l’utilisateur
Route::post('/register/user', [AuthController::class, "register_user"]);
Route::post('/login/user', [AuthController::class, "login_user"]);
Route::post('/verify/code/otp', [AuthController::class, "verify_otp"]);

// Scan d'un QR code (nécessite un utilisateur connecté)
Route::get('/scan/qr/{qrId}', [QrController::class, "scanner_qr"]);
Route::post('/scan/qr/link', [QrController::class, "scanner_via_lien"]);

Route::middleware('auth:user')->group(function(){
    //Informations de l’utilisateur
    Route::get('/info/user', [AuthController::class, "info_user"]);
    //Modifier les infos de l’utilisateur
    Route::post('/update/info/user', [AuthController::class, 'update_info_user']);

    //Formatter code Qr
    Route::get('/qr/user/{qrId}/formatte', [QrController::class, 'formater_qr_user']);

    //Ajouter un objet au code Qr
    Route::post('/create/objet/qr/{qrId}', [ObjetController::class, "create_objet"]);
    //Modifier un objet au code Qr
    Route::post('/update/objet/qr/{qrId}', [ObjetController::class, "update_objet"]);
    //Supprimer un objet du code Qr
    Route::post('/delete/objet/qr/{qrId}', [ObjetController::class, "delete_objet"]);
    
});

//Authentification Administrateur
Route::post('/login/admin', [AuthController::class, "login_admin"]);

Route::middleware('auth:admin')->group(function(){
    //Informations de l’administrateur
    Route::get('/info/admin', [AuthController::class, "info_admin"]);

    // Creation de code Qr (nécessite un admin connecté)
    Route::post('/create/qr', [QrController::class, "creer_qr"]);

    //Formatter code Qr
    Route::get('/qr/{qrId}/formatte', [QrController::class, 'formater_qr']);

    //Liste des code qr
    Route::get('/liste/qr', [QrController::class, 'liste_qr']);

    //Liste des code qr par occasions
    Route::get('/liste/qr/occasion', [QrController::class, 'liste_qr_par_occasion']);
});



    //test sms
    Route::get('/test-sms', function () {
        $twilio = new TwilioService();
        $twilio->sendSMS('+2250140022693', 'Test depuis Laravel 🚀');
        return 'Message envoyé !';
    });



