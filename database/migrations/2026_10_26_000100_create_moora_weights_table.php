<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bobot kriteria MOORA disimpan global (bukan per kegiatan), jadi tabel ini hanya berisi
 * satu baris setelan. Baris itu tidak dibuat oleh migrasi: MooraWeights memakai konstanta
 * default selama tabel kosong, sehingga instalasi lama tetap berjalan tanpa seed tambahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moora_weights', function (Blueprint $t) {
            $t->id();
            $t->json('weights');
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moora_weights');
    }
};
