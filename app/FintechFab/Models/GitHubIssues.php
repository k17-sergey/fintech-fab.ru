<?php

namespace FintechFab\Models;

use Eloquent;

//use FintechFab\Models\GitHubMembers;

/**
 * Class GitHubIssues
 *
 * @package FintechFab\Models
 *
 * @property integer $number
 * @property string  $html_url
 * @property string  $title
 * @property string  $state
 * @property integer $created
 * @property integer $updated
 * @property integer $closed
 * @property string  $user_login
 *
 * @method GitHubIssues whereState static
 *
 */
class GitHubIssues extends Eloquent implements IGitHubModel
{
	public $timestamps = false;
	protected $table = 'github_issues';
	protected $primaryKey = 'number';

	/**
	 * @return GitHubMembers
	 */
	public function user()
	{
		return GitHubMembers::find($this->user_login);

	}

	/**
	 * @return GitHubComments[]
	 */
	public function comments()
	{
		return GitHubComments::where("issue_number", $this->number)->orderBy("created")->get();

	}

	public function getMyName()
	{
		return 'issue';
	}


	public function dataGitHub($inData)
	{
		if (!isset(GitHubMembers::find($inData->user->login)->login)) {
			$user = new GitHubMembers;
			$user->login = $inData->user->login;
			$user->save();
		}
		$this->html_url = $inData->html_url;
		$this->number = $inData->number;
		$this->title = $inData->title;
		$this->state = $inData->state;
		$this->created = $inData->created_at;
		$this->updated = $inData->updated_at;
		if (!empty($inData->closed_at)) {
			$this->closed = $inData->closed_at;
		}
		$this->user_login = $inData->user->login;

		return true;
	}

	public function updateFromGitHub($inData)
	{
		$changed = false;
		if ($this->html_url != $inData->html_url) {
			$this->html_url = $inData->html_url;
			$changed = true;
		}
		if ($this->title != $inData->title) {
			$this->title = $inData->title;
			$changed = true;
		}
		if ($this->state != $inData->state) {
			$this->state = $inData->state;
			$changed = true;
		}
		if ((str_replace(" ", "T", $this->updated) . "Z") != $inData->updated_at) //<--------
		{
			$this->updated = $inData->updated_at;
			$changed = true;
		}
		if (is_null($this->closed) && (!is_null($inData->closed_at))) {
			$this->closed = $inData->closed_at;
			$changed = true;
		}

		return $changed;
	}


}