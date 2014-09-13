<?php

use FintechFab\Components\GitHubAPI;
use FintechFab\Models\GitHubComments;
use FintechFab\Models\GitHubEvents;
use FintechFab\Models\GitHubIssues;
use FintechFab\Models\GitHubMembers;
use FintechFab\Models\GitHubRefcommits;
use FintechFab\Models\GitHubConditions;
use FintechFab\Models\IGitHubModel;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class FintechFabFromGitHub extends Command
{
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'fintech-fab:git-hub';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command for receiving of news from GitHub API.';

	/**
	 * Зппросы к API GitHub и ответы
	 *
	 * @var GitHubAPI
	 */
	private $gitHubAPI;


	/**
	 * Create a new command instance.
	 */
	public function __construct()
	{
		parent::__construct();
		$owner = Config::get("github.owner");
		$repo = Config::get("github.trainingRepo");

		$this->gitHubAPI = new GitHubAPI($owner, $repo);
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		$helpOption = $this->option('helpArg');
		if (!empty($helpOption)) {
			$this->showHelp($helpOption);

			return;
		}

		$this->info("It's OK. Begin...");

		//Данные пользователей GitHub'а загружаются вперед (на них ссылаются почти все таблицы).
		//При этом лишних загрузок не будет, поскольку в запросе используется условие "If-None-Match"
		//Причем, для несуществующих пользователей, при необходимости, Eloquent сам создаст запись в таблице (а необходимость — при невозможности вставить строку из-за ограничения foreign key)
		$this->usersData('assignees'); //Who is working on specific issues and pull requests in your project.

		switch ($this->argument('Category')) {
			case "comments":
				$this->comments();
				break;
			case "commits":
				$this->commits();
				break;
			case "events":
				$this->events();
				break;
			case "issues":
				$this->issues();
				break;
			case "issuesEvents":
				$this->issuesEvents();
				break;
			case "users":
				$this->usersData('contributors'); //Who has contributed to a project by having a pull request merged but does not have collaborator access.
				break;
			case "rateLimit":
				//Получение инф. о лимите запросов
				$this->info($this->gitHubAPI->getLimit());
				break;
			default:

		}


	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('Category', InputArgument::OPTIONAL, 'Category of data for request to GitHub API.'),
		);
	}

	/**
	 * Get the console command options.
	 * (help занят, он показывает аргументы и опци, которые здесь есть)
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('helpArg', null, InputOption::VALUE_OPTIONAL, 'The help about used argument. --helpArg=list|:value_of_argument', null),
		);
	}

	/**
	 * Выводит на экран список используемых значений аргумента или детально по указанному значению
	 *
	 * @param string $option
	 */
	private function showHelp($option)
	{
		$baseQuery = '/repos/:owner/:repo';
		switch ($option) {
			case "list":
				$this->comment("Список аргументов:");
				$this->info("\t comments \t\t загрузка комментариев к задачам");
				$this->info("\t commits \t\t (не сделано) коммиты в главную ветку");
				$this->info("\t events \t\t загрузка общих событий");
				$this->info("\t issues \t\t загрузка списка задач (вторые в последовательности загрузки)");
				$this->info("\t issuesEvents \t\t загрузка событий, имеющих ссылку на задачу");
				$this->info("\t list    \t\t показывает это сообщение ");
				$this->info("\t rateLimit \t\t информация об ограничениях подключения к API GitHub");
				$this->info("\t users   \t\t загрузка пользователей (первые в загрузке)");
				$this->comment("Важно: ");
				$this->comment("\t Первыми в загрузке должны быть пользователи GitHub'а \"users\", затем задачи \"issues\"");
				break;
			case "comments":
				$this->comment('--helpArg=comments ');
				$this->info("\t comments \t загрузка комментариев к задачам");
				$this->info("\t\t\t Запрос к API GitHub: \t $baseQuery/issues/comments");
				break;
			case "commits":
				$this->comment("--helpArg=commits ");
				$this->info("\t commits \t (не сделано) коммиты в главную ветку");
				break;
			case "events":
				$this->comment("--helpArg=events ");
				$this->info("\t events \t загрузка общих событий");
				$this->info("\t\t\t Запрос к API GitHub: \t $baseQuery/events");
				break;
			case "issues":
				$this->comment("--helpArg=issues ");
				$this->info("\t issues \t загрузка списка задач");
				$this->info("\t\t\t Запрос к API GitHub: \t $baseQuery/issues");
				break;
			case "issuesEvents":
				$this->comment("--helpArg=issuesEvents ");
				$this->info("\t issuesEvents \t загрузка событий, имеющих ссылку на задачу");
				$this->info("\t\t\t Запрос к API GitHub: \t $baseQuery/issues/events");
				$this->info("\t\t\t и на основе полученных данных ");
				$this->info("\t\t\t для каждого коммита: \t $baseQuery/git/commits/:sha");
				$this->info("\t  \t\t ");
				break;
			case "users":
				$this->comment("--helpArg=users ");
				$this->info("\t users   \t загрузка пользователей");
				$this->info("\t\t\t Запросы к API GitHub: \t $baseQuery/contributors");
				$this->info("\t\t\t                       \t $baseQuery/assignees");
				break;
			case "rateLimit":
				$this->info("Rate limit. Ограничение количества запросов к GitHub API.\n\nКоличество запросов ограничено в течение часа, называемое \"Rate limit\"\n" .
					"Для авторизованного соединения лимит намного выше.\n" .
					"GitHub API сообщает также:" .
					"\n      \"Rate limit remaining\" — количество доступных запросов (неизрасходованных)" .
					"\n      \"Rate limit reset\" — время окончания текущего периода ограничений.");
				break;
		}
	}

	/**
	 * Загрузка комментариев к задачам
	 */
	public function comments()
	{
		$maxDate = GitHubComments::max('updated'); //Максимальная дата, полученная с GitHub'а
		//В парметре — запрос еще не полученных данных, добавленных или измененных после указанной даты
		$param = empty($maxDate) ? "" : "since='" . str_replace(" ", "T", $maxDate) . "Z'";

		$this->gitHubAPI->setNewRepoQuery('issues/comments', $param);
		$this->processTheData(GitHubComments::class, empty($maxDate) ? 0 : strtotime(str_replace(" ", "T", $maxDate) . "Z"));

	}

	/**
	 *  Коммиты в главную ветку (не сделано)
	 */
	public function commits()
	{
		//
	}

	/**
	 * Получение из GitHub’а и добавление в БД событий, например, открытие новой задачи
	 */
	public function events()
	{
		$strMaxDate = GitHubEvents::max('created');
		$maxDate = empty($strMaxDate) ? 0 : strtotime(str_replace(' ', 'T', $strMaxDate) . 'Z');
		$this->gitHubAPI->setNewRepoQuery('events');

		$isContinue = true;
		while ($isContinue && $this->gitHubAPI->doNextRequest()) {
			$this->info("\nLimit remaining: " . $this->gitHubAPI->getLimitRemaining());
			$this->info("Результат запроса: " . $this->gitHubAPI->messageOfResponse);
			$isContinue = $this->timeFilter($maxDate, 'created_at');

			//Фильтр полученных данных
			$response = array();
			foreach ($this->gitHubAPI->response as $item) {
				if (GitHubEvents::isAcceptData($item)) {
					$response[] = $item;
				}
			}

			$this->saveInDB($response, GitHubEvents::class);
		}
		if (!$this->gitHubAPI->isDoneRequest()) {
			$this->info("Результат запроса: " . $this->gitHubAPI->messageOfResponse);
		}
	}

	/**
	 * Загрузка задач (должны загружаться вторые, т.е. после пользователей)
	 */
	public function issues()
	{
		$maxDate = GitHubIssues::max('updated'); //Максимальная дата, полученная с GitHub'а
		//В парметре — запрос на новые данные
		$param = empty($maxDate) ? "" :
			"state=all&since='" . str_replace(" ", "T", $maxDate) . "Z'";

		$this->gitHubAPI->setNewRepoQuery('issues', $param);
		$this->processTheData(GitHubIssues::class);
	}

	/**
	 * Получение из GitHub’а и добавление в БД коммитов, имеющих ссылку на конкретные задачи.
	 * В принятых данных, коммиты обозначены как события 'referenced'
	 */
	public function issuesEvents()
	{
		$maxDateStr = GitHubRefcommits::max('created'); //Максимальная дата в БД
		$maxDate = empty($maxDateStr) ? 0 : strtotime(str_replace(" ", "T", $maxDateStr) . "Z");

		$this->gitHubAPI->setNewRepoQuery("issues/events");
		$this->processTheData(GitHubRefcommits::class, $maxDate, 'created_at');

		//Добавление сообщений коммитов
		$refCommits = GitHubRefcommits::where('message', '')->get();
		foreach ($refCommits as $issueCommit) {
			$this->gitHubAPI->setNewRepoQuery("git/commits/" . $issueCommit->commit_id);
			if ($this->gitHubAPI->doNextRequest()) {
				$this->info("\nLimit remaining: " . $this->gitHubAPI->getLimitRemaining());
				$this->info("Результат запроса: " . $this->gitHubAPI->messageOfResponse);

				if ($issueCommit->updateFromGitHub($this->gitHubAPI->response)) {
					$this->info('Adding commit message: ' . substr($issueCommit->message, 0, 60));
					$issueCommit->save();
				}
			} else {
				$this->info("Результат запроса: " . $this->gitHubAPI->messageOfResponse);
			}
		}
	}

	/**
	 * Данные пользователей GitHub'а, нужно загружать первыми (на них ссылаются почти все таблицы в БД).
	 */
	public function users()
	{
		$this->usersData('assignees'); //Who is working on specific issues and pull requests in your project.
		$this->usersData('contributors'); //Who has contributed to a project by having a pull request merged but does not have collaborator access.
	}

	/**
	 * Получение данных пользователей
	 *
	 * @param string $group
	 */
	private function usersData($group)
	{
		$this->gitHubAPI->setNewRepoQuery($group);
		$this->prepareCondition($group); //Если запрос повторный, подготовка соответствующего заголовка запроса
		if ($this->gitHubAPI->doNextRequest()) {
			$this->info("\nLimit remaining: " . $this->gitHubAPI->getLimitRemaining());
			$this->info("Результат запроса: " . $this->gitHubAPI->messageOfResponse);
			$this->saveInDB($this->gitHubAPI->response, GitHubMembers::class);

			$this->updateConditionalRequest($group); //Обновление условия повторных запросов, если есть
		} else {
			$this->info("Результат запроса: " . $this->gitHubAPI->messageOfResponse);
		}
	}


	/**
	 * Загрузка всех данных и вывод сообщений
	 * Объект $this->gitHubAPI должен быть заранее подготовлен к запросам.
	 *
	 * Ограничение по дате, типа "WHERE DateValue > $filterDate"
	 *
	 * @param Eloquent $dataModel
	 * @param integer  $filterDate
	 * @param string   $fieldName Поле принятых даных, содержащих дату-время
	 */
	private function processTheData($dataModel, $filterDate = 0, $fieldName = 'updated_at')
	{
		$isContinue = true;
		while ($isContinue && $this->gitHubAPI->doNextRequest()) {
			$this->info("\nLimit remaining: " . $this->gitHubAPI->getLimitRemaining());
			$this->info("Результат запроса: " . $this->gitHubAPI->messageOfResponse);

			$isContinue = $this->timeFilter($filterDate, $fieldName);
			$this->saveInDB($this->gitHubAPI->response, $dataModel);
		}
		if (!$this->gitHubAPI->isDoneRequest()) {
			$this->info("Результат запроса: " . $this->gitHubAPI->messageOfResponse);
		}
	}

	/**
	 * Ограничивает принимаемые данные, типа "WHERE DateValue > $filterDate"
	 * Обрабатывает принятые данные (предполагаются упорядоченными по-убыванию значений даты-времени, как это обычно происходит из API GitHub'а)
	 *
	 * @param integer $filterDate
	 * @param string  $fieldName
	 *
	 * @return bool   При значении true — разрешено загружать следующие страницы из API GitHub
	 */
	private function timeFilter($filterDate, $fieldName = 'updated_at')
	{
		if ($filterDate == 0) {
			return true;
		}

		$maxIndex = count($this->gitHubAPI->response) - 1;
		for ($i = $maxIndex; $i >= 0; $i--) {
			if ($filterDate >= strtotime($this->gitHubAPI->response[$i]->$fieldName)) {
				array_pop($this->gitHubAPI->response);
			} else {
				break;
			}
		}

		return ($maxIndex == (count($this->gitHubAPI->response) - 1));
	}

	/**
	 * Подготовка условия (если есть) для повторных запросов к API GitHub.
	 *
	 * @param string $forRepoItem
	 */
	private function prepareCondition($forRepoItem)
	{
		/** @var Eloquent|GitHubConditions $repoCondition */
		$repoCondition = GitHubConditions::whereRepoItem($forRepoItem)->first();
		if (!empty($repoCondition)) {
			$this->gitHubAPI->setHeader304($repoCondition->condition);
		}
	}

	/**
	 * Обновление (в БД) условия запроса к API GitHub. В заголовке ответа содержатся нужные значения для повторных запросов.
	 *
	 * @param string $forRepoItem
	 */
	private function updateConditionalRequest($forRepoItem)
	{
		if (!$this->gitHubAPI->isDoneRequest()) {
			return;
		}

		$newCondition = '';
		if (isset($this->gitHubAPI->header['Last-Modified'])) {
			$newCondition = 'If-Modified-Since:' . $this->gitHubAPI->header['Last-Modified'];
		} elseif (isset($this->gitHubAPI->header['ETag'])) {
			$newCondition = 'If-None-Match:' . $this->gitHubAPI->header['ETag'];
		}

		/** @var Eloquent|GitHubConditions $repoCondition */
		$repoCondition = GitHubConditions::whereRepoItem($forRepoItem)->first();

		if ($newCondition == '') {
			return;
		}

		if (empty($repoCondition)) {
			$repoCondition = new GitHubConditions();
			$repoCondition->repo_item = $forRepoItem;
		}
		$repoCondition->condition = $newCondition;
		$repoCondition->save();
	}


	/**
	 * @param array    $inData
	 * @param Eloquent $classDB
	 *
	 * Сохранение или обновление данных в БД,
	 * вывод сообщений на экран по каждой отдельной записи данных (при добавлении в БД, при обновлении).
	 *
	 * $inData  — данные, полученные из GitHub'а
	 * $classDB — имя класса таблицы БД (модель). Заполение и контроль полученных данных выполняется в этой модели.
	 *      Методы, принимающие данные ("dataGitHub($inItem)", "updateFromGitHub($inItem)")
	 *      дают положительный ответ "true", если разрешено сохранять.
	 *
	 * $item->getKeyName() — имя ключевого поля (может быть 'id' или иным). Задается в модели данных.
	 * $item->getMyName()  — нужно для вывода на экран (показать, какие данные сохраняются).
	 */
	private function saveInDB($inData, $classDB)
	{
		$this->info(sprintf("\nAddition to DataBase: %u records...", count($inData)));

		/** @var Eloquent|IGitHubModel $item */
		$item = new $classDB;
		$keyName = $item->getKeyName();
		$myName = $item->getMyName();
		foreach ($inData as $inItem) {
			$item = $classDB::where($keyName, $inItem->$keyName)->first();
			if (isset($item->$keyName)) {
				$this->info("Found $myName:" . $item->$keyName);
				if ($item->updateFromGitHub($inItem)) {
					$this->info("Update: " . $item->$keyName);
					$item->save();
				}
			} else {
				$item = new $classDB;
				if ($item->dataGitHub($inItem)) {
					$this->info("Addition $myName: " . $inItem->$keyName);
					$item->save();
				}
			}
		}
	}


}
