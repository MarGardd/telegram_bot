<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('paycheck_orders', function (Blueprint $table) {
            $table->id('id');
            $table->integer('order_number')->unique();
            $table->string('chat_id');
            $table->string('username');
            $table->boolean('send')->default(false);
            $table->boolean('checked')->default(false);
            $table->boolean('archive')->default(false);
            $table->boolean('deleted')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paycheck_orders');
    }
};
