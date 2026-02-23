<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDonationsTable extends Migration
{
    public function up()
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->decimal('amount', 15, 2);
            $table->string('campaign'); // education, healthcare, disaster
            $table->string('payment_method'); // bank_transfer, ewallet
            $table->string('payment_provider')->nullable(); // bri, bni, ovo, gopay, etc
            $table->string('status')->default('pending'); // pending, success, failed
            $table->string('transaction_id')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('email');
            $table->index('campaign');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('donations');
    }
}