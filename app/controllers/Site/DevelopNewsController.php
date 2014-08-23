<?php

namespace App\Controllers\Site;

use App\Controllers\BaseController;
use FintechFab\Models\GitHubComments;
use FintechFab\Models\GitHubIssues;
use FintechFab\Models\GitHubMembers;
use FintechFab\Models\GitHubRefcommits;
use Input;

class DevelopNewsController extends BaseController
{

	public $layout = 'developNews';

	public function developNews()
	{
		$inTime = 1; //Количество недель
		if (input::has('inTime')) {
			$inTime = input::get('inTime');
		}

		//Время на начало выборки данных (в пересчете на несколько недель назад)
		$timeRequest = date('c', time() - $inTime * 3600 * 24 * 7);
		$eventData = array();
		$issuesData = array();

		$issues = GitHubIssues::whereNull("closed")->select('number', 'html_url', 'title', 'user_login')->get();
		foreach ($issues as $item) {
			$issue = new \stdClass();
			$issue->head = $item;
			$issue->avatar_url = GitHubMembers::find($item->user_login)->avatar_url;
			$issue->comments = $this->getComments($item->number, $timeRequest);
			$issue->commits = $this->getCommits($item->number, $timeRequest);

			if (count($issue->comments) > 0 || count($issue->commits) > 0) {
				$issuesData[] = $issue;
			}
		}


		return $this->make('developNews', array(
				'inTime'     => $inTime,
				'eventData'  => $eventData,
				'issuesData' => $issuesData
			)
		);
	}

	/**
	 * @param integer $issueNum Номер задачи
	 * @param string  $timeRequest
	 *
	 * @return array
	 */
	private function getComments($issueNum, $timeRequest)
	{
		$comments = GitHubComments::whereIssueNumber($issueNum)
			->where('updated', '>', $timeRequest)
			->orderBy('updated', 'desc')->get();

		$outComments = array();
		/** @var GitHubComments $comment */
		foreach ($comments as $comment) {
			$localtime = date('H:i:s d.m.Y', strtotime(str_replace(" ", "T", $comment->updated) . "Z"));
			$out = new \stdClass();
			$out->html_url = $comment->html_url;
			$out->user_login = $comment->user_login;
			$out->time = $localtime;
			$out->preview = $comment->prev;
			$out->avatar_url = GitHubMembers::find($comment->user_login)->avatar_url;

			$outComments[] = $out;
		}

		return $outComments;
	}

	/**
	 * @param integer $issueNum Номер задачи
	 * @param string  $timeRequest
	 *
	 * @return array
	 */
	private function getCommits($issueNum, $timeRequest)
	{
		$commits = GitHubRefcommits::whereIssueNumber($issueNum)
			->where('created', '>', $timeRequest)
			->orderBy('created', 'desc')
			->get();

		$outCommits = array();
		/** @var GitHubRefcommits $commit */
		foreach ($commits as $commit) {
			$localtime = date('H:i:s d.m.Y', strtotime(str_replace(" ", "T", $commit->created) . "Z"));
			$out = new \stdClass();
			$out->actor_login = $commit->actor_login;
			$out->time = $localtime;
			$out->message = $commit->message;
			$out->avatar_url = GitHubMembers::find($commit->actor_login)->avatar_url;

			$outCommits[] = $out;
		}

		return $outCommits;
	}

}