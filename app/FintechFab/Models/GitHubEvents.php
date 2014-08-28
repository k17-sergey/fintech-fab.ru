<?php

namespace FintechFab\Models;

use Eloquent;


/**
 * Class GitHubEvents
 *
 * @package FintechFab\Models
 *
 * @property integer $id
 * @property string  $type
 * @property string  $actor_login
 * @property integer $created
 * @property string  $payload
 *
 */
class GitHubEvents extends Eloquent implements IGitHubModel
{
	public $timestamps = false;

	protected $table = 'github_events';

	public function user()
	{
		return GitHubMembers::find($this->actor_login);
	}


	public function getKeyName()
	{
		return 'id';
	}

	public function getMyName()
	{
		return 'event';
	}

	public function dataGitHub($inData)
	{
		if (!self::isAcceptData($inData)) {
			return false;
		}

		$this->id = $inData->id;
		$this->type = $inData->type;
		$this->actor_login = $inData->actor->login;
		$this->created = $inData->created_at;

		if ($inData->type == 'IssuesEvent') {
			if ($inData->payload->action == 'opened') {
				$action = "Открыта задача: ";
			} elseif ($inData->payload->action == 'closed') {
				$action = "Задача закрыта: ";
			} else {
				$action = "Action \"{$inData->payload->action}\": ";
			}
			$this->payload = $action . $inData->payload->issue->number . ' ' . $inData->payload->issue->title;
		}

		return true;
	}

	public function updateFromGitHub($inData)
	{
		return false;
	}

	/**
	 * @param object $inData
	 *
	 * @return bool
	 */
	public static function isAcceptData($inData)
	{
		if ($inData->type != 'IssuesEvent') //Возможные события также "CreateEvent", "DeleteEvent":  $inData->payload->ref_type ("branch")
		{
			return false;
		}

		return true;
	}

}