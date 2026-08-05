<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  $schema=Schema::connection('exam');
  if(!$schema->hasTable('viva_finalization_runs')){
   $schema->create('viva_finalization_runs',function(Blueprint $table):void{
    $table->id();$table->unsignedBigInteger('processing_run_id')->index();$table->unsignedInteger('processing_version');
    $table->string('status',30)->default('current')->index();$table->json('summary');
    $table->unsignedBigInteger('finalized_by')->nullable()->index();$table->timestamp('finalized_at')->nullable()->index();
    $table->text('notes')->nullable();$table->timestamps();
    $table->foreign('processing_run_id','viva_fin_proc_run_fk')->references('id')->on('viva_processing_runs')->restrictOnDelete();
   });
  }
 }
 public function down():void{Schema::connection('exam')->dropIfExists('viva_finalization_runs');}
};
