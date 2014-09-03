<?php

namespace App\Controllers\Site;

use App\Controllers\BaseController;
use FintechFab\Models\GitHubComments;
use FintechFab\Models\GitHubEvents;
use FintechFab\Models\GitHubIssues;
use FintechFab\Models\GitHubRefcommits;
use Input;
use DB;

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

		$eventData = new \stdClass();
		$eventData->countIssuesOpened = GitHubIssues::whereState('open')->count('*');
		$eventData->countIssuesInWork = DB::table('github_issues')
			->join('github_refcommits', function ($join) {
				$join->on('github_refcommits.issue_number', '=', 'github_issues.number')
					->where('github_issues.state', '=', 'open');
			})
			->distinct()
			->count('github_issues.number');
		$eventData->issuesEvents = $this->getEvents('issuesEvent', $timeRequest);


		$issuesData = array();

		$issues = GitHubIssues::whereNull("closed")->select('number', 'html_url', 'title', 'user_login')->get();
		foreach ($issues as $item) {
			$issue = new \stdClass();
			$issue->head = $item;
			$issue->avatar_url = $item->user()->avatar_url;
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
			$out = new \stdClass();
			$out->html_url = $comment->html_url;
			$out->user_login = $comment->user_login;
			$out->time = self::timeToLocalForShow($comment->updated);
			$out->preview = $comment->prev;
			$out->avatar_url = $comment->user()->avatar_url;

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
			$out = new \stdClass();
			$out->actor_login = $commit->actor_login;
			$out->time = self::timeToLocalForShow($commit->created);
			$out->message = $commit->message;
			$out->avatar_url = $commit->user()->avatar_url;

			$outCommits[] = $out;
		}

		return $outCommits;
	}

	/**
	 * @param string $type
	 * @param string $timeRequest
	 *
	 * @return array
	 */
	private function getEvents($type, $timeRequest)
	{
		$events = GitHubEvents::whereType($type)
			->where('created', '>', $timeRequest)
			->orderBy('created', 'desc')
			->get();

		$outEvents = array();
		/** @var GitHubEvents $event */
		foreach ($events as $event) {
			$out = new \stdClass();
			$out->actor_login = $event->actor_login;
			$out->time = self::timeToLocalForShow($event->created);
			$out->payload = $event->payload;
			$out->avatar_url = $event->user()->avatar_url;

			$outEvents[] = $out;
		}

		return $outEvents;
	}

	/**
	 * Преобразование в локальное время (в БД — глобальное) и форматирование его для показа на странице
	 *
	 * @param string $strTime
	 *
	 * @return bool|string
	 */
	private static function timeToLocalForShow($strTime)
	{
		return date('H:i:s d.m.Y', strtotime(str_replace(" ", "T", $strTime) . "Z"));
	}


}