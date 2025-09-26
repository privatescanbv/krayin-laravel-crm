<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('sku')->unique();
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('price', 12, 4)->nullable();
            $table->unsignedBigInteger('resource_type_id')->nullable();
            $table->unsignedBigInteger('product_type_id')->nullable();
            $table->timestamps();

            $table->foreign('resource_type_id')->references('id')->on('resource_types')->onDelete('set null');
            $table->foreign('product_type_id')->references('id')->on('product_types')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
};
