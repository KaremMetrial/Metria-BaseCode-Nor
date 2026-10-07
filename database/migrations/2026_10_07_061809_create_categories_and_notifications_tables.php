<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up(): void {
  Schema::create('application_locks',function(Blueprint $t){$t->string('name')->primary();});
  DB::table('application_locks')->insert(['name'=>'category_tree']);
  Schema::create('categories',function(Blueprint $t){$t->id();$t->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();$t->boolean('is_active')->default(true);$t->unsignedSmallInteger('sort_order')->default(0);$t->timestamps();$t->index(['parent_id','is_active','sort_order']);});
  Schema::create('category_translations',function(Blueprint $t){$t->id();$t->foreignId('category_id')->constrained()->cascadeOnDelete();$t->string('locale',5)->index();$t->string('name');$t->string('description')->nullable();$t->timestamps();$t->unique(['category_id','locale']);});
  Schema::create('user_notifications',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('event_key')->unique();$t->string('translation_key');$t->json('parameters');$t->string('locale',5);$t->timestamp('read_at')->nullable();$t->timestamp('published_at')->nullable();$t->timestamps();$t->index(['user_id','created_at']);$t->index(['published_at','created_at']);});
 }
 public function down(): void {Schema::dropIfExists('user_notifications');Schema::dropIfExists('category_translations');Schema::dropIfExists('categories');Schema::dropIfExists('application_locks');}
};
