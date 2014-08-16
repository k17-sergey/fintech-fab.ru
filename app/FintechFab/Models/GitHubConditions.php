<?php

namespace FintechFab\Models;

use Eloquent;

/**
 * Class GitHubConditions
 * Хранит данные из заголовка ответа API GitHub;
 * для отправки в заголовке повторных запросов к API GitHub.
 *
 * @package FintechFab\Models
 *
 * @property string  $repo_item
 * @property string  $condition
 * @method GitHubConditions whereRepoItem static
 *
 * //Most responses return an ETag header. Many responses also return a Last-Modified header.
 * //You can use the values of these headers to make subsequent requests to those resources
 * //using the If-None-Match and If-Modified-Since headers, respectively.
 */
class GitHubConditions extends Eloquent
{
	protected $table = 'github_conditional_requests';


}