<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Create2TablesForGithub extends Migration
{

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		/**
		 * Хранит данные из заголовка ответа API GitHub;
		 * для отправки повторных запросов к API GitHub.
		 *
		 * Most responses return an ETag header. Many responses also return a Last-Modified header.
		 * You can use the values of these headers to make subsequent requests to those resources
		 * using the If-None-Match and If-Modified-Since headers, respectively.
		 */
		Schema::create('github_conditional_requests', function (Blueprint $table) {
			$table->string('repo_item', 20)->primary();
			$table->string('condition', 100)->default('');
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('github_conditional_requests');
	}

}
