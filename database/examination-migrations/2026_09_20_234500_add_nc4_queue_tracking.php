<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $s=Schema::connection('exam');
        if(!$s->hasTable('non_cadre_allocation_runs')) return;
        $columns=[
            'queue_stage'=>fn(Blueprint $t)=>$t->string('queue_stage',30)->nullable()->after('phase'),
            'progress_percent'=>fn(Blueprint $t)=>$t->unsignedTinyInteger('progress_percent')->default(0)->after('queue_stage'),
            'failure_message'=>fn(Blueprint $t)=>$t->text('failure_message')->nullable()->after('progress_percent'),
            'queued_at'=>fn(Blueprint $t)=>$t->timestamp('queued_at')->nullable()->after('failure_message'),
            'processing_started_at'=>fn(Blueprint $t)=>$t->timestamp('processing_started_at')->nullable()->after('queued_at'),
            'processing_finished_at'=>fn(Blueprint $t)=>$t->timestamp('processing_finished_at')->nullable()->after('processing_started_at'),
        ];
        foreach($columns as $name=>$definition) if(!$s->hasColumn('non_cadre_allocation_runs',$name)) $s->table('non_cadre_allocation_runs',fn(Blueprint $t)=>$definition($t));
    }
    public function down(): void
    {
        $s=Schema::connection('exam'); if(!$s->hasTable('non_cadre_allocation_runs'))return;
        $columns=array_values(array_filter(['queue_stage','progress_percent','failure_message','queued_at','processing_started_at','processing_finished_at'],fn($c)=>$s->hasColumn('non_cadre_allocation_runs',$c)));
        if($columns)$s->table('non_cadre_allocation_runs',fn(Blueprint $t)=>$t->dropColumn($columns));
    }
};
