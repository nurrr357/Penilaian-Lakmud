<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Schema::getForeignKeys('inputs') as $foreign) {
            Schema::table('inputs', fn (Blueprint $t) => $t->dropForeign($foreign['name']));
        }
        foreach (['events' => 'lakmud_events', 'materials' => 'event_materials', 'participants' => 'event_participants'] as $old => $new) {
            if (! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }
        DB::purge();
        Schema::table('event_materials', function (Blueprint $t) {
            if (Schema::hasColumn('event_materials', 'event_id')) {
                $t->renameColumn('event_id', 'lakmud_event_id');
            }
            if (! Schema::hasColumn('event_materials', 'instructor_name')) {
                $t->string('instructor_name', 180)->nullable();
            }
            if (! Schema::hasColumn('event_materials', 'material_date')) {
                $t->date('material_date')->nullable();
            }
            if (! Schema::hasColumn('event_materials', 'start_time')) {
                $t->time('start_time')->nullable();
            }
            if (! Schema::hasColumn('event_materials', 'end_time')) {
                $t->time('end_time')->nullable();
            }
        });
        Schema::create('participants', function (Blueprint $t) {
            $t->id();
            $t->string('full_name', 200)->index();
            $t->timestamps();
        });
        foreach (DB::table('event_participants')->orderBy('id')->get() as $row) {
            DB::table('participants')->insert(['id' => $row->id, 'full_name' => $row->name, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at]);
        }
        Schema::table('event_participants', function (Blueprint $t) {
            $t->renameColumn('event_id', 'lakmud_event_id');
            $t->renameColumn('sequence', 'sequence_number');
            $t->foreignId('participant_id')->nullable()->constrained('participants')->restrictOnDelete();
            $t->string('group_name', 100)->nullable();
            $t->string('delegation', 180)->nullable();
            $t->string('status', 20)->default('registered');
            $t->timestamp('registered_at')->nullable();
        });
        DB::table('event_participants')->update(['participant_id' => DB::raw('id')]);
        Schema::table('event_participants', function (Blueprint $t) {
            $t->unsignedBigInteger('participant_id')->nullable(false)->change();
            $t->unique(['lakmud_event_id', 'participant_id']);
            $t->dropColumn('name');
        });
        Schema::create('attendance_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_participant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('event_material_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20);
            $t->string('notes', 255)->nullable();
            $t->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('marked_at')->nullable();
            $t->timestamps();
            $t->unique(['event_participant_id', 'event_material_id'], 'attendance_records_ep_material_unique');
        });
        Schema::create('assessment_scores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_participant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('event_material_id')->constrained()->cascadeOnDelete();
            $t->string('assessment_type', 20);
            $t->decimal('score', 6, 2);
            $t->foreignId('scored_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('scored_at')->nullable();
            $t->timestamps();
            $t->unique(['event_participant_id', 'event_material_id', 'assessment_type'], 'assessment_scores_ep_material_type_unique');
        });
        Schema::create('moora_aspect_scores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_participant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('event_material_id')->constrained()->cascadeOnDelete();
            foreach (['activity', 'ethics', 'understanding'] as $field) {
                $t->decimal($field, 6, 2)->nullable();
            }
            $t->foreignId('scored_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['event_participant_id', 'event_material_id'], 'moora_aspect_ep_material_unique');
        });
        foreach (DB::table('inputs')->orderBy('id')->get() as $row) {
            $key = ['event_participant_id' => $row->participant_id, 'event_material_id' => $row->material_id];
            $times = ['created_at' => $row->created_at, 'updated_at' => $row->updated_at];
            if ($row->attendance !== null) {
                DB::table('attendance_records')->insert($key + $times + ['status' => $row->attendance]);
            }
            foreach (['pretest', 'posttest'] as $type) {
                if ($row->$type !== null) {
                    DB::table('assessment_scores')->insert($key + $times + ['assessment_type' => $type, 'score' => $row->$type]);
                }
            }
            if ($row->activity !== null || $row->ethics !== null || $row->understanding !== null) {
                DB::table('moora_aspect_scores')->insert($key + $times + ['activity' => $row->activity, 'ethics' => $row->ethics, 'understanding' => $row->understanding]);
            }
        }
        Schema::table('moora_runs', function (Blueprint $t) {
            $t->renameColumn('event_id', 'lakmud_event_id');
            $t->renameColumn('finalized_at', 'locked_at');
            $t->string('formula_version', 30)->change();
            $t->string('status', 16)->default('valid')->change();
            $t->dropForeign(['calculated_by']);
            $t->unsignedBigInteger('calculated_by')->nullable()->change();
            $t->foreign('calculated_by')->references('id')->on('users')->nullOnDelete();
            $t->index(['lakmud_event_id', 'status']);
        });
        Schema::table('moora_results', function (Blueprint $t) {
            $t->renameColumn('run_id', 'moora_run_id');
            $t->renameColumn('participant_id', 'event_participant_id');
            foreach (['yi', 'benefit_total', 'cost_total'] as $field) {
                $t->decimal($field, 18, 12)->change();
            }
            $t->decimal('certificate_score', 6, 2)->change();
            $t->string('certificate_predicate', 1)->change();
            $t->string('certificate_status', 20)->change();
            $t->timestamps();
        });
        // Keep timestamps from the run rather than inventing a new calculation time.
        DB::statement('UPDATE moora_results r JOIN moora_runs m ON m.id=r.moora_run_id SET r.created_at=m.created_at, r.updated_at=m.updated_at');
        Schema::table('moora_results', function (Blueprint $t) {
            $t->foreign('event_participant_id')->references('id')->on('event_participants')->cascadeOnDelete();
            $t->index(['moora_run_id', 'rank']);
            $t->dropColumn('participant_name');
        });
        Schema::drop('inputs');
    }

    public function down(): void
    {
        throw new RuntimeException('Forward-only data reconciliation. Restore the verified external backup to roll back.');
    }
};
