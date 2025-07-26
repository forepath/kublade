<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->enum('type', ['application', 'cluster'])->default('application')->after('user_id');
        });

        Schema::table('template_files', function (Blueprint $table) {
            $table->integer('sort')->nullable()->after('content');
        });

        Schema::table('clusters', function (Blueprint $table) {
            $table->foreignUuid('template_id')->nullable()->after('project_id')->references('id')->on('templates');
            $table->boolean('update')->default(false)->after('name');
            $table->boolean('delete')->default(false)->after('update');
            $table->timestamp('approved_at')->nullable()->after('delete');
            $table->timestamp('deployed_at')->nullable()->after('approved_at');
            $table->timestamp('deployment_updated_at')->nullable()->after('deployed_at');
            $table->timestamp('creation_dispatched_at')->nullable()->after('deployment_updated_at');
            $table->timestamp('update_dispatched_at')->nullable()->after('creation_dispatched_at');
            $table->timestamp('deletion_dispatched_at')->nullable()->after('update_dispatched_at');
        });

        Schema::create('cluster_data', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cluster_id')->references('id')->on('clusters');
            $table->foreignUuid('template_field_id')->references('id')->on('template_fields');
            $table->string('key');
            $table->longText('value');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cluster_secret_data', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cluster_id')->references('id')->on('clusters');
            $table->foreignUuid('template_field_id')->references('id')->on('template_fields');
            $table->string('key');
            $table->longText('value');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('template_env_variables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->references('id')->on('templates');
            $table->string('key');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cluster_env_variables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cluster_id')->references('id')->on('clusters');
            $table->foreignUuid('template_env_variable_id')->references('id')->on('template_env_variables');
            $table->longText('value');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('cluster_env_variables');
        Schema::dropIfExists('template_env_variables');
        Schema::dropIfExists('cluster_secret_data');
        Schema::dropIfExists('cluster_data');

        Schema::table('clusters', function (Blueprint $table) {
            $table->dropColumn('deletion_dispatched_at');
            $table->dropColumn('update_dispatched_at');
            $table->dropColumn('creation_dispatched_at');
            $table->dropColumn('deployment_updated_at');
            $table->dropColumn('deployed_at');
            $table->dropColumn('approved_at');
            $table->dropColumn('delete');
            $table->dropColumn('update');
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');
        });

        Schema::table('template_files', function (Blueprint $table) {
            $table->dropColumn('sort');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
