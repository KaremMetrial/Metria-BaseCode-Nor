<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('governorates',function(Blueprint $t){$t->dropForeign(['country_id']);$t->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();});
  Schema::table('cities',function(Blueprint $t){$t->dropForeign(['governorate_id']);$t->foreign('governorate_id')->references('id')->on('governorates')->restrictOnDelete();});
 }
 public function down(): void {
  Schema::table('cities',function(Blueprint $t){$t->dropForeign(['governorate_id']);$t->foreign('governorate_id')->references('id')->on('governorates')->cascadeOnDelete();});
  Schema::table('governorates',function(Blueprint $t){$t->dropForeign(['country_id']);$t->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();});
 }
};
