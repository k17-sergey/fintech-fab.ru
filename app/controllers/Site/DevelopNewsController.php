<?php

namespace App\Controllers\Site;

use App\Controllers\BaseController;
use FintechFab\Models\GitHubComments;
use FintechFab\Models\GitHubIssues;
use FintechFab\Models\GitHubMembers;
use Input;

class DevelopNewsController extends BaseController
{

	public $layout = 'developNews';

	public function developNews()
	{
		$inTime = 1; //Количество недель
		if(input::has('inTime'))
		{
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

			$issuesData[] = $issue;

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
		$comments = GitHubComments::whereIssueNumber($issueNum)->where('updated', '>', $timeRequest)
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

}