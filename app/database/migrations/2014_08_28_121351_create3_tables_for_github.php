<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Create3TablesForGithub extends Migration
{

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('github_events', function (Blueprint $table) {
			$table->integer('id')->unsigned()->primary(); //id из GitHub
			$table->string('type', 20); //Тип события
			$table->string('actor_login', 20);
			$table->dateTime('created');
			$table->string('payload', 200); //Содержание события (полученные данные обрабатываются, сохраняя как текст)
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('github_events');
	}

}
