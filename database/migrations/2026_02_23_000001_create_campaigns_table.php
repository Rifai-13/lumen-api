<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCampaignsTable extends Migration
{
    public function up()
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category');
            $table->decimal('goal', 15, 2);
            $table->decimal('raised', 15, 2)->default(0);
            $table->integer('donors')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('image')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('category');
        });
    }

    public function down()
    {
        Schema::dropIfExists('campaigns');
    }
}