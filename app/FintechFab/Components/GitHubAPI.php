<?php
/**
 *  Применение
 *
 *  начало:
 *        $gitHubAPI = new GitHubAPI();
 *      $gitHubAPI->setRepo($owner, $repository);  //('fintech-fab', 'fintech-fab.ru');
 *      $gitHubAPI->setOrganisation($organisation);//('fintech-fab');
 *
 * либо:
 *        $gitHubAPI = new GitHubAPI($owner, $repository);
 *
 *
 * запросы:
 *      $gitHubAPI->setNewRepoQuery($contentOfRepository, $params); //('issues/comments') | ('')
 *      либо:
 *      $gitHubAPI->setNewOrgQuery($orgData, $params);              //Запрос данных об организации
 *
 *        while($gitHubAPI->doNextRequest())
 *        {
 *            //
 *        }
 *
 *----------------------------------------------
 *
 *  Вывод сообщений  в команде "php artisan"
 *
 *            $this->info("Limit remaining: " . $gitHubAPI->getLimitRemaining());
 *            $this->info("Результат запроса: " . $gitHubAPI->messageOfResponse);
 *
 *      //сообщения в случае неудач:
 *      if(! $gitHubAPI->isDoneRequest())
 *      {
 *          $this->info("Результат выполнения запроса: " . $gitHubAPI->messageOfResponse);
 *      }
 *
 *       // вывод сообщений об ограничении запросов:
 *       $this->info($gitHubAPI->getLimit());
 *
 *
 */
namespace FintechFab\Components;

use Config;

class GitHubAPI
{
	const BASE_URL = 'https://api.github.com/'; //адрес API GitHub'а (не изменяемый)

	/**
	 * Репозиторий, для которого создаются запросы к API GitHub
	 * (запрос данных о самом репозитории, или же в строку адреса добавляется уточнение
	 *  о конкретном содержимом репозитория: задачи, коммиты и пр.)
	 *
	 * @var string
	 */
	private $workRepo = '';
	/**
	 * Название организации на GitHub'е (она же — "owner" в запросах к API GitHub)
	 *
	 * @var string
	 */
	private $organisation = '';

	/**
	 * @param string $owner Владелец репозитория
	 * @param string $repo  Репозиторий на GitHub'е
	 */
	public function __construct($owner = '', $repo = '')
	{
		if (!($owner == '' || $repo == '')) {
			$this->setRepo($owner, $repo);
		}
		if ($owner != '') {
			$this->organisation = $owner;
		}

		$user = Config::get("github.username");
		$password = Config::get("github.password");
		if (!(empty($user) || empty($password))) {
			$this->userPassword = "$user:$password";
		}
	}

	/**
	 * @param string $org Название организации на GitHub'е
	 */
	public function setOrganisation($org)
	{
		$this->organisation = $org;
	}

	/**
	 * @param string $owner Владелец репозитория
	 * @param string $repo  Репозиторий на GitHub'е
	 */
	public function setRepo($owner, $repo)
	{
		$this->workRepo = self::BASE_URL . 'repos/' . $owner . '/' . $repo;
	}


	//Исполняемые запросы
	private $startUrl = ''; //информативно, о первом запросе в цепочке запросов
	private $currentUrl = ''; //подготовленный к выполнению
	private $usedUrl = ''; //выполненный
	private $isDone = false; // выполнен ли запрос?

	/**
	 * Имя пользователя и пароль в виде  [username]:[password]
	 *
	 * @var string|null
	 */
	private $userPassword = null;

	/**
	 * Задание имя пользователя и пароль для доступа к API GitHub
	 *
	 * @param string $user
	 * @param string $password
	 */
	public function setUser($user, $password)
	{
		$this->userPassword = "$user:$password";
	}

	/**
	 * @return bool Установлены ли имя пользователя и пароль для доступа к API GitHub
	 */
	public function isSetUser()
	{
		return (!is_null($this->userPassword));
	}

	/**
	 * Завершение подготовки запроса.
	 * Задается конкретное содержимое репозитория, о котором будет запрос к API GitHub
	 *
	 * @param string $repoData
	 * @param string $params
	 */
	public function setNewRepoQuery($repoData = '', $params = '')
	{
		if ($this->workRepo == '') {
			$this->startUrl = '';
			$this->currentUrl = '';
		} else {
			$repoData = ($repoData == '') ? '' : ('/' . $repoData);
			$this->startUrl = $this->workRepo .
				$repoData .
				($params = "" ? "" : ("?" . $params));
			$this->currentUrl = $this->startUrl;
			$this->usedUrl = '';
		}
	}

	/**
	 * Подготовка запроса по имени организации на GitHub'е
	 * (предварительно должно быть установлено имя организации)
	 *
	 * @param string $orgData
	 * @param string $params
	 */
	public function setNewOrgQuery($orgData = '', $params = '')
	{
		if ($this->organisation == '') {
			$this->startUrl = '';
			$this->currentUrl = '';
		} else {
			$orgData = ($orgData == '') ? '' : ('/' . $orgData);
			$this->startUrl = self::BASE_URL .
				'orgs/' .
				$this->organisation .
				$orgData .
				($params = "" ? "" : ("?" . $params));
			$this->currentUrl = $this->startUrl;
			$this->usedUrl = '';
		}
	}

	/**  Для повторных запросов с заголовком If-None-Match или If-Modified-Since. Корректный статус ответа: 304.*/
	private $conditional = '';

	/**
	 * Задается заголовок запроса к API GitHub, содержащий If-None-Match или If-Modified-Since
	 * (ожидаемый статус ответа 304)
	 *
	 * @parameter string $conditionalRequest
	 */
	public function setHeader304($conditionalRequest)
	{
		$this->conditional = $conditionalRequest;
	}

	/**
	 * Заголовок ответа
	 *
	 * @var array
	 */
	var $header = array();

	/**
	 * Данные ответа из GitHub API
	 *
	 * @var mixed
	 */
	var $response;

	/**
	 * Описание ошибки curl
	 *
	 * @var string
	 */
	var $error = '';

	/**
	 * Номер ошибки curl
	 *
	 * @var integer
	 */
	var $errno = 0;

	/**
	 * Сообщение о результате последнего запроса к API GitHub
	 *
	 * @var string
	 */
	var $messageOfResponse = '';

	/**
	 * Выполнять запросы к API GitHub через этот метод
	 * Адрес запроса уже должен быть готов (содержится в $this->currentUrl)
	 *
	 * Метод запускает запрос на выполнение и подготавливает слеюдующий (если есть ссылка на следующую страницу)
	 *
	 * @return bool
	 */
	public function doNextRequest()
	{
		$this->messageOfResponse = '';
		if ($this->currentUrl == '') {
			return false;
		}
		$this->doGitHubRequest($this->currentUrl);

		$this->conditional = ''; //Очистка "Conditional request"

		//Запись инф-ции об использованном запросе
		$this->usedUrl = $this->currentUrl;

		//Подготовка следующего запроса
		if (isset($this->header['Link']['next'])) {
			$this->currentUrl = $this->header['Link']['next'];
		} else {
			$this->currentUrl = '';
		}

		return $this->isDone;
	}

	/**
	 * @return bool
	 */
	public function isDoneRequest()
	{
		return $this->isDone;
	}


	/**
	 * Получает данные из GitHub API
	 * Сохраняет их в разобранной форме:
	 * заголовок $this->header и данные $this->response
	 *
	 * @param string $url
	 *
	 * Возврщает true если выполнено без ошибок
	 *
	 * @return bool
	 */
	private function doGitHubRequest($url)
	{
		$this->error = '';
		$this->errno = 0;
		$this->header = array();
		$this->response = '';
		$this->isDone = false; //Успешный запрос ресурса

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, "fintech-fab");
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0); //без перенаправлений на другой адрес
		if (!is_null($this->userPassword)) {
			curl_setopt($ch, CURLOPT_USERPWD, $this->userPassword);
		}

		if ($this->conditional != '') {
			curl_setopt($ch, CURLOPT_HTTPHEADER, array($this->conditional));
		}


		$response = curl_exec($ch);
		if (curl_errno($ch) != 0) {
			$this->error = curl_error($ch);
			$this->errno = curl_errno($ch);
			curl_close($ch);
			$this->parseResponseStatus(0);

			return false;
		}

		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->isDone = ($http_code == 200);
		curl_close($ch);

		$pos = strpos($response, "\r\n\r\n");
		preg_match_all('/(.+):(.+)\r\n/U', substr($response, 0, $pos), $strArray);

		$this->header = array_combine($strArray[1], $strArray[2]);
		if (isset($this->header["Link"])) {
			$this->header["Link"] = self::decodePageLinks($this->header["Link"]);
		}

		$response = trim(substr($response, $pos));
		$this->response = json_decode($response);

		$this->parseResponseStatus($http_code);

		return true;
	}

	/**
	 * GitHub выдает данные постранично. В заголовке ответа (header) дает ссылки на другие страницы.
	 *
	 * Из полученной строки функция выделяет адреса страниц и указатели, со значениями: first, next, prev, last
	 * Например:  <https://api.github.com/repositories/16651992/issues/events?page=1>; rel="first"
	 *
	 * @param string $inLinks
	 *
	 * @return array
	 */
	private static function decodePageLinks($inLinks)
	{
		$rel = ""; //Приходит из GitHub'а
		$links = explode(",", $inLinks);
		$pageLinks = array();
		foreach ($links as $strLink) {
			$link = explode(";", $strLink);
			parse_str($link[1]);
			$pageLinks[trim($rel, ' "')] = trim($link[0], " <>");
		}

		return $pageLinks;
	}

	/**
	 * Разбор статуса http ответа и
	 * создание итогового сообщения о результате
	 *
	 * @param integer $status
	 */
	private function parseResponseStatus($status)
	{
		switch ($status) {
			case 0:
				$this->messageOfResponse = "Error number: {$this->errno} \r\n{$this->error} \r\n";
				break;
			case 200:
				$this->messageOfResponse = 'OK';
				break;
			case 304:
				$this->messageOfResponse = "Запрос выполнен успешно. Новых данных нет.\n";
				break;
			case 403:
				if (isset($this->header['X-RateLimit-Remaining'])) {
					if ($this->header['X-RateLimit-Remaining'] == 0) {
						$this->messageOfResponse .= "Лимит запросов исчерпан. \nВозобновить можно после: "
							. date("c", $this->header['X-RateLimit-Reset']) . "\n";
					}
				}
		}
		if (!($status == 0 || $status == 200)) {
			$this->messageOfResponse .= isset($this->header['Status']) ?
				"Status: {$this->header['Status']} \n" :
				"Status: $status \n";
			if (isset($this->response->message)) {
				$this->messageOfResponse .= $this->response->message;
			}
		}
	}

	/**
	 * Лимит доступного количества запросов к API GitHub
	 *
	 * @return int
	 */
	public function getLimitRemaining()
	{
		if (!isset($this->header['X-RateLimit-Remaining'])) {
			$this->doGitHubRequest(self::BASE_URL . 'rate_limit');
		}

		if (isset($this->header['X-RateLimit-Remaining'])) {
			return $this->header['X-RateLimit-Remaining'];
		} else {
			return 0;
		}

	}

	/**
	 * Лимит доступа к API GitHub
	 *
	 * @return string
	 */
	public function getLimit()
	{
		if (!isset($this->header['X-RateLimit-Remaining'])) {
			$this->doGitHubRequest(self::BASE_URL . 'rate_limit');
		}
		if (isset($this->header['X-RateLimit-Remaining'])) {
			return (
				"Rate limit: " . $this->header['X-RateLimit-Limit'] . "\r\n" .
				"Limit remaining: " . $this->header['X-RateLimit-Remaining'] . "\r\n" .
				'Limit reset: ' . date("c", $this->header['X-RateLimit-Reset'])
			);

		} else {
			return "";
		}

	}

	/**
	 * @return string
	 */
	public function getLastUrl()
	{
		return $this->usedUrl;
	}


}