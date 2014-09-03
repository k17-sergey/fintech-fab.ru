<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateForeignForGithub extends Migration
{

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('github_comments', function (Blueprint $table) {
			$table->foreign('user_login')->references('login')->on('github_members');
		});
		Schema::table('github_refcommits', function (Blueprint $table) {
			$table->foreign('actor_login')->references('login')->on('github_members');
		});
		Schema::table('github_events', function (Blueprint $table) {
			$table->foreign('actor_login')->references('login')->on('github_members');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('github_comments', function (Blueprint $table) {
			$table->dropForeign('github_comments_user_login_foreign');
		});
		Schema::table('github_refcommits', function (Blueprint $table) {
			$table->dropForeign('github_refcommits_actor_login_foreign');
		});
		Schema::table('github_events', function (Blueprint $table) {
			$table->dropForeign('github_events_actor_login_foreign');
		});
	}

}
