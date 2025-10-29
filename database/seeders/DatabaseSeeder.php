<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Admin::create([
            'nom_admin' => 'Administrateur',
            'email_admin' => 'administrateur@gmail.com',
            'tel_admin' => '0102030405',
            'password_admin' => Hash::make('admin123'),
            'type' => 2
        ]);
        
    }
}
