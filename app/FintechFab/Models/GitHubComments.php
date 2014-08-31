<?php

namespace FintechFab\Models;

use Eloquent;


/**
 * Class GitHubComments
 *
 * @package FintechFab\Models
 *
 * @property integer $id
 * @property string  $html_url
 * @property integer $issue_number
 * @property integer $created  //Поля метки времени из другой БД переименованы
 * @property integer $updated
 * @property string  $user_login
 * @property string  $prev     //preview, начало текста комментария
 *
 * @method GitHubComments whereIssueNumber static
 *
 */
class GitHubComments extends Eloquent implements IGitHubModel
{
	public $timestamps = false; //Используются даные GitHub'а (поля "timestamps" из другой БД)

	protected $table = 'github_comments';

	public function issue()
	{
		return GitHubIssues::where("number", $this->issue_number)->first();
	}

	public function user()
	{
		return GitHubMembers::find($this->user_login);
	}


	public function getMyName()
	{
		return 'issue comment';
	}

	public function dataGitHub($inData)
	{
		$this->id = $inData->id;
		$this->html_url = $inData->html_url;
		$n = explode('/', $inData->issue_url);
		$this->issue_number = $n[count($n) - 1];
		//Поля метки времени из другой БД должны быть переименованы
		$this->created = $inData->created_at;
		$this->updated = $inData->updated_at;

		$this->user_login = $inData->user->login;
		$this->prev = $this->trimCommentBody($inData->body);

		return true;
	}

	public function updateFromGitHub($inData)
	{
		if ((str_replace(" ", "T", $this->updated) . "Z") == $inData->updated_at) //<-------
		{
			return false;
		} else {
			$this->updated = $inData->updated_at;
			$this->prev = $this->trimCommentBody($inData->body);

			return true;
		}
	}

	private function trimCommentBody($str)
	{
		$body = strip_tags($str);

		return (mb_strlen($body) > 27)
			? (mb_substr($body, 0, 26) . "...")
			: $body;

	}

}