<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->string('name', 200);
            $t->string('organization');
            $t->string('venue');
            $t->date('start_date');
            $t->date('end_date');
            $t->boolean('finalized')->default(false);
            $t->timestamps();
        });
        foreach (['materials', 'participants'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $t->string('name', 200);
                $t->unsignedInteger('sequence');
                if ($table === 'materials') {
                    $t->boolean('is_active')->default(true);
                }
                $t->timestamps();
                $t->unique(['event_id', 'sequence']);
                $t->unique(['id', 'event_id']);
            });
        }
        Schema::create('inputs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('event_id');
            $t->unsignedBigInteger('participant_id');
            $t->unsignedBigInteger('material_id');
            $t->enum('attendance', ['present', 'absent'])->nullable();
            foreach (['pretest', 'activity', 'ethics', 'understanding', 'posttest'] as $field) {
                $t->decimal($field, 4, 2)->nullable();
            }
            $t->timestamps();
            $t->unique(['participant_id', 'material_id']);
            $t->foreign(['participant_id', 'event_id'])->references(['id', 'event_id'])->on('participants')->cascadeOnDelete();
            $t->foreign(['material_id', 'event_id'])->references(['id', 'event_id'])->on('materials')->cascadeOnDelete();
        });
        Schema::create('moora_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $t->string('status');
            $t->string('formula_version')->default('moora-v1');
            $t->json('weights');
            $t->json('divisors');
            $t->unsignedInteger('alternative_count');
            $t->foreignId('calculated_by')->constrained('users');
            $t->timestamp('finalized_at')->nullable();
            $t->timestamps();
        });
        Schema::create('moora_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('run_id')->constrained('moora_runs')->cascadeOnDelete();
            $t->unsignedBigInteger('participant_id');
            $t->string('participant_name');
            foreach (['criteria', 'normalized', 'weighted', 'material_scores'] as $field) {
                $t->json($field);
            }
            foreach (['yi', 'benefit_total', 'cost_total'] as $field) {
                $t->double($field);
            }
            $t->unsignedInteger('rank');
            $t->decimal('certificate_score', 5, 2);
            $t->string('certificate_predicate');
            $t->string('certificate_status');
            $t->unique(['run_id', 'participant_id']);
        });
    }

    public function down(): void
    {
        foreach (['moora_results', 'moora_runs', 'inputs', 'materials', 'participants', 'events'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
