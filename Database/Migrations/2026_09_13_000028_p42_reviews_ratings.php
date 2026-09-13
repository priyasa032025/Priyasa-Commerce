<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_product_reviews')) {
            Schema::connection('priyasa')->create('priyasa_product_reviews', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('variant_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('order_item_id')->nullable()->index();
                $table->unsignedTinyInteger('rating');
                $table->string('title', 160)->nullable();
                $table->text('body')->nullable();
                $table->string('status', 24)->default('pending')->index();
                $table->boolean('verified_purchase')->default(false)->index();
                $table->unsignedInteger('helpful_count')->default(0);
                $table->unsignedInteger('not_helpful_count')->default(0);
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('moderation_note')->nullable();
                $table->timestamps();
                $table->unique(['customer_id','order_item_id'], 'p42_review_customer_item_unique');
                $table->index(['product_id','status','created_at'], 'p42_review_product_status_created');
            });
        }

        if (!Schema::connection('priyasa')->hasTable('priyasa_review_media')) {
            Schema::connection('priyasa')->create('priyasa_review_media', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('review_id')->index();
                $table->string('url', 2048);
                $table->string('type', 16)->default('image');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['review_id','sort_order']);
            });
        }

        if (!Schema::connection('priyasa')->hasTable('priyasa_review_votes')) {
            Schema::connection('priyasa')->create('priyasa_review_votes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('review_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->boolean('helpful');
                $table->timestamps();
                $table->unique(['review_id','customer_id'], 'p42_review_vote_unique');
            });
        }

        if (!Schema::connection('priyasa')->hasTable('priyasa_product_rating_aggregates')) {
            Schema::connection('priyasa')->create('priyasa_product_rating_aggregates', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->primary();
                $table->unsignedInteger('review_count')->default(0);
                $table->unsignedInteger('verified_review_count')->default(0);
                $table->decimal('average_rating', 3, 2)->default(0);
                $table->unsignedInteger('rating_1_count')->default(0);
                $table->unsignedInteger('rating_2_count')->default(0);
                $table->unsignedInteger('rating_3_count')->default(0);
                $table->unsignedInteger('rating_4_count')->default(0);
                $table->unsignedInteger('rating_5_count')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_product_rating_aggregates');
        Schema::connection('priyasa')->dropIfExists('priyasa_review_votes');
        Schema::connection('priyasa')->dropIfExists('priyasa_review_media');
        Schema::connection('priyasa')->dropIfExists('priyasa_product_reviews');
    }
};
